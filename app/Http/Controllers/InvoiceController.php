<?php

namespace App\Http\Controllers;

use App\Models\Calendar;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\InvoicePaymentSubmission;
use App\Models\Vehicle;
use App\Models\WorkshopPaymentQr;
use App\Services\InvoiceLifecycleService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function __construct(private InvoiceLifecycleService $lifecycle) {}

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
            'pendingPaymentProofs' => InvoicePaymentSubmission::query()
                ->where('status', InvoicePaymentSubmission::STATUS_PENDING)->count(),
            'today' => $today,
        ]);
    }

    public function create(Request $request): View
    {
        return $this->form($request, new Invoice);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateInvoice($request);
        $this->ensureInventoryEntitlement($request, $validated['items']);
        $invoice = $this->lifecycle->save($validated, $request->user());

        return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice created.');
    }

    public function edit(Request $request, string $invoice): View
    {
        $invoice = Invoice::query()->findOrFail($invoice);
        $this->lifecycle->assertEditable($invoice);
        $this->ensureExistingInventoryEntitlement($request, $invoice);

        return $this->form($request, $invoice);
    }

    public function update(Request $request, string $invoice): RedirectResponse
    {
        $invoice = Invoice::query()->findOrFail($invoice);
        $this->lifecycle->assertEditable($invoice);
        $validated = $this->validateInvoice($request, $invoice);
        $this->ensureInventoryEntitlement($request, $validated['items']);
        $this->ensureExistingInventoryEntitlement($request, $invoice);
        $invoice = $this->lifecycle->save($validated, $request->user(), $invoice);

        return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice updated.');
    }

    public function void(Request $request, string $invoice): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $invoice = Invoice::query()->findOrFail($invoice);
        $this->lifecycle->void($invoice, $data['reason'], $request->user());

        return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice voided.');
    }

    public function show(string $invoice): View
    {
        $invoice = Invoice::withSum('payments as paid_sen', 'amount_sen')->findOrFail($invoice);

        return view('invoices.show', [
            'invoice' => $invoice,
            'payments' => $invoice->payments()->latest('id')->paginate(15),
            'paymentSubmissions' => $invoice->paymentSubmissions()->latest('id')->paginate(10, ['*'], 'submissions_page'),
            'paymentQrs' => WorkshopPaymentQr::query()->where('is_active', true)
                ->orderBy('category')->orderBy('label')->get(),
            'today' => CarbonImmutable::today(Calendar::TIMEZONE)->toDateString(),
            'canEdit' => $invoice->status === Invoice::STATUS_ISSUED
                && ! $invoice->payments()->exists(),
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

        $record = Invoice::query()->findOrFail($invoice);
        DB::connection('tenant')->transaction(function () use ($record, $request, $data, $amount) {
            $record = Invoice::query()->lockForUpdate()->findOrFail($record->id);
            if ($record->status !== Invoice::STATUS_ISSUED) {
                throw ValidationException::withMessages(['amount' => 'Payments cannot be recorded on a voided invoice.']);
            }

            $paid = (int) $record->payments()->sum('amount_sen');
            if ($amount > $record->balanceDue($paid)) {
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

    private function form(Request $request, Invoice $invoice): View
    {
        $canManageInventory = $request->attributes->get('account_subscription')?->canManageInventory() ?? false;
        $initialItems = $invoice->exists
            ? array_map(fn (array $item) => $item + [
                'unit_price' => sprintf(
                    '%d.%02d',
                    intdiv((int) $item['unit_price_sen'], 100),
                    (int) $item['unit_price_sen'] % 100
                ),
            ], $invoice->items ?? [])
            : [];

        return view('invoices.create', [
            'invoice' => $invoice,
            'initialItems' => $initialItems,
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name', 'email']),
            'vehicles' => Vehicle::query()->orderBy('plate_number')->get(['id', 'customer_id', 'plate_number']),
            'canManageInventory' => $canManageInventory,
            'inventoryItems' => $canManageInventory
                ? InventoryItem::query()->orderBy('sku')->get(['id', 'sku', 'name', 'quantity'])
                : collect(),
            'today' => CarbonImmutable::today(Calendar::TIMEZONE)->toDateString(),
        ]);
    }

    private function validateInvoice(Request $request, ?Invoice $invoice = null): array
    {
        $dueDateRules = ['bail', 'nullable', 'date_format:Y-m-d'];
        if (! $invoice) {
            $dueDateRules[] = 'after_or_equal:'.CarbonImmutable::today(Calendar::TIMEZONE)->toDateString();
        }

        return $request->validate([
            'customer_id' => ['required', 'integer', 'min:1'],
            'vehicle_id' => ['nullable', 'integer', 'min:1'],
            'due_date' => $dueDateRules,
            'items' => ['required', 'array', 'min:1', 'max:'.Invoice::MAX_ITEMS],
            'items.*' => ['required', 'array:description,quantity,unit_price,inventory_item_id'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'items.*.unit_price' => ['required', 'regex:/\A[0-9]{1,6}(?:\.[0-9]{1,2})?\z/'],
            'items.*.inventory_item_id' => ['nullable', 'integer', 'min:1'],
        ]);
    }

    private function ensureInventoryEntitlement(Request $request, array $items): void
    {
        $canManageInventory = $request->attributes->get('account_subscription')?->canManageInventory() ?? false;
        $inventoryItemIds = collect($items)->pluck('inventory_item_id')->filter();
        if ($inventoryItemIds->isNotEmpty() && ! $canManageInventory) {
            throw ValidationException::withMessages([
                'items' => 'Inventory-linked invoice items require an active inventory entitlement.',
            ]);
        }
    }

    private function ensureExistingInventoryEntitlement(Request $request, Invoice $invoice): void
    {
        $hasLinkedInventory = collect($invoice->items ?? [])
            ->contains(fn (array $item) => isset($item['inventory_item_id']));
        if ($hasLinkedInventory
            && ! ($request->attributes->get('account_subscription')?->canManageInventory() ?? false)) {
            abort(403, 'This invoice contains inventory-linked items. An eligible subscription is required to edit it.');
        }
    }
}
