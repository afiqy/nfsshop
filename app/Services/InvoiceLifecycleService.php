<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\InvoicePaymentSubmission;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceLifecycleService
{
    public function assertEditable(Invoice $invoice): void
    {
        abort_unless($invoice->status === Invoice::STATUS_ISSUED, 409, 'Only issued invoices can be changed.');
        abort_if(
            $invoice->payments()->exists(),
            409,
            'An invoice with recorded payments cannot be edited. Refund and credit-note workflows are not available yet.'
        );
        abort_if(
            $invoice->paymentSubmissions()->where('status', InvoicePaymentSubmission::STATUS_PENDING)->exists(),
            409,
            'Resolve pending payment proofs before changing or voiding this invoice.'
        );
    }

    public function save(array $validated, User $actor, ?Invoice $invoice = null): Invoice
    {
        return DB::connection('tenant')->transaction(function () use ($validated, $actor, $invoice) {
            $record = $invoice
                ? Invoice::query()->lockForUpdate()->findOrFail($invoice->id)
                : new Invoice;
            if ($record->exists) {
                $this->assertEditable($record);
                if ((int) $record->customer_id !== (int) $validated['customer_id']
                    && $record->paymentSubmissions()->exists()) {
                    throw ValidationException::withMessages([
                        'customer_id' => 'The customer cannot be changed after payment proof has been submitted.',
                    ]);
                }
            }

            $customer = Customer::query()->lockForUpdate()->find($validated['customer_id']);
            if (! $customer) {
                throw ValidationException::withMessages([
                    'customer_id' => 'Select a customer from this workshop.',
                ]);
            }

            $vehicle = null;
            if (isset($validated['vehicle_id'])) {
                $vehicle = $customer->vehicles()->lockForUpdate()->find($validated['vehicle_id']);
                if (! $vehicle) {
                    throw ValidationException::withMessages([
                        'vehicle_id' => 'Select a vehicle belonging to this customer.',
                    ]);
                }
            }

            $oldDeductions = $record->exists ? $this->inventoryDeductions($record->items ?? []) : [];
            $newDeductions = [];
            $items = [];
            foreach ($validated['items'] as $item) {
                $unitPrice = Invoice::toSen((string) $item['unit_price']);
                $quantity = (int) $item['quantity'];
                $inventoryItemId = isset($item['inventory_item_id']) ? (int) $item['inventory_item_id'] : null;
                $items[] = array_filter([
                    'description' => $item['description'],
                    'quantity' => $quantity,
                    'unit_price_sen' => $unitPrice,
                    'total_sen' => $quantity * $unitPrice,
                    'inventory_item_id' => $inventoryItemId,
                    'inventory_sku' => null,
                ], fn ($value) => $value !== null);
                if ($inventoryItemId) {
                    $newDeductions[$inventoryItemId] = ($newDeductions[$inventoryItemId] ?? 0) + $quantity;
                }
            }

            $inventoryItems = $this->lockInventoryItems(array_unique(array_merge(
                array_keys($oldDeductions),
                array_keys($newDeductions)
            )));
            if ($inventoryItems->count() !== count(array_unique(array_merge(
                array_keys($oldDeductions),
                array_keys($newDeductions)
            )))) {
                throw ValidationException::withMessages([
                    'items' => 'An inventory item linked to this invoice is no longer available.',
                ]);
            }

            foreach ($items as $index => $item) {
                if (! isset($item['inventory_item_id'])) {
                    continue;
                }

                $inventoryItem = $inventoryItems->get($item['inventory_item_id']);
                $items[$index]['inventory_sku'] = $inventoryItem->sku;
            }

            foreach ($newDeductions as $inventoryItemId => $quantity) {
                $inventoryItem = $inventoryItems->get($inventoryItemId);
                $additionalDeduction = $quantity - ($oldDeductions[$inventoryItemId] ?? 0);
                if ($additionalDeduction > $inventoryItem->quantity) {
                    throw ValidationException::withMessages([
                        'items' => "Not enough stock for {$inventoryItem->sku}.",
                    ]);
                }
            }

            $isNew = ! $record->exists;
            $record->fill([
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle?->id,
                'workshop_name' => $isNew
                    ? (tenant('workshop_name') ?: ($actor->workshop_name ?: tenant('id')))
                    : $record->workshop_name,
                'customer_name' => $customer->name,
                'customer_address' => $customer->address,
                'vehicle_plate' => $vehicle?->plate_number,
                'due_date' => $validated['due_date'] ?? null,
                'items' => $items,
                'total_sen' => array_sum(array_column($items, 'total_sen')),
                'status' => $record->status ?: Invoice::STATUS_ISSUED,
            ]);
            $record->save();

            foreach (array_unique(array_merge(array_keys($oldDeductions), array_keys($newDeductions))) as $inventoryItemId) {
                $quantityChange = ($oldDeductions[$inventoryItemId] ?? 0) - ($newDeductions[$inventoryItemId] ?? 0);
                if ($quantityChange === 0) {
                    continue;
                }

                $inventoryItem = $inventoryItems->get($inventoryItemId);
                $inventoryItem->quantity += $quantityChange;
                $inventoryItem->save();
                $inventoryItem->movements()->create([
                    'invoice_id' => $record->id,
                    'quantity_change' => $quantityChange,
                    'balance_after' => $inventoryItem->quantity,
                    'recorded_by_user_id' => $actor->id,
                    'note' => $isNew
                        ? "Sold on invoice {$record->number()}"
                        : "Stock adjusted for invoice {$record->number()} edit",
                ]);
            }

            return $record;
        }, 3);
    }

    public function void(Invoice $invoice, string $reason, User $actor): Invoice
    {
        return DB::connection('tenant')->transaction(function () use ($invoice, $reason, $actor) {
            $record = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $this->assertEditable($record);

            $deductions = $this->inventoryDeductions($record->items ?? []);
            $inventoryItems = $this->lockInventoryItems(array_keys($deductions));
            if ($inventoryItems->count() !== count($deductions)) {
                throw ValidationException::withMessages([
                    'reason' => 'An inventory item linked to this invoice is no longer available, so it cannot be voided safely.',
                ]);
            }

            foreach ($deductions as $inventoryItemId => $quantity) {
                $inventoryItem = $inventoryItems->get($inventoryItemId);
                $inventoryItem->quantity += $quantity;
                $inventoryItem->save();
                $inventoryItem->movements()->create([
                    'invoice_id' => $record->id,
                    'quantity_change' => $quantity,
                    'balance_after' => $inventoryItem->quantity,
                    'recorded_by_user_id' => $actor->id,
                    'note' => "Restocked from voided invoice {$record->number()}",
                ]);
            }

            $record->forceFill([
                'status' => Invoice::STATUS_VOIDED,
                'voided_at' => now(),
                'voided_by_user_id' => $actor->id,
                'void_reason' => $reason,
            ])->save();

            return $record;
        }, 3);
    }

    private function inventoryDeductions(array $items): array
    {
        $deductions = [];
        foreach ($items as $item) {
            if (! is_array($item) || ! isset($item['inventory_item_id'])) {
                continue;
            }

            $inventoryItemId = (int) $item['inventory_item_id'];
            $deductions[$inventoryItemId] = ($deductions[$inventoryItemId] ?? 0) + (int) $item['quantity'];
        }

        return $deductions;
    }

    private function lockInventoryItems(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        return InventoryItem::query()->whereIn('id', $ids)
            ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
    }
}
