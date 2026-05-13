<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;
use App\Models\GisInventory;
use App\Models\GisRequest;
use App\Models\GisReceive;
use App\Helpers\WaHelper;

class GisController extends Controller
{
    private $currentUser;

    public function handle(Request $request)
    {
        // Parsing JSON Body
        if ($request->getContent()) {
            $request->merge(json_decode($request->getContent(), true) ?? []);
        }

        if (!Auth::check() || session('user_logged_in') !== true) {
            return response()->json(['success' => false, 'message' => 'Sesi habis. Silakan login ulang.', 'code' => 401]);
        }

        $this->currentUser = Auth::user();
        $action = $request->input('action');

        // DAFTAR ROUTES ACTION API
        if ($action == 'getInventory') return $this->getInventory();
        if ($action == 'deleteItem') return $this->deleteItem($request);
        if ($action == 'saveItem') return $this->saveItem($request);
        if ($action == 'importItems') return $this->importItems($request);

        if ($action == 'getReceives') return $this->getReceives();
        if ($action == 'submitGR') return $this->submitGR($request);
        if ($action == 'editErpGrNo') return $this->editErpGrNo($request); // <-- Fungsi Edit No GR

        if ($action == 'getRequests') return $this->getRequests();
        if ($action == 'submitRequest') return $this->submitRequest($request);
        if ($action == 'editRequest') return $this->editRequest($request);
        if ($action == 'cancelRequest') return $this->cancelRequest($request);
        if ($action == 'updateStatus') return $this->updateStatus($request);
        if ($action == 'editErpGiNo') return $this->editErpGiNo($request); // <-- Fungsi Edit No GI

        if ($action == 'exportData') return $this->exportData($request);

        // Jika action tidak dikenali
        return response()->json(['success' => false, 'message' => 'Action is invalid']);
    }

    private function checkAccessSession($perm)
    {
        $role = $this->currentUser->role;
        $dept = $this->currentUser->department;

        if ($role === 'Administrator') return true;

        $rights = [];
        try {
            if (is_string($this->currentUser->access_rights)) {
                $rights = json_decode($this->currentUser->access_rights, true);
            } elseif (is_array($this->currentUser->access_rights)) {
                $rights = $this->currentUser->access_rights;
            }
        } catch (\Exception $e) {}

        if (!is_array($rights)) $rights = [];

        // Fallback untuk Warehouse lama
        if (empty($rights) && (in_array($role, ['Warehouse']) || ($role === 'TeamLeader' && strtolower($dept) === 'warehouse'))) {
            $rights = ['gi_submit', 'gr_submit', 'item_add', 'item_edit', 'stock_edit', 'export_data', 'price_add', 'price_edit', 'item_delete', 'edit_gi_no', 'edit_gr_no'];
        }

        return in_array($perm, $rights);
    }

    private function uploadGisPhoto($base64Data, $prefix)
    {
        $uploadDir = public_path('uploads/gis/');
        if (!File::exists($uploadDir)) {
            File::makeDirectory($uploadDir, 0777, true);
        }

        if (strpos($base64Data, 'base64,') !== false) {
            $base64Data = explode('base64,', $base64Data)[1];
        }

        $decodedData = base64_decode($base64Data);
        if ($decodedData === false) return false;

        $fileName = $prefix . "_" . time() . "_" . rand(100, 999) . ".jpg";
        $filePath = $uploadDir . $fileName;

        if (file_put_contents($filePath, $decodedData)) {
            return "uploads/gis/" . $fileName;
        }
        return false;
    }

    private function buildItemListText($itemsArray)
    {
        $text = "📦 *DETAIL BARANG:*\n";
        $text .= "┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈\n";
        $grandTotal = 0;

        foreach ($itemsArray as $it) {
            $code = $it['code'];
            $name = $it['name'];
            $qty = $it['qty'];
            $uom = $it['uom'] ?? '';
            $price = floatval($it['price'] ?? 0);
            $total = $price * intval($qty);
            $grandTotal += $total;

            $currentStock = GisInventory::where('item_code', $code)->value('stock') ?? 0;

            $text .= "🔸 *$code*\n";
            $text .= "   ▪ Nama: $name\n";
            $text .= "   ▪ Qty : *$qty $uom*\n";
            if ($price > 0) {
                $text .= "   ▪ Harga: Rp " . number_format($price, 0, ',', '.') . "\n";
                $text .= "   ▪ Total: Rp " . number_format($total, 0, ',', '.') . "\n";
            }
            $text .= "   ▪ Sisa: $currentStock $uom\n\n";
        }

        if ($grandTotal > 0) {
            $text .= "┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈\n";
            $text .= "💰 *GRAND TOTAL: Rp " . number_format($grandTotal, 0, ',', '.') . "*\n";
        }
        $text .= "┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈";

        return $text;
    }

