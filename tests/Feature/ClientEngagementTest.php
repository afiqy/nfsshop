<?php

namespace Tests\Feature;

use App\Mail\VehicleReminderEmail;
use App\Models\AccountSubscription;
use App\Models\Customer;
use App\Models\Promotion;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleReminder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClientEngagementTest extends TestCase
{
    use DatabaseMigrations;

    private array $workshops = [];

    public function runDatabaseMigrations(): void
    {
        $this->artisan('migrate:fresh');
        RefreshDatabaseState::$migrated = false;
    }

    public function test_manual_loyalty_is_scoped_and_cannot_go_negative(): void
    {
        [$admin, $workshop] = $this->workshop();
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'tenant_id' => $workshop->id]);
        $otherClient = User::factory()->create(['role' => User::ROLE_CLIENT, 'tenant_id' => $workshop->id]);
        [$customer, $other] = $workshop->run(fn () => [
            Customer::create(['name' => 'A', 'email' => 'a@example.test', 'portal_user_id' => $client->id]),
            Customer::create(['name' => 'B', 'email' => 'b@example.test', 'portal_user_id' => $otherClient->id]),
        ]);
        $this->actingAs($admin)->post(route('engagement.points', $customer->id), ['action' => 'award', 'points' => 10, 'reason' => 'Visit'])->assertSessionHasNoErrors();
        $this->post(route('engagement.points', $customer->id), ['action' => 'redeem', 'points' => 11, 'reason' => 'Reward'])->assertSessionHasErrors('points');
        $this->post(route('engagement.points', $customer->id), ['action' => 'redeem', 'points' => 4, 'reason' => 'Reward'])->assertSessionHasNoErrors();
        $this->assertSame(6, $workshop->run(fn () => (int) $customer->loyaltyEntries()->sum('points')));
        $this->actingAs($client)->get(route('client.engagement'))->assertOk()->assertSee('Loyalty balance: 6 points')->assertSee('Visit');
        $this->actingAs($otherClient)->get(route('client.engagement'))->assertOk()->assertSee('Loyalty balance: 0 points')->assertDontSee('Visit');
        $this->get(route('engagement.customers.show', $other->id))->assertForbidden();
    }

    public function test_promotion_dates_and_plan_access_are_enforced(): void
    {
        [$admin, $workshop] = $this->workshop();
        [$basic, $basicWorkshop] = $this->workshop('basic');
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'tenant_id' => $workshop->id]);
        $basicClient = User::factory()->create(['role' => User::ROLE_CLIENT, 'tenant_id' => $basicWorkshop->id]);
        $workshop->run(fn () => Customer::create(['name' => 'A', 'email' => 'a@example.test', 'portal_user_id' => $client->id]));
        $basicWorkshop->run(fn () => Customer::create(['name' => 'B', 'email' => 'b@example.test', 'portal_user_id' => $basicClient->id]));
        $this->travelTo(now()->setTimezone('Asia/Kuala_Lumpur')->startOfDay());
        $today = now('Asia/Kuala_Lumpur')->toDateString();
        $this->actingAs($admin)->post(route('engagement.promotions.store'), ['title' => 'Visible offer', 'description' => 'Offer details', 'starts_on' => $today, 'ends_on' => $today, 'active' => 1])->assertSessionHasNoErrors();
        $workshop->run(fn () => Promotion::create(['title' => 'Draft offer', 'description' => 'Hidden', 'starts_on' => $today, 'ends_on' => $today, 'active' => false]));
        $this->actingAs($client)->get(route('client.engagement'))->assertOk()->assertSee('Visible offer')->assertDontSee('Draft offer');
        $promotion = $workshop->run(fn () => Promotion::query()->where('title', 'Visible offer')->firstOrFail());
        $this->actingAs($admin)->put(route('engagement.promotions.update', $promotion->id), ['title' => 'Visible offer', 'description' => 'Offer details', 'starts_on' => $today, 'ends_on' => $today, 'active' => 0])->assertSessionHasNoErrors();
        $this->actingAs($client)->get(route('client.engagement'))->assertDontSee('Visible offer');
        $this->actingAs($basic)->get(route('engagement.index'))->assertForbidden();
        $this->actingAs($basicClient)->get(route('client.engagement'))->assertForbidden();
        $this->actingAs($basicClient)->put(route('client.reminder-preference'), ['opt_in' => 0])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('engagement.promotions.store'), ['title' => 'Bad', 'description' => 'Bad', 'starts_on' => '2026-03-02', 'ends_on' => '2026-03-01', 'active' => 1])->assertSessionHasErrors('ends_on');
    }

    public function test_reminders_require_consent_and_current_ownership_and_record_delivery(): void
    {
        Mail::fake();
        [$admin, $workshop] = $this->workshop();
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'tenant_id' => $workshop->id]);
        [$customer, $vehicle, $other] = $workshop->run(function () use ($client) {
            $customer = Customer::create(['name' => 'A', 'email' => 'a@example.test', 'portal_user_id' => $client->id]);
            $other = Customer::create(['name' => 'B', 'email' => 'b@example.test']);
            $vehicle = Vehicle::create(['customer_id' => $customer->id, 'plate_number' => 'TEST1', 'chassis_number' => 'C1', 'engine_number' => 'E1', 'brand' => 'Test', 'model' => 'Car']);

            return [$customer, $vehicle, $other];
        });
        $this->actingAs($admin)->post(route('engagement.reminders.store'), ['vehicle_id' => $vehicle->id, 'type' => 'service', 'due_on' => '2026-12-01', 'note' => 'Oil'])->assertSessionHasNoErrors();
        $reminder = $workshop->run(fn () => VehicleReminder::sole());
        $this->post(route('engagement.reminders.send', $reminder->id))->assertSessionHasErrors('reminder');
        Mail::assertNothingSent();
        $this->actingAs($client)->put(route('client.reminder-preference'), ['opt_in' => 1])->assertSessionHasNoErrors();
        $this->get(route('client.engagement'))->assertOk()->assertSee('Oil')->assertSee('TEST1');
        $this->actingAs($admin)->post(route('engagement.reminders.send', $reminder->id))->assertSessionHasNoErrors();
        Mail::assertSent(VehicleReminderEmail::class, fn ($mail) => $mail->hasTo('a@example.test')
            && str_contains($mail->render(), 'TEST1')
            && str_contains($mail->render(), 'Oil'));
        $this->assertSame('sent', $workshop->run(fn () => $reminder->attempts()->sole()->status));
        $workshop->run(fn () => $vehicle->update(['customer_id' => $other->id]));
        $this->post(route('engagement.reminders.send', $reminder->id))->assertSessionHasErrors('reminder');
        $this->actingAs($client)->get(route('client.engagement'))->assertDontSee('Oil');
        $this->actingAs($admin)->patch(route('engagement.reminders.complete', $reminder->id))->assertSessionHasNoErrors();
        $this->post(route('engagement.reminders.send', $reminder->id))->assertSessionHasErrors('reminder');
        $this->actingAs($admin)->put(route('customers.update', $customer->id), ['name' => 'A', 'email' => 'new@example.test'])->assertSessionHasNoErrors();
        $this->assertFalse($workshop->run(fn () => $customer->fresh()->engagement_reminders_opt_in));
    }

    public function test_workshop_and_staff_isolation(): void
    {
        [$admin, $workshop] = $this->workshop();
        [$otherAdmin] = $this->workshop();
        $mechanic = User::factory()->create(['role' => User::ROLE_MECHANIC, 'tenant_id' => $workshop->id]);
        $customer = $workshop->run(fn () => Customer::create(['name' => 'Private', 'email' => 'private@example.test']));
        $this->actingAs($otherAdmin)->get(route('engagement.customers.show', $customer->id))->assertNotFound();
        $this->actingAs($mechanic)->get(route('engagement.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('engagement.index'))->assertOk()->assertSee('Private')->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_failed_and_unknown_attempts_are_visible_and_require_manual_action(): void
    {
        [$admin, $workshop] = $this->workshop();
        $reminder = $workshop->run(function () {
            $customer = Customer::create(['name' => 'A', 'email' => 'a@example.test', 'engagement_reminders_opt_in' => true]);
            $vehicle = Vehicle::create(['customer_id' => $customer->id, 'plate_number' => 'TEST1', 'chassis_number' => 'C1', 'engine_number' => 'E1', 'brand' => 'Test', 'model' => 'Car']);

            return VehicleReminder::create(['customer_id' => $customer->id, 'vehicle_id' => $vehicle->id, 'type' => 'insurance', 'due_on' => '2026-12-01']);
        });
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('private transport error'));
        $this->actingAs($admin)->post(route('engagement.reminders.send', $reminder->id))->assertSessionHasErrors('reminder');
        $attempt = $workshop->run(fn () => $reminder->attempts()->sole());
        $this->assertSame('failed', $attempt->status);
        $this->get(route('engagement.index'))->assertSee('Mail transport failed')->assertDontSee('private transport error');
        $workshop->run(fn () => $reminder->attempts()->create(['requested_by_user_id' => $admin->id, 'recipient_email' => 'a@example.test', 'status' => 'sending']));
        $this->post(route('engagement.reminders.send', $reminder->id))->assertSessionHasErrors('reminder');
        $unknown = $workshop->run(fn () => $reminder->attempts()->latest('id')->firstOrFail());
        $this->patch(route('engagement.reminders.resolve', [$reminder->id, $unknown->id]))->assertSessionHasNoErrors();
        $this->assertSame($admin->id, $workshop->run(fn () => $unknown->fresh()->resolved_by_user_id));
        Mail::fake();
        $this->post(route('engagement.reminders.send', $reminder->id))->assertSessionHasNoErrors();
        Mail::assertSent(VehicleReminderEmail::class);
    }

    private function workshop(string $tier = 'pro'): array
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $workshop = Tenant::create(['id' => 'engagement-test-'.Str::lower(Str::random(12)), 'account_owner_id' => $admin->id, 'workshop_name' => 'Test Workshop']);
        $this->workshops[] = $workshop;
        $admin->update(['tenant_id' => $workshop->id]);
        $plan = SubscriptionPlan::firstOrCreate(['slug' => $tier], ['name' => ucfirst($tier)]);
        AccountSubscription::create(['user_id' => $admin->id, 'subscription_plan_id' => $plan->id,
            'status' => AccountSubscription::STATUS_ACTIVE, 'current_period_ends_at' => now()->addDay()]);

        return [$admin, $workshop];
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        foreach ($this->workshops as $workshop) {
            $workshop->database()->manager()->deleteDatabase($workshop);
            Tenant::query()->whereKey($workshop->id)->delete();
        }
        parent::tearDown();
    }
}
