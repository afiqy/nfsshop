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
            ['email' => 'admin@nfsworkshop.com'], // Check if admin already exists
            [
                'name' => 'Admin User',
                'password' => Hash::make('password123'), // Securely hash password
                'role' => 'admin',
            ]
        );
    }
}

