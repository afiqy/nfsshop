<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            $workshopAdmin = User::where('email', 'admin@invsys.com')->firstOrFail();

            $adminTenant = Tenant::updateOrCreate(
                ['id' => 'admin-workshop'],
                [
                    'account_owner_id' => $workshopAdmin->id,
                    'data' => [
                        'workshop_name' => 'Admin Workshop',
                        'owner_email' => 'admin@invsys.com',
                        'address' => null,
                        'phone' => '000-000-0000',
                    ],
                ]
            );

            User::where('email', 'admin@invsys.com')->update([
                'tenant_id' => $adminTenant->id,
                'workshop_name' => 'Admin Workshop',
                'status' => User::STATUS_ACTIVE,
            ]);
        });
    }
}
