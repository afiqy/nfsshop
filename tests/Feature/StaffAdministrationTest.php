<?php

namespace Tests\Feature;

use App\Mail\StaffInvitationEmail;
use App\Models\AccountSubscription;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\JobCard;
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
            'can_record_payments' => '1',
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
        $this->assertTrue($staff->can_record_payments);
        $this->actingAs($staff)->get(route('customers.index'))->assertOk()->assertSee('Sample Customer')->assertDontSee('Add customer');
        $this->get(route('vehicles.index'))->assertOk()->assertSee('BETA123')->assertDontSee('Add vehicle');
        $this->get(route('vehicles.history', 1))->assertForbidden();
        $this->post(route('customers.store'), [])->assertForbidden();
        $this->get(route('invoices.index'))->assertForbidden();
        $this->get(route('staff-invoices.index'))->assertOk();
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
            ->assertDontSee($other->email)->assertSee($pending->email);
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

    public function test_staff_request_can_be_rejected_then_approved_only_by_its_workshop(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        $this->post(route('register.account'), [
            'name' => 'Applicant', 'email' => 'applicant@example.test', 'phone' => '123',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertRedirect(route('register.workshop'));
        $this->post(route('register.workshop.submit'), ['workshop_name' => 'Beta Workshop', 'workshop_address' => 'Test address'])
            ->assertRedirect(route('register.staff'));
        $this->post(route('register.staff.submit'), ['desired_role' => 'mechanic'])->assertRedirect(route('login'));
        $applicant = User::on(config('tenancy.database.central_connection'))->where('email', 'applicant@example.test')->sole();
        $this->assertSame($workshop->id, $applicant->tenant_id);
        [$otherAdmin] = $this->createWorkshop();
        $url = route('staff.requests.decide', $applicant->id);
        $this->actingAs($otherAdmin)->patch($url, ['decision' => 'approve', 'role' => 'admin'])->assertNotFound();
        $this->actingAs($admin)->get(route('staff.index'))->assertSee('applicant@example.test');
        $this->patch($url, ['decision' => 'reject'])->assertSessionHasNoErrors();
        $this->assertSame('Staff request rejected', $applicant->fresh()->suspension_reason);
        $this->assertSame(User::STATUS_SUSPENDED, $applicant->fresh()->status);
        $this->patch($url, ['decision' => 'approve', 'role' => 'mechanic', 'can_record_payments' => 1])
            ->assertSessionHasNoErrors();
        $applicant->refresh();
        $this->assertSame(User::STATUS_ACTIVE, $applicant->status);
        $this->assertTrue($applicant->can_record_payments);
        $this->assertFalse($applicant->can_create_job_invoices);
        $this->patch($url, ['decision' => 'approve', 'role' => 'admin'])->assertNotFound();
    }

    public function test_existing_unassigned_mechanic_must_sign_in_to_accept_and_cannot_move_a_linked_account(): void
    {
        Mail::fake();
        [$admin, $workshop] = $this->createWorkshop();
        $existing = User::factory()->create(['role' => User::ROLE_MECHANIC, 'tenant_id' => null])->fresh();
        $other = User::factory()->create(['role' => User::ROLE_MECHANIC, 'tenant_id' => null])->fresh();
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'tenant_id' => null])->fresh();
        $blocked = User::factory()->create(['role' => User::ROLE_MECHANIC, 'tenant_id' => null, 'status' => User::STATUS_SUSPENDED])->fresh();
        $this->actingAs($admin)->post(route('staff.invite'), ['email' => $client->email, 'role' => 'mechanic'])->assertSessionHasErrors('email');
        $this->post(route('staff.invite'), ['email' => $blocked->email, 'role' => 'mechanic'])->assertSessionHasErrors('email');
        $this->actingAs($admin)->post(route('staff.invite'), ['email' => $existing->email, 'role' => 'mechanic',
            'can_create_job_invoices' => 1])->assertSessionHasNoErrors();
        $url = Mail::sent(StaffInvitationEmail::class)->last()->invitationUrl;
        $this->actingAs($other)->get($url)->assertNotFound();
        $this->actingAs($existing)->get($url)->assertOk();
        $this->post($url)->assertRedirect(route('dashboard'));
        $existing->refresh();
        $this->assertSame($workshop->id, $existing->tenant_id);
        $this->assertTrue($existing->can_create_job_invoices);
        $this->assertSame(1, $existing->credential_version);
        $this->get($url)->assertNotFound();
        $this->actingAs($admin)->post(route('staff.invite'), ['email' => $existing->email, 'role' => 'mechanic'])
            ->assertSessionHasErrors('email');
    }

    public function test_financial_permissions_limit_invoice_creation_and_manual_payments(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        [$otherAdmin] = $this->createWorkshop();
        $staff = User::factory()->create(['role' => User::ROLE_MECHANIC, 'tenant_id' => $workshop->id,
            'can_create_job_invoices' => true])->fresh();
        [$completed, $open, $otherJob] = $workshop->run(function () use ($staff) {
            [$customer, $vehicle] = $this->createCustomerAndVehicle();
            $base = ['customer_id' => $customer->id, 'vehicle_id' => $vehicle->id, 'description' => 'Service'];

            return [
                JobCard::create($base + ['status' => JobCard::COMPLETED, 'assigned_mechanic_id' => $staff->id]),
                JobCard::create($base + ['status' => JobCard::OPEN, 'assigned_mechanic_id' => $staff->id]),
                JobCard::create($base + ['status' => JobCard::COMPLETED]),
            ];
        });
        $payload = ['customer_id' => 999, 'vehicle_id' => 999,
            'items' => [['description' => 'Labour', 'quantity' => 1, 'unit_price' => '100.00']]];
        $this->actingAs($staff)->get(route('staff-invoices.create', $completed))->assertOk();
        $this->post(route('staff-invoices.store', $open), $payload)->assertNotFound();
        $this->post(route('staff-invoices.store', $otherJob), $payload)->assertNotFound();
        $this->post(route('staff-invoices.store', $completed), array_replace($payload, [
            'items' => [['description' => 'Part', 'quantity' => 1, 'unit_price' => '1.00', 'inventory_item_id' => 1]],
        ]))->assertSessionHasErrors('items');
        $this->post(route('staff-invoices.store', $completed), $payload)->assertRedirect(route('staff-invoices.show', 1));
        $this->post(route('staff-invoices.store', $completed), $payload)->assertSessionHasErrors('job');
        $workshop->run(function () use ($completed) {
            $invoice = Invoice::sole();
            $this->assertSame($completed->id, $invoice->job_card_id);
            $this->assertSame($completed->customer_id, $invoice->customer_id);
            $this->assertSame($completed->vehicle_id, $invoice->vehicle_id);
        });
        $this->post(route('staff-invoices.payments.store', 1), ['amount' => '10.00', 'payment_method' => 'cash'])->assertForbidden();
        $this->get(route('invoices.show', 1))->assertForbidden();
        $this->actingAs($otherAdmin)->get(route('invoices.show', 1))->assertNotFound();
        $this->actingAs($admin)->patch(route('staff.update', $staff), [
            'role' => 'mechanic', 'status' => 'active', 'can_record_payments' => 1,
        ])->assertSessionHasNoErrors();
        $this->post(route('invoices.store'), [
            'customer_id' => $completed->customer_id,
            'items' => [['description' => 'Other work', 'quantity' => 1, 'unit_price' => '50.00']],
        ])->assertRedirect(route('invoices.show', 2));
        $this->put(route('invoices.update', 1), $payload)->assertSessionHasErrors('customer_id');
        $this->actingAs($staff->fresh())->get(route('staff-invoices.index'))->assertOk()->assertSee('Sample Customer');
        $this->get(route('staff-invoices.show', 2))->assertOk()->assertSee('Other work');
        $this->get(route('staff-invoices.create', $completed))->assertForbidden();
        $this->post(route('staff-invoices.payments.store', 1), ['amount' => '10.00', 'payment_method' => 'pos'])
            ->assertRedirect(route('staff-invoices.show', 1))->assertSessionHasNoErrors();
        $workshop->run(fn () => $this->assertSame('pos', Invoice::findOrFail(1)->payments()->sole()->payment_method));
        $this->post(route('staff-invoices.payments.store', 1), ['amount' => '1000.00', 'payment_method' => 'cash'])
            ->assertSessionHasErrors('amount');
        $this->post(route('staff-invoices.payments.store', 2), ['amount' => '5.00', 'payment_method' => 'transfer'])
            ->assertSessionHasNoErrors();
        $this->get(route('payment-preferences.edit'))->assertForbidden();
        $this->post(route('invoices.credit-notes.store', 1))->assertForbidden();
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
