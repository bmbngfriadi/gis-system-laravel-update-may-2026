@php
    // --- MENGAMBIL DATA DARI DATABASE UNTUK CHART & STATISTIK ---
    use App\Models\GisRequest;
    use App\Models\GisReceive;
    use App\Models\GisInventory;
    use Carbon\Carbon;

    // Statistik Header
    $today = Carbon::today('Asia/Jakarta');

    $totalGI = GisRequest::count();
    $todayGI = GisRequest::whereDate('created_at', $today)->count();
    $percentGI = $totalGI > 0 ? round(($todayGI / $totalGI) * 100, 1) : 0;

    $totalGR = GisReceive::count();
    $todayGR = GisReceive::whereDate('created_at', $today)->count();
    $percentGR = $totalGR > 0 ? round(($todayGR / $totalGR) * 100, 1) : 0;

    $totalItems = GisInventory::count();
    $lowStock = GisInventory::where('stock', '<', 10)->count();

    // Data untuk Chart Transaksi (7 Hari Terakhir)
    $dates = collect();
    $giDataChart = collect();
    $grDataChart = collect();

    for ($i = 6; $i >= 0; $i--) {
        $date = Carbon::today('Asia/Jakarta')->subDays($i);
        $dates->push($date->format('d M'));

        $giDataChart->push(GisRequest::whereDate('created_at', $date)->count());
        $grDataChart->push(GisReceive::whereDate('created_at', $date)->count());
    }

    // Top Products (Dari Good Issue)
    $allGi = GisRequest::all();
    $itemFreq = [];
    foreach ($allGi as $req) {
        $items = is_string($req->items_json) ? json_decode($req->items_json, true) : $req->items_json;
        if(is_array($items)) {
            foreach ($items as $item) {
                if(isset($item['name'])) {
                    $itemFreq[$item['name']] = ($itemFreq[$item['name']] ?? 0) + intval($item['qty'] ?? 0);
                }
            }
        }
    }
    arsort($itemFreq);
    $topProducts = array_slice($itemFreq, 0, 5, true);
@endphp

@include('includes.header')