    private function getInventory() { return response()->json(GisInventory::orderBy('item_name', 'asc')->get()); }

    private function deleteItem(Request $request)
    {
        if (!$this->checkAccessSession('item_delete')) return response()->json(['success' => false, 'message' => 'Unauthorized.', 'code' => 403]);
        $code = $request->input('item_code');
        if (empty($code)) return response()->json(['success' => false, 'message' => 'Kode item kosong.']);
        try {
            GisInventory::where('item_code', $code)->delete();
            return response()->json(['success' => true, 'message' => "Item $code berhasil dihapus."]);
        } catch (\Exception $e) { return response()->json(['success' => false, 'message' => $e->getMessage()]); }
    }

    private function saveItem(Request $request)
    {
        $isEdit = $request->input('is_edit') == '1';
        if (!$isEdit && !$this->checkAccessSession('item_add')) return response()->json(['success' => false, 'message' => 'Access Denied', 'code' => 403]);
        if ($isEdit && !$this->checkAccessSession('item_edit') && !$this->checkAccessSession('stock_edit')) return response()->json(['success' => false, 'message' => 'Access Denied', 'code' => 403]);

        $code = $request->input('item_code');
        $data = ['item_name' => $request->input('item_name'), 'item_spec' => $request->input('item_spec'), 'category' => $request->input('category'), 'uom' => $request->input('uom'), 'last_updated' => Carbon::now('Asia/Jakarta')];
        $stock = intval($request->input('stock', 0)); $price = floatval($request->input('price', 0));
        $canEditInfo = $this->checkAccessSession('item_edit'); $canEditStock = $this->checkAccessSession('stock_edit'); $canEditPriceExisting = $this->checkAccessSession('price_edit'); $canAddPriceNew = $this->checkAccessSession('price_add');

        try {
            if ($isEdit) {
                $updateData = ['last_updated' => Carbon::now('Asia/Jakarta')];
                if ($canEditInfo) { $updateData['item_name'] = $data['item_name']; $updateData['item_spec'] = $data['item_spec']; $updateData['category'] = $data['category']; $updateData['uom'] = $data['uom']; }
                if ($canEditStock) $updateData['stock'] = $stock;
                if ($canEditPriceExisting) $updateData['price'] = $price;
                GisInventory::where('item_code', $code)->update($updateData);
            } else {
                $data['item_code'] = $code; $data['stock'] = $stock; $data['price'] = $canAddPriceNew ? $price : 0;
                GisInventory::updateOrCreate(['item_code' => $code], $data);
            }
            return response()->json(['success' => true, 'message' => 'Item saved.']);
        } catch (\Exception $e) { return response()->json(['success' => false, 'message' => $e->getMessage()]); }
    }

    private function importItems(Request $request)
    {
        if ($this->currentUser->role !== 'Administrator') return response()->json(['success' => false, 'message' => 'Unauthorized.', 'code' => 403]);
        $items = $request->input('data');
        DB::beginTransaction();
        try {
            foreach ($items as $it) {
                $code = $it['item_code'] ?? ''; $name = $it['item_name'] ?? '';
                if (!empty($code) && !empty($name)) {
                    GisInventory::updateOrCreate(['item_code' => $code], ['item_name' => $name, 'item_spec' => $it['item_spec'] ?? '', 'category' => $it['category'] ?? 'General', 'uom' => $it['uom'] ?? 'Pcs', 'stock' => intval($it['stock'] ?? 0), 'price' => floatval($it['price'] ?? 0), 'last_updated' => Carbon::now('Asia/Jakarta')]);
                }
            }
            DB::commit(); return response()->json(['success' => true, 'message' => 'Bulk import master item successful.']);
        } catch (\Exception $e) { DB::rollBack(); return response()->json(['success' => false, 'message' => $e->getMessage()]); }
    }

