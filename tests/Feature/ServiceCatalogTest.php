<?php

namespace Tests\Feature;

use App\Mail\AppointmentNotificationEmail;
use App\Mail\InvoiceEmail;
use App\Models\AccountSubscription;
use App\Models\AppointmentNotification;
use App\Models\AppointmentRequest;
use App\Models\Calendar;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\JobCard;
use App\Models\ServicePackage;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkshopService;
use App\Services\AppointmentNotificationService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class ServiceCatalogTest extends TestCase
{
    use DatabaseMigrations;

    private array $workshops = [];

    public function runDatabaseMigrations(): void
    {
        // Tenant provisioning commits MySQL transactions; legacy migrations cannot be rolled back safely.
        $this->artisan('migrate:fresh');
        RefreshDatabaseState::$migrated = false;
    }

    public function test_admin_manages_services_and_packages_with_a_calculated_or_override_price(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        $this->actingAs($admin)->post(route('services.store'), ['name' => 'Oil change', 'price' => '100.25', 'is_active' => 1])->assertRedirect();
        $this->post(route('services.store'), ['name' => 'Inspection', 'price' => '25.50', 'is_active' => 1])->assertRedirect();
        $services = $workshop->run(fn () => WorkshopService::orderBy('id')->get());
        $ids = $services->pluck('id')->all();
        $this->post(route('service-packages.store'), ['name' => 'Basic', 'service_ids' => $ids, 'is_active' => 1])->assertRedirect();
        $package = $workshop->run(fn () => ServicePackage::sole());
        $this->assertSame(12575, $package->price_sen);
        $this->assertCount(2, $package->services);
        $this->get(route('service-packages.edit', $package->id))->assertOk()->assertSee('Selected service total');
        $this->put(route('service-packages.update', $package->id), [
            'name' => 'Basic', 'service_ids' => $ids, 'price' => '110.00', 'is_active' => 1,
        ])->assertRedirect();
        $this->assertSame(11000, $workshop->run(fn () => $package->fresh()->price_sen));
        $this->put(route('services.update', $ids[0]), ['name' => 'Oil change', 'price' => '90', 'is_active' => 0])->assertRedirect();
        $this->post(route('service-packages.store'), ['name' => 'Invalid', 'service_ids' => $ids, 'is_active' => 1])->assertSessionHasErrors('service_ids');
        $this->get(route('services.index'))->assertOk()->assertSee('Inactive')->assertSee('RM 110.00');
    }

    public function test_customer_requests_are_private_and_admin_confirmation_checks_conflicts_and_snapshots(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        [$otherAdmin, $otherWorkshop] = $this->createWorkshop();
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'tenant_id' => $workshop->id]);
        $otherClient = User::factory()->create(['role' => User::ROLE_CLIENT, 'tenant_id' => $workshop->id]);
        [$vehicle, $otherVehicle, $package] = $workshop->run(function () use ($client, $otherClient) {
            $vehicle = $this->vehicleFor($client);
            $otherVehicle = $this->vehicleFor($otherClient, 'OTHER2');
            $package = ServicePackage::create(['name' => 'Package A', 'services' => [
                ['id' => 1, 'name' => 'Oil service', 'price_sen' => 10000],
            ], 'price_sen' => 9000, 'is_active' => true]);

            return [$vehicle, $otherVehicle, $package];
        });
        $start = now('Asia/Kuala_Lumpur')->addDays(3)->startOfHour();
        $details = [
            'vehicle_id' => $vehicle->id, 'selection_type' => 'package', 'package_id' => $package->id,
            'start' => $start->format('Y-m-d\TH:i'), 'end' => $start->copy()->addHour()->format('Y-m-d\TH:i'),
            'notes' => 'Please inspect tyres',
        ];
        $this->actingAs($client)->get(route('client.appointment-requests.index'))->assertOk()->assertSee('Package A');
        $this->post(route('client.appointment-requests.store'), array_replace($details, ['vehicle_id' => $otherVehicle->id]))->assertSessionHasErrors('vehicle_id');
        $this->post(route('client.appointment-requests.store'), $details)->assertRedirect();
        $booking = $workshop->run(fn () => AppointmentRequest::sole());
        $this->assertSame(9000, $booking->service_quote['total_sen']);
        $this->actingAs($otherClient)->get(route('client.appointment-requests.index'))->assertOk()->assertDontSee('Please inspect tyres');
        $this->actingAs($otherAdmin)->patch(route('appointment-requests.confirm', $booking->id))->assertNotFound();
        $workshop->run(function () use ($vehicle, $start, $package) {
            $package->update(['name' => 'Changed', 'price_sen' => 1]);
            Calendar::create([
                'vehicle_id' => $vehicle->id, 'customer_id' => $vehicle->customer_id, 'title' => 'Existing',
                'start' => $start->format('Y-m-d H:i:s'), 'end' => $start->copy()->addHour()->format('Y-m-d H:i:s'),
                'status' => Calendar::STATUS_SCHEDULED,
            ]);
        });
        $this->actingAs($admin)->patch(route('appointment-requests.confirm', $booking->id))->assertSessionHasErrors('start');
        $workshop->run(fn () => Calendar::sole()->update(['status' => Calendar::STATUS_CANCELLED]));
        $this->patch(route('appointment-requests.confirm', $booking->id))->assertRedirect();
        $this->patch(route('appointment-requests.confirm', $booking->id))->assertStatus(409);
        $appointment = $workshop->run(fn () => Calendar::where('status', Calendar::STATUS_SCHEDULED)->sole());
        $this->assertSame('Package A', $appointment->service_quote['name']);
        $this->assertSame(9000, $appointment->service_quote['total_sen']);
        $this->get(route('job-cards.create', ['appointment' => $appointment->id]))->assertOk()->assertSee('Oil service')->assertSee('Please inspect tyres');
        $this->actingAs($client)->get(route('client.appointment-requests.index'))->assertOk()->assertSee('Confirmed')->assertSee('RM 90.00');
    }

    public function test_admin_and_customer_can_select_custom_services_and_rejected_requests_stay_out_of_calendar(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'tenant_id' => $workshop->id]);
        [$vehicle, $service] = $workshop->run(fn () => [
            $this->vehicleFor($client),
            WorkshopService::create(['name' => 'Brake check', 'price_sen' => 1250, 'is_active' => true]),
        ]);
        $start = now('Asia/Kuala_Lumpur')->addDays(2)->startOfHour();
        $details = [
            'vehicle_id' => $vehicle->id, 'selection_type' => 'custom', 'service_ids' => [$service->id],
            'notes' => 'Noise', 'start' => $start->format('Y-m-d\TH:i'), 'end' => $start->copy()->addHour()->format('Y-m-d\TH:i'),
        ];
        $this->actingAs($client)->post(route('client.appointment-requests.store'), $details)->assertRedirect();
        $request = $workshop->run(fn () => AppointmentRequest::sole());
        $this->actingAs($admin)->get(route('appointment-requests.index'))->assertOk()->assertSee('Brake check')->assertSee('Noise');
        $this->patch(route('appointment-requests.reject', $request->id))->assertRedirect();
        $this->assertSame(0, $workshop->run(fn () => Calendar::count()));
        $this->post(route('calendar.store'), $details)->assertRedirect();
        $appointment = $workshop->run(fn () => Calendar::sole());
        $this->assertSame(1250, $appointment->service_quote['total_sen']);
        $this->post(route('job-cards.store'), $details + ['description' => 'Walk-in'])->assertRedirect();
        $job = $workshop->run(fn () => JobCard::sole());
        $this->assertStringContainsString('Brake check', $job->description);
        $this->assertStringContainsString('Noise', $job->description);
    }

    public function test_clients_manage_only_their_future_requests_and_appointments_without_jobs(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        [$admin, $workshop] = $this->createWorkshop();
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'tenant_id' => $workshop->id]);
        $other = User::factory()->create(['role' => User::ROLE_CLIENT, 'tenant_id' => $workshop->id]);
        [$vehicle, $otherVehicle, $service] = $workshop->run(fn () => [
            $this->vehicleFor($client), $this->vehicleFor($other, 'OTHER2'),
            WorkshopService::create(['name' => 'Service', 'price_sen' => 1000, 'is_active' => true]),
        ]);
        $start = now(Calendar::TIMEZONE)->addDays(3)->startOfHour();
        $slot = fn ($time) => ['start' => $time->format('Y-m-d\TH:i'), 'end' => $time->copy()->addHour()->format('Y-m-d\TH:i')];
        $this->actingAs($client)->post(route('client.appointment-requests.store'), $slot($start) + [
            'vehicle_id' => $vehicle->id, 'selection_type' => 'custom', 'service_ids' => [$service->id],
        ])->assertSessionHasNoErrors();
        $booking = $workshop->run(fn () => AppointmentRequest::sole());
        $this->actingAs($other)->put(route('client.appointment-requests.update', $booking->id), $slot($start->copy()->addHour()))->assertNotFound();
        $this->actingAs($client)->put(route('client.appointment-requests.update', $booking->id), $slot($start->copy()->addHour()))->assertSessionHasNoErrors();
        $this->put(route('client.appointment-requests.update', $booking->id), $slot(now(Calendar::TIMEZONE)->subDay()))->assertSessionHasErrors('start');
        $this->post(route('client.appointment-requests.store'), $slot($start->copy()->addDays(1)) + [
            'vehicle_id' => $vehicle->id, 'selection_type' => 'custom', 'service_ids' => [$service->id],
        ])->assertSessionHasNoErrors();
        $cancelledRequest = $workshop->run(fn () => AppointmentRequest::latest('id')->firstOrFail());
        $this->patch(route('client.appointment-requests.cancel', $cancelledRequest->id))->assertSessionHasNoErrors();
        $this->patch(route('client.appointment-requests.cancel', $cancelledRequest->id))->assertStatus(409);
        $this->actingAs($admin)->patch(route('appointment-requests.confirm', $booking->id))->assertSessionHasNoErrors();
        $calendar = $workshop->run(fn () => Calendar::sole());
        $this->actingAs($client)->patch(route('client.appointment-requests.cancel', $booking->id))->assertStatus(409);
        $this->get(route('client.appointment-requests.index'))->assertOk()->assertSee('My appointments')->assertSee(route('client.appointments.update', $calendar->id));
        $this->actingAs($other)->patch(route('client.appointments.cancel', $calendar->id))->assertNotFound();
        $workshop->run(fn () => Calendar::create([
            'customer_id' => $vehicle->customer_id, 'vehicle_id' => $vehicle->id, 'title' => 'Conflict',
            'start' => $start->copy()->addHours(3)->format('Y-m-d H:i:s'),
            'end' => $start->copy()->addHours(4)->format('Y-m-d H:i:s'), 'status' => Calendar::STATUS_SCHEDULED,
        ]));
        $this->actingAs($client)->put(route('client.appointments.update', $calendar->id), $slot($start->copy()->addHours(3)))->assertSessionHasErrors('start');
        $this->put(route('client.appointments.update', $calendar->id), $slot($start->copy()->addHours(5)))->assertSessionHasNoErrors();
        $this->patch(route('client.appointments.cancel', $calendar->id))->assertSessionHasNoErrors();
        $this->patch(route('client.appointments.cancel', $calendar->id))->assertStatus(409);

        $jobBooking = $workshop->run(function () use ($vehicle, $start) {
            $appointment = Calendar::create([
                'customer_id' => $vehicle->customer_id, 'vehicle_id' => $vehicle->id, 'title' => 'Job opened',
                'start' => $start->copy()->addDays(1)->format('Y-m-d H:i:s'),
                'end' => $start->copy()->addDays(1)->addHour()->format('Y-m-d H:i:s'), 'status' => Calendar::STATUS_SCHEDULED,
            ]);
            JobCard::create(['customer_id' => $vehicle->customer_id, 'vehicle_id' => $vehicle->id,
                'calendar_id' => $appointment->id, 'description' => 'Opened', 'status' => JobCard::OPEN]);

            return $appointment;
        });
        $this->patch(route('client.appointments.cancel', $jobBooking->id))->assertStatus(409);
        $this->put(route('client.appointments.update', $jobBooking->id), $slot($start->copy()->addDays(2)))->assertStatus(409);
        $past = $workshop->run(fn () => Calendar::create([
            'customer_id' => $vehicle->customer_id, 'vehicle_id' => $vehicle->id, 'title' => 'Past',
            'start' => now(Calendar::TIMEZONE)->subHour()->toDateTimeString(),
            'end' => now(Calendar::TIMEZONE)->addHour()->toDateTimeString(), 'status' => Calendar::STATUS_SCHEDULED,
        ]));
        $this->patch(route('client.appointments.cancel', $past->id))->assertStatus(409);
        $workshop->run(function () {
            $this->assertSame(2, AppointmentNotification::where('event', 'confirmed')->count());
            $this->assertSame(2, AppointmentNotification::where('event', 'request_cancelled')->count());
            $this->assertSame(2, AppointmentNotification::where('event', 'rescheduled')->count());
        });
        $workshop->run(fn () => $vehicle->update(['customer_id' => $otherVehicle->customer_id]));
        $this->patch(route('client.appointments.cancel', $jobBooking->id))->assertNotFound();
    }

    public function test_appointment_emails_are_queued_once_and_stale_reminders_are_suppressed(): void
    {
        Mail::fake();
        [$admin, $workshop] = $this->createWorkshop();
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'tenant_id' => $workshop->id]);
        $vehicle = $workshop->run(fn () => $this->vehicleFor($client));
        $start = now(Calendar::TIMEZONE)->addHours(23)->startOfMinute();
        $this->actingAs($admin)->post(route('calendar.store'), [
            'vehicle_id' => $vehicle->id, 'service_type' => 'Inspection',
            'start' => $start->format('Y-m-d\TH:i'), 'end' => $start->copy()->addHour()->format('Y-m-d\TH:i'),
        ])->assertSessionHasNoErrors();
        $workshop->run(function () use ($start) {
            $service = app(AppointmentNotificationService::class);
            $service->queueReminders();
            $service->queueReminders();
            $this->assertSame(2, AppointmentNotification::where('event', 'reminder')->count());
            Calendar::sole()->update(['start' => $start->copy()->addDays(2)->format('Y-m-d H:i:s')]);
            $this->assertSame(0, $service->deliverPending());
            $this->assertSame(2, AppointmentNotification::where('status', 'sent')->count());
            $this->assertSame(2, AppointmentNotification::where('status', 'cancelled')->count());
            Calendar::sole()->update(['start' => $start->format('Y-m-d H:i:s')]);
            $service->queueReminders();
            $this->assertSame(2, AppointmentNotification::where('event', 'reminder')->where('status', 'pending')->count());
            Calendar::sole()->update(['end' => $start->copy()->addHours(2)->format('Y-m-d H:i:s')]);
            $this->assertSame(0, $service->deliverPending());
            $this->assertSame(2, AppointmentNotification::where('event', 'reminder')->where('status', 'cancelled')->count());
            $service->queueReminders();
            $this->assertSame(0, $service->deliverPending());
            $service->queueReminders();
            $this->assertSame(2, AppointmentNotification::where('event', 'reminder')->where('status', 'sent')->count());
        });
        Mail::assertSent(AppointmentNotificationEmail::class, 4);
        Mail::assertSent(AppointmentNotificationEmail::class, fn ($mail) => $mail->hasTo($client->email));
        Mail::assertSent(AppointmentNotificationEmail::class, fn ($mail) => $mail->hasTo($admin->email));
        $this->assertStringContainsString('Later changes may apply', Mail::sent(AppointmentNotificationEmail::class)->first()->render());
    }

    public function test_failed_appointment_emails_are_visible_and_require_manual_retry(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'tenant_id' => $workshop->id]);
        $vehicle = $workshop->run(fn () => $this->vehicleFor($client));
        $start = now(Calendar::TIMEZONE)->addDays(3)->startOfHour();
        $this->actingAs($admin)->post(route('calendar.store'), [
            'vehicle_id' => $vehicle->id, 'service_type' => 'Inspection',
            'start' => $start->format('Y-m-d\TH:i'), 'end' => $start->copy()->addHour()->format('Y-m-d\TH:i'),
        ])->assertSessionHasNoErrors();
        tenancy()->end();
        AccountSubscription::query()->where('user_id', $admin->id)->update(['status' => AccountSubscription::STATUS_PENDING]);
        $mailManager = app('mail.manager');
        Mail::fake();
        $this->artisan('appointments:send-notifications')->assertExitCode(0);
        $workshop->run(fn () => $this->assertSame(2, AppointmentNotification::where('status', 'pending')->count()));
        Mail::assertNothingSent();
        AccountSubscription::query()->where('user_id', $admin->id)->update(['status' => AccountSubscription::STATUS_TRIALING]);
        Mail::swap($mailManager);
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('Transport failure'));
        $this->artisan('appointments:send-notifications')->assertExitCode(1);
        $workshop->run(function () {
            $service = app(AppointmentNotificationService::class);
            $this->assertSame(0, $service->deliverPending());
            $this->assertSame(2, AppointmentNotification::where('status', 'failed')->count());
        });
        Mail::swap($mailManager);
        Mail::fake();
        $this->get(route('appointment-requests.index'))->assertOk()->assertSee('Appointment emails needing attention');
        $this->post(route('appointment-notifications.retry', 1))->assertSessionHasNoErrors();
        $this->post(route('appointment-notifications.retry', 1))->assertStatus(409);
        tenancy()->end();
        $this->artisan('appointments:send-notifications')->assertExitCode(0);
        $workshop->run(function () {
            $this->assertSame('sent', AppointmentNotification::findOrFail(1)->status);
            $this->assertSame('failed', AppointmentNotification::findOrFail(2)->status);
            AppointmentNotification::findOrFail(2)->update(['status' => 'sending']);
        });
        Mail::assertSent(AppointmentNotificationEmail::class, 1);
        $this->actingAs($client)->patch(route('appointment-notifications.resolve', 2))->assertForbidden();
        $this->actingAs($admin)->patch(route('appointment-notifications.resolve', 2))->assertSessionHasNoErrors();
        $this->patch(route('appointment-notifications.resolve', 2))->assertStatus(409);
        $this->post(route('appointment-notifications.retry', 2))->assertStatus(409);
        $workshop->run(function () use ($admin) {
            $notification = AppointmentNotification::findOrFail(2);
            $this->assertSame('unknown', $notification->status);
            $this->assertSame($admin->id, $notification->resolved_by_user_id);
            $this->assertNotNull($notification->resolved_at);
        });
    }

    public function test_invoice_discount_and_tax_are_rounded_snapshotted_and_used_for_balances(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'tenant_id' => $workshop->id]);
        $vehicle = $workshop->run(fn () => $this->vehicleFor($client));
        $this->actingAs($admin)->put(route('services.tax'), ['tax_percent' => '6'])->assertRedirect();
        $this->get(route('invoices.create'))->assertOk()->assertSee('value="6.00"', false);
        $data = [
            'customer_id' => $vehicle->customer_id, 'items' => [['description' => 'Work', 'quantity' => 3, 'unit_price' => '10.01']],
            'discount_percent' => '12.50', 'tax_percent' => '6.00', 'tax_mode' => 'exclusive',
        ];
        $this->post(route('invoices.store'), $data)->assertRedirect();
        $invoice = $workshop->run(fn () => Invoice::sole());
        $this->assertSame(2786, $invoice->total_sen);
        $this->assertSame(375, $invoice->pricing['discount_sen']);
        $this->assertSame(158, $invoice->pricing['tax_sen']);
        $this->get(route('invoices.show', $invoice->id))->assertOk()->assertSee('RM 27.86')->assertSee('Tax added');
        $email = $workshop->run(fn () => (new InvoiceEmail($invoice))->render());
        $this->assertStringContainsString('Tax added', $email);
        $this->assertStringContainsString('RM 27.86', $email);
        $this->actingAs($client)->get(route('client.invoices.show', $invoice->id))->assertOk()->assertSee('RM 27.86')->assertSee('Tax added');
        $this->actingAs($admin)->put(route('services.tax'), ['tax_percent' => '10'])->assertRedirect();
        $this->put(route('invoices.update', $invoice->id), $data + ['vehicle_id' => null])->assertRedirect();
        $this->assertSame(600, $workshop->run(fn () => $invoice->fresh()->pricing['tax_rate']));
        $this->post(route('invoices.store'), array_replace($data, ['tax_mode' => 'inclusive']))->assertRedirect();
        $inclusive = $workshop->run(fn () => Invoice::latest('id')->firstOrFail());
        $this->assertSame(2628, $inclusive->total_sen);
        $this->assertSame(149, $inclusive->pricing['tax_sen']);
        $this->post(route('invoices.payments.store', $invoice->id), ['amount' => '27.87'])->assertSessionHasErrors('amount');
        $this->post(route('invoices.payments.store', $invoice->id), ['amount' => '27.86'])->assertRedirect();
        $this->get(route('invoices.show', $invoice->id))->assertOk()->assertSee('Paid');
        $this->post(route('invoices.store'), array_replace($data, ['tax_percent' => '100.01']))->assertSessionHasErrors('tax_percent');
    }

    public function test_service_configuration_is_workshop_scoped_and_admin_only(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        [$otherAdmin, $otherWorkshop] = $this->createWorkshop();
        $mechanic = User::factory()->create(['role' => User::ROLE_MECHANIC, 'tenant_id' => $workshop->id]);
        $service = $workshop->run(fn () => WorkshopService::create(['name' => 'Private service', 'price_sen' => 500, 'is_active' => true]));
        $this->actingAs($otherAdmin)->get(route('services.index'))->assertOk()->assertDontSee('Private service');
        $this->put(route('services.update', $service->id), ['name' => 'Changed', 'price' => '1'])->assertNotFound();
        $this->actingAs($mechanic)->get(route('services.index'))->assertForbidden();
        $this->post(route('services.store'), ['name' => 'Forbidden', 'price' => '1'])->assertForbidden();
    }

    public function test_invoice_pricing_handles_half_sen_rounding_zero_tax_and_a_full_discount(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'tenant_id' => $workshop->id]);
        $vehicle = $workshop->run(fn () => $this->vehicleFor($client));
        $data = [
            'customer_id' => $vehicle->customer_id,
            'items' => [['description' => 'Small charge', 'quantity' => 1, 'unit_price' => '0.05']],
            'discount_percent' => '10', 'tax_percent' => '0', 'tax_mode' => 'inclusive',
        ];
        $this->actingAs($admin)->post(route('invoices.store'), $data)->assertRedirect();
        $invoice = $workshop->run(fn () => Invoice::sole());
        $this->assertSame(1, $invoice->pricing['discount_sen']);
        $this->assertSame(0, $invoice->pricing['tax_sen']);
        $this->assertSame(4, $invoice->total_sen);
        $this->put(route('invoices.update', $invoice->id), [
            'customer_id' => $vehicle->customer_id,
            'items' => [['description' => 'Small charge', 'quantity' => 1, 'unit_price' => '0.05']],
        ])->assertRedirect();
        $this->assertSame(4, $workshop->run(fn () => $invoice->fresh()->total_sen));
        $this->post(route('invoices.store'), array_replace($data, ['discount_percent' => '100', 'tax_percent' => '100', 'tax_mode' => 'exclusive']))->assertRedirect();
        $free = $workshop->run(fn () => Invoice::latest('id')->firstOrFail());
        $this->assertSame(0, $free->total_sen);
        $this->assertSame(0, $free->pricing['tax_sen']);
    }

    private function createWorkshop(): array
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $workshop = Tenant::create(['id' => 'service-test-'.Str::lower(Str::random(12)), 'account_owner_id' => $admin->id, 'workshop_name' => 'Test Workshop']);
        $this->workshops[] = $workshop;
        $admin->update(['tenant_id' => $workshop->id]);
        $plan = SubscriptionPlan::firstOrCreate(['slug' => 'service-beta'], ['name' => 'Service beta']);
        AccountSubscription::create(['user_id' => $admin->id, 'subscription_plan_id' => $plan->id,
            'status' => AccountSubscription::STATUS_TRIALING, 'current_period_ends_at' => now()->addDay()]);

        return [$admin, $workshop];
    }

    private function vehicleFor(User $client, string $plate = 'TEST1'): Vehicle
    {
        $customer = Customer::create(['name' => $client->name, 'email' => $client->email, 'portal_user_id' => $client->id]);

        return Vehicle::create([
            'customer_id' => $customer->id, 'plate_number' => $plate, 'chassis_number' => 'CHASSIS-'.$plate,
            'engine_number' => 'ENGINE-'.$plate, 'brand' => 'Test', 'model' => 'Car',
        ]);
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
