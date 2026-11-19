<?php

namespace Tests\Feature;

use App\Models\AccountSubscription;
use App\Models\Calendar;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InventoryItem;
use App\Models\JobCard;
use App\Models\PaymentPreference;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as StripeRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    private array $workshops = [];

    public function test_admin_can_assign_reassign_and_clear_jobs_for_active_mechanics_in_the_same_workshop(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        [, $otherWorkshop] = $this->createWorkshop();
        $staff = ['role' => User::ROLE_MECHANIC, 'tenant_id' => $workshop->id];
        $first = User::factory()->create($staff + ['name' => 'First Mechanic']);
        $second = User::factory()->create($staff + ['name' => 'Second Mechanic']);
        $suspended = User::factory()->create($staff + ['name' => 'Suspended Mechanic', 'status' => User::STATUS_SUSPENDED]);
        $outsider = User::factory()->create(['role' => User::ROLE_MECHANIC, 'tenant_id' => $otherWorkshop->id, 'name' => 'Outside Mechanic']);
        [$job, $unassigned] = $workshop->run(function () {
            [$customer, $vehicle] = $this->createCustomerAndVehicle();
            $details = ['customer_id' => $customer->id, 'vehicle_id' => $vehicle->id, 'status' => JobCard::OPEN];

            return [
                JobCard::create($details + ['description' => 'Check the brakes']),
                JobCard::create($details + ['description' => 'Check the steering']),
            ];
        });

        $show = route('job-cards.show', $job);
        $assign = route('job-cards.assign', $job);
        $myJob = route('my-jobs.show', $job);
        $advance = route('my-jobs.advance', $job);

        $this->actingAs($admin)->get($show)->assertOk()
            ->assertSeeText('First Mechanic')->assertSeeText('Second Mechanic')
            ->assertDontSeeText('Outside Mechanic')->assertDontSeeText('Suspended Mechanic');
        foreach ([$admin, $outsider, $suspended] as $ineligible) {
            $this->patch($assign, ['assigned_mechanic_id' => $ineligible->id])->assertSessionHasErrors('assigned_mechanic_id');
        }
        $this->patch($assign, ['assigned_mechanic_id' => $first->id])->assertRedirect($show);
        $this->get(route('job-cards.index'))->assertOk()->assertSeeText('First Mechanic');

        $this->actingAs($first)->get(route('my-jobs.index'))->assertOk()
            ->assertSee('href="'.$myJob.'"', false)->assertDontSee(route('my-jobs.show', $unassigned));
        $this->get($myJob)->assertOk()->assertSeeText('Check the brakes')->assertDontSeeText('Change assignment');
        $this->get(route('my-jobs.show', $unassigned))->assertNotFound();
        $this->patch($assign, ['assigned_mechanic_id' => null])->assertForbidden();
        $this->patch($advance, ['status' => JobCard::IN_PROGRESS])->assertRedirect($myJob);
        $this->actingAs($outsider)->get($myJob)->assertNotFound();

        $this->actingAs($admin)->patch($assign, ['assigned_mechanic_id' => null])->assertRedirect($show);
        $this->actingAs($first)->get(route('my-jobs.index'))->assertOk()->assertSeeText('No jobs assigned to you.');
        $this->actingAs($admin)->patch($assign, ['assigned_mechanic_id' => $second->id])->assertRedirect($show);
        $this->actingAs($first)->patch($advance, ['status' => JobCard::COMPLETED])->assertNotFound();
        $this->actingAs($second)->patch($advance, ['status' => JobCard::COMPLETED])->assertRedirect($myJob);
        $this->get($myJob)->assertOk()->assertSeeText('Completed');
        $this->actingAs($admin)->patch($assign, ['assigned_mechanic_id' => null])->assertSessionHasErrors('assigned_mechanic_id');
    }

    public function test_invoice_due_dates_follow_malaysia_time_and_only_unpaid_balances_are_overdue(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-18 15:59:00', 'UTC'));
        [$admin, $workshop] = $this->createWorkshop();
        $customer = $workshop->run(fn () => Customer::create(['name' => 'Sample Customer', 'email' => 'sample@example.test']));
        $invoiceDetails = [
            'customer_id' => $customer->id,
            'items' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => '100.00']],
        ];
        $this->actingAs($admin);
        foreach (['2026-11-17', '2026-11-31'] as $date) {
            $this->from(route('invoices.create'))->post(route('invoices.store'), $invoiceDetails + ['due_date' => $date])
                ->assertRedirect(route('invoices.create'))->assertSessionHasErrors('due_date');
        }

        $filtered = route('invoices.index', ['status' => 'overdue']);
        $this->post(route('invoices.store'), $invoiceDetails + ['due_date' => '2026-11-18'])
            ->assertRedirect(route('invoices.show', 1));
        $this->post(route('invoices.store'), $invoiceDetails + ['due_date' => '2026-11-19'])
            ->assertRedirect(route('invoices.show', 2));
        $this->post(route('invoices.store'), $invoiceDetails)
            ->assertRedirect(route('invoices.show', 3));
        $this->post(route('invoices.payments.store', 1), ['amount' => '25.50'])->assertRedirect();
        $this->get(route('invoices.show', 1))->assertOk()->assertSeeText('Payment due:')
            ->assertSeeText('18 Nov 2026')->assertDontSeeText('Overdue');
        $this->get($filtered)->assertOk()->assertSeeText('No overdue invoices.');

        $this->travelTo(CarbonImmutable::parse('2026-11-18 16:00:00', 'UTC'));
        $this->get(route('invoices.show', 1))->assertOk()->assertSeeText('Overdue');
        $this->get($filtered)->assertOk()
            ->assertSeeText('INV-000001')->assertSeeText('RM 74.50')
            ->assertDontSeeText('INV-000002')->assertDontSeeText('INV-000003');
        $this->get(route('dashboard'))->assertOk()
            ->assertViewHas('outstandingSen', 27450)
            ->assertViewHas('overdueCount', 1)
            ->assertViewHas('overdueSen', 7450)
            ->assertSee($filtered);

        $this->post(route('invoices.payments.store', 1), ['amount' => '74.50'])->assertRedirect();
        $this->get($filtered)->assertOk()->assertSeeText('No overdue invoices.');
        $this->get(route('dashboard'))->assertOk()->assertViewHas('overdueSen', 0);
    }

    public function test_workshop_dashboard_shows_todays_appointments_jobs_and_outstanding_balances(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-18 17:00:00', 'UTC'));
        [$admin, $workshop] = $this->createWorkshop();
        [$otherAdmin] = $this->createWorkshop();
        $staff = User::factory()->create(['role' => User::ROLE_MECHANIC, 'tenant_id' => $workshop->id]);

        $workshop->run(function () use ($admin) {
            [$customer, $vehicle] = $this->createCustomerAndVehicle();
            $details = ['customer_id' => $customer->id, 'vehicle_id' => $vehicle->id];
            foreach ([
                ['Morning service', '2026-11-19 09:00:00', '2026-11-19 10:00:00', Calendar::STATUS_SCHEDULED],
                ['Overnight service', '2026-11-18 23:30:00', '2026-11-19 00:30:00', Calendar::STATUS_SCHEDULED],
                ['Cancelled service', '2026-11-19 14:00:00', '2026-11-19 15:00:00', Calendar::STATUS_CANCELLED],
                ['Tomorrow service', '2026-11-20 00:00:00', '2026-11-20 01:00:00', Calendar::STATUS_SCHEDULED],
                ['Yesterday service', '2026-11-18 23:00:00', '2026-11-19 00:00:00', Calendar::STATUS_SCHEDULED],
            ] as [$title, $start, $end, $status]) {
                Calendar::create($details + compact('title', 'start', 'end', 'status'));
            }

            foreach ([JobCard::OPEN, JobCard::IN_PROGRESS, JobCard::COMPLETED] as $status) {
                JobCard::create($details + ['description' => 'Workshop job', 'status' => $status]);
            }

            foreach ([[10000, [1000, 1499]], [1235, []], [5000, [5000]], [0, []]] as [$total, $payments]) {
                $invoice = Invoice::create($details + [
                    'workshop_name' => 'Beta Workshop',
                    'customer_name' => $customer->name,
                    'items' => [],
                    'total_sen' => $total,
                ]);
                foreach ($payments as $amount) {
                    $invoice->payments()->create(['amount_sen' => $amount, 'recorded_by_user_id' => $admin->id]);
                }
            }
        });

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('appointmentCount', 2)
            ->assertViewHas('openJobCount', 2)
            ->assertSeeText('Thu, 19 Nov 2026')
            ->assertSeeTextInOrder(['Overnight service', 'Morning service'])
            ->assertDontSeeText('Cancelled service')
            ->assertDontSeeText('Tomorrow service')
            ->assertDontSeeText('Yesterday service')
            ->assertSeeText('Plan: Invoice beta')
            ->assertSeeText('RM 87.36')
            ->assertSeeText('2 invoices with a balance')
            ->assertSee(route('invoices.create'))
            ->assertSee(route('job-cards.create'))
            ->assertSee(route('calendar.create'));

        $this->actingAs($staff)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSeeText('Morning service')
            ->assertDontSeeText('Outstanding invoice balance')
            ->assertDontSeeText('Create invoice');

        $this->actingAs($otherAdmin)->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('openJobCount', 0)
            ->assertSeeText('RM 0.00')
            ->assertSeeText('No appointments scheduled today.')
            ->assertDontSeeText('Morning service')
            ->assertDontSeeText('RM 87.36');
    }

    public function test_workshop_admin_can_create_list_and_print_invoice_with_exact_totals(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        [$customer, $vehicle] = $workshop->run(fn () => $this->createCustomerAndVehicle());

        $this->actingAs($admin)->get(route('invoices.create'))->assertOk()
            ->assertSee($customer->name)->assertSee($vehicle->plate_number)
            ->assertSee(asset('css/sb-admin-2.min.css'))->assertDontSee('/tenancy/assets/css/');

        $this->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'total_sen' => 1,
            'items' => [
                ['description' => 'Washer', 'quantity' => 3, 'unit_price' => '0.10'],
                ['description' => 'Oil change', 'quantity' => 2, 'unit_price' => '12.3'],
            ],
        ])->assertRedirect(route('invoices.show', 1));

        $invoice = Invoice::query()->sole();
        $this->assertSame(2490, $invoice->total_sen);
        $this->assertSame([30, 2460], array_column($invoice->items, 'total_sen'));

        $this->get(route('invoices.index'))->assertOk()->assertSee('INV-000001')->assertSee('RM 24.90');

        $customer->delete();
        $this->get(route('invoices.show', $invoice))->assertOk()
            ->assertSee('Print / Save as PDF')
            ->assertSee('Beta test document')
            ->assertSee('Beta Workshop')
            ->assertSee($customer->name)
            ->assertSee($vehicle->plate_number)
            ->assertSee('RM 24.90');
    }

    public function test_invoice_rejects_invalid_price_and_vehicle_from_a_different_customer(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        [$customer, $vehicle] = $workshop->run(fn () => $this->createCustomerAndVehicle());
        $other = $workshop->run(fn () => Customer::create(['name' => 'Other', 'email' => 'other@example.test']));

        $this->actingAs($admin)->from(route('invoices.create'));
        $this->post(route('invoices.store'), [
            'customer_id' => $other->id,
            'vehicle_id' => $vehicle->id,
            'items' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => '10.00']],
        ])->assertSessionHasErrors('vehicle_id');

        $this->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'items' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => '10.005']],
        ])->assertSessionHasErrors('items.0.unit_price');
        $this->assertSame(0, Invoice::query()->count());
    }

    public function test_invoice_access_is_limited_to_workshop_admins_and_their_own_workshop(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        [$otherAdmin, $otherWorkshop] = $this->createWorkshop();
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'tenant_id' => $workshop->id]);
        [$customer] = $workshop->run(fn () => $this->createCustomerAndVehicle());

        $this->actingAs($admin)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'items' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => '25.00']],
        ])->assertRedirect(route('invoices.show', 1));

        $this->actingAs($client)->get(route('invoices.show', 1))->assertForbidden();
        $this->actingAs($otherAdmin)->get(route('invoices.index'))->assertOk()->assertSee('No invoices yet.');
        $this->get(route('invoices.show', 1))->assertNotFound();
        $this->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'items' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => '25.00']],
        ])->assertSessionHasErrors('customer_id');
    }

    public function test_workshop_admin_can_add_edit_and_invoice_a_customer_with_a_vehicle(): void
    {
        [$admin] = $this->createWorkshop();
        $details = [
            'name' => 'Beta Customer',
            'email' => 'customer@example.test',
            'phone' => '0123456789',
            'address' => 'Test address',
        ];

        $this->actingAs($admin)->get(route('invoices.create'))->assertOk()
            ->assertSee(route('customers.create'));
        $this->get(route('vehicles.create'))->assertOk()->assertSee(route('customers.create'));
        $this->get(route('customers.create'))->assertOk()->assertSee('Add customer');
        $this->post(route('customers.store'), $details)->assertRedirect(route('customers.index'));

        $customer = Customer::query()->sole();
        $this->get(route('customers.index'))->assertOk()->assertSee($details['email']);
        $this->get(route('customers.edit', $customer))->assertOk()->assertSee($details['address']);

        $details['name'] = 'Updated Beta Customer';
        $this->put(route('customers.update', $customer), $details)->assertRedirect(route('customers.index'));
        $vehicleDetails = $this->vehicleDetails($customer);
        $this->get(route('vehicles.create'))->assertOk()->assertSee($details['name']);
        $this->post(route('vehicles.store'), $vehicleDetails)->assertRedirect(route('vehicles.index'));
        $vehicle = Vehicle::query()->sole();
        $this->get(route('vehicles.index'))->assertOk()->assertSee($vehicleDetails['plate_number']);
        $this->get(route('vehicles.edit', $vehicle))->assertOk()->assertSee($vehicleDetails['chassis_number']);

        $vehicleDetails['plate_number'] = 'BETA456';
        $this->put(route('vehicles.update', $vehicle), $vehicleDetails)->assertRedirect(route('vehicles.index'));
        $this->get(route('invoices.create'))->assertOk()->assertSee($details['name'])->assertSee('BETA456');
        $this->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'items' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => '25.00']],
        ])->assertRedirect();
        $invoice = Invoice::query()->sole();
        $this->assertSame($details['name'], $invoice->customer_name);
        $this->assertSame('BETA456', $invoice->vehicle_plate);
    }

    public function test_customer_records_and_email_uniqueness_are_scoped_to_the_workshop(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        [$otherAdmin] = $this->createWorkshop();
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'tenant_id' => $workshop->id]);
        $details = ['name' => 'Private Customer', 'email' => 'customer@example.test'];

        $this->actingAs($admin)->post(route('customers.store'), $details)->assertRedirect(route('customers.index'));
        $customer = Customer::query()->sole();
        $this->from(route('customers.create'))->post(route('customers.store'), $details)->assertSessionHasErrors('email');
        $this->delete(route('customers.update', $customer))->assertMethodNotAllowed();

        $this->actingAs($client)->get(route('customers.index'))->assertForbidden();
        $this->actingAs($otherAdmin)->get(route('customers.index'))->assertOk()->assertSee('No customers yet.');
        $this->put(route('customers.update', $customer), $details)->assertNotFound();
        $this->post(route('customers.store'), $details)->assertRedirect(route('customers.index'));
    }

    public function test_vehicle_records_and_customer_links_are_scoped_to_the_workshop(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        [$otherAdmin] = $this->createWorkshop();
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'tenant_id' => $workshop->id]);
        [$customer, $vehicle] = $workshop->run(fn () => $this->createCustomerAndVehicle());
        $details = $this->vehicleDetails($customer);

        $this->actingAs($admin)->post(route('vehicles.store'), $details)
            ->assertSessionHasErrors(['plate_number', 'chassis_number', 'engine_number']);
        $this->delete(route('vehicles.update', $vehicle))->assertMethodNotAllowed();
        $this->actingAs($client)->get(route('vehicles.index'))->assertForbidden();

        $this->actingAs($otherAdmin)->get(route('vehicles.index'))->assertOk()->assertSee('No vehicles yet.');
        $this->put(route('vehicles.update', $vehicle), $details)->assertNotFound();
        $this->post(route('vehicles.store'), $details)->assertSessionHasErrors('customer_id');
    }

    public function test_workshop_admin_can_book_reschedule_and_cancel_an_appointment(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        [$customer, $vehicle] = $workshop->run(fn () => $this->createCustomerAndVehicle());
        $details = [
            'vehicle_id' => $vehicle->id,
            'customer_id' => $customer->id + 1,
            'service_type' => 'Oil change',
            'start' => '2026-11-18T09:00',
            'end' => '2026-11-18T10:00',
        ];
        $week = route('calendar.index', ['week' => '2026-11-18']);

        $this->actingAs($admin)->get(route('calendar.create'))->assertOk()
            ->assertSee($vehicle->plate_number)->assertSee($customer->name);
        $this->post(route('calendar.store'), $details)->assertRedirect($week);
        $booking = Calendar::query()->sole();
        $this->assertSame($customer->id, $booking->customer_id);
        $this->get($week)->assertOk()->assertSee('Oil change')->assertSee('2026-11-18 09:00');
        $this->get(route('calendar.index', ['week' => '2026-11-23']))->assertOk()->assertDontSee('Oil change');

        $this->get(route('calendar.edit', $booking))->assertOk()->assertSee('2026-11-18T09:00');
        $this->put(route('calendar.update', $booking), ['start' => '2026-11-18T11:00', 'end' => '2026-11-18T12:00'])
            ->assertRedirect($week);
        $this->get($week)->assertSee('2026-11-18 11:00')->assertDontSee('2026-11-18 09:00');
        $this->patch(route('calendar.cancel', $booking))->assertRedirect($week);
        $this->get($week)->assertSee('Cancelled')->assertDontSee('Reschedule');
        $this->put(route('calendar.update', $booking), $details)->assertStatus(409);
        $this->post(route('calendar.store'), array_replace($details, ['start' => '2026-11-18T11:00', 'end' => '2026-11-18T12:00']))
            ->assertRedirect($week);
    }

    public function test_calendar_blocks_conflicting_vehicle_slots_and_cross_workshop_access(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        [$otherAdmin] = $this->createWorkshop();
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'tenant_id' => $workshop->id]);
        [$customer, $vehicle] = $workshop->run(fn () => $this->createCustomerAndVehicle());
        $otherVehicle = $workshop->run(fn () => Vehicle::create(array_replace($this->vehicleDetails($customer), [
            'plate_number' => 'BETA567', 'chassis_number' => 'OTHER-CHASSIS', 'engine_number' => 'OTHER-ENGINE',
        ])));
        $details = [
            'vehicle_id' => $vehicle->id,
            'service_type' => 'Private booking',
            'start' => '2026-11-18T09:00',
            'end' => '2026-11-18T10:00',
        ];
        $week = route('calendar.index', ['week' => '2026-11-18']);

        $this->actingAs($admin)->post(route('calendar.store'), $details)->assertRedirect($week);
        $booking = Calendar::query()->sole();
        $this->post(route('calendar.store'), array_replace($details, ['start' => '2026-11-18T09:30']))
            ->assertSessionHasErrors('start');
        $this->post(route('calendar.store'), array_replace($details, ['end' => $details['start']]))
            ->assertSessionHasErrors('end');
        $this->post(route('calendar.store'), array_replace($details, ['vehicle_id' => $otherVehicle->id]))
            ->assertRedirect($week);
        $this->post(route('calendar.store'), array_replace($details, ['start' => '2026-11-18T10:00', 'end' => '2026-11-18T11:00']))
            ->assertRedirect($week);
        $this->put(route('calendar.update', $booking), ['start' => '2026-11-18T10:30', 'end' => '2026-11-18T11:30'])
            ->assertSessionHasErrors('start');

        $this->actingAs($client)->get($week)->assertForbidden();
        $this->actingAs($otherAdmin)->get($week)->assertOk()->assertDontSee('Private booking');
        $this->patch(route('calendar.cancel', $booking))->assertNotFound();
        $this->post(route('calendar.store'), $details)->assertSessionHasErrors('vehicle_id');
    }

    public function test_workshop_admin_can_open_a_job_from_the_calendar_and_track_progress(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        [$customer, $vehicle] = $workshop->run(fn () => $this->createCustomerAndVehicle());
        $week = route('calendar.index', ['week' => '2026-11-18']);
        $this->actingAs($admin)->post(route('calendar.store'), [
            'vehicle_id' => $vehicle->id,
            'service_type' => 'Brake inspection',
            'start' => '2026-11-18T09:00',
            'end' => '2026-11-18T10:00',
        ])->assertRedirect($week);
        $appointment = Calendar::query()->sole();
        $create = route('job-cards.create', ['appointment' => $appointment->id]);
        $this->get($week)->assertOk()->assertSee($create);
        $this->get($create)->assertOk()->assertSee('Brake inspection');

        $details = ['vehicle_id' => $vehicle->id, 'calendar_id' => $appointment->id, 'description' => 'Check brake pads'];
        $this->post(route('job-cards.store'), $details)->assertRedirect();
        $job = JobCard::query()->sole();
        $show = route('job-cards.show', $job);
        $this->assertSame($customer->id, $job->customer_id);
        $this->assertSame($appointment->id, $job->calendar_id);
        $this->get($show)->assertOk()->assertSee('Check brake pads')->assertSee('Start work');
        $this->get($create)->assertRedirect($show);
        $this->post(route('job-cards.store'), $details)->assertSessionHasErrors('calendar_id');

        $update = route('job-cards.advance', $job);
        $this->patch($update, ['status' => JobCard::COMPLETED])->assertSessionHasErrors('status');
        $this->patch($update, ['status' => JobCard::IN_PROGRESS])->assertRedirect($show);
        $this->patch($update, ['status' => JobCard::COMPLETED])->assertRedirect($show);
        $this->get($show)->assertOk()->assertSee('Completed at')->assertDontSee('Start work');
        $this->assertNotNull($job->fresh()->completed_at);
    }

    public function test_job_cards_can_be_created_without_appointments_and_are_workshop_scoped(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        [$otherAdmin] = $this->createWorkshop();
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'tenant_id' => $workshop->id]);
        [, $vehicle] = $workshop->run(fn () => $this->createCustomerAndVehicle());
        $details = ['vehicle_id' => $vehicle->id, 'description' => 'Walk-in battery test'];

        $this->actingAs($admin)->get(route('job-cards.create'))->assertOk()->assertSee($vehicle->plate_number);
        $this->post(route('job-cards.store'), $details)->assertRedirect();
        $job = JobCard::query()->sole();
        $this->assertNull($job->calendar_id);
        $this->get(route('job-cards.index'))->assertOk()->assertSee($vehicle->plate_number);
        $this->actingAs($client)->get(route('job-cards.show', $job))->assertForbidden();
        $this->actingAs($otherAdmin)->get(route('job-cards.index'))->assertOk()->assertSee('No job cards yet.');
        $this->patch(route('job-cards.advance', $job), ['status' => JobCard::IN_PROGRESS])->assertNotFound();
        $this->post(route('job-cards.store'), $details)->assertSessionHasErrors('vehicle_id');
    }

    public function test_inventory_movements_are_traced_and_cannot_overdraw_stock(): void
    {
        [$admin] = $this->createWorkshop();
        $details = ['sku' => 'FILTER-01', 'name' => 'Oil filter', 'low_stock_threshold' => 3];

        $this->actingAs($admin)->get(route('inventory.create'))->assertOk();
        $this->post(route('inventory.store'), $details + ['quantity' => 99])->assertRedirect();
        $item = InventoryItem::query()->sole();
        $this->assertSame(0, $item->quantity);
        $show = route('inventory.show', $item);
        $move = route('inventory.move', $item);
        $this->post($move, ['direction' => 'in', 'quantity' => 8, 'note' => 'Opening stock'])->assertRedirect($show);
        $this->get(route('inventory.index'))->assertOk()->assertSee('In stock');
        $this->post($move, ['direction' => 'out', 'quantity' => 5, 'note' => 'Used for a service'])->assertRedirect($show);
        $this->get($show)->assertOk()->assertSee('Low stock')->assertSee('Used for a service');
        $this->post($move, ['direction' => 'out', 'quantity' => 4, 'note' => 'Overdraw'])->assertSessionHasErrors('quantity');
        $this->assertSame(3, $item->fresh()->quantity);
        $this->assertSame([8, 3], $item->movements()->orderBy('id')->pluck('balance_after')->all());
        $this->assertSame([$admin->id], $item->movements()->distinct()->pluck('recorded_by_user_id')->all());
        $this->get(route('inventory.edit', $item))->assertOk()->assertSee('FILTER-01');
        $this->put(route('inventory.update', $item), array_replace($details, ['low_stock_threshold' => 2]))->assertRedirect($show);
        $this->get(route('inventory.index'))->assertOk()->assertSee('In stock');
    }

    public function test_inventory_is_limited_to_eligible_workshop_admins_and_their_own_items(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        [$otherAdmin] = $this->createWorkshop();
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'tenant_id' => $workshop->id]);
        $basic = SubscriptionPlan::create(['name' => 'Basic', 'slug' => 'basic']);
        $pro = SubscriptionPlan::create(['name' => 'Pro', 'slug' => 'pro']);
        $admin->accountSubscription()->update(['status' => AccountSubscription::STATUS_ACTIVE, 'subscription_plan_id' => $basic->id]);
        $this->actingAs($admin)->get(route('inventory.index'))->assertForbidden();

        tenancy()->end();
        $admin->accountSubscription()->update(['subscription_plan_id' => $pro->id]);
        $this->post(route('inventory.store'), ['sku' => 'PART-01', 'name' => 'Pilot part', 'low_stock_threshold' => 1])->assertRedirect();
        $item = InventoryItem::query()->sole();
        $this->actingAs($client)->get(route('inventory.show', $item))->assertForbidden();
        $this->actingAs($otherAdmin)->get(route('inventory.index'))->assertOk()->assertSee('No inventory items yet.');
        $this->post(route('inventory.move', $item), ['direction' => 'in', 'quantity' => 1, 'note' => 'Invalid workshop'])
            ->assertNotFound();
    }

    public function test_workshop_admin_can_record_partial_and_final_invoice_payments(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        [$customer] = $workshop->run(fn () => $this->createCustomerAndVehicle());

        $this->actingAs($admin)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'items' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => '25.10']],
        ])->assertRedirect();
        $invoice = Invoice::query()->sole();
        $show = route('invoices.show', $invoice);
        $record = route('invoices.payments.store', $invoice);

        $this->get(route('invoices.index'))->assertOk()->assertSee('Unpaid');
        $this->from($show)->post($record, ['amount' => '10.05', 'reference' => 'Transfer ref 123'])
            ->assertRedirect($show);
        $this->get($show)->assertOk()->assertSee('Partially paid')
            ->assertSee('Outstanding: RM 15.05')->assertSee('Transfer ref 123');

        foreach (['0', '-1', '1.001', '15.06'] as $invalidAmount) {
            $this->post($record, ['amount' => $invalidAmount])->assertSessionHasErrors('amount');
        }
        $this->assertSame(1, $invoice->payments()->count());
        $this->assertSame($admin->id, $invoice->payments()->sole()->recorded_by_user_id);

        $this->post($record, ['amount' => '15.05'])->assertRedirect($show);
        $this->get($show)->assertOk()->assertSee('Outstanding: RM 0.00')->assertDontSee('Record payment');
        $this->get(route('invoices.index'))->assertOk()->assertSee('Paid')->assertDontSee('Partially paid');
        $this->post($record, ['amount' => '0.01'])->assertSessionHasErrors('amount');
        $this->assertSame([1005, 1505], $invoice->payments()->orderBy('id')->pluck('amount_sen')->all());
    }

    public function test_invoice_payments_require_an_admin_in_the_same_workshop(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        [$otherAdmin] = $this->createWorkshop();
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'tenant_id' => $workshop->id]);
        [$customer] = $workshop->run(fn () => $this->createCustomerAndVehicle());

        $this->actingAs($admin)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'items' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => '25.00']],
        ])->assertRedirect();
        $invoice = Invoice::query()->sole();
        $record = route('invoices.payments.store', $invoice);

        $this->actingAs($client)->post($record, ['amount' => '10'])->assertForbidden();
        $this->actingAs($otherAdmin)->post($record, ['amount' => '10'])->assertNotFound();
        $this->assertSame(0, $workshop->run(fn () => Invoice::query()->sole()->payments()->count()));
    }

    public function test_workshop_admin_can_print_an_acknowledgment_for_one_payment(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        [$customer] = $workshop->run(fn () => $this->createCustomerAndVehicle());

        $this->actingAs($admin)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'items' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => '25.10']],
        ])->assertRedirect();
        $invoice = Invoice::query()->sole();
        $record = route('invoices.payments.store', $invoice);
        $this->post($record, ['amount' => '10.05', 'reference' => 'Internal transaction note'])->assertRedirect();
        $payment = $invoice->payments()->sole();
        $receipt = route('invoices.payments.receipt', [$invoice, $payment]);
        $this->post($record, ['amount' => '15.05'])->assertRedirect();

        $this->get(route('invoices.show', $invoice))->assertOk()->assertSee($receipt);
        $response = $this->get($receipt)->assertOk()
            ->assertSee('PAY-000001')->assertSee($invoice->number())->assertSee($customer->name)
            ->assertSee('RM 10.05')->assertSee('Print / Save as PDF')->assertSee('not a MyInvois-validated document')
            ->assertDontSee('RM 15.05')->assertDontSee('Internal transaction note');
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
    }

    public function test_receipt_requires_the_correct_invoice_and_workshop_admin(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        [$otherAdmin] = $this->createWorkshop();
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'tenant_id' => $workshop->id]);
        [$customer] = $workshop->run(fn () => $this->createCustomerAndVehicle());
        $details = [
            'customer_id' => $customer->id,
            'items' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => '25.00']],
        ];

        $this->actingAs($admin)->post(route('invoices.store'), $details)->assertRedirect();
        $invoice = Invoice::query()->sole();
        $this->post(route('invoices.payments.store', $invoice), ['amount' => '10'])->assertRedirect();
        $payment = $invoice->payments()->sole();
        $this->post(route('invoices.store'), $details)->assertRedirect();
        $otherInvoice = Invoice::query()->whereKeyNot($invoice->id)->sole();

        $receipt = route('invoices.payments.receipt', [$invoice, $payment]);
        $this->get(route('invoices.payments.receipt', [$otherInvoice, $payment]))->assertNotFound();
        $this->actingAs($client)->get($receipt)->assertForbidden();
        $this->actingAs($otherAdmin)->get($receipt)->assertNotFound();
    }

    public function test_vehicle_history_shows_only_records_linked_to_that_vehicle(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        [$customer, $vehicle] = $workshop->run(fn () => $this->createCustomerAndVehicle());
        $otherVehicle = $workshop->run(fn () => Vehicle::create(array_replace($this->vehicleDetails($customer), [
            'plate_number' => 'BETA567', 'chassis_number' => 'OTHER-CHASSIS', 'engine_number' => 'OTHER-ENGINE',
        ])));

        $this->actingAs($admin)->post(route('calendar.store'), [
            'vehicle_id' => $vehicle->id,
            'service_type' => 'Brake inspection',
            'start' => '2026-11-18T09:00',
            'end' => '2026-11-18T10:00',
        ])->assertRedirect();
        $this->post(route('job-cards.store'), [
            'vehicle_id' => $vehicle->id,
            'description' => 'Replace brake pads',
        ])->assertRedirect();
        $job = JobCard::query()->sole();

        $invoiceDetails = [
            'customer_id' => $customer->id,
            'items' => [['description' => 'Brake pads', 'quantity' => 1, 'unit_price' => '25.00']],
        ];
        $this->post(route('invoices.store'), $invoiceDetails + ['vehicle_id' => $vehicle->id])->assertRedirect();
        $invoice = Invoice::query()->sole();
        $this->post(route('invoices.store'), $invoiceDetails)->assertRedirect();
        $unlinkedInvoice = Invoice::query()->whereNull('vehicle_id')->sole();

        $history = route('vehicles.history', $vehicle);
        $this->get(route('vehicles.index'))->assertOk()->assertSee($history);
        $this->get($history)->assertOk()
            ->assertSee($vehicle->plate_number)
            ->assertSee('Brake inspection')->assertSee(route('calendar.index', ['week' => '2026-11-18']))
            ->assertSee('Replace brake pads')->assertSee(route('job-cards.show', $job))
            ->assertSee($invoice->number())->assertSee(route('invoices.show', $invoice))
            ->assertDontSee($unlinkedInvoice->number());
        $this->get(route('vehicles.history', $otherVehicle))->assertOk()
            ->assertSee('No appointments for this vehicle.')
            ->assertSee('No job cards for this vehicle.')
            ->assertSee('No invoices for this vehicle.');
    }

    public function test_vehicle_history_is_limited_to_workshop_admins_in_the_same_workshop(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        [$otherAdmin] = $this->createWorkshop();
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'tenant_id' => $workshop->id]);
        [, $vehicle] = $workshop->run(fn () => $this->createCustomerAndVehicle());
        $history = route('vehicles.history', $vehicle);

        $this->actingAs($client)->get($history)->assertForbidden();
        $this->actingAs($otherAdmin)->get($history)->assertNotFound();
        $this->actingAs($admin)->get($history)->assertOk();
    }

    public function test_workshop_admin_can_save_every_listed_gateway_and_clear_preferences(): void
    {
        [$admin] = $this->createWorkshop();
        $edit = route('payment-preferences.edit');
        $update = route('payment-preferences.update');
        $this->actingAs($admin);
        $page = $this->get($edit)->assertOk();

        foreach (array_merge(...array_values(config('payment_methods.gateways'))) as $provider => $label) {
            $page->assertSee($label);
            $this->put($update, ['gateways' => [['provider' => $provider]]])->assertRedirect($edit);
            $this->assertSame([['provider' => $provider]], PaymentPreference::query()->sole()->gateways);
        }

        $this->put($update, [
            'gateways' => [['provider' => 'stripe'], ['provider' => 'custom', 'name' => 'Local provider']],
            'offline_methods' => ['cash', 'bank-transfer', 'duitnow-qr'],
        ])->assertRedirect($edit);
        $preference = PaymentPreference::query()->sole();
        $this->assertSame(['cash', 'bank-transfer', 'duitnow-qr'], $preference->offline_methods);
        $this->assertCount(2, $preference->gateways);
        $this->get($edit)->assertOk()->assertSee('value="stripe" selected', false)->assertSee('Local provider');
        $this->put($update, ['gateways' => [['provider' => ''], ['provider' => '']]])->assertRedirect($edit);
        $this->assertSame([], $preference->fresh()->gateways);
        $this->assertSame([], $preference->fresh()->offline_methods);
    }

    public function test_payment_preferences_enforce_valid_choices_and_the_two_gateway_limit(): void
    {
        [$admin] = $this->createWorkshop();
        $this->actingAs($admin);
        $update = route('payment-preferences.update');

        $cases = [
            ['gateways', [['provider' => 'stripe'], ['provider' => 'billplz'], ['provider' => 'chip']]],
            ['gateways.1.provider', [['provider' => 'stripe'], ['provider' => 'stripe']]],
            ['gateways.1.provider', [['provider' => 'custom', 'name' => 'Local'], ['provider' => 'custom', 'name' => 'local']]],
            ['gateways.0.provider', [['provider' => 'unknown']]],
            ['gateways.0.name', [['provider' => 'custom']]],
            ['gateways.0', [['provider' => 'stripe', 'api_key' => 'test-only']]],
        ];

        foreach ($cases as [$field, $gateways]) {
            $this->putJson($update, ['gateways' => $gateways])->assertUnprocessable()->assertJsonValidationErrors($field);
        }

        $this->putJson($update, ['offline_methods' => ['stripe']])->assertUnprocessable()
            ->assertJsonValidationErrors('offline_methods.0');
        $this->assertSame(0, PaymentPreference::query()->count());
    }

    public function test_payment_preferences_belong_to_the_signed_in_workshop_admin(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        [$otherWorkshopAdmin] = $this->createWorkshop();
        $colleague = User::factory()->create(['role' => User::ROLE_ADMIN, 'tenant_id' => $workshop->id]);
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'tenant_id' => $workshop->id]);
        $edit = route('payment-preferences.edit');
        $update = route('payment-preferences.update');

        $this->actingAs($admin)->put($update, [
            'gateways' => [['provider' => 'custom', 'name' => 'Private provider']],
        ])->assertRedirect($edit);

        $this->actingAs($colleague)->get($edit)->assertOk()->assertDontSee('Private provider');
        $this->put($update, ['user_id' => $admin->id, 'offline_methods' => ['cash']])->assertRedirect($edit);
        $this->assertSame(['cash'], PaymentPreference::where('user_id', $colleague->id)->sole()->offline_methods);
        $this->assertSame('Private provider', PaymentPreference::where('user_id', $admin->id)->sole()->gateways[0]['name']);

        $this->actingAs($otherWorkshopAdmin)->get($edit)->assertOk()->assertDontSee('Private provider');
        $this->actingAs($client)->get($edit)->assertForbidden();
        $this->put($update, ['offline_methods' => ['cash']])->assertForbidden();
    }

    public function test_stripe_sandbox_verifies_a_demo_payment_without_recording_it(): void
    {
        [$admin] = $this->createWorkshop();
        config(['services.stripe.sandbox_enabled' => true, 'services.stripe.test_secret' => 'sk_test_FakeKey']);
        $api = 'https://api.stripe.com/v1/checkout/sessions';
        $id = 'cs_test_Demo123';
        $checkout = 'https://checkout.stripe.com/c/pay/'.$id.'#stripe';
        $session = $this->stripeDemoSession($id) + ['url' => $checkout];
        Http::preventStrayRequests()->fake([
            $api => Http::response($session),
            "$api/$id" => Http::sequence()
                ->push($session + ['status' => 'open', 'payment_status' => 'unpaid'])
                ->push($session + ['status' => 'complete', 'payment_status' => 'paid']),
        ]);

        $this->actingAs($admin)->put(route('payment-preferences.update'), ['gateways' => [['provider' => 'stripe']]])
            ->assertRedirect();
        $this->get(route('payment-preferences.edit'))->assertOk()->assertSee('Open Stripe test checkout');
        $this->post(route('stripe-sandbox.start'), ['amount' => 999, 'customer_email' => 'not-sent@example.test'])
            ->assertStatus(303)->assertRedirect($checkout);
        $this->get(route('stripe-sandbox.result', ['session_id' => 'cs_test_Unrelated']))->assertNotFound();
        $result = route('stripe-sandbox.result', ['session_id' => $id]);
        $this->get($result)->assertOk()->assertSee('Stripe has not confirmed the test payment.');
        $this->get($result)->assertOk()->assertSee('Stripe confirmed the test payment.');
        $this->assertSame(0, Invoice::query()->count());
        $this->assertSame(0, \App\Models\InvoicePayment::query()->count());

        Http::assertSent(function (StripeRequest $request) use ($api) {
            if ($request->method() !== 'POST' || $request->url() !== $api) {
                return false;
            }

            parse_str($request->body(), $form);
            $this->assertSame('1000', data_get($form, 'line_items.0.price_data.unit_amount'));
            $this->assertSame('myr', data_get($form, 'line_items.0.price_data.currency'));
            $this->assertStringEndsWith('session_id={CHECKOUT_SESSION_ID}', $form['success_url']);
            $this->assertArrayNotHasKey('customer_email', $form);

            return true;
        });
        Http::assertSentCount(3);
    }

    public function test_stripe_sandbox_stays_disabled_for_live_keys_and_requires_a_saved_preference(): void
    {
        [$admin] = $this->createWorkshop();
        Http::preventStrayRequests()->fake();
        $this->actingAs($admin);
        $start = route('stripe-sandbox.start');

        config(['services.stripe.sandbox_enabled' => false, 'services.stripe.test_secret' => 'sk_test_FakeKey']);
        $this->post($start)->assertNotFound();
        config(['services.stripe.sandbox_enabled' => true, 'services.stripe.test_secret' => 'sk_live_FakeKey']);
        $this->post($start)->assertNotFound();
        config(['services.stripe.test_secret' => 'sk_test_FakeKey']);
        $this->post($start)->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_stripe_sandbox_rejects_checkout_links_outside_stripe(): void
    {
        [$admin] = $this->createWorkshop();
        config(['services.stripe.sandbox_enabled' => true, 'services.stripe.test_secret' => 'sk_test_FakeKey']);
        Http::preventStrayRequests()->fake([
            'https://api.stripe.com/v1/checkout/sessions' => Http::response(
                $this->stripeDemoSession('cs_test_Demo123') + ['url' => 'https://checkout.stripe.com.example.test/c/pay/demo']
            ),
        ]);

        $this->actingAs($admin)->put(route('payment-preferences.update'), ['gateways' => [['provider' => 'stripe']]])
            ->assertRedirect();
        $this->post(route('stripe-sandbox.start'))->assertStatus(502);
        $this->get(route('stripe-sandbox.result', ['session_id' => 'cs_test_Demo123']))->assertNotFound();
    }

    public function test_toyyibpay_sandbox_verifies_the_matching_fpx_payment_without_recording_it(): void
    {
        [$admin] = $this->createWorkshop();
        config([
            'services.toyyibpay.sandbox_enabled' => true,
            'services.toyyibpay.test_secret' => 'fake-test-secret',
            'services.toyyibpay.test_category' => 'test-category',
        ]);
        $api = 'https://dev.toyyibpay.com/index.php/api/';
        $reference = null;
        $attempt = 0;
        Http::preventStrayRequests()->fake(function ($request) use ($api, &$reference, &$attempt) {
            $this->assertSame('POST', $request->method());
            parse_str($request->body(), $form);
            if ($request->url() === $api.'createBill') {
                $this->assertSame('1000', $form['billAmount']);
                $this->assertSame('0', $form['billPaymentChannel']);
                $this->assertSame('0', $form['billPayorInfo']);
                $this->assertArrayNotHasKey('billEmail', $form);
                $this->assertStringEndsWith('/payment-preferences/toyyibpay-sandbox/result', $form['billReturnUrl']);
                $reference = $form['billExternalReferenceNo'];

                return Http::response([['BillCode' => 'demobill1']]);
            }

            $this->assertSame($api.'getBillTransactions', $request->url());
            $this->assertSame('demobill1', $form['billCode']);
            $attempt++;

            return Http::response([[
                'billExternalReferenceNo' => $attempt === 1 ? 'different-reference' : $reference,
                'billpaymentStatus' => '1',
                'billpaymentAmount' => $attempt === 2 ? '1.00' : '10.00',
            ]]);
        });

        $this->actingAs($admin)->put(route('payment-preferences.update'), ['gateways' => [['provider' => 'toyyibpay']]])
            ->assertRedirect();
        $this->get(route('payment-preferences.edit'))->assertOk()->assertSee('Open toyyibPay test checkout');
        $this->post(route('toyyibpay-sandbox.start'), ['billAmount' => 1, 'billEmail' => 'not-sent@example.test'])
            ->assertStatus(303)->assertRedirect('https://dev.toyyibpay.com/demobill1');
        $this->get(route('toyyibpay-sandbox.result', ['billcode' => 'another']))->assertNotFound();
        $result = route('toyyibpay-sandbox.result', ['billcode' => 'demobill1', 'status_id' => '1']);
        $this->get($result)->assertOk()->assertSee('toyyibPay has not confirmed the test payment.');
        $this->get($result)->assertOk()->assertSee('toyyibPay has not confirmed the test payment.');
        $this->get($result)->assertOk()->assertSee('toyyibPay confirmed the test payment.');
        $this->assertSame(0, Invoice::query()->count());
        $this->assertSame(0, \App\Models\InvoicePayment::query()->count());
        Http::assertSentCount(4);
    }

    public function test_toyyibpay_sandbox_requires_configuration_and_rejects_api_errors_with_http_200(): void
    {
        [$admin] = $this->createWorkshop();
        Http::preventStrayRequests()->fake([
            'https://dev.toyyibpay.com/index.php/api/createBill' => Http::response('[CATEGORY-NOT-MATCH]', 200),
        ]);
        $this->actingAs($admin);
        $start = route('toyyibpay-sandbox.start');
        config(['services.toyyibpay.sandbox_enabled' => false]);
        $this->post($start)->assertNotFound();
        config([
            'services.toyyibpay.sandbox_enabled' => true,
            'services.toyyibpay.test_secret' => 'fake-test-secret',
            'services.toyyibpay.test_category' => 'test-category',
        ]);
        $this->post($start)->assertForbidden();
        $this->put(route('payment-preferences.update'), ['gateways' => [['provider' => 'toyyibpay']]])->assertRedirect();
        $this->post($start)->assertStatus(502);
        Http::assertSentCount(1);
    }

    public function test_billplz_sandbox_verifies_a_demo_payment_without_recording_it(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        $this->configureBillplzSandbox();
        $host = 'https://www.billplz-sandbox.com';
        $api = $host.'/api/v3/bills';
        $id = 'bill_demo';
        $url = $host.'/bills/'.$id;
        $bill = ['id' => $id, 'collection_id' => 'demo-collection', 'amount' => 1000, 'url' => $url];
        Http::preventStrayRequests()->fake([
            $api => Http::response($bill, 201),
            "$api/$id" => Http::sequence()
                ->push(array_replace($bill, ['collection_id' => 'another-collection']))
                ->push(array_replace($bill, ['amount' => 100]))
                ->push($bill + ['paid' => true, 'state' => 'paid', 'paid_amount' => 100])
                ->push($bill + ['paid' => true, 'state' => 'paid', 'paid_amount' => 1000]),
        ]);
        $this->actingAs($admin)->put(route('payment-preferences.update'), ['gateways' => [['provider' => 'billplz']]])
            ->assertRedirect();
        $this->get(route('payment-preferences.edit'))->assertOk()->assertSee('Open Billplz test checkout');
        $result = route('billplz-sandbox.result');
        $fields = [
            'paid' => 'true',
            'id' => $id,
            'paid_at' => '2026-04-23 15:15:09 +0800',
            'x_signature' => hash_hmac('sha256', 'billplzidbill_demo|billplzpaid_at2026-04-23 15:15:09 +0800|billplzpaidtrue', 'fake-signature-key'),
        ];
        $signedReturn = $result.'?'.http_build_query(['billplz' => $fields]);
        $this->get($signedReturn)->assertNotFound();
        $this->post(route('billplz-sandbox.start'), ['amount' => 1, 'email' => 'not-sent@example.test'])
            ->assertStatus(303)->assertRedirect($url);
        $this->get($result.'?'.http_build_query(['billplz' => array_replace($fields, ['paid' => 'false'])]))
            ->assertForbidden();
        $this->get($signedReturn)->assertStatus(502);
        $this->get($signedReturn)->assertStatus(502);
        $this->get($signedReturn)->assertOk()->assertSee('Billplz has not confirmed the test payment.');
        $this->get($signedReturn)->assertOk()->assertSee('Billplz confirmed the test payment.')
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $workshop->run(function () {
            $this->assertSame(0, Invoice::query()->count());
            $this->assertSame(0, \App\Models\InvoicePayment::query()->count());
        });

        Http::assertSent(function ($request) use ($api) {
            if ($request->method() !== 'POST' || $request->url() !== $api) {
                return false;
            }

            parse_str($request->body(), $form);
            $this->assertTrue($request->hasHeader('Authorization', 'Basic '.base64_encode('fake-test-secret:')));
            $this->assertSame('1000', $form['amount']);
            $this->assertSame('demo-collection', $form['collection_id']);
            $this->assertSame('sandbox@example.com', $form['email']);
            $this->assertSame('https://staging.example.test/api/sandbox/billplz/callback', $form['callback_url']);
            $this->assertSame('https://staging.example.test/payment-preferences/billplz-sandbox/result', $form['redirect_url']);

            return true;
        });
        Http::assertSentCount(5);
    }

    public function test_billplz_sandbox_requires_configuration_and_a_saved_preference_and_rejects_external_links(): void
    {
        [$admin] = $this->createWorkshop();
        Http::preventStrayRequests()->fake([
            'https://www.billplz-sandbox.com/api/v3/bills' => Http::response([
                'id' => 'bill_demo',
                'collection_id' => 'demo-collection',
                'amount' => 1000,
                'url' => 'https://www.billplz-sandbox.com.example.test/bills/bill_demo',
            ]),
        ]);
        $this->actingAs($admin);
        $start = route('billplz-sandbox.start');
        config(['services.billplz.sandbox_enabled' => false]);
        $this->post($start)->assertNotFound();
        $this->configureBillplzSandbox();
        $this->post($start)->assertForbidden();
        $this->put(route('payment-preferences.update'), ['gateways' => [['provider' => 'billplz']]])->assertRedirect();
        $this->post($start)->assertStatus(502);
        Http::assertSentCount(1);
    }

    public function test_billplz_accepts_only_valid_signed_sandbox_callbacks_without_authentication(): void
    {
        $this->configureBillplzSandbox();
        Http::preventStrayRequests()->fake();
        $callback = route('billplz-sandbox.callback');
        $fields = [
            'paid' => 'true',
            'paid_at' => '2026-04-23 15:15:09 +0800',
            'id' => 'bill_demo',
            'collection_id' => 'demo-collection',
            'amount' => '1000',
            'mobile' => '',
            'x_signature' => hash_hmac('sha256', 'amount1000|collection_iddemo-collection|idbill_demo|mobile|paid_at2026-04-23 15:15:09 +0800|paidtrue', 'fake-signature-key'),
        ];
        $post = fn (array $body) => $this->call('POST', $callback, [], [], [],
            ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'], http_build_query($body));

        $post($fields)->assertOk()->assertContent('OK');
        $post(array_replace($fields, ['paid' => 'false']))->assertForbidden();
        $post(array_replace($fields, ['paid_at' => ['bad']]))->assertForbidden();
        config(['services.billplz.sandbox_enabled' => false]);
        $post($fields)->assertNotFound();
        Http::assertNothingSent();
    }

    private function configureBillplzSandbox(): void
    {
        config([
            'app.url' => 'https://staging.example.test',
            'services.billplz.sandbox_enabled' => true,
            'services.billplz.test_secret' => 'fake-test-secret',
            'services.billplz.test_collection' => 'demo-collection',
            'services.billplz.test_signature_key' => 'fake-signature-key',
        ]);
    }

    private function stripeDemoSession(string $id): array
    {
        return [
            'id' => $id,
            'object' => 'checkout.session',
            'livemode' => false,
            'mode' => 'payment',
            'currency' => 'myr',
            'amount_total' => 1000,
        ];
    }

    protected function tearDown(): void
    {
        if ($this->workshops) {
            tenancy()->end();
            foreach ($this->workshops as $workshop) {
                $workshop->database()->manager()->deleteDatabase($workshop);
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