    private function getReceives()
    {
        $receives = GisReceive::orderBy('created_at', 'desc')->limit(100)->get();
        $receives->transform(function ($item) { $item->items = $item->items_json; return $item; });
        return response()->json($receives);
    }

    private function submitGR(Request $request)
    {
        if (!$this->checkAccessSession('gr_submit')) return response()->json(['success' => false, 'message' => 'Unauthorized.', 'code' => 403]);
        $grId = "GR-" . time(); $erpGrNo = $request->input('erp_gr_no'); $remarks = $request->input('remarks'); $items = $request->input('items');
        $photoBase64 = $request->input('photoBase64');
        if (empty($photoBase64)) return response()->json(['success' => false, 'message' => 'Bukti foto penerimaan wajib dilampirkan.']);
        $photoUrl = $this->uploadGisPhoto($photoBase64, "GR_" . preg_replace('/[^a-zA-Z0-9]/', '', $grId));
        if (!$photoUrl) return response()->json(['success' => false, 'message' => 'Gagal menyimpan foto GR.']);

        DB::beginTransaction();
        try {
            GisReceive::create(['gr_id' => $grId, 'erp_gr_no' => $erpGrNo, 'username' => $this->currentUser->username, 'fullname' => $this->currentUser->fullname, 'remarks' => $remarks, 'gr_photo' => $photoUrl, 'items_json' => $items, 'created_at' => Carbon::now('Asia/Jakarta'), 'updated_at' => Carbon::now('Asia/Jakarta')]);
            $canEditPrice = $this->checkAccessSession('price_edit');
            foreach ($items as $it) {
                $qty = intval($it['qty']); $price = floatval($it['price'] ?? 0);
                $inventory = GisInventory::where('item_code', $it['code'])->first();
                if ($inventory) { $inventory->stock += $qty; $inventory->last_updated = Carbon::now('Asia/Jakarta'); if ($canEditPrice && $price > 0) { $inventory->price = $price; } $inventory->save(); }
            }
            DB::commit();
            $itemsText = $this->buildItemListText($items);
            $msgHeader = "📥 *GOOD RECEIVE (BARANG MASUK)* 📥\n┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈\n🔖 *ID GR* : $grId\n🧾 *No ERP* : $erpGrNo\n👤 *Penerima* : {$this->currentUser->fullname}\n📝 *Catatan* : $remarks\n┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈\n\n" . $itemsText;
            $whPhones = array_unique(WaHelper::getPhones(['Warehouse', 'TeamLeader', 'Administrator'], 'Warehouse'));
            foreach ($whPhones as $ph) WaHelper::sendWA($ph, $msgHeader . "\n\n💡 *Info:* Stok Master telah berhasil ditambahkan secara otomatis.");
            return response()->json(['success' => true, 'message' => 'Good Receive berhasil diproses. Stok bertambah.']);
        } catch (\Exception $e) { DB::rollBack(); return response()->json(['success' => false, 'message' => $e->getMessage()]); }
    }

    // FUNGSI BARU: EDIT NOMOR GR ERP
    private function editErpGrNo(Request $request)
    {
        if (!$this->checkAccessSession('edit_gr_no')) return response()->json(['success' => false, 'message' => 'Akses Edit Nomor GR Ditolak.', 'code' => 403]);
        try {
            GisReceive::where('gr_id', $request->input('grId'))->update(['erp_gr_no' => $request->input('newNo'), 'updated_at' => Carbon::now('Asia/Jakarta')]);
            return response()->json(['success' => true, 'message' => 'Nomor GR ERP berhasil diperbarui.']);
        } catch (\Exception $e) { return response()->json(['success' => false, 'message' => $e->getMessage()]); }
    }

    private function getRequests()
    {
        $role = $this->currentUser->role; $dept = $this->currentUser->department; $username = $this->currentUser->username;
        $query = GisRequest::query();
        if (!in_array($role, ['Administrator', 'Warehouse', 'PlantHead']) && !($role === 'TeamLeader' && strtolower($dept) === 'warehouse')) {
            if (in_array($role, ['SectionHead', 'TeamLeader'])) $query->where('department', $dept); else $query->where('username', $username);
        }
        $requests = $query->orderBy('created_at', 'desc')->limit(100)->get();
        $requests->transform(function ($item) { $item->items = $item->items_json; return $item; });
        return response()->json($requests);
    }

