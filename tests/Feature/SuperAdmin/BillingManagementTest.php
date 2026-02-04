<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\WorkshopSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stancl\Tenancy\Database\Models\Tenant;
use Tests\TestCase;

class BillingManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_subscription_plan(): void
    {
        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
        ]);

        $payload = [
            'name' => 'Pro Monthly',
            'price' => 149,
            'billing_interval' => 'monthly',
            'trial_days' => 7,
            'description' => 'Pro features for growing workshops.',
            'features' => [
                'Priority support',
                'Advanced analytics',
            ],
            'is_active' => true,
        ];

        $response = $this->actingAs($superAdmin)
            ->post(route('super-admin.billing.plans.store'), $payload);

        $response->assertRedirect();

        $this->assertDatabaseHas('subscription_plans', [
            'name' => 'Pro Monthly',
            'price' => 149,
            'billing_interval' => 'monthly',
        ]);
    }

    public function test_super_admin_can_mark_subscription_as_paid(): void
    {
        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
        ]);

        $plan = SubscriptionPlan::create([
            'name' => 'Standard Monthly',
            'slug' => 'standard-monthly',
            'price' => 99,
            'billing_interval' => 'monthly',
        ]);

        $tenant = Tenant::create([
            'id' => 'billing-shop',
            'data' => [
                'workshop_name' => 'Billing Shop',
                'subscription' => [
                    'plan' => 'Standard Monthly',
                    'status' => 'past_due',
                ],
            ],
        ]);

        $subscription = WorkshopSubscription::create([
            'tenant_id' => $tenant->id,
            'subscription_plan_id' => $plan->id,
            'status' => WorkshopSubscription::STATUS_PAST_DUE,
            'amount' => 99,
            'overdue_amount' => 99,
        ]);

        $response = $this->actingAs($superAdmin)->patch(route('super-admin.billing.subscriptions.update', $subscription), [
            'status' => WorkshopSubscription::STATUS_ACTIVE,
            'overdue_amount' => 0,
            'last_paid_at' => now()->toDateString(),
        ]);

        $response->assertRedirect();

        $subscription->refresh();

        $this->assertSame(WorkshopSubscription::STATUS_ACTIVE, $subscription->status);
        $this->assertEquals(0.0, (float) $subscription->overdue_amount);
    }
}
