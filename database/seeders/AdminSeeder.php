<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run()
    {
        User::updateOrCreate(
            ['email' => 'admin@invsys.com'], // Check if admin already exists
            [
                'name' => 'Admin User',
                'phone' => '000-000-0000',
                'password' => Hash::make('password123'), // Securely hash password
                'role' => User::ROLE_ADMIN,
                'status' => User::STATUS_ACTIVE,
            ]
        );
    }
}
