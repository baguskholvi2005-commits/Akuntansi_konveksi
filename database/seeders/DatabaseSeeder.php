<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([AccountSeeder::class, MasterSeeder::class]);

        foreach (['admin','keuangan','gudang','pemilik'] as $role) {
            User::updateOrCreate(
                ['email' => "$role@konveksi.test"],
                ['name' => ucfirst($role), 'role' => $role, 'password' => bcrypt('password')]
            );
        }
    }
}