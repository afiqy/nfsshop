<?php

namespace Tests\Feature;

use App\Mail\InvoiceEmail;
use App\Mail\InvoicePaymentReminderEmail;
use App\Models\AccountSubscription;
use App\Models\Calendar;
use App\Models\Customer;
use App\Models\CustomerVehicleRequest;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Invoice;
use App\Models\InvoiceCreditNote;
use App\Models\InvoiceEmailDelivery;
use App\Models\InvoicePaymentReminder;
use App\Models\InvoicePaymentReminderAttempt;
use App\Models\InvoicePaymentSubmission;
use App\Models\JobCard;
use App\Models\JobCardPart;
use App\Models\JobCardUpdate;
use App\Models\PaymentPreference;
use App\Models\PurchaseOrder;
use App\Models\SubscriptionPlan;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkshopPaymentQr;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as StripeRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    private array $workshops = [];

    public function test_myinvois_preparation_saves_encrypted_tax_details_without_changing_the_invoice(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        $customer = $workshop->run(fn () => Customer::create(['name' => 'Sample Customer', 'email' => 'sample@example.test']));
        $sellerUrl = route('myinvois.seller.edit');
        $buyerUrl = route('myinvois.buyer.edit', 1);
        $buyer = [
            'legal_name' => 'Sample Buyer', 'tin' => 'C876543210', 'id_type' => 'BRN', 'id_number' => 'TESTBRN987',
            'address_line' => '1 Example Road', 'postcode' => '01000', 'city' => 'Kangar',
            'state' => '09', 'phone' => '+60123456789',
        ];
        $seller = array_replace($buyer, ['legal_name' => 'Sample Supplier', 'tin' => 'C123456780'])
            + ['msic' => '45201', 'business_activity' => 'Motor vehicle repairs'];
        $this->actingAs($admin)->get($sellerUrl)->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');
        $this->put(route('myinvois.seller.update'), array_replace($seller, ['msic' => 'invalid']))
            ->assertRedirect($sellerUrl)->assertSessionHasErrors('msic')->assertSessionMissing('_old_input');
        $this->put(route('myinvois.seller.update'), $seller)->assertRedirect($sellerUrl);
        $this->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'items' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => '10.00']],
        ])->assertRedirect(route('invoices.show', 1));
        $this->get($buyerUrl)->assertOk()->assertSeeText('Buyer details: Missing');
        $this->put(route('myinvois.buyer.update', 1), $buyer)->assertRedirect($buyerUrl);
        $this->get($buyerUrl)->assertOk()->assertSeeText('Buyer details: Saved')->assertSee($buyer['tin']);

        $workshop->run(function () use ($seller, $buyer) {
            $profile = \App\Models\WorkshopTaxProfile::sole();
            $invoice = Invoice::sole();
            $this->assertSame($seller['tin'], $profile->details['tin']);
            $this->assertStringNotContainsString($seller['tin'], $profile->getRawOriginal('details'));
            $this->assertSame($buyer['id_number'], $invoice->myinvois_buyer['id_number']);
            $this->assertStringNotContainsString($buyer['id_number'], $invoice->getRawOriginal('myinvois_buyer'));
            $this->assertArrayNotHasKey('myinvois_buyer', $invoice->toArray());
            $this->assertSame(1000, $invoice->total_sen);
            $this->assertSame('Sample Customer', $invoice->customer_name);
        });
        $this->get(route('invoices.show', 1))->assertOk()->assertDontSee($buyer['tin'])->assertDontSee($buyer['id_number']);
        $this->delete(route('myinvois.buyer.clear', 1))->assertRedirect($buyerUrl);
        $this->get($buyerUrl)->assertOk()->assertSeeText('Buyer details: Missing')->assertDontSee($buyer['id_number']);
    }

    public function test_myinvois_preparation_is_restricted_to_active_admins_in_their_workshop(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        [$otherAdmin] = $this->createWorkshop();
        $mechanic = User::factory()->create(['role' => User::ROLE_MECHANIC, 'tenant_id' => $workshop->id]);
        $suspended = User::factory()->create([
            'role' => User::ROLE_ADMIN, 'tenant_id' => $workshop->id, 'status' => User::STATUS_SUSPENDED,
        ]);
        $customer = $workshop->run(fn () => Customer::create(['name' => 'Sample Customer', 'email' => 'sample@example.test']));
        $this->actingAs($admin)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'items' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => '10.00']],
        ])->assertRedirect(route('invoices.show', 1));

        $this->actingAs($mechanic)->get(route('myinvois.seller.edit'))->assertForbidden();
        $this->put(route('myinvois.buyer.update', 1))->assertForbidden();
        $this->actingAs($suspended)->get(route('myinvois.buyer.edit', 1))->assertForbidden();
        $this->actingAs($otherAdmin)->get(route('myinvois.buyer.edit', 1))->assertNotFound();
        $this->delete(route('myinvois.buyer.clear', 1))->assertNotFound();
        AccountSubscription::query()->where('user_id', $admin->id)->update(['current_period_ends_at' => now()->subDay()]);
        $this->actingAs($admin)->get(route('myinvois.seller.edit'))->assertRedirect(route('workshop.subscription'));
    }

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

    public function test_mechanics_and_admins_can_append_authorized_job_notes_and_view_progress_history(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        [$otherAdmin, $otherWorkshop] = $this->createWorkshop();
        $mechanic = User::factory()->create(['role' => User::ROLE_MECHANIC, 'tenant_id' => $workshop->id]);
        $anotherMechanic = User::factory()->create(['role' => User::ROLE_MECHANIC, 'tenant_id' => $workshop->id]);
        $outsideMechanic = User::factory()->create(['role' => User::ROLE_MECHANIC, 'tenant_id' => $otherWorkshop->id]);
        [$customer, $vehicle] = $workshop->run(fn () => $this->createCustomerAndVehicle());

        $this->actingAs($admin)->post(route('job-cards.store'), [
            'vehicle_id' => $vehicle->id,
            'description' => 'Inspect the brakes',
        ])->assertRedirect();
        $job = JobCard::query()->sole();
        $adminJob = route('job-cards.show', $job);
        $mechanicJob = route('my-jobs.show', $job);
        $adminNotes = route('job-cards.notes.store', $job);
        $mechanicNotes = route('my-jobs.notes.store', $job);
        $this->assertSame(JobCardUpdate::TYPE_OPENED, $job->updates()->sole()->type);

        $this->actingAs($admin)->patch(route('job-cards.assign', $job), [
            'assigned_mechanic_id' => $mechanic->id,
        ])->assertRedirect($adminJob);
        $this->actingAs($anotherMechanic)->post($mechanicNotes, ['note' => 'Not assigned'])
            ->assertNotFound();
        $this->actingAs($outsideMechanic)->post($mechanicNotes, ['note' => 'Cross workshop'])
            ->assertNotFound();
        $workshop->run(fn () => $this->assertSame(1, $job->updates()->count()));

        $note = "Brake pads checked\n<script>alert('x')</script>";
        $this->actingAs($mechanic)->post($mechanicNotes, ['note' => $note])->assertRedirect($mechanicJob);
        $this->post($mechanicNotes, ['note' => str_repeat('x', 4001)])->assertSessionHasErrors('note');
        $this->actingAs($mechanic)->patch(route('my-jobs.advance', $job), ['status' => JobCard::IN_PROGRESS])
            ->assertRedirect($mechanicJob);
        $this->post($mechanicNotes, ['note' => ''])->assertSessionHasErrors('note');

        $this->actingAs($admin)->post($adminNotes, ['note' => 'Workshop follow-up recorded'])
            ->assertRedirect($adminJob);
        $this->get($adminJob)->assertOk()
            ->assertSeeText('Job opened')
            ->assertSeeText('Status changed to In progress')
            ->assertSeeText('Brake pads checked')
            ->assertSeeText('Workshop follow-up recorded')
            ->assertSee('&lt;script&gt;alert(&#039;x&#039;)&lt;/script&gt;', false)
            ->assertDontSee("<script>alert('x')</script>", false);
        $workshop->run(function () use ($job, $note) {
            $saved = $job->updates()->where('type', JobCardUpdate::TYPE_NOTE)->firstOrFail();
            $this->assertSame($note, $saved->note);
            $this->assertStringNotContainsString('Brake pads checked', $saved->getRawOriginal('note'));
        });

        $this->actingAs($admin)->patch(route('job-cards.advance', $job), ['status' => JobCard::COMPLETED])
            ->assertRedirect($adminJob);
        $this->get(route('vehicles.history', $vehicle))
            ->assertOk()->assertSeeText('Brake pads checked')
            ->assertSeeText('Workshop follow-up recorded')
            ->assertSeeText('Status: Completed');
        $this->actingAs($mechanic)->get($mechanicJob)->assertOk()->assertSeeText('Status changed to Completed');

        $this->actingAs($admin)->get(route('job-cards.show', $job))->assertOk();
        $this->actingAs($otherAdmin)->post($adminNotes, ['note' => 'Cross tenant'])
            ->assertNotFound();
        $workshop->run(function () use ($job) {
            $this->assertSame(5, $job->updates()->count());
            $this->assertSame(2, $job->updates()->where('type', JobCardUpdate::TYPE_NOTE)->count());
            $this->assertSame(2, $job->updates()->where('type', JobCardUpdate::TYPE_STATUS)->count());
            $this->assertSame(1, $job->updates()->where('type', JobCardUpdate::TYPE_OPENED)->count());
        });
    }

    public function test_assigned_mechanics_can_record_vehicle_updates_in_job_and_vehicle_history(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        [$otherAdmin, $otherWorkshop] = $this->createWorkshop();
        $mechanic = User::factory()->create(['role' => User::ROLE_MECHANIC, 'tenant_id' => $workshop->id]);
        $anotherMechanic = User::factory()->create(['role' => User::ROLE_MECHANIC, 'tenant_id' => $workshop->id]);
        $outsideMechanic = User::factory()->create(['role' => User::ROLE_MECHANIC, 'tenant_id' => $otherWorkshop->id]);
        [, $vehicle] = $workshop->run(fn () => $this->createCustomerAndVehicle());

        $this->actingAs($admin)->post(route('job-cards.store'), [
            'vehicle_id' => $vehicle->id,
            'description' => 'Inspect the engine',
        ])->assertRedirect();
        $job = JobCard::query()->sole();
        $jobUrl = route('my-jobs.show', $job);
        $updateUrl = route('my-jobs.vehicle-updates.store', $job);
        $this->patch(route('job-cards.assign', $job), ['assigned_mechanic_id' => $mechanic->id])->assertRedirect();

        $this->actingAs($mechanic)->get($jobUrl)->assertOk()
            ->assertSee('Add vehicle update')->assertSee($updateUrl);
        $this->from($jobUrl)->post($updateUrl, [
            'mileage_km' => '',
            'vehicle_condition' => '',
            'note' => '',
        ])->assertRedirect($jobUrl)->assertSessionHasErrors('vehicle_update');
        $this->from($jobUrl)->post($updateUrl, [
            'mileage_km' => -1,
            'vehicle_condition' => str_repeat('x', 1001),
            'note' => str_repeat('x', 4001),
        ])->assertRedirect($jobUrl)->assertSessionHasErrors(['mileage_km', 'vehicle_condition', 'note']);

        $condition = "Good overall\n<script>alert('unsafe')</script>";
        $note = "Odometer checked\n<script>alert('unsafe')</script>";
        $this->post($updateUrl, [
            'mileage_km' => 123456,
            'vehicle_condition' => $condition,
            'note' => $note,
        ])->assertRedirect($jobUrl);

        $workshop->run(function () use ($job, $condition, $note, $mechanic) {
            $update = $job->updates()->where('type', JobCardUpdate::TYPE_VEHICLE_UPDATE)->sole();
            $this->assertSame(123456, $update->mileage_km);
            $this->assertSame($condition, $update->vehicle_condition);
            $this->assertSame($note, $update->note);
            $this->assertSame($mechanic->id, $update->actor_user_id);
            $this->assertStringNotContainsString('Good overall', $update->getRawOriginal('vehicle_condition'));
            $this->assertStringNotContainsString('Odometer checked', $update->getRawOriginal('note'));
        });

        $this->get($jobUrl)->assertOk()->assertSeeText('123,456 km')
            ->assertSee('&lt;script&gt;alert(&#039;unsafe&#039;)&lt;/script&gt;', false)
            ->assertDontSee("<script>alert('unsafe')</script>", false);
        $this->actingAs($anotherMechanic)->post($updateUrl, ['mileage_km' => 123457])->assertNotFound();
        $this->actingAs($outsideMechanic)->post($updateUrl, ['mileage_km' => 123457])->assertNotFound();

        $history = route('vehicles.history', $vehicle);
        $this->actingAs($admin)->get($history)->assertOk()
            ->assertSeeText('Vehicle update')->assertSeeText('123,456 km')
            ->assertSeeText('Good overall')->assertSeeText('Odometer checked')
            ->assertDontSee("<script>alert('unsafe')</script>", false);

        $this->actingAs($mechanic)->patch(route('my-jobs.advance', $job), ['status' => JobCard::IN_PROGRESS])
            ->assertRedirect($jobUrl);
        $this->patch(route('my-jobs.advance', $job), ['status' => JobCard::COMPLETED])
            ->assertRedirect($jobUrl);
        $this->get($jobUrl)->assertOk()->assertDontSee('name="mileage_km"', false);
        $this->post($updateUrl, ['mileage_km' => 123457])->assertRedirect($jobUrl)
            ->assertSessionHasErrors('vehicle_update');
        $workshop->run(fn () => $this->assertSame(1, $job->updates()->where(
            'type',
            JobCardUpdate::TYPE_VEHICLE_UPDATE
        )->count()));

        $this->actingAs($otherAdmin)->get($history)->assertNotFound();
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

    public function test_job_deadlines_are_optional_and_become_overdue_after_the_due_date_in_malaysia_time(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-18 15:59:00', 'UTC'));
        [$admin, $workshop] = $this->createWorkshop();
        [$otherAdmin] = $this->createWorkshop();
        [, $vehicle] = $workshop->run(fn () => $this->createCustomerAndVehicle());
        $this->actingAs($admin);

        foreach (['2026-11-17', '2026-11-31'] as $date) {
            $this->from(route('job-cards.create'))->post(route('job-cards.store'), [
                'vehicle_id' => $vehicle->id,
                'description' => 'Invalid deadline',
                'due_date' => $date,
            ])->assertRedirect(route('job-cards.create'))->assertSessionHasErrors('due_date');
        }

        $this->post(route('job-cards.store'), [
            'vehicle_id' => $vehicle->id,
            'description' => 'Due today',
            'due_date' => '2026-11-18',
        ])->assertRedirect();
        $this->post(route('job-cards.store'), [
            'vehicle_id' => $vehicle->id,
            'description' => 'Due tomorrow',
            'due_date' => '2026-11-19',
        ])->assertRedirect();
        $this->post(route('job-cards.store'), [
            'vehicle_id' => $vehicle->id,
            'description' => 'No deadline',
        ])->assertRedirect();

        $jobs = $workshop->run(fn () => JobCard::query()->orderBy('id')->get());
        $this->assertSame(['2026-11-18', '2026-11-19', null], $jobs->map(
            fn (JobCard $job) => $job->due_date?->toDateString()
        )->all());
        $overdue = route('job-cards.index', ['filter' => 'overdue']);
        $this->get($overdue)->assertOk()->assertSeeText('No overdue jobs.');

        $this->travelTo(CarbonImmutable::parse('2026-11-18 16:00:00', 'UTC'));
        $this->get(route('job-cards.index'))->assertOk()->assertSeeText('Overdue');
        $this->get($overdue)->assertOk()->assertSeeText('Job #1')
            ->assertDontSeeText('Job #2')->assertDontSeeText('Job #3');
        $this->get(route('dashboard'))->assertOk()->assertViewHas('overdueJobCount', 1)
            ->assertSee(route('job-cards.index', ['filter' => 'overdue']));

        $workshop->run(fn () => $jobs[0]->update(['status' => JobCard::COMPLETED]));
        $this->get($overdue)->assertOk()->assertSeeText('No overdue jobs.');
        $this->get(route('dashboard'))->assertOk()->assertViewHas('overdueJobCount', 0);
        $this->actingAs($otherAdmin)->get($overdue)->assertOk()->assertSeeText('No overdue jobs.');
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

    public function test_inventory_entitled_workshop_dashboard_lists_low_stock_items_without_tenant_leaks(): void
    {
        [$admin] = $this->createWorkshop();
        [$otherAdmin] = $this->createWorkshop();
        $this->actingAs($admin);

        foreach (range(1, 6) as $number) {
            $this->post(route('inventory.store'), [
                'sku' => sprintf('LOW-%02d', $number),
                'name' => sprintf('Low stock item %02d', $number),
                'low_stock_threshold' => 2,
            ])->assertRedirect();
            $item = InventoryItem::query()->where('sku', sprintf('LOW-%02d', $number))->firstOrFail();
            $this->post(route('inventory.move', $item), [
                'direction' => 'in',
                'quantity' => 1,
                'note' => 'Opening stock',
            ])->assertRedirect();
        }
        $this->post(route('inventory.store'), [
            'sku' => 'AVAILABLE',
            'name' => 'Available item',
            'low_stock_threshold' => 0,
        ])->assertRedirect();
        $available = InventoryItem::query()->where('sku', 'AVAILABLE')->firstOrFail();
        $this->post(route('inventory.move', $available), [
            'direction' => 'in',
            'quantity' => 1,
            'note' => 'Opening stock',
        ])->assertRedirect();

        $this->get(route('dashboard'))->assertOk()
            ->assertViewHas('lowStockCount', 6)
            ->assertViewHas('lowStockItems', fn ($items) => $items->count() === 5)
            ->assertSeeText('Low inventory stock (6)')
            ->assertSeeText('LOW-01')->assertSeeText('LOW-05')
            ->assertDontSeeText('LOW-06')->assertDontSeeText('AVAILABLE');

        $this->actingAs($otherAdmin)->get(route('dashboard'))->assertOk()
            ->assertViewHas('lowStockCount', 0)
            ->assertSeeText('No low-stock inventory items.')
            ->assertDontSeeText('LOW-01');

        $basic = SubscriptionPlan::firstOrCreate(['slug' => 'basic'], ['name' => 'Basic']);
        tenancy()->end();
        $admin->accountSubscription()->update([
            'status' => AccountSubscription::STATUS_ACTIVE,
            'subscription_plan_id' => $basic->id,
        ]);
        $this->actingAs($admin)->get(route('dashboard'))->assertOk()
            ->assertViewMissing('lowStockCount')
            ->assertDontSeeText('Low inventory stock');
    }

    public function test_mechanic_dashboard_shows_only_their_open_jobs_scheduled_today_in_malaysia_time(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-18 17:00:00', 'UTC'));
        [$admin, $workshop] = $this->createWorkshop();
        $mechanic = User::factory()->create(['role' => User::ROLE_MECHANIC, 'tenant_id' => $workshop->id]);
        $otherMechanic = User::factory()->create(['role' => User::ROLE_MECHANIC, 'tenant_id' => $workshop->id]);

        $workshop->run(function () use ($mechanic, $otherMechanic) {
            [$customer, $vehicle] = $this->createCustomerAndVehicle();
            $appointment = fn (string $title, string $start, string $status = Calendar::STATUS_SCHEDULED) => Calendar::create([
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle->id,
                'title' => $title,
                'service_type' => $title,
                'start' => $start,
                'end' => CarbonImmutable::parse($start)->addHour()->toDateTimeString(),
                'status' => $status,
            ]);
            $today = $appointment('Today assigned repair', '2026-11-19 10:00:00');
            $tomorrow = $appointment('Tomorrow assigned repair', '2026-11-20 10:00:00');
            $cancelled = $appointment('Cancelled assigned repair', '2026-11-19 11:00:00', Calendar::STATUS_CANCELLED);
            $otherMechanicToday = $appointment('Other mechanic repair', '2026-11-19 12:00:00');

            JobCard::create([
                'customer_id' => $customer->id, 'vehicle_id' => $vehicle->id, 'calendar_id' => $today->id,
                'description' => 'Today brake service', 'status' => JobCard::OPEN,
                'assigned_mechanic_id' => $mechanic->id, 'due_date' => '2026-11-18',
            ]);
            JobCard::create([
                'customer_id' => $customer->id, 'vehicle_id' => $vehicle->id, 'calendar_id' => $tomorrow->id,
                'description' => 'Tomorrow service', 'status' => JobCard::IN_PROGRESS,
                'assigned_mechanic_id' => $mechanic->id,
            ]);
            JobCard::create([
                'customer_id' => $customer->id, 'vehicle_id' => $vehicle->id, 'calendar_id' => $cancelled->id,
                'description' => 'Cancelled service', 'status' => JobCard::OPEN,
                'assigned_mechanic_id' => $mechanic->id,
            ]);
            JobCard::create([
                'customer_id' => $customer->id, 'vehicle_id' => $vehicle->id,
                'description' => 'Completed service', 'status' => JobCard::COMPLETED,
                'assigned_mechanic_id' => $mechanic->id,
            ]);
            JobCard::create([
                'customer_id' => $customer->id, 'vehicle_id' => $vehicle->id, 'calendar_id' => $otherMechanicToday->id,
                'description' => 'Another mechanic service', 'status' => JobCard::OPEN,
                'assigned_mechanic_id' => $otherMechanic->id,
            ]);
        });

        $this->actingAs($mechanic)->get(route('dashboard'))
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertSeeText('My dashboard')
            ->assertViewHas('todayJobCount', 1)
            ->assertViewHas('assignedJobCount', 3)
            ->assertViewHas('overdueJobCount', 1)
            ->assertSeeText('Thu, 19 Nov 2026')
            ->assertSeeText('Today assigned repair')
            ->assertSeeText('Today brake service')
            ->assertSeeText('Overdue jobs: 1')
            ->assertDontSeeText('Tomorrow assigned repair')
            ->assertDontSeeText('Cancelled assigned repair')
            ->assertDontSeeText('Another mechanic service')
            ->assertSee(route('my-jobs.index'))
            ->assertDontSee(route('users.index'))
            ->assertDontSee(route('admin.settings'));
        $this->get(route('my-jobs.index', ['filter' => 'overdue']))
            ->assertOk()->assertSeeText('Job #1')->assertDontSeeText('Job #2');
        $this->get(route('users.index'))->assertForbidden();
        $this->get(route('admin.settings'))->assertForbidden();

        $this->actingAs($admin)->get(route('dashboard'))->assertOk()
            ->assertDontSeeText('My dashboard')
            ->assertSeeText('Workshop dashboard');
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

    public function test_unpaid_invoice_edits_reconcile_linked_inventory_atomically(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        [$customer] = $workshop->run(fn () => $this->createCustomerAndVehicle());
        $this->actingAs($admin)->post(route('inventory.store'), [
            'sku' => 'INV-EDIT',
            'name' => 'Editable item',
            'low_stock_threshold' => 1,
        ])->assertRedirect();
        $item = InventoryItem::query()->sole();
        $this->post(route('inventory.move', $item), [
            'direction' => 'in',
            'quantity' => 10,
            'note' => 'Opening stock',
        ])->assertRedirect();

        $this->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'items' => [[
                'description' => 'Original part',
                'quantity' => 2,
                'unit_price' => '10.00',
                'inventory_item_id' => $item->id,
            ]],
        ])->assertRedirect(route('invoices.show', 1));
        $invoice = Invoice::query()->sole();
        $edit = route('invoices.edit', $invoice);
        $update = route('invoices.update', $invoice);
        $this->get($edit)->assertOk()->assertSee('Edit invoice')->assertSee('Original part');

        $editedItems = [[
            'description' => 'Updated part',
            'quantity' => 5,
            'unit_price' => '15.00',
            'inventory_item_id' => $item->id,
        ]];
        $this->put($update, ['customer_id' => $customer->id, 'items' => $editedItems])
            ->assertRedirect(route('invoices.show', $invoice));
        $this->assertSame(7500, $invoice->fresh()->total_sen);
        $this->assertSame(5, $item->fresh()->quantity);
        $this->assertSame('Updated part', $invoice->fresh()->items[0]['description']);

        $this->from($edit)->put($update, [
            'customer_id' => $customer->id,
            'items' => [[
                'description' => 'Too many parts',
                'quantity' => 11,
                'unit_price' => '15.00',
                'inventory_item_id' => $item->id,
            ]],
        ])->assertRedirect($edit)->assertSessionHasErrors('items');
        $this->assertSame(7500, $invoice->fresh()->total_sen);
        $this->assertSame(5, $item->fresh()->quantity);

        $this->put($update, [
            'customer_id' => $customer->id,
            'items' => [['description' => 'Labour', 'quantity' => 1, 'unit_price' => '20.00']],
        ])->assertRedirect(route('invoices.show', $invoice));
        $this->assertSame(10, $item->fresh()->quantity);
        $this->assertSame([-2, -3, 5], InventoryMovement::where('invoice_id', $invoice->id)
            ->orderBy('id')->pluck('quantity_change')->all());
    }

    public function test_voiding_an_unpaid_invoice_restores_stock_and_preserves_a_void_record(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        [$customer] = $workshop->run(fn () => $this->createCustomerAndVehicle());
        $this->actingAs($admin)->post(route('inventory.store'), [
            'sku' => 'INV-VOID',
            'name' => 'Voidable item',
            'low_stock_threshold' => 1,
        ])->assertRedirect();
        $item = InventoryItem::query()->sole();
        $this->post(route('inventory.move', $item), [
            'direction' => 'in',
            'quantity' => 6,
            'note' => 'Opening stock',
        ])->assertRedirect();
        $this->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'due_date' => CarbonImmutable::today(Calendar::TIMEZONE)->toDateString(),
            'items' => [[
                'description' => 'Voidable service',
                'quantity' => 2,
                'unit_price' => '25.00',
                'inventory_item_id' => $item->id,
            ]],
        ])->assertRedirect(route('invoices.show', 1));
        $invoice = Invoice::query()->sole();
        $invoice->forceFill(['due_date' => CarbonImmutable::today(Calendar::TIMEZONE)->subDay()->toDateString()])->save();
        $void = route('invoices.void', $invoice);

        $this->patch($void, ['reason' => 'Duplicate invoice'])->assertRedirect(route('invoices.show', $invoice));
        $invoice->refresh();
        $this->assertSame(Invoice::STATUS_VOIDED, $invoice->status);
        $this->assertSame('Duplicate invoice', $invoice->void_reason);
        $this->assertSame($admin->id, (int) $invoice->voided_by_user_id);
        $this->assertNotNull($invoice->voided_at);
        $this->assertSame(6, $item->fresh()->quantity);
        $this->assertSame([-2, 2], InventoryMovement::where('invoice_id', $invoice->id)
            ->orderBy('id')->pluck('quantity_change')->all());

        $show = route('invoices.show', $invoice);
        $this->get($show)->assertOk()->assertSeeText('VOIDED')->assertSeeText('Duplicate invoice')
            ->assertSeeText('Voided')->assertDontSee('Edit invoice')->assertDontSee('Void unpaid invoice')
            ->assertDontSee('Record payment');
        $this->get(route('invoices.index'))->assertOk()->assertSeeText('Voided')->assertSeeText('RM 0.00');
        $this->get(route('invoices.index', ['status' => 'overdue']))->assertOk()->assertSeeText('No overdue invoices.');
        $this->get(route('dashboard'))->assertOk()
            ->assertViewHas('outstandingCount', 0)
            ->assertViewHas('overdueCount', 0);
        $this->post(route('invoices.payments.store', $invoice), ['amount' => '1.00'])
            ->assertSessionHasErrors('amount');
        $this->get(route('invoices.edit', $invoice))->assertStatus(409);
        $this->patch($void, ['reason' => 'Duplicate attempt'])->assertStatus(409);
        $this->assertSame(1, Invoice::query()->count());
    }

    public function test_paid_invoices_cannot_be_edited_or_voided(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        [$customer] = $workshop->run(fn () => $this->createCustomerAndVehicle());
        $this->actingAs($admin)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'items' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => '25.00']],
        ])->assertRedirect();
        $invoice = Invoice::query()->sole();
        $this->post(route('invoices.payments.store', $invoice), ['amount' => '10.00'])->assertRedirect();

        $this->get(route('invoices.edit', $invoice))->assertStatus(409);
        $this->put(route('invoices.update', $invoice))->assertStatus(409);
        $this->patch(route('invoices.void', $invoice), ['reason' => 'Paid invoice'])->assertStatus(409);
        $this->assertSame(Invoice::STATUS_ISSUED, $invoice->fresh()->status);
        $this->assertSame(2500, $invoice->fresh()->total_sen);
        $this->assertSame(1, $invoice->payments()->count());
    }

    public function test_invoice_creation_deducts_linked_inventory_once_and_records_the_invoice_movement(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        [$customer] = $workshop->run(fn () => $this->createCustomerAndVehicle());
        $this->actingAs($admin);
        $this->post(route('inventory.store'), [
            'sku' => 'INV-BRAKE',
            'name' => 'Brake pad set',
            'low_stock_threshold' => 3,
        ])->assertRedirect();
        $item = InventoryItem::query()->sole();
        $this->post(route('inventory.move', $item), [
            'direction' => 'in',
            'quantity' => 8,
            'note' => 'Opening stock',
        ])->assertRedirect();

        $this->get(route('invoices.create'))->assertOk()
            ->assertSee('Deduct from inventory (optional)')
            ->assertSee('INV-BRAKE');
        $this->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'items' => [
                ['description' => 'Brake pad set', 'quantity' => 2, 'unit_price' => '45.00', 'inventory_item_id' => $item->id],
                ['description' => 'Brake inspection', 'quantity' => 1, 'unit_price' => '10.00'],
                ['description' => 'Additional set', 'quantity' => 1, 'unit_price' => '45.00', 'inventory_item_id' => $item->id],
            ],
        ])->assertRedirect(route('invoices.show', 1));

        $invoice = Invoice::query()->sole();
        $item->refresh();
        $this->assertSame(5, $item->quantity);
        $this->assertSame(14500, $invoice->total_sen);
        $this->assertSame([$item->id, null, $item->id], array_map(
            fn (array $line) => $line['inventory_item_id'] ?? null,
            $invoice->items
        ));
        $this->assertSame(['INV-BRAKE', null, 'INV-BRAKE'], array_map(
            fn (array $line) => $line['inventory_sku'] ?? null,
            $invoice->items
        ));
        $movement = InventoryMovement::where('invoice_id', $invoice->id)->sole();
        $this->assertSame(-3, $movement->quantity_change);
        $this->assertSame(5, $movement->balance_after);
        $this->assertSame($admin->id, $movement->recorded_by_user_id);
        $this->assertSame('Sold on invoice INV-000001', $movement->note);

        $this->get(route('invoices.show', $invoice))->assertOk()->assertSeeText('INV-BRAKE');
        $this->get(route('inventory.show', $item))->assertOk()
            ->assertSee(route('invoices.show', $invoice))->assertSee('INV-000001');
        $this->post(route('invoices.payments.store', $invoice), ['amount' => '145.00'])->assertRedirect();
        $this->assertSame(5, $item->fresh()->quantity);
        $this->assertSame(1, InventoryMovement::where('invoice_id', $invoice->id)->count());
    }

    public function test_invoice_stock_shortage_or_unentitled_link_rolls_back_without_changes(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        [$customer] = $workshop->run(fn () => $this->createCustomerAndVehicle());
        $this->actingAs($admin)->post(route('inventory.store'), [
            'sku' => 'INV-LIMITED',
            'name' => 'Limited item',
            'low_stock_threshold' => 1,
        ])->assertRedirect();
        $item = InventoryItem::query()->sole();
        $this->post(route('inventory.move', $item), [
            'direction' => 'in',
            'quantity' => 2,
            'note' => 'Opening stock',
        ])->assertRedirect();
        $details = [
            'customer_id' => $customer->id,
            'items' => [['description' => 'Limited item', 'quantity' => 3, 'unit_price' => '2.00', 'inventory_item_id' => $item->id]],
        ];
        $this->from(route('invoices.create'))->post(route('invoices.store'), $details)
            ->assertRedirect(route('invoices.create'))->assertSessionHasErrors('items');
        $this->assertSame(0, Invoice::query()->count());
        $this->assertSame(2, $item->fresh()->quantity);
        $this->assertSame(0, InventoryMovement::whereNotNull('invoice_id')->count());

        $basic = SubscriptionPlan::firstOrCreate(['slug' => 'basic'], ['name' => 'Basic']);
        tenancy()->end();
        $admin->accountSubscription()->update([
            'status' => AccountSubscription::STATUS_ACTIVE,
            'subscription_plan_id' => $basic->id,
        ]);
        $this->actingAs($admin)->get(route('invoices.create'))->assertOk()
            ->assertDontSee('Deduct from inventory (optional)');
        $this->from(route('invoices.create'))->post(route('invoices.store'), $details)
            ->assertRedirect(route('invoices.create'))->assertSessionHasErrors('items');
        $this->assertSame(0, Invoice::query()->count());
        $this->assertSame(2, $item->fresh()->quantity);
        $this->assertSame(0, InventoryMovement::whereNotNull('invoice_id')->count());
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
            'short_name' => 'Beta Fleet',
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
        $this->get(route('customers.index', ['search' => 'Beta Fleet']))->assertOk()->assertSeeText($details['name']);
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

    public function test_workshop_admin_confirms_client_identity_before_portal_history_is_available(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        [$customer, $vehicle] = $workshop->run(fn () => $this->createCustomerAndVehicle());
        $client = User::factory()->create([
            'name' => 'Sample Customer',
            'email' => 'unmatched@example.test',
            'role' => User::ROLE_CLIENT,
            'tenant_id' => $workshop->id,
            'status' => User::STATUS_SUSPENDED,
        ]);

        $workshop->run(function () use ($customer, $vehicle) {
            $appointment = Calendar::create([
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle->id,
                'title' => 'Oil change',
                'service_type' => 'Oil change',
                'start' => '2026-11-18 09:00:00',
                'end' => '2026-11-18 10:00:00',
                'status' => Calendar::STATUS_SCHEDULED,
            ]);
            $job = JobCard::create([
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle->id,
                'calendar_id' => $appointment->id,
                'description' => 'Replace the brake pads',
                'status' => JobCard::COMPLETED,
            ]);
            $job->updates()->create([
                'actor_user_id' => 999,
                'actor_name' => 'Workshop mechanic',
                'type' => JobCardUpdate::TYPE_NOTE,
                'note' => 'Internal workshop note',
            ]);
            Invoice::create([
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle->id,
                'workshop_name' => 'Beta Workshop',
                'customer_name' => $customer->name,
                'vehicle_plate' => $vehicle->plate_number,
                'items' => [['description' => 'Service', 'quantity' => 1, 'unit_price_sen' => 2500, 'total_sen' => 2500]],
                'total_sen' => 2500,
            ]);
        });

        $this->actingAs($client)->get(route('client.portal'))->assertOk()
            ->assertSeeText('waiting for the workshop owner')->assertDontSeeText($vehicle->plate_number);
        $this->actingAs($admin)->post(route('client-accounts.activate', $client))
            ->assertSessionHasErrors('email');
        $this->assertSame(User::STATUS_SUSPENDED, $client->fresh()->status);
        $client->update(['email' => 'sample@example.test']);
        $this->actingAs($admin)->get(route('client-accounts.index'))->assertOk()
            ->assertSeeText($client->email)->assertSeeText($customer->name);
        $this->post(route('client-accounts.activate', $client))->assertRedirect(route('client-accounts.index'));

        $this->assertSame(User::STATUS_ACTIVE, $client->fresh()->status);
        $this->assertSame($client->id, $workshop->run(fn () => (int) $customer->fresh()->portal_user_id));
        $this->actingAs($client->fresh())->get(route('client.portal'))->assertOk()
            ->assertSeeText($vehicle->plate_number)->assertSeeText('View service history');
        $this->get(route('client.vehicles.history', $vehicle))->assertOk()
            ->assertSeeText('Oil change')->assertSeeText('Replace the brake pads')
            ->assertSeeText('INV-000001')->assertSeeText('RM 25.00')
            ->assertDontSeeText('Internal workshop note');
    }

    public function test_unregistered_customer_records_and_other_customers_vehicles_are_not_visible_in_the_portal(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        [, $otherWorkshop] = $this->createWorkshop();
        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'tenant_id' => $workshop->id,
            'email' => 'registered@example.test',
        ]);
        [$registeredCustomer, $registeredVehicle, $unregisteredVehicle] = $workshop->run(function () use ($client) {
            $registeredCustomer = Customer::create([
                'name' => 'Registered Customer',
                'email' => 'registered@example.test',
            ]);
            $registeredVehicle = Vehicle::create(array_replace($this->vehicleDetails($registeredCustomer), [
                'plate_number' => 'REG100',
                'chassis_number' => 'REG-CHASSIS',
                'engine_number' => 'REG-ENGINE',
            ]));
            $unregisteredCustomer = Customer::create([
                'name' => 'Walk-in Customer',
                'short_name' => 'Walk-in',
                'email' => 'walkin@example.test',
            ]);
            $unregisteredVehicle = Vehicle::create(array_replace($this->vehicleDetails($unregisteredCustomer), [
                'plate_number' => 'WALK200',
                'chassis_number' => 'WALK-CHASSIS',
                'engine_number' => 'WALK-ENGINE',
            ]));
            $registeredCustomer->update(['portal_user_id' => $client->id]);

            return [$registeredCustomer, $registeredVehicle, $unregisteredVehicle];
        });
        [, $outsideVehicle] = $otherWorkshop->run(function () {
            $customer = Customer::create(['name' => 'Outside Customer', 'email' => 'outside@example.test']);

            return [$customer, Vehicle::create(array_replace($this->vehicleDetails($customer), [
                'plate_number' => 'OUT300',
                'chassis_number' => 'OUT-CHASSIS',
                'engine_number' => 'OUT-ENGINE',
            ]))];
        });

        $this->get(route('client.portal'))->assertRedirect(route('login'));
        $this->actingAs($client)->get(route('client.portal'))->assertOk()
            ->assertSeeText($registeredVehicle->plate_number)
            ->assertDontSeeText('Walk-in')
            ->assertDontSeeText($unregisteredVehicle->plate_number);
        $this->get(route('client.vehicles.history', $unregisteredVehicle))->assertNotFound();
        $this->get(route('client.vehicles.history', $outsideVehicle))->assertOk()
            ->assertSeeText($registeredVehicle->plate_number)->assertDontSeeText('OUT300');
        $this->get(route('client-accounts.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('client.portal'))->assertForbidden();
    }

    public function test_clients_can_self_add_five_vehicles_and_request_additional_vehicles_for_approval(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'tenant_id' => $workshop->id,
            'email' => 'fleet@example.test',
        ]);
        $customer = $workshop->run(function () use ($client) {
            return Customer::create([
                'name' => 'Fleet Customer',
                'email' => 'fleet@example.test',
                'portal_user_id' => $client->id,
            ]);
        });
        for ($index = 1; $index <= 5; $index++) {
            $this->actingAs($client)->post(route('client.vehicles.store'), [
                'plate_number' => "FLEET{$index}",
                'chassis_number' => "FLEET-CHASSIS-{$index}",
                'engine_number' => "FLEET-ENGINE-{$index}",
                'brand' => 'Sample',
                'model' => 'Car',
            ])->assertRedirect(route('client.portal'));
        }
        $this->assertSame(5, $workshop->run(fn () => $customer->vehicles()->count()));
        $extraVehicle = [
            'plate_number' => 'FLEET6',
            'chassis_number' => 'FLEET-CHASSIS-6',
            'engine_number' => 'FLEET-ENGINE-6',
            'brand' => 'Sample',
            'model' => 'Car',
            'year' => '2024',
            'color' => 'Blue',
        ];

        $this->actingAs($client)->get(route('client.vehicle.create'))
            ->assertRedirect(route('client.portal'))->assertSessionHasErrors('vehicles');
        $this->post(route('client.vehicles.store'), $extraVehicle)->assertSessionHasErrors('vehicles');
        $this->post(route('client.vehicle-requests.store'), $extraVehicle)
            ->assertRedirect(route('client.portal'));
        $request = CustomerVehicleRequest::query()->sole();
        $this->assertSame($customer->id, $request->customer_id);

        $this->actingAs($admin)->get(route('client-accounts.index'))->assertOk()
            ->assertSeeText('FLEET6');
        $this->patch(route('client-vehicle-requests.approve', $request))
            ->assertRedirect(route('client-accounts.index'));
        $this->assertSame(CustomerVehicleRequest::APPROVED, $request->fresh()->status);
        $this->assertSame(6, $workshop->run(fn () => $customer->vehicles()->count()));

        $extraVehicle['plate_number'] = 'FLEET7';
        $extraVehicle['chassis_number'] = 'FLEET-CHASSIS-7';
        $extraVehicle['engine_number'] = 'FLEET-ENGINE-7';
        $this->actingAs($client)->post(route('client.vehicle-requests.store'), $extraVehicle)
            ->assertRedirect(route('client.portal'));
        $rejectedRequest = CustomerVehicleRequest::query()->latest('id')->firstOrFail();
        $this->actingAs($admin)->patch(route('client-vehicle-requests.reject', $rejectedRequest))
            ->assertRedirect(route('client-accounts.index'));
        $this->assertSame(CustomerVehicleRequest::REJECTED, $rejectedRequest->fresh()->status);
        $this->assertSame(6, $workshop->run(fn () => $customer->vehicles()->count()));
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

    public function test_suppliers_are_workshop_scoped_and_inactive_suppliers_cannot_be_ordered(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        [$otherAdmin] = $this->createWorkshop();
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'tenant_id' => $workshop->id]);
        $this->actingAs($admin)->post(route('suppliers.store'), [
            'name' => 'Reliable Parts',
            'contact_name' => 'Alex Tan',
            'email' => 'parts@example.test',
            'phone' => '+60123456789',
            'address' => 'Kuala Lumpur',
            'is_active' => 1,
        ])->assertRedirect(route('suppliers.index'));
        $supplier = Supplier::query()->sole();
        $this->get(route('suppliers.index'))->assertOk()
            ->assertSeeText('Reliable Parts')->assertSeeText('Alex Tan')
            ->assertSeeText('Active')->assertSeeText('0');
        $this->put(route('suppliers.update', $supplier), [
            'name' => 'Reliable Parts',
            'contact_name' => 'Alex Tan',
            'email' => 'parts@example.test',
            'phone' => '+60123456789',
            'address' => 'Kuala Lumpur',
            'is_active' => 0,
        ])->assertRedirect(route('suppliers.index'));
        $this->get(route('purchase-orders.create'))->assertOk()->assertSeeText('Add an active supplier first.');
        $this->post(route('purchase-orders.store'), [
            'supplier_id' => $supplier->id,
            'items' => [['inventory_item_id' => 1, 'quantity' => 1]],
        ])->assertSessionHasErrors('supplier_id');

        $this->actingAs($otherAdmin)->get(route('suppliers.index'))->assertOk()->assertDontSeeText('Reliable Parts');
        $this->get(route('suppliers.edit', $supplier))->assertNotFound();
        $this->actingAs($client)->get(route('suppliers.index'))->assertForbidden();
    }

    public function test_purchase_orders_update_stock_only_when_partially_or_fully_received(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        [$otherAdmin] = $this->createWorkshop();
        $supplier = $workshop->run(fn () => Supplier::create(['name' => 'Parts Supplier', 'is_active' => true]));
        $this->actingAs($admin);
        foreach ([
            ['sku' => 'PO-FILTER', 'name' => 'Oil filter', 'stock' => 2],
            ['sku' => 'PO-PLUG', 'name' => 'Spark plug', 'stock' => 1],
        ] as $details) {
            $this->post(route('inventory.store'), [
                'sku' => $details['sku'],
                'name' => $details['name'],
                'low_stock_threshold' => 0,
            ])->assertRedirect();
            $item = InventoryItem::query()->where('sku', $details['sku'])->firstOrFail();
            $this->post(route('inventory.move', $item), [
                'direction' => 'in',
                'quantity' => $details['stock'],
                'note' => 'Opening stock',
            ])->assertRedirect();
        }
        $filter = InventoryItem::query()->where('sku', 'PO-FILTER')->firstOrFail();
        $plug = InventoryItem::query()->where('sku', 'PO-PLUG')->firstOrFail();
        $this->post(route('purchase-orders.store'), [
            'supplier_id' => $supplier->id,
            'notes' => 'Weekly stock replenishment',
            'items' => [
                ['inventory_item_id' => $filter->id, 'quantity' => 5],
                ['inventory_item_id' => $plug->id, 'quantity' => 3],
            ],
        ])->assertRedirect();
        $order = PurchaseOrder::query()->with('items')->sole();
        $this->actingAs($otherAdmin)->get(route('purchase-orders.show', $order))->assertNotFound();
        $this->actingAs($admin);
        $this->get(route('purchase-orders.show', $order))->assertOk()
            ->assertSeeText('Weekly stock replenishment')->assertSeeText('Receive stock');
        $this->assertSame(2, $filter->fresh()->quantity);
        $this->assertSame(1, $plug->fresh()->quantity);

        $lines = $order->items->keyBy('inventory_item_id');
        $receive = route('purchase-orders.receive', $order);
        $this->from(route('purchase-orders.show', $order))->post($receive, [
            'quantities' => [$lines[$filter->id]->id => 6, $lines[$plug->id]->id => 0],
        ])->assertRedirect(route('purchase-orders.show', $order))->assertSessionHasErrors();
        $this->post($receive, [
            'quantities' => [$lines[$filter->id]->id => 2, $lines[$plug->id]->id => 0],
        ])->assertRedirect(route('purchase-orders.show', $order));
        $order->refresh();
        $this->assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $order->status);
        $this->assertNull($order->received_at);
        $this->assertSame(4, $filter->fresh()->quantity);
        $this->assertSame(1, $plug->fresh()->quantity);
        $this->assertSame(2, $lines[$filter->id]->movements()->sole()->quantity_change);

        $this->post($receive, [
            'quantities' => [$lines[$filter->id]->id => 3, $lines[$plug->id]->id => 3],
        ])->assertRedirect(route('purchase-orders.show', $order));
        $order->refresh();
        $this->assertSame(PurchaseOrder::STATUS_RECEIVED, $order->status);
        $this->assertNotNull($order->received_at);
        $this->assertSame(7, $filter->fresh()->quantity);
        $this->assertSame(4, $plug->fresh()->quantity);
        $this->assertSame([2, 3], $lines[$filter->id]->movements()->orderBy('id')->pluck('quantity_change')->all());
        $this->assertSame([3], $lines[$plug->id]->movements()->pluck('quantity_change')->all());
        $this->get(route('inventory.show', $filter))->assertOk()
            ->assertSee(route('purchase-orders.show', $order))->assertSee('PO-000001');
        $this->get(route('purchase-orders.show', $order))->assertOk()
            ->assertSeeText('Received 2 on')->assertSeeText('Received 3 on');
        $this->post($receive, [
            'quantities' => [$lines[$filter->id]->id => 0, $lines[$plug->id]->id => 0],
        ])->assertSessionHasErrors('status');
    }

    public function test_purchase_order_cancellation_and_stock_limit_fail_without_receipt_side_effects(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        $supplier = $workshop->run(fn () => Supplier::create(['name' => 'Parts Supplier', 'is_active' => true]));
        $item = $workshop->run(fn () => InventoryItem::create([
            'sku' => 'PO-LIMIT',
            'name' => 'Limited stock',
            'low_stock_threshold' => 0,
        ]));
        $this->actingAs($admin)->post(route('purchase-orders.store'), [
            'supplier_id' => $supplier->id,
            'items' => [['inventory_item_id' => $item->id, 'quantity' => 2]],
        ])->assertRedirect();
        $order = PurchaseOrder::query()->with('items')->sole();
        $line = $order->items->sole();
        $receive = route('purchase-orders.receive', $order);
        $workshop->run(function () use ($item) {
            $item->forceFill(['quantity' => InventoryItem::MAX_QUANTITY])->save();
        });

        $this->from(route('purchase-orders.show', $order))->post($receive, [
            'quantities' => [$line->id => 1],
        ])->assertRedirect(route('purchase-orders.show', $order))->assertSessionHasErrors();
        $workshop->run(function () use ($item, $order, $line) {
            $this->assertSame(InventoryItem::MAX_QUANTITY, $item->fresh()->quantity);
            $this->assertSame(PurchaseOrder::STATUS_ORDERED, $order->fresh()->status);
            $this->assertSame(0, $line->fresh()->received_quantity);
            $this->assertSame(0, InventoryMovement::where('purchase_order_item_id', $line->id)->count());
        });

        $this->patch(route('purchase-orders.cancel', $order))->assertRedirect(route('purchase-orders.show', $order));
        $this->assertSame(PurchaseOrder::STATUS_CANCELLED, $order->fresh()->status);
        $this->post($receive, ['quantities' => [$line->id => 1]])->assertSessionHasErrors('status');
    }

    public function test_job_part_usage_and_returns_update_stock_and_ledger_atomically(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        [$otherAdmin] = $this->createWorkshop();
        $mechanic = User::factory()->create(['role' => User::ROLE_MECHANIC, 'tenant_id' => $workshop->id]);
        [, $vehicle] = $workshop->run(fn () => $this->createCustomerAndVehicle());
        $this->actingAs($admin)->post(route('job-cards.store'), [
            'vehicle_id' => $vehicle->id,
            'description' => 'Replace the filter',
        ])->assertRedirect();
        $job = JobCard::query()->sole();
        $this->patch(route('job-cards.assign', $job), ['assigned_mechanic_id' => $mechanic->id])
            ->assertRedirect();
        $this->post(route('inventory.store'), [
            'sku' => 'JOB-FILTER',
            'name' => 'Oil filter',
            'low_stock_threshold' => 1,
        ])->assertRedirect();
        $item = InventoryItem::query()->sole();
        $this->post(route('inventory.move', $item), [
            'direction' => 'in',
            'quantity' => 10,
            'note' => 'Opening stock',
        ])->assertRedirect();

        $jobUrl = route('job-cards.show', $job);
        $usePart = route('my-jobs.parts.store', $job);
        $this->actingAs($mechanic)->post($usePart, ['inventory_item_id' => $item->id, 'quantity' => 4])
            ->assertRedirect(route('my-jobs.show', $job));

        $workshop->run(function () use ($mechanic, $item, $job) {
            $item->refresh();
            $this->assertSame(6, $item->quantity);
            $usage = $job->parts()->whereNull('return_for_id')->sole();
            $this->assertSame(JobCardPart::TYPE_USED, $usage->type);
            $this->assertSame(4, $usage->quantity);
            $this->assertSame('JOB-FILTER', $usage->item_sku);
            $movement = InventoryMovement::findOrFail($usage->inventory_movement_id);
            $this->assertSame(-4, $movement->quantity_change);
            $this->assertSame(6, $movement->balance_after);
            $this->assertSame($mechanic->id, $usage->recorded_by_user_id);
        });

        $usage = $workshop->run(fn () => $job->parts()->whereNull('return_for_id')->sole());
        $returnPart = route('my-jobs.parts.return', [$job, $usage]);
        $this->post($returnPart, ['quantity' => 1])->assertRedirect(route('my-jobs.show', $job));
        $this->from($jobUrl)->post($returnPart, ['quantity' => 4])->assertRedirect($jobUrl)
            ->assertSessionHasErrors('quantity');
        $this->post($returnPart, ['quantity' => 1])->assertRedirect(route('my-jobs.show', $job));

        $workshop->run(function () use ($item, $job, $usage) {
            $item->refresh();
            $this->assertSame(8, $item->quantity);
            $this->assertSame([1, 1], $usage->returns()->orderBy('id')->pluck('quantity')->all());
            $this->assertSame(4, InventoryMovement::where('inventory_item_id', $item->id)->count());
            $this->assertSame(3, $job->parts()->count());
            $this->assertSame(3, $job->updates()->whereIn('type', [
                JobCardUpdate::TYPE_PART_USED,
                JobCardUpdate::TYPE_PART_RETURNED,
            ])->count());
            $this->assertSame(0, Invoice::query()->count());
            $this->assertSame(0, \App\Models\InvoicePayment::query()->count());
        });

        $this->actingAs($mechanic)->post($usePart, ['inventory_item_id' => $item->id, 'quantity' => 11])
            ->assertSessionHasErrors('quantity');
        $adminUsePart = route('job-cards.parts.store', $job);
        $adminReturnPart = route('job-cards.parts.return', [$job, $usage]);
        $this->actingAs($admin)->patch(route('job-cards.advance', $job), ['status' => JobCard::IN_PROGRESS])
            ->assertRedirect($jobUrl);
        $this->patch(route('job-cards.advance', $job), ['status' => JobCard::COMPLETED])->assertRedirect($jobUrl);
        $this->post($adminUsePart, ['inventory_item_id' => $item->id, 'quantity' => 1])->assertSessionHasErrors('quantity');
        $this->post($adminReturnPart, ['quantity' => 1])->assertSessionHasErrors('quantity');
        $workshop->run(fn () => $this->assertSame(8, $item->fresh()->quantity));

        $this->actingAs($otherAdmin)->post($adminUsePart, ['inventory_item_id' => $item->id, 'quantity' => 1])
            ->assertNotFound();
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

    public function test_admin_can_record_partial_refunds_against_a_payment_without_deleting_it(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-22 12:00:00', 'UTC'));
        [$admin, $workshop] = $this->createWorkshop();
        [$otherAdmin] = $this->createWorkshop();
        [$customer] = $workshop->run(fn () => $this->createCustomerAndVehicle());

        $this->actingAs($admin)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'items' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => '25.10']],
        ])->assertRedirect();
        $invoice = Invoice::query()->sole();
        $invoice->forceFill(['due_date' => CarbonImmutable::today(Calendar::TIMEZONE)->subDay()->toDateString()])->save();
        $this->post(route('invoices.payments.store', $invoice), ['amount' => '20.00'])->assertRedirect();
        $payment = $invoice->payments()->sole();

        $refundUrl = route('invoices.payments.refunds.store', [$invoice, $payment]);
        $this->post($refundUrl, [
            'amount' => '5.00',
            'reason' => 'Customer returned an unused part.',
            'reference' => 'EXT-REFUND-01',
        ])->assertRedirect();
        $refund = $payment->refunds()->sole();
        $this->assertSame(500, $refund->amount_sen);
        $this->assertSame(2000, $payment->fresh()->amount_sen);
        $this->assertSame(1010, $invoice->fresh()->balanceDue(
            (int) $invoice->payments()->sum('amount_sen'),
            (int) $invoice->refunds()->sum('invoice_refunds.amount_sen')
        ));
        $this->get(route('invoices.refunds.show', [$invoice, $refund]))->assertOk()
            ->assertSeeText($refund->number())
            ->assertSeeText('EXT-REFUND-01')
            ->assertSeeText('money was returned outside this app');
        $this->get(route('invoices.index', ['status' => 'overdue']))->assertOk()
            ->assertSeeText($invoice->number())->assertSeeText('RM 10.10');
        $this->get(route('dashboard'))->assertOk()
            ->assertViewHas('outstandingSen', 1010)->assertViewHas('overdueSen', 1010);

        $this->from(route('invoices.show', $invoice))->post($refundUrl, [
            'amount' => '15.01',
            'reason' => 'Exceeds the unrefunded payment.',
        ])->assertRedirect(route('invoices.show', $invoice))->assertSessionHasErrors('amount');
        $this->assertSame(1, $payment->refunds()->count());
        $this->post(route('invoices.credit-notes.store', $invoice), [
            'amount' => '2.00',
            'reason' => 'Reduce the remaining service charge.',
        ])->assertRedirect();
        $this->assertSame(810, $invoice->fresh()->balanceDue(
            (int) $invoice->payments()->sum('amount_sen'),
            (int) $invoice->refunds()->sum('invoice_refunds.amount_sen'),
            (int) $invoice->creditNotes()->sum('amount_sen')
        ));
        $this->get(route('invoices.index', ['status' => 'overdue']))->assertOk()->assertSeeText('RM 8.10');
        $this->get(route('dashboard'))->assertOk()
            ->assertViewHas('outstandingSen', 810)->assertViewHas('overdueSen', 810);
        $this->actingAs($otherAdmin)->post($refundUrl, [
            'amount' => '1.00',
            'reason' => 'Wrong workshop.',
        ])->assertNotFound();
    }

    public function test_admin_can_send_invoice_email_and_audit_transport_acceptance(): void
    {
        Mail::fake();
        [$admin, $workshop] = $this->createWorkshop();
        [$otherAdmin] = $this->createWorkshop();
        $mechanic = User::factory()->create(['role' => User::ROLE_MECHANIC, 'tenant_id' => $workshop->id]);
        $customer = $workshop->run(fn () => Customer::create([
            'name' => 'Email Customer',
            'email' => 'email-customer@example.test',
        ]));

        $this->actingAs($admin)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'items' => [['description' => 'Email service', 'quantity' => 1, 'unit_price' => '50.00']],
        ])->assertRedirect();
        $invoice = Invoice::query()->sole();
        $show = route('invoices.show', $invoice);
        $send = route('invoices.email-deliveries.store', $invoice);

        $this->get($show)->assertOk()
            ->assertSeeText('Invoice email delivery')
            ->assertSeeText('email-customer@example.test')
            ->assertSee('action="'.$send.'"', false);
        $this->post($send)->assertRedirect($show)->assertSessionHas('success');

        Mail::assertSent(InvoiceEmail::class, function (InvoiceEmail $mail) {
            return $mail->hasTo('email-customer@example.test')
                && str_contains($mail->render(), 'Email service')
                && str_contains($mail->render(), 'not submitted to or validated by LHDN MyInvois');
        });
        $delivery = $workshop->run(fn () => InvoiceEmailDelivery::sole());
        $this->assertSame(InvoiceEmailDelivery::STATUS_SENT, $delivery->status);
        $this->assertSame('email-customer@example.test', $delivery->recipient_email);
        $this->assertNotNull($delivery->sent_at);
        $this->get($show)->assertOk()->assertSeeText('Accepted by mail transport');

        $this->actingAs($mechanic)->post($send)->assertForbidden();
        $this->actingAs($otherAdmin)->post($send)->assertNotFound();
    }

    public function test_failed_invoice_emails_can_be_retried_and_each_attempt_is_audited(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        $customer = $workshop->run(fn () => Customer::create([
            'name' => 'Retry Customer',
            'email' => 'retry-customer@example.test',
        ]));
        $this->actingAs($admin)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'items' => [['description' => 'Retry service', 'quantity' => 1, 'unit_price' => '25.00']],
        ])->assertRedirect();

        $invoice = Invoice::query()->sole();
        $show = route('invoices.show', $invoice);
        $send = route('invoices.email-deliveries.store', $invoice);
        Mail::shouldReceive('to')->once()->with('retry-customer@example.test')
            ->andThrow(new \RuntimeException('Do not expose transport details.'));

        $this->post($send)->assertRedirect($show)->assertSessionHas('error');
        $failed = $workshop->run(fn () => InvoiceEmailDelivery::sole());
        $this->assertSame(InvoiceEmailDelivery::STATUS_FAILED, $failed->status);
        $this->assertStringNotContainsString('transport details', $failed->failure_message);
        $this->assertNull($failed->sent_at);

        Mail::fake();
        $this->post(route('invoices.email-deliveries.retry', [$invoice, $failed]))
            ->assertRedirect($show)->assertSessionHas('success');
        Mail::assertSent(InvoiceEmail::class, fn (InvoiceEmail $mail) => $mail->hasTo('retry-customer@example.test'));
        $attempts = $workshop->run(fn () => InvoiceEmailDelivery::query()->orderBy('id')->get());
        $this->assertCount(2, $attempts);
        $this->assertSame($failed->id, $attempts[1]->retry_of_id);
        $this->assertSame(InvoiceEmailDelivery::STATUS_SENT, $attempts[1]->status);
        $this->get($show)->assertOk()->assertSeeText('Failed:')->assertSeeText('Accepted by mail transport');
    }

    public function test_payment_reminders_are_opt_in_scheduled_once_and_pause_for_pending_proofs(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-22 09:00:00', 'Asia/Kuala_Lumpur'));
        Mail::fake();
        [$admin, $workshop] = $this->createWorkshop();
        [$otherAdmin, $otherWorkshop] = $this->createWorkshop();
        AccountSubscription::query()->whereIn('user_id', [$admin->id, $otherAdmin->id])
            ->update(['current_period_ends_at' => now()->addDays(30)]);
        $invoice = $workshop->run(function () {
            $customer = Customer::create(['name' => 'Reminder Customer', 'email' => 'reminder@example.test']);

            $paid = Invoice::create([
                'customer_id' => $customer->id, 'customer_name' => $customer->name, 'workshop_name' => 'Beta Workshop',
                'due_date' => '2026-11-22', 'items' => [], 'total_sen' => 10000,
            ]);
            $paid->payments()->create(['amount_sen' => 10000, 'recorded_by_user_id' => 1]);
            $credited = Invoice::create([
                'customer_id' => $customer->id, 'customer_name' => $customer->name, 'workshop_name' => 'Beta Workshop',
                'due_date' => '2026-11-22', 'items' => [], 'total_sen' => 10000,
            ]);
            $credited->creditNotes()->create(['amount_sen' => 10000, 'reason' => 'Full credit', 'issued_by_user_id' => 1]);
            Invoice::create([
                'customer_id' => $customer->id, 'customer_name' => $customer->name, 'workshop_name' => 'Beta Workshop',
                'due_date' => '2026-11-22', 'items' => [], 'total_sen' => 10000, 'status' => Invoice::STATUS_VOIDED,
            ]);
            Invoice::create([
                'customer_id' => $customer->id, 'customer_name' => $customer->name, 'workshop_name' => 'Beta Workshop',
                'items' => [], 'total_sen' => 10000,
            ]);

            return Invoice::create([
                'customer_id' => $customer->id, 'customer_name' => $customer->name, 'workshop_name' => 'Beta Workshop',
                'due_date' => '2026-11-22', 'items' => [], 'total_sen' => 10000,
            ]);
        });
        $otherWorkshop->run(function () {
            $customer = Customer::create(['name' => 'Other Customer', 'email' => 'other@example.test']);
            Invoice::create([
                'customer_id' => $customer->id, 'customer_name' => $customer->name, 'workshop_name' => 'Other Workshop',
                'due_date' => '2026-11-22', 'items' => [], 'total_sen' => 10000,
            ]);
        });
        $this->artisan('invoices:send-payment-reminders')->assertSuccessful();
        Mail::assertNothingSent();

        $this->actingAs($otherAdmin)->put(route('payment-reminders.settings'), ['enabled' => 1])->assertRedirect();
        AccountSubscription::query()->where('user_id', $otherAdmin->id)->update(['current_period_ends_at' => now()->subMinute()]);
        $this->actingAs($admin)->put(route('payment-reminders.settings'), ['enabled' => 1])->assertRedirect();
        $submission = $workshop->run(fn () => $invoice->paymentSubmissions()->create([
            'submitted_by_user_id' => $admin->id, 'payment_method' => 'Bank', 'amount_sen' => 1000,
            'proof_path' => 'test-proof', 'proof_mime_type' => 'application/pdf',
            'status' => InvoicePaymentSubmission::STATUS_PENDING,
        ]));
        $this->artisan('invoices:send-payment-reminders')->assertSuccessful();
        Mail::assertNothingSent();
        $workshop->run(fn () => $submission->update(['status' => InvoicePaymentSubmission::STATUS_REJECTED]));

        $this->artisan('invoices:send-payment-reminders')->assertSuccessful();
        $this->artisan('invoices:send-payment-reminders')->assertSuccessful();
        Mail::assertSent(InvoicePaymentReminderEmail::class, 1);
        Mail::assertSent(InvoicePaymentReminderEmail::class, fn ($mail) => $mail->hasTo('reminder@example.test')
            && $mail->balanceSen === 10000 && str_contains($mail->render(), 'RM 100.00'));
        $this->assertSame(0, $otherWorkshop->run(fn () => InvoicePaymentReminder::count()));

        $this->travelTo(CarbonImmutable::parse('2026-11-29 10:00:00', 'Asia/Kuala_Lumpur'));
        $workshop->run(function () use ($invoice, $admin) {
            $payment = $invoice->payments()->create(['amount_sen' => 4000, 'recorded_by_user_id' => $admin->id]);
            $payment->refunds()->create(['amount_sen' => 500, 'reason' => 'Partial refund', 'refunded_by_user_id' => $admin->id]);
            $invoice->creditNotes()->create(['amount_sen' => 1000, 'reason' => 'Adjustment', 'issued_by_user_id' => $admin->id]);
        });
        $this->artisan('invoices:send-payment-reminders')->assertSuccessful();
        Mail::assertSent(InvoicePaymentReminderEmail::class, fn ($mail) => $mail->balanceSen === 5500);
        $this->travelTo(CarbonImmutable::parse('2026-12-06 10:00:00', 'Asia/Kuala_Lumpur'));
        $this->artisan('invoices:send-payment-reminders')->assertSuccessful();
        Mail::assertSent(InvoicePaymentReminderEmail::class, 3);
        $this->assertSame([0, 7, 14], $workshop->run(fn () => $invoice->paymentReminders()
            ->orderBy('id')->pluck('days_overdue')->map(fn ($value) => (int) $value)->all()));
    }

    public function test_failed_reminder_retry_checks_eligibility_and_records_each_attempt(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-22 09:00:00', 'Asia/Kuala_Lumpur'));
        [$admin, $workshop] = $this->createWorkshop();
        [$otherAdmin] = $this->createWorkshop();
        $mechanic = User::factory()->create(['role' => User::ROLE_MECHANIC, 'tenant_id' => $workshop->id]);
        $invoice = $workshop->run(function () {
            $customer = Customer::create(['name' => 'Reminder Customer', 'email' => 'retry@example.test']);

            return Invoice::create([
                'customer_id' => $customer->id, 'customer_name' => $customer->name, 'workshop_name' => 'Beta Workshop',
                'due_date' => '2026-11-22', 'items' => [], 'total_sen' => 10000,
            ]);
        });
        $this->actingAs($admin)->put(route('payment-reminders.settings'), ['enabled' => 1])->assertRedirect();
        Mail::shouldReceive('to')->once()->with('retry@example.test')->andThrow(new \RuntimeException('private error'));
        $this->artisan('invoices:send-payment-reminders')->assertSuccessful();
        $reminder = $workshop->run(fn () => InvoicePaymentReminder::sole());
        $attempt = $workshop->run(fn () => $reminder->attempts()->sole());
        $this->assertSame(InvoicePaymentReminderAttempt::FAILED, $attempt->status);
        $this->assertStringNotContainsString('private error', $attempt->failure_message);
        $retry = route('invoices.payment-reminders.retry', [$invoice, $reminder]);
        $show = route('invoices.show', $invoice);
        $this->actingAs($mechanic)->post($retry)->assertForbidden();
        $this->actingAs($otherAdmin)->post($retry)->assertNotFound();

        $this->actingAs($admin)->put(route('payment-reminders.settings'), ['enabled' => 0])->assertRedirect();
        $this->from($show)->post($retry)->assertSessionHasErrors('reminder');
        $this->put(route('payment-reminders.settings'), ['enabled' => 1])->assertRedirect();
        $submission = $workshop->run(fn () => $invoice->paymentSubmissions()->create([
            'submitted_by_user_id' => $admin->id, 'payment_method' => 'Bank', 'amount_sen' => 1000,
            'proof_path' => 'test-proof', 'proof_mime_type' => 'application/pdf',
            'status' => InvoicePaymentSubmission::STATUS_PENDING,
        ]));
        $this->from($show)->post($retry)->assertSessionHasErrors('reminder');
        $workshop->run(fn () => $submission->update(['status' => InvoicePaymentSubmission::STATUS_REJECTED]));
        Mail::fake();
        $workshop->run(fn () => $invoice->customer()->firstOrFail()->update(['email' => 'invalid-address']));
        $this->post($retry)->assertRedirect($show)->assertSessionHas('error');
        Mail::assertNothingSent();
        $workshop->run(fn () => $invoice->customer()->firstOrFail()->update(['email' => 'retry@example.test']));
        $this->post($retry)->assertRedirect($show)->assertSessionHas('success');
        Mail::assertSent(InvoicePaymentReminderEmail::class, 1);
        $this->from($show)->post($retry)->assertSessionHasErrors('reminder');
        $this->assertSame(
            [InvoicePaymentReminderAttempt::FAILED, InvoicePaymentReminderAttempt::FAILED, InvoicePaymentReminderAttempt::SENT],
            $workshop->run(fn () => $reminder->attempts()->orderBy('id')->pluck('status')->all())
        );
        $this->get($show)->assertOk()->assertSeeText('Failed:')->assertSeeText('Accepted by mail transport');
    }

    public function test_credit_notes_reduce_invoice_balance_and_refunds_consume_customer_credit(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'tenant_id' => $workshop->id,
            'status' => User::STATUS_ACTIVE,
        ]);
        [$customer] = $workshop->run(fn () => $this->createCustomerAndVehicle());
        $workshop->run(fn () => $customer->update(['portal_user_id' => $client->id]));

        $this->actingAs($admin)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'items' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => '100.00']],
        ])->assertRedirect();
        $invoice = Invoice::query()->sole();
        $this->post(route('invoices.credit-notes.store', $invoice), [
            'amount' => '1.00',
            'reason' => 'No payment yet.',
        ])->assertSessionHasErrors('amount');
        $this->post(route('invoices.payments.store', $invoice), ['amount' => '100.00'])->assertRedirect();
        $payment = $invoice->payments()->sole();

        $creditUrl = route('invoices.credit-notes.store', $invoice);
        $this->post($creditUrl, ['amount' => '70.00', 'reason' => 'Service adjustment.'])->assertRedirect();
        $creditNote = InvoiceCreditNote::query()->sole();
        $this->assertSame('Customer credit', $invoice->fresh()->paymentStatus(10000, 0, 7000));
        $this->assertSame(7000, $invoice->fresh()->customerCredit(10000, 0, 7000));
        $this->get(route('invoices.credit-notes.show', [$invoice, $creditNote]))->assertOk()
            ->assertSeeText($creditNote->number())->assertSeeText('not submitted to LHDN MyInvois');

        $refundUrl = route('invoices.payments.refunds.store', [$invoice, $payment]);
        $this->post($refundUrl, ['amount' => '50.00', 'reason' => 'Returning excess credit.'])->assertRedirect();
        $this->assertSame(2000, $invoice->fresh()->customerCredit(10000, 5000, 7000));
        $this->from(route('invoices.show', $invoice))->post($creditUrl, [
            'amount' => '31.00',
            'reason' => 'Exceeds remaining invoice value.',
        ])->assertRedirect(route('invoices.show', $invoice))->assertSessionHasErrors('amount');
        $this->from(route('invoices.show', $invoice))->post($refundUrl, [
            'amount' => '21.00',
            'reason' => 'Exceeds available customer credit.',
        ])->assertRedirect(route('invoices.show', $invoice))->assertSessionHasErrors('amount');
        $this->post($refundUrl, ['amount' => '20.00', 'reason' => 'Returning remaining credit.'])->assertRedirect();
        $this->from(route('invoices.show', $invoice))->post($refundUrl, [
            'amount' => '0.01',
            'reason' => 'Refund without remaining customer credit.',
        ])->assertRedirect(route('invoices.show', $invoice))->assertSessionHasErrors('amount');

        $this->post($creditUrl, ['amount' => '30.00', 'reason' => 'Final service adjustment.'])->assertRedirect();
        $this->assertSame(3000, $invoice->fresh()->customerCredit(10000, 7000, 10000));
        $this->post($refundUrl, ['amount' => '30.00', 'reason' => 'Returning remaining customer credit.'])->assertRedirect();
        $this->assertSame(0, $invoice->fresh()->customerCredit(10000, 10000, 10000));
        $this->assertSame('Credited', $invoice->fresh()->paymentStatus(10000, 10000, 10000));
        $this->assertSame(10000, (int) $invoice->payments()->sum('amount_sen'));
        $this->assertSame(10000, (int) $invoice->refunds()->sum('invoice_refunds.amount_sen'));
        $this->assertSame(10000, (int) $invoice->creditNotes()->sum('amount_sen'));
        $this->assertSame(10000, $invoice->fresh()->total_sen);
        $this->get(route('invoices.edit', $invoice))->assertStatus(409);
        $this->patch(route('invoices.void', $invoice), ['reason' => 'Paid and adjusted'])->assertStatus(409);
        $this->actingAs($admin)->get(route('dashboard'))->assertOk()
            ->assertViewHas('outstandingCount', 0)->assertViewHas('outstandingSen', 0);

        $this->actingAs($client)->get(route('client.invoices.show', $invoice))->assertOk()
            ->assertSeeText('Credited')->assertSeeText('CN-')->assertSeeText('REF-')
            ->assertSeeText('Customer credit: RM 0.00');
    }

    public function test_customer_payment_proofs_need_admin_approval_and_admin_can_record_proof_directly(): void
    {
        Storage::fake('local');
        [$admin, $workshop] = $this->createWorkshop();
        [$otherAdmin] = $this->createWorkshop();
        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'tenant_id' => $workshop->id,
            'status' => User::STATUS_ACTIVE,
        ]);
        $unlinkedClient = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'tenant_id' => $workshop->id,
            'status' => User::STATUS_ACTIVE,
        ]);
        [$customer, $invoice] = $workshop->run(function () use ($client) {
            $customer = Customer::create([
                'name' => 'QR Customer',
                'email' => 'qr-customer@example.test',
                'portal_user_id' => $client->id,
            ]);
            $invoice = Invoice::create([
                'customer_id' => $customer->id,
                'workshop_name' => 'QR Workshop',
                'customer_name' => $customer->name,
                'items' => [['description' => 'Service', 'quantity' => 1, 'unit_price_sen' => 2510, 'total_sen' => 2510]],
                'total_sen' => 2510,
            ]);

            return [$customer, $invoice];
        });

        $this->actingAs($admin)->post(route('payment-qrs.store'), [
            'category' => 'bank',
            'label' => 'Workshop Bank QR',
            'image' => UploadedFile::fake()->image('bank.png'),
        ])->assertRedirect(route('payment-qrs.index'));
        $qr = WorkshopPaymentQr::query()->sole();
        $this->get(route('payment-qrs.index'))->assertOk()->assertSeeText('Workshop Bank QR');
        $this->actingAs($client)->get(route('client.invoices.index'))->assertOk()->assertSeeText($invoice->number());
        $this->get(route('client.invoices.show', $invoice))->assertOk()->assertSeeText('Workshop Bank QR');
        $this->get(route('client.payment-qrs.image', $qr))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->actingAs($unlinkedClient)->get(route('client.invoices.show', $invoice))->assertNotFound();

        $proof = UploadedFile::fake()->image('transfer-proof.png');
        $proofContents = file_get_contents($proof->getRealPath());
        $show = route('client.invoices.show', $invoice);
        $submit = route('client.invoices.payment-submissions.store', $invoice);
        $this->actingAs($client)->from($show)->post($submit, [
            'amount' => '10.05',
            'workshop_payment_qr_id' => $qr->id,
            'reference' => 'BANK-QR-123',
            'proof' => $proof,
        ])->assertRedirect($show);

        $submission = InvoicePaymentSubmission::query()->sole();
        $this->assertSame(InvoicePaymentSubmission::STATUS_PENDING, $submission->status);
        $this->assertSame(0, $invoice->payments()->count());
        $this->assertSame($proofContents, Crypt::decryptString(Storage::disk('local')->get($submission->proof_path)));
        $this->get(route('client.invoices.show', $invoice))->assertOk()->assertSeeText('Pending');
        $this->actingAs($admin)->get(route('payment-submissions.index'))->assertOk()
            ->assertSeeText($invoice->number())->assertSeeText('BANK-QR-123');
        $this->get(route('invoices.index'))->assertOk()->assertViewHas('pendingPaymentProofs', 1);
        $this->actingAs($client)->get(route('client.invoices.payment-submissions.proof', [$invoice, $submission]))
            ->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="payment-proof.png"')
            ->assertContent($proofContents);
        $this->actingAs($otherAdmin)->get(route('invoices.payment-submissions.proof', [$invoice, $submission]))
            ->assertNotFound();

        $this->actingAs($admin)->get(route('invoices.edit', $invoice))->assertStatus(409);
        $this->patch(route('invoices.void', $invoice), ['reason' => 'Pending proof'])->assertStatus(409);
        $this->actingAs($client)->from($show)->post($submit, [
            'amount' => '15.06',
            'workshop_payment_qr_id' => $qr->id,
            'proof' => UploadedFile::fake()->image('excess-proof.png'),
        ])->assertRedirect($show)->assertSessionHasErrors('amount');

        $this->actingAs($admin)->post(route('invoices.payment-submissions.approve', [$invoice, $submission]))
            ->assertRedirect(route('invoices.show', $invoice));
        $submission->refresh();
        $this->assertSame(InvoicePaymentSubmission::STATUS_APPROVED, $submission->status);
        $this->assertSame(1005, $invoice->payments()->sole()->amount_sen);
        $this->get(route('payment-submissions.index'))->assertOk()->assertSeeText('No payment proofs are waiting for review.');

        $this->actingAs($client)->post($submit, [
            'amount' => '5.00',
            'workshop_payment_qr_id' => $qr->id,
            'proof' => UploadedFile::fake()->image('rejected-proof.png'),
        ])->assertRedirect($show);
        $rejected = InvoicePaymentSubmission::query()->latest('id')->firstOrFail();
        $this->actingAs($admin)->post(route('invoices.payment-submissions.reject', [$invoice, $rejected]), [
            'reason' => 'Transfer amount could not be confirmed.',
        ])->assertRedirect(route('invoices.show', $invoice));
        $rejected->refresh();
        $this->assertSame(InvoicePaymentSubmission::STATUS_REJECTED, $rejected->status);
        $this->assertSame('Transfer amount could not be confirmed.', $rejected->review_note);
        $this->assertSame(1005, (int) $invoice->payments()->sum('amount_sen'));

        $adminProof = UploadedFile::fake()->image('admin-proof.png');
        $this->post(route('invoices.payment-submissions.store', $invoice), [
            'amount' => '15.05',
            'workshop_payment_qr_id' => $qr->id,
            'reference' => 'ADMIN-QR-456',
            'proof' => $adminProof,
        ])->assertRedirect(route('invoices.show', $invoice));
        $this->assertSame([1005, 1505], $invoice->payments()->orderBy('id')->pluck('amount_sen')->all());
        $this->assertSame(InvoicePaymentSubmission::STATUS_APPROVED, InvoicePaymentSubmission::query()->latest('id')->value('status'));
        $this->assertSame(0, $invoice->fresh()->balanceDue((int) $invoice->payments()->sum('amount_sen')));

        $this->patch(route('payment-qrs.active', $qr), ['active' => '0'])->assertRedirect(route('payment-qrs.index'));
        $this->actingAs($client)->get(route('client.payment-qrs.image', $qr))->assertNotFound();
        $this->actingAs($otherAdmin)->get(route('payment-qrs.image', $qr))->assertNotFound();
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
