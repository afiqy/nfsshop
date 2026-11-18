<?php

namespace Tests\Feature;

use App\Models\AccountSubscription;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    private array $workshops = [];

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
