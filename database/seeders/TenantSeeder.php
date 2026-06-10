<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Database\Models\Tenant;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            $adminTenant = Tenant::updateOrCreate(
                ['id' => 'admin-workshop'],
                [
                    'data' => [
                        'workshop_name' => 'Admin Workshop',
                        'owner_email' => 'admin@invsys.com',
                        'address' => null,
                        'phone' => '000-000-0000',
                        'subscription' => [
                            'plan' => 'standard',
                            'status' => 'active',
                            'monthly_fee' => 0,
                            'next_billing_date' => null,
                            'notes' => 'Default seeded admin tenant',
                        ],
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
