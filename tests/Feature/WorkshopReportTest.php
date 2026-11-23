<?php

namespace Tests\Feature;

use App\Models\AccountSubscription;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceCreditNote;
use App\Models\InvoiceRefund;
use App\Models\JobCard;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkshopService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkshopReportTest extends TestCase
{
    use DatabaseMigrations;

    private array $workshops = [];

    public function runDatabaseMigrations(): void
    {
        // Tenant provisioning commits MySQL transactions; legacy migrations cannot be rolled back safely.
        $this->artisan('migrate:fresh');
        RefreshDatabaseState::$migrated = false;
    }

    public function test_report_separates_period_cash_credits_service_and_staff_with_malaysia_time_boundaries(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        $mechanic = User::factory()->create(['role' => User::ROLE_MECHANIC, 'tenant_id' => $workshop->id, 'name' => 'Workshop mechanic']);
        $workshop->run(function () use ($admin, $mechanic) {
            $a = Customer::create(['name' => 'Earlier customer', 'email' => 'earlier@example.test']);
            $b = Customer::create(['name' => 'Current customer', 'email' => 'current@example.test']);
            $service = WorkshopService::create(['name' => 'Oil change', 'price_sen' => 10000, 'is_active' => true]);
            $early = $this->invoice($a, 10000, '2026-02-28 15:59:59', [
                ['description' => 'Legacy', 'quantity' => 1, 'unit_price_sen' => 10000, 'total_sen' => 10000],
            ]);
            $current = $this->invoice($b, 15900, '2026-02-28 16:00:00', [
                ['description' => 'Oil', 'quantity' => 1, 'unit_price_sen' => 10600, 'total_sen' => 10600, 'service_id' => $service->id, 'service_name' => 'Oil change'],
                ['description' => 'Other', 'quantity' => 1, 'unit_price_sen' => 5300, 'total_sen' => 5300],
            ], ['subtotal_sen' => 15900, 'discount_rate' => 0, 'discount_sen' => 0,
                'tax_mode' => 'inclusive', 'tax_rate' => 600, 'tax_sen' => 900]);
            $this->invoice($a, 20000, '2026-03-31 16:00:00', [
                ['description' => 'April', 'quantity' => 1, 'unit_price_sen' => 20000, 'total_sen' => 20000],
            ]);
            $void = $this->invoice($a, 99999, '2026-03-05 00:00:00', [
                ['description' => 'Void', 'quantity' => 1, 'unit_price_sen' => 99999, 'total_sen' => 99999],
            ]);
            $void->update(['status' => Invoice::STATUS_VOIDED]);
            $payment1 = $this->dated($early->payments()->create(['amount_sen' => 3000, 'recorded_by_user_id' => $admin->id]), '2026-03-01 01:00:00');
            $payment2 = $this->dated($current->payments()->create(['amount_sen' => 5000, 'recorded_by_user_id' => $admin->id]), '2026-03-02 01:00:00');
            $this->dated(InvoiceRefund::create(['invoice_payment_id' => $payment1->id, 'amount_sen' => 500, 'reason' => 'Refund', 'refunded_by_user_id' => $admin->id]), '2026-03-03 01:00:00');
            $this->dated(InvoiceRefund::create(['invoice_payment_id' => $payment1->id, 'amount_sen' => 500, 'reason' => 'April refund', 'refunded_by_user_id' => $admin->id]), '2026-03-31 16:00:00');
            $this->dated(InvoiceRefund::create(['invoice_payment_id' => $payment2->id, 'amount_sen' => 1000, 'reason' => 'Refund', 'refunded_by_user_id' => $admin->id]), '2026-03-04 01:00:00');
            $this->dated(InvoiceCreditNote::create(['invoice_id' => $current->id, 'amount_sen' => 1500, 'reason' => 'Credit', 'issued_by_user_id' => $admin->id]), '2026-03-06 01:00:00');
            JobCard::create(['customer_id' => $b->id, 'description' => 'Completed', 'status' => JobCard::COMPLETED,
                'assigned_mechanic_id' => $mechanic->id, 'completed_at' => '2026-02-28 16:00:00']);
            JobCard::create(['customer_id' => $b->id, 'description' => 'Outside period', 'status' => JobCard::COMPLETED,
                'assigned_mechanic_id' => $mechanic->id, 'completed_at' => '2026-03-31 16:00:00']);
            JobCard::create(['customer_id' => $b->id, 'description' => 'Unassigned', 'status' => JobCard::COMPLETED,
                'completed_at' => '2026-03-02 00:00:00']);
        });
        $report = $this->actingAs($admin)->get(route('reports.index', ['period' => '2026-03']))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('RM 159.00')->assertSee('RM 15.00')->assertSee('RM 80.00')->assertSee('RM 65.00')
            ->assertSee('RM 100.00')->assertSee('RM 50.00')->assertSee('Workshop mechanic')->assertSee('Unassigned');
        $report->assertViewHas('invoiced', 15900)->assertViewHas('credited', 1500)
            ->assertViewHas('received', 8000)->assertViewHas('refunded', 1500)->assertViewHas('invoiceCount', 1)
            ->assertViewHas('unclassified', 5000);
        $report->assertViewHas('customers', fn ($rows) => $rows->count() === 2
            && (int) $rows[0]->net_sen === 4000 && (int) $rows[1]->net_sen === 2500);
        $report->assertViewHas('staff', fn ($rows) => $rows->count() === 2 && $rows->sum('completed_count') === 2);
        $this->get(route('reports.index', ['period' => '2026']))->assertOk()->assertViewHas('invoiced', 45900);
        $this->get(route('reports.index', ['period' => '2026-04']))->assertOk()
            ->assertViewHas('received', 0)->assertViewHas('refunded', 500)->assertSee('-RM 5.00');
        $csv = $this->get(route('reports.export', ['format' => 'csv', 'period' => '2026-03']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Oil change (#1)', $csv);
        $this->assertStringContainsString('Workshop mechanic', $csv);
        $this->assertStringContainsString('Unclassified', $csv);
        $this->assertStringContainsString('6500', $csv);
        $this->get(route('reports.index', ['period' => '2026-13']))->assertSessionHasErrors('period');
    }

    public function test_service_tags_are_validated_preserved_and_shown_in_gross_reports(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        [$customer, $service] = $workshop->run(fn () => [
            Customer::create(['name' => 'Customer', 'email' => 'service@example.test']),
            WorkshopService::create(['name' => 'Service name', 'price_sen' => 1000, 'is_active' => true]),
        ]);
        $data = ['customer_id' => $customer->id, 'discount_percent' => '10', 'tax_percent' => '6', 'tax_mode' => 'exclusive', 'items' => [[
            'description' => 'Actual work', 'quantity' => 2, 'unit_price' => '10.00', 'service_id' => $service->id,
        ]]];
        $this->actingAs($admin)->get(route('invoices.create'))->assertOk()->assertSee('Service name');
        $this->post(route('invoices.store'), $data)->assertRedirect();
        $invoice = $workshop->run(fn () => Invoice::sole());
        $this->assertSame('Service name', $invoice->items[0]['service_name']);
        $workshop->run(fn () => $service->update(['name' => 'Renamed', 'is_active' => false]));
        $this->get(route('invoices.edit', $invoice->id))->assertOk()->assertSee('Renamed (inactive)');
        $this->put(route('invoices.update', $invoice->id), $data)->assertRedirect();
        $this->assertSame('Service name', $workshop->run(fn () => $invoice->fresh()->items[0]['service_name']));
        $this->post(route('invoices.store'), $data)->assertSessionHasErrors('items');
        $invalid = $data;
        $invalid['items'][0]['service_id'] = 9999;
        $this->post(route('invoices.store'), $invalid)->assertSessionHasErrors('items');
        $invalid['items'][0]['service_id'] = $service->id;
        $invalid['items'][0]['inventory_item_id'] = 1;
        $this->put(route('invoices.update', $invoice->id), $invalid)->assertSessionHasErrors('items');
        $this->get(route('reports.index'))->assertOk()->assertSee('Service name')->assertSee('RM 20.00')
            ->assertViewHas('netTotals', ['invoiced_sen' => 1800, 'credited_sen' => 0]);
    }

    public function test_reports_are_entitlement_role_and_workshop_scoped(): void
    {
        [$pro, $workshop] = $this->createWorkshop('pro');
        [$basic] = $this->createWorkshop('basic');
        [$other] = $this->createWorkshop('pro');
        $mechanic = User::factory()->create(['role' => User::ROLE_MECHANIC, 'tenant_id' => $workshop->id]);
        $workshop->run(function () {
            $customer = Customer::create(['name' => 'Private customer', 'email' => 'private@example.test']);
            $invoice = $this->invoice($customer, 9999, now()->format('Y-m-d H:i:s'), [
                ['description' => 'Secret work', 'quantity' => 1, 'unit_price_sen' => 9999, 'total_sen' => 9999,
                    'service_id' => 1, 'service_name' => 'Private service'],
            ]);
            $invoice->payments()->create(['amount_sen' => 9999, 'recorded_by_user_id' => 1]);
        });
        $this->actingAs($pro)->get(route('reports.index'))->assertOk()->assertSee('Private customer')->assertSee('Private service');
        $this->actingAs($other)->get(route('reports.index'))->assertOk()->assertDontSee('Private customer')->assertDontSee('Private service')->assertDontSee('RM 99.99');
        $this->actingAs($mechanic)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($basic)->get(route('reports.index'))->assertForbidden();
        $this->get(route('dashboard'))->assertOk()->assertDontSee('href="'.route('reports.index').'"', false);
        AccountSubscription::query()->where('user_id', $pro->id)->update(['current_period_ends_at' => now()->subDay()]);
        $this->actingAs($pro)->get(route('reports.index'))->assertRedirect(route('workshop.subscription'));
    }

    public function test_reports_paginate_service_and_customer_results_without_losing_totals(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        $workshop->run(function () {
            for ($n = 1; $n <= 26; $n++) {
                $customer = Customer::create(['name' => 'Customer '.$n, 'email' => "customer{$n}@example.test"]);
                $service = WorkshopService::create(['name' => 'Service '.$n, 'price_sen' => 100, 'is_active' => true]);
                $invoice = $this->invoice($customer, 100, now()->format('Y-m-d H:i:s'), [[
                    'description' => 'Work', 'quantity' => 1, 'unit_price_sen' => 100, 'total_sen' => 100,
                    'service_id' => $service->id, 'service_name' => $service->name,
                ]]);
                $invoice->payments()->create(['amount_sen' => 100, 'recorded_by_user_id' => 1]);
            }
        });
        $this->actingAs($admin)->get(route('reports.index', ['services_page' => 2, 'customers_page' => 2]))
            ->assertOk()->assertViewHas('invoiced', 2600)->assertViewHas('received', 2600)
            ->assertViewHas('services', fn ($rows) => $rows->total() === 26 && $rows->count() === 1)
            ->assertViewHas('customers', fn ($rows) => $rows->total() === 26 && $rows->count() === 1);
        $this->get(route('reports.index', ['net_services_page' => 2]))
            ->assertOk()->assertViewHas('netServices', fn ($rows) => $rows->total() === 26 && $rows->count() === 1)
            ->assertViewHas('netTotals', ['invoiced_sen' => 2600, 'credited_sen' => 0]);
    }

    public function test_net_revenue_allocates_discounts_tax_and_credits_across_periods_without_deducting_refunds(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        $workshop->run(function () use ($admin) {
            $customer = Customer::create(['name' => 'Customer', 'email' => 'net@example.test']);
            $invoice = $this->invoice($customer, 19080, '2026-03-01 00:00:00', [
                ['description' => 'A', 'quantity' => 1, 'unit_price_sen' => 10000, 'total_sen' => 10000, 'service_id' => 1, 'service_name' => 'A'],
                ['description' => 'B', 'quantity' => 1, 'unit_price_sen' => 5000, 'total_sen' => 5000, 'service_id' => 2, 'service_name' => 'B'],
                ['description' => 'Other', 'quantity' => 1, 'unit_price_sen' => 5000, 'total_sen' => 5000],
            ], ['subtotal_sen' => 20000, 'discount_rate' => 1000, 'discount_sen' => 2000,
                'tax_mode' => 'exclusive', 'tax_rate' => 600, 'tax_sen' => 1080]);
            $payment = $this->dated($invoice->payments()->create(['amount_sen' => 10000, 'recorded_by_user_id' => $admin->id]), '2026-03-02 00:00:00');
            $this->dated(InvoiceRefund::create(['invoice_payment_id' => $payment->id, 'amount_sen' => 1000, 'reason' => 'Refund', 'refunded_by_user_id' => $admin->id]), '2026-03-03 00:00:00');
            foreach (['2026-03-04 00:00:00', '2026-04-04 00:00:00'] as $date) {
                $this->dated($invoice->creditNotes()->create(['amount_sen' => 9540, 'reason' => 'Credit', 'issued_by_user_id' => $admin->id]), $date);
            }
        });
        $this->actingAs($admin)->get(route('reports.index', ['period' => '2026-03']))->assertOk()
            ->assertViewHas('netServiceError', null)
            ->assertViewHas('netTotals', ['invoiced_sen' => 18000, 'credited_sen' => 9000])
            ->assertViewHas('netUnclassified', ['invoiced_sen' => 4500, 'credited_sen' => 2250])
            ->assertViewHas('netServices', fn ($rows) => $rows->keyBy('id')[1]['invoiced_sen'] === 9000
                && $rows->keyBy('id')[1]['credited_sen'] === 4500
                && $rows->keyBy('id')[2]['invoiced_sen'] === 4500
                && $rows->keyBy('id')[2]['credited_sen'] === 2250);
        $this->get(route('reports.index', ['period' => '2026-04']))->assertOk()
            ->assertViewHas('netTotals', ['invoiced_sen' => 0, 'credited_sen' => 9000])
            ->assertViewHas('netUnclassified', ['invoiced_sen' => 0, 'credited_sen' => 2250])
            ->assertSee('-RM 90.00');
        $this->get(route('reports.index', ['period' => '2026']))->assertOk()
            ->assertViewHas('netTotals', ['invoiced_sen' => 18000, 'credited_sen' => 18000]);
    }

    public function test_net_revenue_rounds_inclusive_tax_and_successive_credits_deterministically(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        $workshop->run(function () use ($admin) {
            $customer = Customer::create(['name' => 'Customer', 'email' => 'rounding@example.test']);
            $invoice = $this->invoice($customer, 2, '2026-02-01 00:00:00', [
                ['description' => 'A', 'quantity' => 1, 'unit_price_sen' => 1, 'total_sen' => 1, 'service_id' => 1, 'service_name' => 'A'],
                ['description' => 'B', 'quantity' => 1, 'unit_price_sen' => 1, 'total_sen' => 1, 'service_id' => 2, 'service_name' => 'B'],
                ['description' => 'Other', 'quantity' => 1, 'unit_price_sen' => 1, 'total_sen' => 1],
            ], ['subtotal_sen' => 3, 'discount_rate' => 3333, 'discount_sen' => 1,
                'tax_mode' => 'inclusive', 'tax_rate' => 10000, 'tax_sen' => 1]);
            foreach (['2026-02-02 00:00:00', '2026-03-02 00:00:00'] as $date) {
                $this->dated($invoice->creditNotes()->create(['amount_sen' => 1, 'reason' => 'Credit', 'issued_by_user_id' => $admin->id]), $date);
            }
            $this->invoice($customer, 0, '2026-03-03 00:00:00', [
                ['description' => 'Free', 'quantity' => 1, 'unit_price_sen' => 100, 'total_sen' => 100, 'service_id' => 3, 'service_name' => 'Free'],
            ], ['subtotal_sen' => 100, 'discount_rate' => 10000, 'discount_sen' => 100,
                'tax_mode' => 'exclusive', 'tax_rate' => 600, 'tax_sen' => 0]);
            $this->invoice($customer, 0, '2026-03-04 00:00:00', [
                ['description' => 'Zero', 'quantity' => 1, 'unit_price_sen' => 0, 'total_sen' => 0],
            ]);
        });
        $this->actingAs($admin)->get(route('reports.index', ['period' => '2026-03']))->assertOk()
            ->assertViewHas('netServiceError', null)
            ->assertViewHas('netTotals', ['invoiced_sen' => 0, 'credited_sen' => 1])
            ->assertViewHas('netUnclassified', ['invoiced_sen' => 0, 'credited_sen' => 1])
            ->assertViewHas('netServices', fn ($rows) => $rows->keyBy('id')[3]['invoiced_sen'] === 0);
        $this->get(route('reports.index', ['period' => '2026-02']))->assertOk()
            ->assertViewHas('netTotals', ['invoiced_sen' => 1, 'credited_sen' => 0]);
    }

    public function test_net_revenue_handles_large_amounts_and_rejects_inconsistent_data(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        $invoice = $workshop->run(function () use ($admin) {
            $customer = Customer::create(['name' => 'Customer', 'email' => 'large@example.test']);
            $item = ['description' => 'Work', 'quantity' => 999, 'unit_price_sen' => 99999999,
                'total_sen' => 99899999001, 'service_id' => 1, 'service_name' => 'Large service'];
            $subtotal = 25 * $item['total_sen'];
            $invoice = $this->invoice($customer, 2 * $subtotal, '2026-03-01 00:00:00', array_fill(0, 25, $item),
                ['subtotal_sen' => $subtotal, 'discount_rate' => 0, 'discount_sen' => 0,
                    'tax_mode' => 'exclusive', 'tax_rate' => 10000, 'tax_sen' => $subtotal]);
            $this->dated($invoice->creditNotes()->create(['amount_sen' => 2 * $subtotal, 'reason' => 'Credit', 'issued_by_user_id' => $admin->id]), '2026-03-02 00:00:00');

            return $invoice;
        });
        $subtotal = 25 * 99899999001;
        $this->actingAs($admin)->get(route('reports.index', ['period' => '2026-03']))->assertOk()
            ->assertViewHas('netTotals', ['invoiced_sen' => $subtotal, 'credited_sen' => $subtotal]);
        $workshop->run(fn () => $invoice->update(['pricing' => ['tax_mode' => 'exclusive', 'tax_rate' => 0]]));
        $this->get(route('reports.index', ['period' => '2026-03']))->assertOk()
            ->assertSee('Net service revenue is unavailable')
            ->assertViewHas('netServices', null)->assertViewHas('netTotals', null);
        $workshop->run(fn () => $invoice->update(['pricing' => ['subtotal_sen' => $subtotal, 'discount_rate' => 100,
            'discount_sen' => 0, 'tax_mode' => 'exclusive', 'tax_rate' => 10000, 'tax_sen' => $subtotal]]));
        $this->get(route('reports.index', ['period' => '2026-03']))->assertOk()
            ->assertSee('inconsistent pricing amounts')->assertViewHas('netTotals', null);
        $workshop->run(function () use ($invoice, $subtotal) {
            $invoice->update(['pricing' => ['subtotal_sen' => $subtotal, 'discount_rate' => 0, 'discount_sen' => 0,
                'tax_mode' => 'exclusive', 'tax_rate' => 10000, 'tax_sen' => $subtotal]]);
            $invoice->creditNotes()->firstOrFail()->update(['amount_sen' => 2 * $subtotal + 1]);
        });
        $this->get(route('reports.index', ['period' => '2026-03']))->assertOk()
            ->assertSee('invalid credit note amounts')->assertViewHas('netTotals', null);
        $workshop->run(fn () => $invoice->update(['items' => [['description' => 'Invalid']]]));
        Log::shouldReceive('warning')->twice();
        $this->get(route('reports.index', ['period' => '2026-03']))->assertOk()
            ->assertSee('Gross service reporting is unavailable')->assertSee('Net service revenue is unavailable')
            ->assertViewHas('netTotals', null);
    }

    public function test_net_revenue_includes_legacy_credits_at_period_boundaries_and_excludes_voided_invoices(): void
    {
        [$admin, $workshop] = $this->createWorkshop();
        $workshop->run(function () use ($admin) {
            $customer = Customer::create(['name' => 'Customer', 'email' => 'legacy@example.test']);
            $items = [['description' => 'Legacy', 'quantity' => 1, 'unit_price_sen' => 1000, 'total_sen' => 1000]];
            $old = $this->invoice($customer, 1000, '2026-02-28 15:59:59', $items);
            foreach ([['2026-02-28 16:00:00', 200], ['2026-03-31 16:00:00', 300]] as [$date, $amount]) {
                $this->dated($old->creditNotes()->create(['amount_sen' => $amount, 'reason' => 'Credit', 'issued_by_user_id' => $admin->id]), $date);
            }
            $void = $this->invoice($customer, 1000, '2026-03-01 00:00:00', $items);
            $this->dated($void->creditNotes()->create(['amount_sen' => 100, 'reason' => 'Credit', 'issued_by_user_id' => $admin->id]), '2026-03-02 00:00:00');
            $void->update(['status' => Invoice::STATUS_VOIDED]);
        });
        $this->actingAs($admin)->get(route('reports.index', ['period' => '2026-03']))->assertOk()
            ->assertViewHas('netTotals', ['invoiced_sen' => 0, 'credited_sen' => 200])
            ->assertViewHas('netUnclassified', ['invoiced_sen' => 0, 'credited_sen' => 200]);
        $this->get(route('reports.index', ['period' => '2026-04']))->assertOk()
            ->assertViewHas('netTotals', ['invoiced_sen' => 0, 'credited_sen' => 300]);
        $workshop->run(function () use ($admin) {
            $customer = Customer::firstOrFail();
            $future = $this->invoice($customer, 100, '2026-04-02 00:00:00', [
                ['description' => 'Future', 'quantity' => 1, 'unit_price_sen' => 100, 'total_sen' => 100],
            ]);
            $this->dated($future->creditNotes()->create(['amount_sen' => 10, 'reason' => 'Invalid date', 'issued_by_user_id' => $admin->id]), '2026-03-02 00:00:00');
        });
        $this->get(route('reports.index', ['period' => '2026-03']))->assertOk()
            ->assertSee('credit note dated before the invoice')->assertViewHas('netTotals', null);
    }

    private function invoice(Customer $customer, int $total, string $created, array $items, ?array $pricing = null): Invoice
    {
        return $this->dated(Invoice::create([
            'customer_id' => $customer->id, 'customer_name' => $customer->name, 'workshop_name' => 'Test Workshop',
            'items' => $items, 'pricing' => $pricing, 'total_sen' => $total, 'status' => Invoice::STATUS_ISSUED,
        ]), $created);
    }

    private function dated($model, string $date)
    {
        $model->forceFill(['created_at' => $date])->save();

        return $model;
    }

    private function createWorkshop(string $tier = 'pro'): array
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $workshop = Tenant::create(['id' => 'report-test-'.Str::lower(Str::random(12)), 'account_owner_id' => $admin->id, 'workshop_name' => 'Test Workshop']);
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
