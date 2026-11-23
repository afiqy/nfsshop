<?php

namespace Tests\Feature;

use App\Mail\StaffInvitationEmail;
use App\Models\AccountSubscription;
use App\Models\Customer;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class StaffAdministrationTest extends TestCase
{
    use DatabaseMigrations;

    private array $workshops = [];

    public function runDatabaseMigrations(): void
    {
        // Tenant provisioning commits MySQL transactions; legacy migrations cannot be rolled back safely.
        $this->artisan('migrate:fresh');
        RefreshDatabaseState::$migrated = false;
    }

    public function test_staff_invitation_is_one_use_and_grants_only_the_selected_access(): void
    {
        Mail::fake();
        [$admin, $workshop] = $this->createWorkshop();
        $workshop->run(fn () => $this->createCustomerAndVehicle());

        $this->actingAs($admin)->post(route('staff.invite'), [
            'email' => 'MECHANIC@example.test', 'role' => 'mechanic', 'can_view_customers' => '1',
        ])->assertRedirect(route('staff.index'));
        Mail::assertSent(StaffInvitationEmail::class, function ($mail) use (&$url) {
            $url = $mail->invitationUrl;

            return $mail->hasTo('mechanic@example.test');
        });
        auth()->logout();
        tenancy()->end();
        $this->get($url)->assertOk()->assertHeader('Referrer-Policy', 'no-referrer')->assertSee('mechanic@example.test');
        $this->post($url, ['name' => 'Invited Mechanic', 'password' => 'password123', 'password_confirmation' => 'password123'])
            ->assertRedirect(route('login'));
        $this->get($url)->assertNotFound();
        $staff = User::where('email', 'mechanic@example.test')->sole();
        $this->assertSame($workshop->id, $staff->tenant_id);
        $this->assertSame(User::ROLE_MECHANIC, $staff->role);
        $this->assertTrue($staff->can_view_customers);
        $this->actingAs($staff)->get(route('customers.index'))->assertOk()->assertSee('Sample Customer')->assertDontSee('Add customer');
        $this->get(route('vehicles.index'))->assertOk()->assertSee('BETA123')->assertDontSee('Add vehicle');
        $this->get(route('vehicles.history', 1))->assertForbidden();
        $this->post(route('customers.store'), [])->assertForbidden();
        $this->get(route('invoices.index'))->assertForbidden();
    }

    public function test_staff_invitations_expire_can_be_revoked_and_cannot_reuse_existing_accounts(): void
    {
        Mail::fake();
        [$admin, $workshop] = $this->createWorkshop();
        $this->actingAs($admin)->post(route('staff.invite'), ['email' => 'new@example.test', 'role' => 'admin'])->assertRedirect();
        Mail::assertSent(StaffInvitationEmail::class, function ($mail) use (&$url) {
            $url = $mail->invitationUrl;

            return true;
        });
        $central = config('tenancy.database.central_connection');
        $invitation = \App\Models\StaffInvitation::on($central)->where('tenant_id', $workshop->id)->sole();
        $this->delete(route('staff.invitations.revoke', $invitation->id))->assertRedirect();
        auth()->logout();
        tenancy()->end();
        $this->get($url)->assertNotFound();
        $this->actingAs($admin)->post(route('staff.invite'), ['email' => 'new@example.test', 'role' => 'admin'])->assertRedirect();
        $new = \App\Models\StaffInvitation::on($central)->where('tenant_id', $workshop->id)->latest('id')->firstOrFail();
        $new->update(['expires_at' => now()->subSecond()]);
        $latestUrl = Mail::sent(StaffInvitationEmail::class)->last()->invitationUrl;
        auth()->logout();
        tenancy()->end();
        $this->post($latestUrl, ['name' => 'New', 'password' => 'password123', 'password_confirmation' => 'password123'])->assertNotFound();
        $this->actingAs($admin)->post(route('staff.invite'), ['email' => $admin->email, 'role' => 'admin'])->assertSessionHasErrors('email');
    }

    public function test_staff_management_is_workshop_scoped_and_suspension_blocks_existing_sessions(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        [$otherAdmin, $otherWorkshop] = $this->createWorkshop();
        $staff = User::factory()->create(['role' => User::ROLE_MECHANIC, 'tenant_id' => $workshop->id]);
        $other = User::factory()->create(['role' => User::ROLE_MECHANIC, 'tenant_id' => $otherWorkshop->id]);
        $pending = User::factory()->create([
            'role' => User::ROLE_MECHANIC, 'tenant_id' => $workshop->id,
            'status' => User::STATUS_SUSPENDED, 'suspension_reason' => 'Awaiting owner approval',
        ]);
        $access = ['role' => 'mechanic', 'status' => 'active', 'can_view_customers' => '1'];
        $this->actingAs($admin)->get(route('staff.index'))->assertOk()->assertSee($staff->email)
            ->assertDontSee($other->email)->assertDontSee($pending->email);
        $this->patch(route('staff.update', $other), $access)->assertNotFound();
        $this->patch(route('staff.update', $pending), $access)->assertNotFound();
        $this->patch(route('staff.update', $admin), $access)->assertForbidden();
        $this->patch(route('staff.update', $staff), $access)->assertRedirect();
        $this->actingAs($staff->fresh())->get(route('customers.index'))->assertOk();
        $staffDevice = session('device_session_id');
        $staffVersion = session('credential_version');
        $this->actingAs($admin)->patch(route('staff.update', $staff), ['role' => 'mechanic', 'status' => 'suspended'])->assertRedirect();
        $this->actingAs($staff->fresh())->get(route('dashboard'))->assertForbidden();
        $this->actingAs($staff->fresh())->get(route('staff.index'))->assertForbidden();
        $this->actingAs($otherAdmin)->patch(route('staff.update', $staff), $access)->assertNotFound();
        $this->actingAs($admin)->patch(route('staff.update', $staff), ['role' => 'admin', 'status' => 'active'])->assertRedirect();
        $this->actingAs($staff->fresh())->withSession([
            'device_session_id' => $staffDevice, 'credential_version' => $staffVersion,
        ])->get(route('staff.index'))->assertRedirect(route('login'));
        $this->actingAs($staff->fresh())->withSession(['credential_version' => $staff->fresh()->credential_version])
            ->get(route('staff.index'))->assertOk();
        $this->patch(route('staff.update', $admin), ['role' => 'mechanic', 'status' => 'suspended'])->assertForbidden();
    }

    public function test_mechanic_customer_access_can_be_removed_without_granting_write_access(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        $staff = User::factory()->create(['role' => User::ROLE_MECHANIC, 'tenant_id' => $workshop->id, 'can_view_customers' => true]);
        $this->actingAs($staff)->get(route('customers.index'))->assertOk();
        $this->get(route('customers.create'))->assertForbidden();
        $this->get(route('vehicles.create'))->assertForbidden();
        $this->actingAs($admin)->patch(route('staff.update', $staff), ['role' => 'mechanic', 'status' => 'active'])->assertRedirect();
        $this->actingAs($staff->fresh())->get(route('customers.index'))->assertForbidden();
        $this->get(route('vehicles.index'))->assertForbidden();
    }

    public function test_reinviting_replaces_the_link_and_can_create_a_workshop_admin(): void
    {
        Mail::fake();
        [$admin, $workshop] = $this->createWorkshop();
        [$otherAdmin] = $this->createWorkshop();
        $this->actingAs($admin)->post(route('staff.invite'), ['email' => 'admin-staff@example.test', 'role' => 'mechanic'])->assertRedirect();
        $first = Mail::sent(StaffInvitationEmail::class)->first()->invitationUrl;
        $invitation = \App\Models\StaffInvitation::on(config('tenancy.database.central_connection'))
            ->where('tenant_id', $workshop->id)->firstOrFail();
        $this->actingAs($otherAdmin)->delete(route('staff.invitations.revoke', $invitation->id))->assertNotFound();
        $this->actingAs($admin)->post(route('staff.invite'), ['email' => 'admin-staff@example.test', 'role' => 'admin'])->assertRedirect();
        $second = Mail::sent(StaffInvitationEmail::class)->last()->invitationUrl;
        auth()->logout();
        tenancy()->end();
        $this->get($first)->assertNotFound();
        $this->post($second, ['name' => 'Assistant admin', 'password' => 'password123', 'password_confirmation' => 'password123'])
            ->assertRedirect(route('login'));
        $staff = User::where('email', 'admin-staff@example.test')->sole();
        $this->assertSame(User::ROLE_ADMIN, $staff->role);
        $this->assertSame($workshop->id, $staff->tenant_id);
        $this->actingAs($staff)->get(route('invoices.index'))->assertOk();
        $this->get(route('staff.index'))->assertOk();
    }

    public function test_suspended_staff_cannot_sign_in(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        $staff = User::factory()->create(['role' => User::ROLE_MECHANIC, 'tenant_id' => $workshop->id]);
        $this->actingAs($admin)->patch(route('staff.update', $staff), ['role' => 'mechanic', 'status' => 'suspended'])->assertRedirect();
        auth()->logout();
        tenancy()->end();
        $this->from(route('login'))->post(route('login.post'), ['email' => $staff->email, 'password' => 'password'])
            ->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    protected function tearDown(): void
    {
        if ($this->workshops) {
            tenancy()->end();
            foreach ($this->workshops as $workshop) {
                $workshop->database()->manager()->deleteDatabase($workshop);
                Tenant::query()->whereKey($workshop->id)->delete();
            }
        }

        parent::tearDown();
    }

    private function createWorkshop(): array
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $workshop = Tenant::create([
            'id' => 'invoice-test-'.Str::lower(Str::random(12)),
            'account_owner_id' => $admin->id,
            'workshop_name' => 'Beta Workshop',
        ]);
        $this->workshops[] = $workshop;
        $admin->update(['tenant_id' => $workshop->id]);
        $plan = SubscriptionPlan::firstOrCreate(['slug' => 'invoice-beta'], ['name' => 'Invoice beta']);
        AccountSubscription::create([
            'user_id' => $admin->id,
            'subscription_plan_id' => $plan->id,
            'status' => AccountSubscription::STATUS_TRIALING,
            'current_period_ends_at' => now()->addDay(),
        ]);

        return [$admin, $workshop];
    }

    private function createCustomerAndVehicle(): array
    {
        $customer = Customer::create(['name' => 'Sample Customer', 'email' => 'sample@example.test']);

        return [$customer, Vehicle::create($this->vehicleDetails($customer))];
    }

    private function vehicleDetails(Customer $customer): array
    {
        return [
            'customer_id' => $customer->id,
            'plate_number' => 'BETA123',
            'chassis_number' => 'TEST-CHASSIS',
            'engine_number' => 'TEST-ENGINE',
            'brand' => 'Sample',
            'model' => 'Car',
        ];
    }
}
