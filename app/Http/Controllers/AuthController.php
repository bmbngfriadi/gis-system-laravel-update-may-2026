<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use App\Helpers\WaHelper;

class AuthController extends Controller
{
    public function handle(Request $request)
    {
        // Trik: Menangkap raw body JSON karena JS lama tidak mengirim header Content-Type
        if ($request->getContent()) {
            $request->merge(json_decode($request->getContent(), true) ?? []);
        }

        $action = $request->input('action');

        if ($action == 'login') return $this->login($request);
        if ($action == 'checkSession') return $this->checkSession();
        if ($action == 'logout') return $this->logout();
        if ($action == 'requestReset') return $this->requestReset($request);
        if ($action == 'confirmReset') return $this->confirmReset($request);

        return response()->json(['success' => false, 'message' => 'Action tidak valid']);
    }

    private function login(Request $request)
    {
        $credentials = [
            'username' => $request->input('username'),
            'password' => $request->input('password')
        ];

        if (Auth::attempt($credentials)) {
            /** @var \App\Models\User $user */
            $user = Auth::user();

            session(['user_logged_in' => true, 'user_data' => $user->toArray()]);

            return response()->json([
                'success' => true,
                'message' => 'Login success',
                'user' => $user
            ]);
        }

        return response()->json(['success' => false, 'message' => 'Username atau Password salah']);
    }

    private function checkSession()
    {
        if (Auth::check() && session('user_logged_in') === true) {
            return response()->json(['success' => true, 'user' => session('user_data')]);
        }
        return response()->json(['success' => false, 'code' => 401]);
    }

    private function logout()
    {
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();

        return response()->json(['success' => true]);
    }

    private function requestReset(Request $request)
    {
        $user = User::where('username', $request->input('username'))->first();

        if ($user) {
            if (!$user->phone) {
                return response()->json(['success' => false, 'message' => 'Nomor WA tidak terdaftar untuk user ini. Hubungi Admin.']);
            }

            $token = bin2hex(random_bytes(16));
            $user->update(['reset_token' => $token]);

            $resetLink = url('/reset?token=' . $token);

            $msg = "🔐 *Permintaan Reset Password*\n\nKlik link berikut untuk membuat password baru Anda:\n$resetLink\n\nJika Anda tidak meminta ini, abaikan pesan ini.";

            WaHelper::sendWA($user->phone, $msg);

            return response()->json(['success' => true, 'message' => 'Link reset password telah dikirim ke WA Anda.']);
        }

        return response()->json(['success' => false, 'message' => 'Username tidak ditemukan.']);
    }

    private function confirmReset(Request $request)
    {
        $token = $request->input('token');
        $user = User::where('reset_token', $token)->first();

        if ($user) {
            $user->update([
                'password' => Hash::make($request->input('newPassword')),
                'reset_token' => null
            ]);

            return response()->json(['success' => true, 'message' => 'Password berhasil diperbarui. Silakan login.']);
        }

        return response()->json(['success' => false, 'message' => 'Token tidak valid atau sudah kadaluarsa.']);
    }
}