<div class="flex justify-between items-center mb-8 animate-slide-up">
    <div>
        <h1 class="text-2xl font-black text-slate-800 tracking-tight">Today's Overview</h1>
        <p class="text-sm text-slate-500 font-medium mt-1">Sistem Rangkuman Aktivitas Gudang</p>
    </div>

    @if($isAdmin || in_array('export_data', $rights))
    <button onclick="openExportModal()" class="bg-white border border-slate-200 text-slate-700 px-5 py-2.5 rounded-xl text-sm font-bold shadow-sm hover:bg-slate-50 transition btn-animated flex items-center gap-2">
        <i class="fas fa-file-export text-slate-400"></i> Export Report
    </button>
    @endif
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8 animate-slide-up" style="animation-delay: 0.1s;">

    <div class="bg-gradient-to-br from-red-50 to-rose-50 p-6 rounded-3xl border border-red-100 shadow-sm relative overflow-hidden group hover:shadow-md transition">
        <div class="w-12 h-12 bg-red-600 text-white rounded-2xl flex items-center justify-center text-xl mb-4 shadow-lg shadow-red-200 group-hover:scale-110 transition"><i class="fas fa-file-export"></i></div>
        <h3 class="text-3xl font-black text-slate-800 tracking-tight mb-1">{{ $totalGI }}</h3>
        <p class="text-sm font-bold text-slate-600 mb-2">Total Good Issue</p>
        <p class="text-[10px] font-bold text-red-600 bg-white px-2 py-1 rounded-md inline-block shadow-sm">+{{ $percentGI }}% dari hari ini</p>

        <div class="absolute -right-6 -bottom-6 w-32 h-32 bg-gradient-to-br from-red-400 to-rose-500 rounded-full opacity-10 group-hover:scale-150 transition duration-500"></div>
    </div>

    <div class="bg-gradient-to-br from-emerald-50 to-teal-50 p-6 rounded-3xl border border-emerald-100 shadow-sm relative overflow-hidden group hover:shadow-md transition">
        <div class="w-12 h-12 bg-emerald-500 text-white rounded-2xl flex items-center justify-center text-xl mb-4 shadow-lg shadow-emerald-200 group-hover:scale-110 transition"><i class="fas fa-file-import"></i></div>
        <h3 class="text-3xl font-black text-slate-800 tracking-tight mb-1">{{ $totalGR }}</h3>
        <p class="text-sm font-bold text-slate-600 mb-2">Total Good Receive</p>
        <p class="text-[10px] font-bold text-emerald-600 bg-white px-2 py-1 rounded-md inline-block shadow-sm">+{{ $percentGR }}% dari hari ini</p>

        <div class="absolute -right-6 -bottom-6 w-32 h-32 bg-gradient-to-br from-emerald-400 to-teal-500 rounded-full opacity-10 group-hover:scale-150 transition duration-500"></div>
    </div>

    <div class="bg-gradient-to-br from-amber-50 to-orange-50 p-6 rounded-3xl border border-amber-100 shadow-sm relative overflow-hidden group hover:shadow-md transition">
        <div class="w-12 h-12 bg-amber-500 text-white rounded-2xl flex items-center justify-center text-xl mb-4 shadow-lg shadow-amber-200 group-hover:scale-110 transition"><i class="fas fa-boxes"></i></div>
        <h3 class="text-3xl font-black text-slate-800 tracking-tight mb-1">{{ $totalItems }}</h3>
        <p class="text-sm font-bold text-slate-600 mb-2">Master Items</p>
        <p class="text-[10px] font-bold text-amber-600 bg-white px-2 py-1 rounded-md inline-block shadow-sm">Total Inventaris Aktif</p>

        <div class="absolute -right-6 -bottom-6 w-32 h-32 bg-gradient-to-br from-amber-400 to-orange-500 rounded-full opacity-10 group-hover:scale-150 transition duration-500"></div>
    </div>

    <div class="bg-gradient-to-br from-rose-50 to-pink-50 p-6 rounded-3xl border border-pink-100 shadow-sm relative overflow-hidden group hover:shadow-md transition">
        <div class="w-12 h-12 bg-pink-500 text-white rounded-2xl flex items-center justify-center text-xl mb-4 shadow-lg shadow-pink-200 group-hover:scale-110 transition"><i class="fas fa-exclamation-triangle"></i></div>
        <h3 class="text-3xl font-black text-slate-800 tracking-tight mb-1">{{ $lowStock }}</h3>
        <p class="text-sm font-bold text-slate-600 mb-2">Low Stock Alert</p>
        <p class="text-[10px] font-bold text-pink-600 bg-white px-2 py-1 rounded-md inline-block shadow-sm">Item Stok < 10</p>

        <div class="absolute -right-6 -bottom-6 w-32 h-32 bg-gradient-to-br from-pink-400 to-rose-500 rounded-full opacity-10 group-hover:scale-150 transition duration-500"></div>
    </div>