    private function submitRequest(Request $request)
    {
        if (!$this->checkAccessSession('gi_submit')) return response()->json(['success' => false, 'message' => 'Unauthorized.', 'code' => 403]);
        $reqId = "GIF-" . time(); $sec = $request->input('section'); $purpose = $request->input('purpose'); $items = $request->input('items');
        $grandTotal = 0;
        foreach ($items as $it) {
            $reqQty = intval($it['qty']); $cc = trim((string)($it['cost_center'] ?? ''));
            if (empty($cc)) return response()->json(['success' => false, 'message' => "Cost Center WAJIB diisi."]);
            $cek = GisInventory::where('item_code', $it['code'])->first();
            if (!$cek || $cek->stock < $reqQty) return response()->json(['success' => false, 'message' => "Stock tidak cukup untuk {$it['code']}"]);
            $price = floatval($it['price'] ?? 0); $grandTotal += ($reqQty * $price);
        }

        try {
            GisRequest::create(['req_id' => $reqId, 'username' => $this->currentUser->username, 'fullname' => $this->currentUser->fullname, 'department' => $this->currentUser->department, 'section' => $sec, 'purpose' => $purpose, 'items_json' => $items, 'created_at' => Carbon::now('Asia/Jakarta'), 'updated_at' => Carbon::now('Asia/Jakarta')]);
            $itemsText = $this->buildItemListText($items);
            $msgHeader = "🚨 *NEW GOOD ISSUE REQUEST* 🚨\n┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈\n🔖 *ID GIF* : $reqId\n👤 *Pemohon* : {$this->currentUser->fullname}\n🏢 *Dept/Sec* : {$this->currentUser->department} / $sec\n📝 *Keperluan*: $purpose\n┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈\n\n" . $itemsText;

            $approverRole = ($grandTotal > 15000000) ? 'Plant Head' : 'Dept Head';
            if ($grandTotal > 15000000) $headPhones = WaHelper::getPhones('PlantHead'); else $headPhones = WaHelper::getPhones(['SectionHead', 'TeamLeader'], $this->currentUser->department);

            foreach ($headPhones as $ph) WaHelper::sendWA($ph, $msgHeader . "\n\n💡 *Action:* Silakan login ke sistem untuk melakukan Approval.");
            $userPhone = WaHelper::getUserPhone($this->currentUser->username);
            if ($userPhone) WaHelper::sendWA($userPhone, $msgHeader . "\n\n💡 *Status:* Menunggu persetujuan {$approverRole}.");
            $whPhones = array_unique(WaHelper::getPhones(['Warehouse', 'TeamLeader'], 'Warehouse'));
            foreach ($whPhones as $ph) WaHelper::sendWA($ph, $msgHeader . "\n\n💡 *Status:* Menunggu persetujuan {$approverRole}.");

            return response()->json(['success' => true, 'message' => 'Request submitted.']);
        } catch (\Exception $e) { return response()->json(['success' => false, 'message' => $e->getMessage()]); }
    }

