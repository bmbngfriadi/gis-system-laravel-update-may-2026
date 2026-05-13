<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function handle(Request $request)
    {
        // Trik: Menangkap raw body JSON
        if ($request->getContent()) {
            $request->merge(json_decode($request->getContent(), true) ?? []);
        }

        if (!Auth::check() || session('user_logged_in') !== true) {
            return response()->json(['success' => false, 'message' => 'Sesi habis.', 'code' => 401]);
        }

        $action = $request->input('action');

        if ($action == 'getAllUsers') return $this->getAllUsers();
        if ($action == 'saveUser') return $this->saveUser($request);
        if ($action == 'updateProfile') return $this->updateProfile($request);
        if ($action == 'deleteUser') return $this->deleteUser($request);

        return response()->json(['success' => false, 'message' => 'Action tidak valid']);
    }

    private function getAllUsers()
    {
        if (Auth::user()->role !== 'Administrator') {
            return response()->json(['success' => false, 'message' => 'Access Denied', 'code' => 403]);
        }

        $users = User::select('id', 'username', 'fullname', 'nik', 'department', 'role', 'phone', 'access_rights')
                     ->orderBy('fullname', 'asc')
                     ->get();

        $users->transform(function ($user) {
            $user->access_rights = json_encode($user->access_rights ?: []);
            return $user;
        });

        return response()->json($users);
    }

    private function saveUser(Request $request)
    {
        if (Auth::user()->role !== 'Administrator') {
            return response()->json(['success' => false, 'message' => 'Access Denied', 'code' => 403]);
        }

        $isEdit = $request->input('isEdit');
        $data = $request->input('data');

        $accessRights = isset($data['access_rights']) ? json_decode($data['access_rights'], true) : [];

        try {
            if (!$isEdit) {
                if (User::where('username', $data['username'])->exists()) {
                    return response()->json(['success' => false, 'message' => 'Username exists!']);
                }

                User::create([
                    'username' => $data['username'],
                    'password' => Hash::make($data['password']),
                    'fullname' => $data['fullname'],
                    'nik' => $data['nik'],
                    'department' => $data['department'],
                    'role' => $data['role'],
                    'phone' => $data['phone'],
                    'access_rights' => $accessRights
                ]);
            } else {
                $user = User::where('username', $data['username'])->first();
                $updateData = [
                    'fullname' => $data['fullname'],
                    'nik' => $data['nik'],
                    'department' => $data['department'],
                    'role' => $data['role'],
                    'phone' => $data['phone'],
                    'access_rights' => $accessRights
                ];

                if (!empty($data['password'])) {
                    $updateData['password'] = Hash::make($data['password']);
                }

                $user->update($updateData);
            }

            return response()->json(['success' => true, 'message' => 'User saved.']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function updateProfile(Request $request)
    {
        $sessionUsername = Auth::user()->username;
        $reqUsername = $request->input('username');

        if ($sessionUsername !== $reqUsername && Auth::user()->role !== 'Administrator') {
            return response()->json(['success' => false, 'message' => 'Akses ditolak!', 'code' => 403]);
        }

        try {
            $user = User::where('username', $reqUsername)->first();
            $updateData = ['phone' => $request->input('phone')];

            if ($request->filled('newPass')) {
                $updateData['password'] = Hash::make($request->input('newPass'));
            }

            $user->update($updateData);

            if ($sessionUsername === $reqUsername) {
                $userData = session('user_data');
                $userData['phone'] = $updateData['phone'];
                session(['user_data' => $userData]);
            }

            return response()->json(['success' => true, 'message' => 'Profile updated successfully']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Update failed: ' . $e->getMessage()]);
        }
    }

    private function deleteUser(Request $request)
    {
        if (Auth::user()->role !== 'Administrator') {
            return response()->json(['success' => false, 'message' => 'Access Denied', 'code' => 403]);
        }

        $username = $request->input('username');
        if (strtolower($username) == 'admin') {
            return response()->json(['success' => false, 'message' => 'Cannot delete Admin.']);
        }

        try {
            User::where('username', $username)->delete();
            return response()->json(['success' => true, 'message' => 'User deleted.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
