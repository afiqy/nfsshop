<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\AccountSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_workshop_owner_can_view_their_own_subscription(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $plan = SubscriptionPlan::create([
            'name' => 'Premium',
            'slug' => 'premium',
            'price' => 199,
            'billing_interval' => 'monthly',
            'included_workshops' => 5,
            'max_workshops' => 9,
        ]);

        AccountSubscription::create([
            'user_id' => $superAdmin->id,
            'subscription_plan_id' => $plan->id,
            'status' => AccountSubscription::STATUS_ACTIVE,
            'additional_workshops' => 2,
        ]);

        $response = $this->actingAs($superAdmin)->get(route('workshop.subscription'));

        $response->assertOk();
        $response->assertSee('Premium Plan');
        $response->assertSee('0 / 7');
    }

}
