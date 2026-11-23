<?php

namespace Tests\Feature;

use App\Models\AccountSubscription;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceRefund;
use App\Models\JobCard;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkshopService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkshopExportTest extends TestCase
{
    use DatabaseMigrations;

    private array $workshops = [];

    public function runDatabaseMigrations(): void
    {
        $this->artisan('migrate:fresh');
        RefreshDatabaseState::$migrated = false;
    }

    public function test_record_exports_respect_filters_permissions_tenants_and_spreadsheet_safety(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        [$other] = $this->createWorkshop();
        [$basic] = $this->createWorkshop('basic');
        $mechanic = User::factory()->create(['role' => User::ROLE_MECHANIC, 'tenant_id' => $workshop->id]);
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'tenant_id' => $workshop->id]);
        $workshop->run(function () use ($admin, $mechanic) {
            $customer = Customer::create(['name' => '=Private customer', 'email' => 'private@example.test']);
            $overdue = $this->invoice($customer, '2026-01-01');
            $this->invoice($customer, null);
            $payment = $overdue->payments()->create(['amount_sen' => 100, 'recorded_by_user_id' => $admin->id,
                'reference' => "\u{00A0}\t=1+1", 'payment_method' => 'cash']);
            InvoiceRefund::create(['invoice_payment_id' => $payment->id, 'amount_sen' => 20, 'reason' => 'Refund', 'refunded_by_user_id' => $admin->id]);
            JobCard::create(['customer_id' => $customer->id, 'description' => '=Sensitive work', 'status' => JobCard::OPEN,
                'due_date' => '2026-01-01', 'assigned_mechanic_id' => $mechanic->id]);
            JobCard::create(['customer_id' => $customer->id, 'description' => 'Completed 😀', 'status' => JobCard::COMPLETED,
                'due_date' => '2026-01-01']);
        });
        $this->actingAs($admin)->get(route('invoices.index'))->assertOk()
            ->assertSee(route('exports.index', ['type' => 'payments', 'format' => 'csv']));
        $this->get(route('job-cards.index', ['filter' => 'overdue']))->assertOk()
            ->assertSee('name="filter" value="overdue"', false);
        $invoiceUrl = route('exports.index', ['type' => 'invoices', 'format' => 'csv', 'status' => 'overdue']);
        $response = $this->actingAs($admin)->get($invoiceUrl)->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Content-Type-Options', 'nosniff');
        $invoices = $this->csv($response->streamedContent());
        $this->assertCount(2, $invoices);
        $this->assertSame("'=Private customer", $invoices[1][3]);
        $this->assertSame('920', $invoices[1][9]);
        $payments = $this->csv($this->get(route('exports.index', ['type' => 'payments', 'format' => 'csv']))->assertOk()->streamedContent());
        $this->assertSame("'\u{00A0}\t=1+1", $payments[1][5]);
        $this->assertSame('100', $payments[1][6]);
        $this->assertSame('20', $payments[1][7]);
        $jobs = $this->csv($this->get(route('exports.index', ['type' => 'jobs', 'format' => 'csv', 'filter' => 'overdue']))->assertOk()->streamedContent());
        $this->assertCount(2, $jobs);
        $this->assertSame("'=Sensitive work", $jobs[1][7]);
        $this->assertSame($mechanic->name, $jobs[1][6]);
        $this->get(route('exports.index', ['type' => 'jobs', 'format' => 'pdf']))
            ->assertUnprocessable()->assertSee('characters not supported');
        foreach (['محمد', 'தமிழ்'] as $name) {
            $workshop->run(fn () => JobCard::where('status', JobCard::COMPLETED)->update(['description' => $name]));
            $this->get(route('exports.index', ['type' => 'jobs', 'format' => 'pdf']))
                ->assertUnprocessable()->assertSee('cannot be rendered reliably');
            $csv = $this->get(route('exports.index', ['type' => 'jobs', 'format' => 'csv']))->assertOk()->streamedContent();
            $this->assertStringContainsString($name, $csv);
        }
        $this->get(route('exports.index', ['type' => 'jobs', 'format' => 'xlsx']))->assertNotFound();
        $this->get(route('exports.index', ['type' => 'jobs', 'format' => 'pdf', 'batch' => 0]))->assertSessionHasErrors('batch');
        $this->get(route('exports.index', ['type' => 'jobs', 'format' => 'csv', 'status' => 'overdue']))->assertSessionHasErrors('status');
        $this->get(route('exports.index', ['type' => 'jobs', 'format' => 'csv', 'batch' => 2]))->assertSessionHasErrors('batch');
        $otherCsv = $this->actingAs($other)->get($invoiceUrl)->assertOk()->streamedContent();
        $this->assertStringNotContainsString('Private customer', $otherCsv);
        $otherReport = $this->get(route('reports.export', ['format' => 'csv']))->assertOk()->streamedContent();
        $this->assertStringNotContainsString('Private customer', $otherReport);
        foreach (['payments', 'jobs'] as $type) {
            $otherCsv = $this->get(route('exports.index', ['type' => $type, 'format' => 'csv']))->assertOk()->streamedContent();
            $this->assertStringNotContainsString('Private customer', $otherCsv);
            $this->assertStringNotContainsString('Sensitive work', $otherCsv);
        }
        foreach ([$mechanic, $client] as $user) {
            $this->actingAs($user)->get($invoiceUrl)->assertForbidden();
            $this->get(route('reports.export', ['format' => 'csv']))->assertForbidden();
            $this->get(route('exports.index', ['type' => 'jobs', 'format' => 'pdf']))->assertForbidden();
            $this->get(route('exports.index', ['type' => 'payments', 'format' => 'pdf']))->assertForbidden();
        }
        $this->actingAs($basic)->get(route('exports.index', ['type' => 'invoices', 'format' => 'csv']))->assertOk();
        $this->actingAs($basic)->get(route('reports.export', ['format' => 'csv']))->assertForbidden();
        AccountSubscription::query()->where('user_id', $admin->id)->update(['current_period_ends_at' => now()->subDay()]);
        $this->actingAs($admin)->get($invoiceUrl)->assertRedirect(route('workshop.subscription'));
    }

    public function test_report_exports_include_all_sections_and_all_pages_and_surface_invalid_pricing(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        $invoice = $workshop->run(function () {
            for ($n = 1; $n <= 26; $n++) {
                $customer = Customer::create(['name' => 'Customer '.$n, 'email' => "export{$n}@example.test"]);
                $service = WorkshopService::create(['name' => 'Service '.$n, 'price_sen' => 1000, 'is_active' => true]);
                $invoice = Invoice::create(['customer_id' => $customer->id, 'customer_name' => $customer->name, 'workshop_name' => 'Workshop',
                    'items' => [['description' => 'Work', 'quantity' => 1, 'unit_price_sen' => 1000, 'total_sen' => 1000,
                        'service_id' => $service->id, 'service_name' => $service->name]],
                    'total_sen' => 1000, 'status' => Invoice::STATUS_ISSUED]);
                $invoice->payments()->create(['amount_sen' => 100, 'recorded_by_user_id' => 1]);
            }

            return $invoice;
        });
        $csv = $this->actingAs($admin)->get(route('reports.export', ['format' => 'csv', 'services_page' => 2]))->assertOk()->streamedContent();
        $this->assertStringContainsString('Service 1 (#1)', $csv);
        $this->assertStringContainsString('Service 26 (#26)', $csv);
        $this->assertStringContainsString('Customer 26', $csv);
        $this->assertStringContainsString('26000', $csv);
        $this->assertStringContainsString('Gross service', $csv);
        $this->assertStringContainsString('Net service', $csv);
        $this->get(route('reports.export', ['format' => 'pdf']))->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')->assertSee('%PDF-', false);
        $workshop->run(fn () => $invoice->update(['pricing' => ['tax_mode' => 'exclusive']]));
        $invalid = $this->get(route('reports.export', ['format' => 'csv']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Unavailable: Invoice', $invalid);
        $this->assertStringNotContainsString('Net service,Total', $invalid);
    }

    public function test_pdf_downloads_are_batched_and_csv_contains_all_records(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        $workshop->run(function () {
            $customer = Customer::create(['name' => '陈建明', 'email' => 'batch@example.test']);
            for ($n = 1; $n <= 301; $n++) {
                $this->invoice($customer, null);
            }
        });
        $this->actingAs($admin);
        $csv = $this->csv($this->get(route('exports.index', ['type' => 'invoices', 'format' => 'csv']))->assertOk()->streamedContent());
        $this->assertCount(302, $csv);
        foreach ([1, 2, 3] as $batch) {
            $this->get(route('exports.index', ['type' => 'invoices', 'format' => 'pdf', 'batch' => $batch]))
                ->assertOk()->assertHeader('Content-Type', 'application/pdf')
                ->assertHeader('Content-Disposition', 'attachment; filename="invoices-batch-'.$batch.'.pdf"')
                ->assertSee('%PDF-', false)->assertSee('DroidSansFallback', false);
        }
        $this->get(route('exports.index', ['type' => 'invoices', 'format' => 'pdf', 'batch' => 4]))->assertNotFound();
    }

    private function csv(string $content): array
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);
        $rows = [];
        while (($row = fgetcsv($stream, null, ',', '"', '')) !== false) {
            $rows[] = $row;
        }
        fclose($stream);

        return $rows;
    }

    private function invoice(Customer $customer, ?string $due): Invoice
    {
        return Invoice::create([
            'customer_id' => $customer->id, 'customer_name' => $customer->name, 'workshop_name' => 'Workshop',
            'items' => [['description' => 'Work', 'quantity' => 1, 'unit_price_sen' => 1000, 'total_sen' => 1000]],
            'total_sen' => 1000, 'status' => Invoice::STATUS_ISSUED, 'due_date' => $due,
        ]);
    }

    private function createWorkshop(string $tier = 'pro'): array
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $workshop = Tenant::create(['id' => 'export-test-'.Str::lower(Str::random(12)), 'account_owner_id' => $admin->id, 'workshop_name' => 'Workshop']);
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
