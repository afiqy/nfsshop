<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function __construct()
    {
        $this->middleware(function (Request $request, \Closure $next) {
            abort_unless(tenancy()->initialized && $request->user()->tenant_id === tenant('id'), 403);

            return $next($request);
        });
    }

    public function index(): View
    {
        return view('invoices.index', ['invoices' => Invoice::query()->latest('id')->paginate(15)]);
    }

    public function create(): View
    {
        return view('invoices.create', [
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name', 'email']),
            'vehicles' => Vehicle::query()->orderBy('plate_number')->get(['id', 'customer_id', 'plate_number']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'min:1'],
            'vehicle_id' => ['nullable', 'integer', 'min:1'],
            'items' => ['required', 'array', 'min:1', 'max:'.Invoice::MAX_ITEMS],
            'items.*' => ['required', 'array:description,quantity,unit_price'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'items.*.unit_price' => ['required', 'regex:/\A[0-9]{1,6}(?:\.[0-9]{1,2})?\z/'],
        ]);

        $workshopName = tenant('workshop_name') ?: ($request->user()->workshop_name ?: tenant('id'));

        $invoice = DB::connection('tenant')->transaction(function () use ($validated, $workshopName) {
            $customer = Customer::query()->lockForUpdate()->find($validated['customer_id']);

            if (! $customer) {
                throw ValidationException::withMessages(['customer_id' => 'Select a customer from this workshop.']);
            }

            $vehicle = null;
            if (isset($validated['vehicle_id'])) {
                $vehicle = $customer->vehicles()->lockForUpdate()->find($validated['vehicle_id']);
                if (! $vehicle) {
                    throw ValidationException::withMessages(['vehicle_id' => 'Select a vehicle belonging to this customer.']);
                }
            }

            $items = [];
            foreach ($validated['items'] as $item) {
                [$ringgit, $sen] = array_pad(explode('.', (string) $item['unit_price'], 2), 2, '');
                $unitPrice = (int) $ringgit * 100 + (int) str_pad($sen, 2, '0');
                $quantity = (int) $item['quantity'];
                $items[] = [
                    'description' => $item['description'],
                    'quantity' => $quantity,
                    'unit_price_sen' => $unitPrice,
                    'total_sen' => $quantity * $unitPrice,
                ];
            }

            return Invoice::create([
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle?->id,
                'workshop_name' => $workshopName,
                'customer_name' => $customer->name,
                'customer_address' => $customer->address,
                'vehicle_plate' => $vehicle?->plate_number,
                'items' => $items,
                'total_sen' => array_sum(array_column($items, 'total_sen')),
            ]);
        });

        return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice created.');
    }

    public function show(string $invoice): View
    {
        return view('invoices.show', ['invoice' => Invoice::query()->findOrFail($invoice)]);
    }
}
