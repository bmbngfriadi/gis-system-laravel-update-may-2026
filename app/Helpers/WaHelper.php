<?php

namespace App\Helpers;

use App\Models\User;
use Illuminate\Support\Facades\Http;

class WaHelper
{
    const WA_FOOTER = "\n\n--------------------------------\n🤖 _System Notification. Do Not Reply._";
    const API_KEY = "jkPPFevPkw4DBtPQMsDn"; // API KEY FONNTE ANDA

    public static function sendWA($target, $message)
    {
        if (empty($target)) return false;

        $target = preg_replace('/[^0-9]/', '', $target);
        if (substr($target, 0, 1) == '0') $target = '62' . substr($target, 1);
        elseif (substr($target, 0, 1) == '8') $target = '62' . $target;

        $response = Http::withHeaders([
            'Authorization' => self::API_KEY
        ])->withoutVerifying()->post('https://api.fonnte.com/send', [
            'target' => $target,
            'message' => $message . self::WA_FOOTER,
            'countryCode' => '62'
        ]);

        return $response->body();
    }

    public static function getPhones($roles, $dept = null)
    {
        $phones = [];
        if (!is_array($roles)) $roles = [$roles];

        foreach ($roles as $role) {
            $query = User::where('role', $role);

            if ($dept && !in_array($role, ['PlantHead', 'Administrator', 'Warehouse'])) {
                $query->whereRaw('LOWER(department) = LOWER(?)', [$dept]);
            }

            $results = $query->pluck('phone')->filter()->toArray();

            if (!empty($results)) {
                $phones = array_merge($phones, $results);
                break;
            }
        }
        return array_values($phones);
    }

    public static function getUserPhone($username)
    {
        $user = User::whereRaw('LOWER(username) = LOWER(?)', [$username])->first();
        return $user ? $user->phone : null;
    }
}
