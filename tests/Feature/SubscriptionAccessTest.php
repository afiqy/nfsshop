<?php

namespace Tests\Feature;

use App\Models\AccountSubscription;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_workshop_owner_can_view_dashboard_and_subscription_but_not_operations(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $tenant = $this->createTenantFor($owner);
        $owner->update(['tenant_id' => $tenant->id]);
        $this->createSubscription($owner, AccountSubscription::STATUS_ACTIVE, now()->subDay());

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('subscription is inactive or expired');

        $this->get(route('invoices.index'))
            ->assertRedirect(route('workshop.subscription'));

        $this->get(route('workshop.subscription'))
            ->assertOk()
            ->assertSee('Expired');
    }

    public function test_expired_workshop_staff_are_directed_to_contact_the_owner(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $tenant = $this->createTenantFor($owner);
        $owner->update(['tenant_id' => $tenant->id]);

        $staff = User::factory()->create([
            'role' => User::ROLE_MECHANIC,
            'tenant_id' => $tenant->id,
        ]);
        $this->createSubscription($owner, AccountSubscription::STATUS_TRIALING, now()->subMinute());

        $this->actingAs($staff)
            ->get(route('invoices.index'))
            ->assertRedirect(route('subscription.required'));

        $this->get(route('subscription.required'))
            ->assertOk()
            ->assertSee('contact your workshop admin')
            ->assertSee('owner@example.test');
    }

    public function test_unprovisioned_workshop_owner_cannot_access_operational_modules(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $tenant = $this->createTenantFor($owner);
        $owner->update(['tenant_id' => $tenant->id]);

        $this->actingAs($owner)
            ->get(route('customers.index'))
            ->assertRedirect(route('workshop.subscription'));
    }

    public function test_expired_account_super_admin_can_only_open_subscription_page(): void
    {
        $accountOwner = User::factory()->create(['role' => User::ROLE_ACCOUNT_SUPER_ADMIN]);
        $this->createSubscription($accountOwner, AccountSubscription::STATUS_ACTIVE, now()->subDay());

        $this->actingAs($accountOwner)
            ->get(route('account.subscription'))
            ->assertOk()
            ->assertSee('Expired');

        $this->get(route('account.workshops.index'))
            ->assertRedirect(route('account.subscription'));
    }

    public function test_canceled_subscription_remains_usable_until_period_end(): void
    {
        $accountOwner = User::factory()->create(['role' => User::ROLE_ACCOUNT_SUPER_ADMIN]);
        $this->createSubscription($accountOwner, AccountSubscription::STATUS_CANCELED, now()->addDay());

        $this->actingAs($accountOwner)
            ->get(route('account.workshops.index'))
            ->assertOk();
    }

    private function createTenantFor(User $owner): Tenant
    {
        return Tenant::create([
            'id' => 'workshop-'.$owner->id,
            'account_owner_id' => $owner->id,
            'data' => [
                'workshop_name' => 'Access Test Workshop',
                'owner_email' => 'owner@example.test',
            ],
        ]);
    }

    private function createSubscription(User $owner, string $status, $periodEndsAt): AccountSubscription
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Basic '.$owner->id,
            'slug' => 'basic-'.$owner->id,
            'price' => 0,
            'billing_interval' => 'monthly',
            'included_workshops' => 1,
            'max_workshops' => 1,
        ]);

        return AccountSubscription::create([
            'user_id' => $owner->id,
            'subscription_plan_id' => $plan->id,
            'status' => $status,
            'current_period_ends_at' => $periodEndsAt,
        ]);
    }
}
