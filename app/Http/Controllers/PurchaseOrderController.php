<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function index(): View
    {
        return view('purchase-orders.index', [
            'purchaseOrders' => PurchaseOrder::withCount('items')
                ->latest('id')->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('purchase-orders.create', [
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(),
            'inventoryItems' => InventoryItem::query()->orderBy('sku')->get(['id', 'sku', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*' => ['required', 'array:inventory_item_id,quantity'],
            'items.*.inventory_item_id' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:'.InventoryItem::MAX_QUANTITY],
        ]);
        $createdBy = $request->user()->id;

        $order = DB::connection('tenant')->transaction(function () use ($validated, $createdBy) {
            $supplier = Supplier::query()->lockForUpdate()->find($validated['supplier_id']);
            if (! $supplier || ! $supplier->is_active) {
                throw ValidationException::withMessages(['supplier_id' => 'Select an active supplier from this workshop.']);
            }

            $itemIds = array_map(fn ($item) => (int) $item['inventory_item_id'], $validated['items']);
            $inventoryItems = InventoryItem::query()->whereIn('id', $itemIds)
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($inventoryItems->count() !== count($itemIds)) {
                throw ValidationException::withMessages(['items' => 'Select inventory items from this workshop.']);
            }

            $order = PurchaseOrder::create([
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->name,
                'created_by_user_id' => $createdBy,
                'status' => PurchaseOrder::STATUS_ORDERED,
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($validated['items'] as $line) {
                $item = $inventoryItems->get((int) $line['inventory_item_id']);
                $order->items()->create([
                    'inventory_item_id' => $item->id,
                    'item_sku' => $item->sku,
                    'item_name' => $item->name,
                    'quantity' => (int) $line['quantity'],
                ]);
            }

            return $order;
        }, 3);

        return redirect()->route('purchase-orders.show', $order)->with('success', 'Purchase order created. Stock is added when goods are received.');
    }

    public function show(string $purchaseOrder): View
    {
        return view('purchase-orders.show', [
            'purchaseOrder' => PurchaseOrder::with('items.movements')->findOrFail($purchaseOrder),
        ]);
    }

    public function receive(Request $request, string $purchaseOrder): RedirectResponse
    {
        $validated = $request->validate([
            'quantities' => ['required', 'array'],
            'quantities.*' => ['required', 'integer', 'min:0', 'max:'.InventoryItem::MAX_QUANTITY],
        ]);

        $order = DB::connection('tenant')->transaction(function () use ($purchaseOrder, $validated, $request) {
            $order = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrder);
            if (! $order->canReceive()) {
                throw ValidationException::withMessages(['status' => 'This purchase order cannot receive any more stock.']);
            }

            $lines = $order->items()->orderBy('inventory_item_id')->orderBy('id')->lockForUpdate()->get();
            $expectedIds = $lines->pluck('id')->map(fn ($id) => (int) $id)->all();
            $receivedIds = array_map('intval', array_keys($validated['quantities']));
            sort($expectedIds);
            sort($receivedIds);
            if ($expectedIds !== $receivedIds) {
                throw ValidationException::withMessages(['quantities' => 'Submit a receipt quantity for each item in this order.']);
            }

            if (array_sum(array_map('intval', $validated['quantities'])) < 1) {
                throw ValidationException::withMessages(['quantities' => 'Enter at least one quantity to receive.']);
            }

            foreach ($lines as $line) {
                $quantity = (int) $validated['quantities'][$line->id];
                if ($quantity === 0) {
                    continue;
                }

                if ($quantity > $line->quantity - $line->received_quantity) {
                    throw ValidationException::withMessages([
                        "quantities.{$line->id}" => 'The received quantity exceeds the amount still outstanding.',
                    ]);
                }

                $item = InventoryItem::query()->lockForUpdate()->findOrFail($line->inventory_item_id);
                $balance = $item->quantity + $quantity;
                if ($balance > InventoryItem::MAX_QUANTITY) {
                    throw ValidationException::withMessages([
                        "quantities.{$line->id}" => 'Receiving this quantity would exceed the inventory stock limit.',
                    ]);
                }

                $item->quantity = $balance;
                $item->save();
                $item->movements()->create([
                    'purchase_order_item_id' => $line->id,
                    'quantity_change' => $quantity,
                    'balance_after' => $balance,
                    'recorded_by_user_id' => $request->user()->id,
                    'note' => "Received purchase order #{$order->id}",
                ]);
                $line->received_quantity += $quantity;
                $line->save();
            }

            $fullyReceived = $lines->every(fn (PurchaseOrderItem $line) => $line->received_quantity === $line->quantity);
            $order->update([
                'status' => $fullyReceived ? PurchaseOrder::STATUS_RECEIVED : PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
                'received_at' => $fullyReceived ? now() : null,
            ]);

            return $order;
        }, 3);

        return redirect()->route('purchase-orders.show', $order)->with('success', 'Received stock was added to inventory.');
    }

    public function cancel(string $purchaseOrder): RedirectResponse
    {
        DB::connection('tenant')->transaction(function () use ($purchaseOrder) {
            $order = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrder);
            if ($order->status !== PurchaseOrder::STATUS_ORDERED
                || $order->items()->where('received_quantity', '>', 0)->exists()) {
                throw ValidationException::withMessages([
                    'status' => 'Only an order with no received stock can be cancelled.',
                ]);
            }

            $order->update(['status' => PurchaseOrder::STATUS_CANCELLED]);
        }, 3);

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('success', 'Purchase order cancelled.');
    }
}
