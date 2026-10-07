<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'kiprotichmeshack173@gmail.com'],
            [
                'name' => 'Meshack Kiprotich',
                'password' => Hash::make('password123'),
                'role' => 'admin',
                'is_verified' => true,
                'staff_id' => 'ADM-002'
            ]
        );
    }
}
