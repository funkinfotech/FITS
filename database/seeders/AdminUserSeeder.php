<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'kevin@funkinfotech.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('Zq5171pC!'),
                'is_admin' => true,
            ]
        );
    }
}