</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 animate-slide-up" style="animation-delay: 0.2s;">

    <div class="lg:col-span-2 bg-white rounded-3xl p-6 border border-slate-100 shadow-sm">
        <div class="flex justify-between items-center mb-6">
            <h3 class="font-black text-lg text-slate-800 tracking-tight">Transaction Overview (7 Days)</h3>
            <div class="flex gap-4 text-xs font-bold">
                <div class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-red-500 block"></span> Good Issue</div>
                <div class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-emerald-400 block"></span> Good Receive</div>
            </div>
        </div>
        <div class="relative h-72 w-full">
            <canvas id="mainChart"></canvas>
        </div>
    </div>

    <div class="bg-white rounded-3xl p-6 border border-slate-100 shadow-sm flex flex-col">
        <div class="flex justify-between items-center mb-6">
            <h3 class="font-black text-lg text-slate-800 tracking-tight">Top Requested Items</h3>
            <button class="text-red-600 hover:bg-red-50 p-2 rounded-xl transition text-xs font-bold">View All</button>
        </div>

        <div class="flex-1 space-y-5 overflow-y-auto custom-scroll pr-2">
            @forelse($topProducts as $name => $qty)
            @php
                // Random warna bar bernuansa hangat (merah, orange, pink) agar senada
                $colors = ['bg-red-500', 'bg-orange-500', 'bg-rose-500', 'bg-pink-500', 'bg-amber-500'];
                $color = $colors[$loop->index % count($colors)];
                $percentage = min(($qty / (max($itemFreq) ?: 1)) * 100, 100);
            @endphp
            <div>
                <div class="flex justify-between items-end mb-2">
                    <div class="flex items-center gap-3">
                        <span class="text-xs font-black text-slate-400">0{{ $loop->iteration }}</span>
                        <span class="text-xs font-bold text-slate-700 truncate w-32 sm:w-48" title="{{ $name }}">{{ $name }}</span>
                    </div>
                    <span class="text-xs font-black text-slate-800">{{ $qty }} <span class="text-[9px] text-slate-400 font-normal">Req</span></span>
                </div>
                <div class="w-full bg-slate-100 rounded-full h-1.5">
                    <div class="{{ $color }} h-1.5 rounded-full" style="width: {{ $percentage }}%"></div>
                </div>
            </div>
            @empty
            <div class="h-full flex flex-col items-center justify-center text-slate-400 opacity-50 pb-10">
                <i class="fas fa-box-open text-4xl mb-3"></i>
                <p class="text-xs font-bold">Belum ada data barang.</p>
            </div>
            @endforelse
        </div>
    </div>
</div>

@include('includes.footer')

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Data dari Laravel Controller di-inject langsung ke JavaScript
        const labels = @json($dates);
        const dataGI = @json($giDataChart);
        const dataGR = @json($grDataChart);

        const ctx = document.getElementById('mainChart').getContext('2d');

        // Buat gradient line untuk estetika (Merah untuk GI, Hijau untuk GR)
        let gradientGI = ctx.createLinearGradient(0, 0, 0, 300);
        gradientGI.addColorStop(0, 'rgba(239, 68, 68, 0.4)'); // Red-500
        gradientGI.addColorStop(1, 'rgba(239, 68, 68, 0.0)');

        let gradientGR = ctx.createLinearGradient(0, 0, 0, 300);
        gradientGR.addColorStop(0, 'rgba(52, 211, 153, 0.4)'); // Emerald-400
        gradientGR.addColorStop(1, 'rgba(52, 211, 153, 0.0)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Good Issue',
                        data: dataGI,
                        borderColor: '#ef4444', // red-500
                        backgroundColor: gradientGI,
                        borderWidth: 3,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: '#ef4444',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        fill: true,
                        tension: 0.4 // Membuat garis melengkung smooth (spline)
                    },
                    {
                        label: 'Good Receive',
                        data: dataGR,
                        borderColor: '#34d399', // emerald-400
                        backgroundColor: gradientGR,
                        borderWidth: 3,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: '#34d399',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        fill: true,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }, // Legend sudah kita buat custom di HTML
                    tooltip: {
                        backgroundColor: '#1e293b',
                        padding: 12,
                        titleFont: { family: 'Inter', size: 13 },
                        bodyFont: { family: 'Inter', size: 12, weight: 'bold' },
                        displayColors: true,
                        cornerRadius: 8,
                    }
                },
                scales: {
                    x: {
                        grid: { display: false, drawBorder: false },
                        ticks: { font: { family: 'Inter', size: 11 }, color: '#94a3b8' }
                    },
                    y: {
                        grid: { borderDash: [5, 5], color: '#f1f5f9', drawBorder: false },
                        ticks: { font: { family: 'Inter', size: 11 }, color: '#94a3b8', stepSize: 1, beginAtZero: true }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index',
                },
            }
        });
    });
</script>
