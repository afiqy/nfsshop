<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'superadmin@invsys.com'],
            [
                'name' => 'Super Admin',
                'phone' => '999-999-9999',
                'password' => Hash::make('superpassword123'),
                'role' => User::ROLE_SUPER_ADMIN,
                'tenant_id' => null,
                'workshop_name' => null,
                'status' => User::STATUS_ACTIVE,
            ]
        );
    }
}
