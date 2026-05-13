<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'username'      => 'admin',
            'password'      => Hash::make('admin'), // Ini adalah password yang akan digunakan
            'fullname'      => 'Super Administrator',
            'nik'           => '100100',
            'department'    => 'IT / Management',
            'role'          => 'Administrator',
            'phone'         => '081363331467',
            // Memberikan full akses untuk berjaga-jaga
            'access_rights' => ['gi_submit', 'gr_submit', 'item_add', 'item_edit', 'stock_edit', 'export_data', 'price_add', 'price_edit', 'item_delete']
        ]);
    }
}
