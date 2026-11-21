<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(): View
    {
        return view('inventory.index', ['items' => InventoryItem::query()->orderBy('sku')->paginate(15)]);
    }

    public function create(): View
    {
        return view('inventory.form', ['item' => new InventoryItem]);
    }

    public function store(Request $request): RedirectResponse
    {
        $item = InventoryItem::create($this->validateItem($request));

        return redirect()->route('inventory.show', $item)->with('success', 'Item added. Record stock in to set its opening balance.');
    }

    public function show(string $inventory): View
    {
        $item = InventoryItem::findOrFail($inventory);

        return view('inventory.show', [
            'item' => $item,
            'movements' => $item->movements()->with(['purchaseOrderItem.purchaseOrder', 'invoice'])->latest('id')->paginate(15),
        ]);
    }

    public function edit(string $inventory): View
    {
        return view('inventory.form', ['item' => InventoryItem::findOrFail($inventory)]);
    }

    public function update(Request $request, string $inventory): RedirectResponse
    {
        $item = InventoryItem::findOrFail($inventory);
        $item->update($this->validateItem($request, $item));

        return redirect()->route('inventory.show', $item)->with('success', 'Item updated.');
    }

    public function move(Request $request, string $inventory): RedirectResponse
    {
        $data = $request->validate([
            'direction' => ['required', Rule::in(['in', 'out'])],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.InventoryItem::MAX_QUANTITY],
            'note' => ['required', 'string', 'max:255'],
        ]);
        $change = $data['direction'] === 'in' ? (int) $data['quantity'] : -(int) $data['quantity'];
        $actor = $request->user()->id;

        $item = DB::connection('tenant')->transaction(function () use ($inventory, $change, $actor, $data) {
            $item = InventoryItem::query()->lockForUpdate()->findOrFail($inventory);
            $balance = $item->quantity + $change;

            if ($balance < 0 || $balance > InventoryItem::MAX_QUANTITY) {
                throw ValidationException::withMessages([
                    'quantity' => $balance < 0 ? 'Not enough stock for this movement.' : 'The resulting stock exceeds the maximum quantity.',
                ]);
            }

            $item->quantity = $balance;
            $item->save();
            $item->movements()->create([
                'quantity_change' => $change,
                'balance_after' => $balance,
                'recorded_by_user_id' => $actor,
                'note' => $data['note'],
            ]);

            return $item;
        }, 3);

        return redirect()->route('inventory.show', $item)->with('success', 'Stock movement recorded.');
    }

    private function validateItem(Request $request, ?InventoryItem $item = null): array
    {
        $sku = Rule::unique(InventoryItem::class, 'sku');
        if ($item) {
            $sku->ignore($item);
        }

        return $request->validate([
            'sku' => ['bail', 'required', 'string', 'max:64', $sku],
            'name' => ['required', 'string', 'max:255'],
            'low_stock_threshold' => ['required', 'integer', 'min:0', 'max:'.InventoryItem::MAX_QUANTITY],
        ]);
    }
}
