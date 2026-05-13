<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Panggil UserSeeder untuk dieksekusi
        $this->call([
            UserSeeder::class,
        ]);
    }
}
