<?php

namespace App\Http\Controllers;

use App\Models\Calendar;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['status' => ['nullable', 'in:overdue']]);
        $overdueOnly = ($filters['status'] ?? null) === 'overdue';
        $today = CarbonImmutable::today(Calendar::TIMEZONE)->toDateString();

        return view('invoices.index', [
            'invoices' => Invoice::withSum('payments as paid_sen', 'amount_sen')
                ->when($overdueOnly, fn ($query) => $query->overdue($today)->orderBy('due_date'))
                ->latest('id')->paginate(15)->appends($overdueOnly ? ['status' => 'overdue'] : []),
            'overdueOnly' => $overdueOnly,
            'today' => $today,
        ]);
    }

    public function create(): View
    {
        return view('invoices.create', [
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name', 'email']),
            'vehicles' => Vehicle::query()->orderBy('plate_number')->get(['id', 'customer_id', 'plate_number']),
            'today' => CarbonImmutable::today(Calendar::TIMEZONE)->toDateString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'min:1'],
            'vehicle_id' => ['nullable', 'integer', 'min:1'],
            'due_date' => ['bail', 'nullable', 'date_format:Y-m-d', 'after_or_equal:'.CarbonImmutable::today(Calendar::TIMEZONE)->toDateString()],
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
                $unitPrice = Invoice::toSen((string) $item['unit_price']);
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
                'due_date' => $validated['due_date'] ?? null,
                'items' => $items,
                'total_sen' => array_sum(array_column($items, 'total_sen')),
            ]);
        });

        return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice created.');
    }

    public function show(string $invoice): View
    {
        $invoice = Invoice::withSum('payments as paid_sen', 'amount_sen')->findOrFail($invoice);

        return view('invoices.show', [
            'invoice' => $invoice,
            'payments' => $invoice->payments()->latest('id')->paginate(15),
            'today' => CarbonImmutable::today(Calendar::TIMEZONE)->toDateString(),
        ]);
    }

    public function receipt(string $invoice, string $payment): Response
    {
        $invoice = Invoice::query()->findOrFail($invoice);
        $payment = $invoice->payments()->findOrFail($payment);

        return response()->view('invoices.receipt', compact('invoice', 'payment'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function recordPayment(Request $request, string $invoice): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'regex:/\A[0-9]{1,11}(?:\.[0-9]{1,2})?\z/'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);
        $amount = Invoice::toSen((string) $data['amount']);
        if ($amount === 0) {
            throw ValidationException::withMessages(['amount' => 'Enter a payment greater than zero.']);
        }

        DB::connection('tenant')->transaction(function () use ($invoice, $request, $data, $amount) {
            $record = Invoice::query()->lockForUpdate()->findOrFail($invoice);
            $paid = (int) $record->payments()->sum('amount_sen');
            if ($amount > $record->total_sen - $paid) {
                throw ValidationException::withMessages(['amount' => 'The payment exceeds the outstanding balance.']);
            }

            $record->payments()->create([
                'amount_sen' => $amount,
                'reference' => $data['reference'] ?? null,
                'recorded_by_user_id' => $request->user()->id,
            ]);
        }, 3);

        return redirect()->route('invoices.show', $invoice)->with('success', 'Payment recorded.');
    }
}