    private function editRequest(Request $request)
    {
        $reqId = $request->input('reqId'); $req = GisRequest::where('req_id', $reqId)->first();
        if (!$req || $req->username !== $this->currentUser->username || $req->status !== 'Pending Head') return response()->json(['success' => false, 'message' => 'Data sudah diproses atau bukan milik Anda.', 'code' => 403]);

        $items = $request->input('items'); $grandTotal = 0;
        foreach ($items as $it) {
            $reqQty = intval($it['qty']);
            if (empty(trim((string)($it['cost_center'] ?? '')))) return response()->json(['success' => false, 'message' => "Cost Center WAJIB diisi."]);
            $cek = GisInventory::where('item_code', $it['code'])->first();
            if (!$cek || $cek->stock < $reqQty) return response()->json(['success' => false, 'message' => "Stock tidak cukup"]);
            $price = floatval($it['price'] ?? 0); $grandTotal += ($reqQty * $price);
        }

        try {
            $req->update(['section' => $request->input('section'), 'purpose' => $request->input('purpose'), 'items_json' => $items, 'updated_at' => Carbon::now('Asia/Jakarta')]);
            $itemsText = $this->buildItemListText($items);
            $msgHeader = "✏️ *GOOD ISSUE UPDATED* ✏️\n┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈\n🔖 *ID GIF* : $reqId\n👤 *Diubah By*: {$this->currentUser->fullname}\n📝 *Keperluan*: {$request->input('purpose')}\n┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈\n\n" . $itemsText;

            $approverRole = ($grandTotal > 15000000) ? 'Plant Head' : 'Dept Head';
            if ($grandTotal > 15000000) $headPhones = WaHelper::getPhones('PlantHead'); else $headPhones = WaHelper::getPhones(['SectionHead', 'TeamLeader'], $req->department);

            foreach ($headPhones as $ph) WaHelper::sendWA($ph, $msgHeader . "\n\n💡 *Action:* Form telah diubah oleh User. Silakan login untuk Approve.");
            $userPhone = WaHelper::getUserPhone($this->currentUser->username);
            if ($userPhone) WaHelper::sendWA($userPhone, $msgHeader . "\n\n💡 *Status:* Form berhasil diubah, menunggu persetujuan {$approverRole}.");
            $whPhones = array_unique(WaHelper::getPhones(['Warehouse', 'TeamLeader'], 'Warehouse'));
            foreach ($whPhones as $ph) WaHelper::sendWA($ph, $msgHeader . "\n\n💡 *Status:* Form telah diubah User. Menunggu persetujuan {$approverRole}.");

            return response()->json(['success' => true, 'message' => 'Request updated successfully.']);
        } catch (\Exception $e) { return response()->json(['success' => false, 'message' => $e->getMessage()]); }
    }

    private function cancelRequest(Request $request)
    {
        $reqId = $request->input('reqId'); $req = GisRequest::where('req_id', $reqId)->where('username', $this->currentUser->username)->first();
        if (!$req || !in_array($req->status, ['Pending Head', 'Pending Plant Head'])) return response()->json(['success' => false, 'message' => 'Data tidak dapat dibatalkan saat ini.', 'code' => 403]);
        try {
            $req->update(['status' => 'Cancelled', 'updated_at' => Carbon::now('Asia/Jakarta')]);
            $itemsText = $this->buildItemListText($req->items_json);
            $msgHeader = "🚫 *GOOD ISSUE CANCELLED* 🚫\n┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈\n🔖 *ID GIF* : $reqId\n👤 *Batal By* : {$this->currentUser->fullname}\n🏢 *Dept/Sec* : {$req->department} / {$req->section}\n┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈\n\n" . $itemsText;
            $userPhone = WaHelper::getUserPhone($this->currentUser->username);
            if ($userPhone) WaHelper::sendWA($userPhone, $msgHeader . "\n\n💡 *Info:* Anda telah berhasil membatalkan permintaan ini.");
            $headPhones = WaHelper::getPhones(['SectionHead', 'TeamLeader'], $req->department);
            foreach ($headPhones as $ph) WaHelper::sendWA($ph, $msgHeader . "\n\n💡 *Info:* Permintaan dibatalkan oleh pemohon. Harap abaikan pengajuan ini.");
            return response()->json(['success' => true, 'message' => 'Request cancelled successfully.']);
        } catch (\Exception $e) { return response()->json(['success' => false, 'message' => $e->getMessage()]); }
    }

    // FUNGSI BARU: EDIT NOMOR GI ERP
    private function editErpGiNo(Request $request)
    {
        if (!$this->checkAccessSession('edit_gi_no')) return response()->json(['success' => false, 'message' => 'Akses Edit Nomor GI Ditolak.', 'code' => 403]);
        try {
            GisRequest::where('req_id', $request->input('reqId'))->update(['erp_gi_no' => $request->input('newNo'), 'updated_at' => Carbon::now('Asia/Jakarta')]);
            return response()->json(['success' => true, 'message' => 'Nomor GI ERP berhasil diperbarui.']);
        } catch (\Exception $e) { return response()->json(['success' => false, 'message' => $e->getMessage()]); }
    }

