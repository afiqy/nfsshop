<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\AccountSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Tenant;
use Tests\TestCase;

class WorkshopManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_pro_account_cannot_create_more_than_four_workshops(): void
    {
        $superAdmin = User::factory()->create([
            'role' => User::ROLE_ACCOUNT_SUPER_ADMIN,
        ]);

        $plan = SubscriptionPlan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'price' => 199,
            'billing_interval' => 'monthly',
            'included_workshops' => 1,
            'max_workshops' => 4,
        ]);

        AccountSubscription::create([
            'user_id' => $superAdmin->id,
            'subscription_plan_id' => $plan->id,
            'status' => AccountSubscription::STATUS_ACTIVE,
            'additional_workshops' => 3,
        ]);

        foreach (range(1, 4) as $number) {
            Tenant::create([
                'id' => 'pro-branch-'.$number,
                'account_owner_id' => $superAdmin->id,
                'data' => ['workshop_name' => 'Pro Branch '.$number],
            ]);
        }

        $response = $this->actingAs($superAdmin)->post(route('account.workshops.store'), [
            'workshop_name' => 'Pro Branch 5',
            'owner_email' => 'branch5@example.com',
            'currency' => 'MYR',
            'subscription_status' => 'active',
        ]);

        $response->assertSessionHasErrors('workshop_limit');
        $this->assertSame(4, Tenant::where('account_owner_id', $superAdmin->id)->count());
    }

    public function test_account_super_admin_only_sees_owned_workshops(): void
    {
        $accountOwner = User::factory()->create(['role' => User::ROLE_ACCOUNT_SUPER_ADMIN]);
        $otherOwner = User::factory()->create(['role' => User::ROLE_ACCOUNT_SUPER_ADMIN]);

        Tenant::create([
            'id' => 'my-workshop',
            'account_owner_id' => $accountOwner->id,
            'data' => ['workshop_name' => 'My Workshop'],
        ]);
        Tenant::create([
            'id' => 'other-workshop',
            'account_owner_id' => $otherOwner->id,
            'data' => ['workshop_name' => 'Other Workshop'],
        ]);

        $response = $this->actingAs($accountOwner)->get(route('account.workshops.index'));

        $response->assertOk();
        $response->assertSee('My Workshop');
        $response->assertDontSee('Other Workshop');
    }

    public function test_super_admin_can_view_workshop_index(): void
    {
        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'email' => 'super@example.com',
        ]);

        $tenant = Tenant::create([
            'id' => 'garage-pro',
            'account_owner_id' => $superAdmin->id,
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
            'account_owner_id' => $superAdmin->id,
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
        $subscription = \App\Models\WorkshopSubscription::where('tenant_id', $tenant->id)->first();
        $this->assertNull($subscription);
    }
}
