<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        SubscriptionPlan::updateOrCreate(
            ['slug' => 'basic'],
            [
                'name' => 'Basic',
                'description' => 'Basic workshop features for one workshop. Pricing is managed by Qysoft Main.',
                'price' => 0,
                'billing_interval' => 'monthly',
                'trial_days' => 14,
                'included_workshops' => 1,
                'max_workshops' => 1,
                'features' => [
                    'Basic invoicing and workshop features',
                    'One workshop',
                ],
                'is_active' => true,
            ]
        );

        SubscriptionPlan::updateOrCreate(
            ['slug' => 'pro'],
            [
                'name' => 'Pro',
                'description' => 'Additional features for one to four workshops. Pricing is managed by Qysoft Main.',
                'price' => 0,
                'billing_interval' => 'monthly',
                'trial_days' => 14,
                'included_workshops' => 1,
                'max_workshops' => 4,
                'features' => [
                    'All Basic features',
                    'Additional Pro features',
                    'Up to four workshops',
                ],
                'is_active' => true,
            ]
        );

        SubscriptionPlan::updateOrCreate(
            ['slug' => 'enterprise'],
            [
                'name' => 'Enterprise',
                'description' => 'Enterprise features for up to ten workshops; additional workshops by request. Pricing is managed by Qysoft Main.',
                'price' => 0,
                'billing_interval' => 'annual',
                'trial_days' => 0,
                'included_workshops' => 10,
                'max_workshops' => null,
                'features' => [
                    'All Pro features',
                    'Enterprise features',
                    'Ten included workshops',
                    'Additional workshops by request',
                ],
                'is_active' => true,
            ]
        );
    }
}
