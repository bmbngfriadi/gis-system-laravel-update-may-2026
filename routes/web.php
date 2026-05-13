<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\GisController;
use App\Http\Controllers\UserController;

// ==== FRONTEND ROUTES ====
Route::get('/login', function () {
    if (Auth::check()) return redirect('/');
    return view('login');
})->name('login');

Route::get('/reset', function () {
    if (!request()->has('token')) return redirect('/login')->with('error', 'Token tidak valid.');
    return view('reset');
});

// ==== BACKEND API ROUTES (Legacy Native PHP Support) ====
// Menonaktifkan CSRF khusus untuk endpoint ini agar fetch() JS lama Anda berjalan mulus dengan session Laravel
Route::withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class])->group(function () {
    Route::post('/api/auth.php', [AuthController::class, 'handle']);
    Route::post('/api/gis.php', [GisController::class, 'handle']);
    Route::post('/api/users.php', [UserController::class, 'handle']);
});
// (Catatan: Jika Anda menggunakan Laravel 10 ke bawah, ganti ValidateCsrfToken menjadi VerifyCsrfToken)


// ==== PROTECTED PAGES (Harus Login) ====
Route::middleware('auth')->group(function () {

    // Fungsi pembantu untuk membagikan variabel ke semua tampilan/view
    $getSharedData = function () {
        $currentUser = auth()->user();
        $role = $currentUser->role;
        $dept = $currentUser->department;
        $rights = $currentUser->access_rights ?: [];
        $isAdmin = ($role === 'Administrator');
        $isWH = ($role === 'Warehouse' || ($role === 'TeamLeader' && strtolower($dept) === 'warehouse'));

        // Default Hak Akses untuk Warehouse jika kosong
        if (empty($rights) && $isWH) {
            $rights = ['gi_submit', 'gr_submit', 'item_add', 'item_edit', 'stock_edit', 'export_data', 'price_add', 'price_edit', 'item_delete', 'edit_gi_no', 'edit_gr_no'];
        }

        return compact('currentUser', 'role', 'dept', 'rights', 'isAdmin', 'isWH');
    };

    // Halaman Dashboard Baru (Home)
    Route::get('/', function () use ($getSharedData) {
        return view('dashboard', $getSharedData());
    })->name('home');

    // Halaman Transaksi
    Route::get('/gr', function () use ($getSharedData) {
        return view('gr', $getSharedData());
    });

    Route::get('/gi', function () use ($getSharedData) {
        return view('index', $getSharedData());
    });

    Route::get('/inventory', function () use ($getSharedData) {
        return view('inventory', $getSharedData());
    });

    // Otomatis mengarahkan link lama .php jika masih diakses
    Route::get('/index.php', function () { return redirect('/gi'); });
    Route::get('/gr.php', function () { return redirect('/gr'); });
    Route::get('/inventory.php', function () { return redirect('/inventory'); });
});