    private function updateStatus(Request $request)
    {
        $id = $request->input('reqId'); $act = $request->input('act'); $reason = $request->input('reason', '');
        $req = GisRequest::where('req_id', $id)->first();
        if (!$req) return response()->json(['success' => false, 'message' => 'Data not found']);

        $reqPhone = WaHelper::getUserPhone($req->username); $items = $req->items_json; $itemsText = $this->buildItemListText($items);
        $isWarehouseAdmin = in_array($this->currentUser->role, ['Administrator', 'Warehouse']) || ($this->currentUser->role === 'TeamLeader' && strtolower($this->currentUser->department) === 'warehouse');

        if ($act == 'approve') {
            $grandTotal = 0; foreach ($items as $it) { $grandTotal += (intval($it['qty']) * floatval($it['price'] ?? 0)); }

            if ($req->status == 'Pending Head') {
                if (!in_array($this->currentUser->role, ['SectionHead', 'TeamLeader', 'Administrator'])) return response()->json(['success' => false, 'message' => 'Akses ditolak!']);
                if ($grandTotal > 15000000) {
                    $req->update(['status' => 'Pending Plant Head', 'app_head' => 'Approved by ' . $this->currentUser->fullname, 'head_time' => Carbon::now('Asia/Jakarta'), 'updated_at' => Carbon::now('Asia/Jakarta')]);
                    $msgHeader = "⏳ *GI ESCALATED TO PLANT HEAD* ⏳\n┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈\n🔖 *ID GIF* : $id\n👤 *L1 Approve*: {$this->currentUser->fullname}\n💰 *Total* : Rp " . number_format($grandTotal, 0, ',', '.') . "\n┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈\n\n" . $itemsText;
                    $plantPhones = WaHelper::getPhones('PlantHead');
                    foreach ($plantPhones as $ph) WaHelper::sendWA($ph, $msgHeader . "\n\n💡 *Action:* Nilai request melebihi Rp 15 Juta. Silakan login untuk melakukan Approval L2 (Plant Head).");
                    if ($reqPhone) WaHelper::sendWA($reqPhone, $msgHeader . "\n\n💡 *Status:* Telah disetujui Dept Head, saat ini eskalasi ke Plant Head.");
                    return response()->json(['success' => true, 'message' => 'Approved by Dept Head. Eskalasi ke Plant Head.']);
                } else {
                    $req->update(['status' => 'Pending Warehouse', 'app_head' => 'Approved by ' . $this->currentUser->fullname, 'head_time' => Carbon::now('Asia/Jakarta'), 'updated_at' => Carbon::now('Asia/Jakarta')]);
                    $msgHeader = "✅ *GI APPROVED BY HEAD* ✅\n┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈\n🔖 *ID GIF* : $id\n👤 *Approve By*: {$this->currentUser->fullname}\n┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈\n\n" . $itemsText;
                    $whPhones = array_unique(WaHelper::getPhones(['Warehouse', 'TeamLeader'], 'Warehouse'));
                    foreach ($whPhones as $ph) WaHelper::sendWA($ph, $msgHeader . "\n\n💡 *Action:* Silakan siapkan barang fisik dan lakukan pengeluaran (Issue) di sistem.");
                    if ($reqPhone) WaHelper::sendWA($reqPhone, $msgHeader . "\n\n💡 *Status:* Permintaan telah disetujui Head. Menunggu Gudang.");
                    return response()->json(['success' => true, 'message' => 'Approved by Head. Dilanjutkan ke Warehouse.']);
                }
            }
            elseif ($req->status == 'Pending Plant Head') {
                if (!in_array($this->currentUser->role, ['PlantHead', 'Administrator'])) return response()->json(['success' => false, 'message' => 'Akses ditolak! Hanya Plant Head yang bisa approve ini.']);
                $req->update(['status' => 'Pending Warehouse', 'app_planthead' => 'Approved by ' . $this->currentUser->fullname, 'planthead_time' => Carbon::now('Asia/Jakarta'), 'updated_at' => Carbon::now('Asia/Jakarta')]);
                $msgHeader = "✅ *GI APPROVED BY PLANT HEAD* ✅\n┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈\n🔖 *ID GIF* : $id\n👤 *Approve By*: {$this->currentUser->fullname}\n┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈\n\n" . $itemsText;
                $whPhones = array_unique(WaHelper::getPhones(['Warehouse', 'TeamLeader'], 'Warehouse'));
                foreach ($whPhones as $ph) WaHelper::sendWA($ph, $msgHeader . "\n\n💡 *Action:* Approval Plant Head Selesai. Silakan siapkan barang fisik (Issue) di sistem.");
                if ($reqPhone) WaHelper::sendWA($reqPhone, $msgHeader . "\n\n💡 *Status:* Permintaan Anda disetujui Plant Head. Menunggu Gudang.");
                return response()->json(['success' => true, 'message' => 'Approved by Plant Head. Dilanjutkan ke Warehouse.']);
            }
        }
        elseif ($act == 'issue') {
            if ($req->status == 'Pending Warehouse' && $isWarehouseAdmin) {
                if (empty($request->input('photoBase64'))) return response()->json(['success' => false, 'message' => 'Bukti foto wajib dilampirkan.']);
                $photoUrl = $this->uploadGisPhoto($request->input('photoBase64'), "ISSUE_" . preg_replace('/[^a-zA-Z0-9]/', '', $id));
                if (!$photoUrl) return response()->json(['success' => false, 'message' => 'Gagal upload foto.']);
                DB::beginTransaction();
                try {
                    foreach ($items as $it) GisInventory::where('item_code', $it['code'])->decrement('stock', intval($it['qty']), ['last_updated' => Carbon::now('Asia/Jakarta')]);
                    $req->update(['status' => 'Pending Receive', 'app_wh' => 'Issued by ' . $this->currentUser->fullname, 'wh_time' => Carbon::now('Asia/Jakarta'), 'issue_photo' => $photoUrl, 'updated_at' => Carbon::now('Asia/Jakarta')]);
                    DB::commit();
                    $msgHeader = "🚚 *GI ISSUED BY WAREHOUSE* 🚚\n┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈\n🔖 *ID GIF* : $id\n👤 *Issued By*: {$this->currentUser->fullname}\n┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈\n\n" . $itemsText;
                    if ($reqPhone) WaHelper::sendWA($reqPhone, $msgHeader . "\n\n💡 *Action:* Barang fisik sudah disiapkan/dikeluarkan dari gudang. Silakan ambil barang dan lakukan Konfirmasi Penerimaan (Receive) di sistem.");
                    return response()->json(['success' => true, 'message' => 'Barang berhasil di-Issue. Menunggu konfirmasi penerimaan user.']);
                } catch (\Exception $e) { DB::rollBack(); return response()->json(['success' => false, 'message' => $e->getMessage()]); }
            }
        }
        elseif ($act == 'receive') {
            if ($req->status == 'Pending Receive' && $req->username == $this->currentUser->username) {
                if (empty($request->input('photoBase64'))) return response()->json(['success' => false, 'message' => 'Bukti foto penerimaan wajib dilampirkan.']);
                $photoUrl = $this->uploadGisPhoto($request->input('photoBase64'), "RECV_" . preg_replace('/[^a-zA-Z0-9]/', '', $id));
                if (!$photoUrl) return response()->json(['success' => false, 'message' => 'Gagal upload foto.']);
                $req->update(['status' => 'Pending No GI (ERP)', 'received_by' => $this->currentUser->fullname, 'receive_time' => Carbon::now('Asia/Jakarta'), 'receive_photo' => $photoUrl, 'updated_at' => Carbon::now('Asia/Jakarta')]);
                $msgHeader = "🏁 *GI RECEIVED BY USER* 🏁\n┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈\n🔖 *ID GIF* : $id\n👤 *Diterima By*: {$this->currentUser->fullname}\n┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈\n\n" . $itemsText;
                if ($reqPhone) WaHelper::sendWA($reqPhone, $msgHeader . "\n\n💡 *Status:* Barang telah Anda konfirmasi. Saat ini menunggu pihak Warehouse menginput Nomor GI ERP.");
                $whPhones = array_unique(WaHelper::getPhones(['Warehouse', 'TeamLeader'], 'Warehouse'));
                foreach ($whPhones as $ph) WaHelper::sendWA($ph, $msgHeader . "\n\n💡 *Action:* User telah menerima fisik barang. *SILAKAN INPUT NOMOR GI ERP* di sistem GIS untuk menyelesaikan (Complete) transaksi ini.");
                return response()->json(['success' => true, 'message' => 'Barang berhasil diterima. Menunggu input Nomor GI ERP dari Gudang.']);
            } else { return response()->json(['success' => false, 'message' => 'Unauthorized to receive.']); }
        }
        elseif ($act == 'complete_erp') {
            if ($req->status == 'Pending No GI (ERP)' && $isWarehouseAdmin) {
                $erpNo = $request->input('erp_gi_no');
                if (empty($erpNo)) return response()->json(['success' => false, 'message' => 'Nomor GI ERP tidak boleh kosong.']);
                $req->update(['status' => 'Completed', 'erp_gi_no' => $erpNo, 'updated_at' => Carbon::now('Asia/Jakarta')]);
                $msgHeader = "🎉 *GI COMPLETED (FULL)* 🎉\n┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈\n🔖 *ID GIF* : $id\n🧾 *No ERP* : $erpNo\n👤 *Admin WH* : {$this->currentUser->fullname}\n┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈\n";
                if ($reqPhone) WaHelper::sendWA($reqPhone, $msgHeader . "\n\n💡 *Info:* Proses pengeluaran barang dan pendataan di sistem ERP telah selesai sepenuhnya. Terima kasih.");
                return response()->json(['success' => true, 'message' => 'Nomor GI ERP berhasil disimpan. Status transaksi selesai.']);
            } else { return response()->json(['success' => false, 'message' => 'Akses ditolak atau status tidak valid.']); }
        }
        elseif ($act == 'reject') {
            $updateData = ['status' => 'Rejected', 'reject_reason' => $reason, 'updated_at' => Carbon::now('Asia/Jakarta')];
            if ($req->status == 'Pending Head') { $updateData['app_head'] = 'Rejected by ' . $this->currentUser->fullname; $updateData['head_time'] = Carbon::now('Asia/Jakarta'); }
            if ($req->status == 'Pending Plant Head') { $updateData['app_planthead'] = 'Rejected by ' . $this->currentUser->fullname; $updateData['planthead_time'] = Carbon::now('Asia/Jakarta'); }
            if ($req->status == 'Pending Warehouse') { $updateData['app_wh'] = 'Rejected by ' . $this->currentUser->fullname; $updateData['wh_time'] = Carbon::now('Asia/Jakarta'); }
            $req->update($updateData);
            $msgHeader = "❌ *GI REJECTED* ❌\n┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈\n🔖 *ID GIF* : $id\n👤 *Ditolak By*: {$this->currentUser->fullname}\n💬 *Alasan* : $reason\n┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈\n\n" . $itemsText;
            if ($reqPhone) WaHelper::sendWA($reqPhone, $msgHeader . "\n\n💡 *Info:* Mohon maaf, permintaan Good Issue Anda telah ditolak.");
            $whPhones = array_unique(WaHelper::getPhones(['Warehouse', 'TeamLeader'], 'Warehouse'));
            foreach ($whPhones as $ph) WaHelper::sendWA($ph, $msgHeader . "\n\n💡 *Info:* Permintaan GI ini telah ditolak dan tidak akan diproses lebih lanjut.");
            return response()->json(['success' => true, 'message' => 'Request rejected.']);
        }
    }

    private function exportData(Request $request)
    {
        if (!$this->checkAccessSession('export_data')) return response()->json(['success' => false, 'message' => 'Unauthorized.', 'code' => 403]);
        $type = $request->input('export_type'); $start = $request->input('start_date') . " 00:00:00"; $end = $request->input('end_date') . " 23:59:59";
        $data = [];
        if ($type === 'GI') { $data = GisRequest::whereBetween('created_at', [$start, $end])->orderBy('created_at', 'asc')->get(); $data->transform(function($req) { $req->items = $req->items_json; return $req; }); }
        elseif ($type === 'GR') { $data = GisReceive::whereBetween('created_at', [$start, $end])->orderBy('created_at', 'asc')->get(); $data->transform(function($req) { $req->items = $req->items_json; return $req; }); }
        elseif ($type === 'INV') { $data = GisInventory::orderBy('item_name', 'asc')->get(); }
        return response()->json(['success' => true, 'data' => $data]);
    }
}
