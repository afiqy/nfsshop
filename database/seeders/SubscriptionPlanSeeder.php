<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        SubscriptionPlan::updateOrCreate(
            ['slug' => 'standard-monthly'],
            [
                'name' => 'Standard Monthly',
                'description' => 'Core invoicing features for small workshops.',
                'price' => 99,
                'billing_interval' => 'monthly',
                'trial_days' => 14,
                'features' => [
                    'Unlimited invoices',
                    'Customer and vehicle CRM',
                    'Basic analytics dashboard',
                ],
                'is_active' => true,
            ]
        );

        SubscriptionPlan::updateOrCreate(
            ['slug' => 'growth-annual'],
            [
                'name' => 'Growth Annual',
                'description' => 'All modules plus premium support billed yearly.',
                'price' => 999,
                'billing_interval' => 'yearly',
                'trial_days' => 30,
                'features' => [
                    'Everything in Standard',
                    'Advanced analytics & export',
                    'Priority support & onboarding',
                ],
                'is_active' => true,
            ]
        );
    }
}
