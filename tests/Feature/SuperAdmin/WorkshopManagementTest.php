<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stancl\Tenancy\Database\Models\Tenant;
use Tests\TestCase;

class WorkshopManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_workshop_index(): void
    {
        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'email' => 'super@example.com',
        ]);

        $tenant = Tenant::create([
            'id' => 'garage-pro',
            'data' => [
                'workshop_name' => 'Garage Pro',
                'owner_email' => 'owner@garagepro.test',
                'subscription' => [
                    'status' => 'active',
                    'plan' => 'growth',
                ],
            ],
        ]);

        User::factory()->create([
            'name' => 'Owner One',
            'email' => 'owner@garagepro.test',
            'role' => User::ROLE_ADMIN,
            'tenant_id' => $tenant->id,
            'workshop_name' => 'Garage Pro',
        ]);

        $response = $this->actingAs($superAdmin)->get(route('super-admin.workshops.index'));

        $response->assertOk();
        $response->assertSee('Garage Pro');
    }

    public function test_super_admin_can_update_workshop_details(): void
    {
        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'email' => 'super@example.com',
        ]);

        $tenant = Tenant::create([
            'id' => 'precision-auto',
            'data' => [
                'workshop_name' => 'Precision Auto',
                'owner_email' => 'owner@precision.test',
                'subscription' => [
                    'status' => 'active',
                    'plan' => 'standard',
                ],
            ],
        ]);

        $owner = User::factory()->create([
            'name' => 'Jane Owner',
            'email' => 'owner@precision.test',
            'role' => User::ROLE_ADMIN,
            'tenant_id' => $tenant->id,
            'workshop_name' => 'Precision Auto',
        ]);

        $plan = SubscriptionPlan::create([
            'name' => 'Premium',
            'slug' => 'premium',
            'price' => 199,
            'billing_interval' => 'monthly',
        ]);

        $payload = [
            'workshop_name' => 'Precision Auto & Co',
            'owner_email' => 'owner@precision.test',
            'address' => '123 Workshop Street',
            'phone' => '+1-555-0100',
            'services' => 'Diagnostics, Engine Repair',
            'staff_notes' => 'Premium team',
            'currency' => 'USD',
            'tax_rate' => '15',
            'labor_rate' => '75',
            'callout_fee' => '25',
            'subscription_plan_id' => $plan->id,
            'subscription_plan_label' => 'Premium',
            'subscription_status' => 'active',
            'monthly_fee' => '199',
            'next_billing_date' => '2025-01-01',
            'subscription_notes' => 'Upgraded plan for Q1',
        ];

        $response = $this->actingAs($superAdmin)
            ->put(route('super-admin.workshops.update', $tenant), $payload);

        $response->assertRedirect(route('super-admin.workshops.edit', $tenant));

        $tenant->refresh();
        $owner->refresh();

        $this->assertSame('Precision Auto & Co', $tenant->data['workshop_name']);
        $this->assertSame('Precision Auto & Co', $owner->workshop_name);
        $this->assertSame('Premium team', $tenant->data['staff_notes']);
        $this->assertSame(['Diagnostics', 'Engine Repair'], $tenant->data['services']);
        $this->assertSame('Premium', $tenant->data['subscription']['plan']);
        $this->assertSame('2025-01-01', $tenant->data['subscription']['next_billing_date']);

        $subscription = \App\Models\WorkshopSubscription::where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($subscription);
        $this->assertSame($plan->id, $subscription->subscription_plan_id);
        $this->assertEquals(199, $subscription->amount);
    }
}
