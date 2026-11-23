<fieldset class="border rounded p-3 mb-3">
    <legend class="sr-only">{{ __('Purchase order item :number', ['number' => is_numeric($index) ? $index + 1 : '']) }}</legend>
    <div class="form-row">
        <div class="form-group col-md-8">
            <label for="item-{{ $index }}">{{ __('Inventory item') }}</label>
            <select id="item-{{ $index }}" name="items[{{ $index }}][inventory_item_id]" class="form-control" required>
                <option value="">{{ __('Select an inventory item') }}</option>
                @foreach ($inventoryItems as $inventoryItem)
                    <option value="{{ $inventoryItem->id }}"
                        @selected((string) ($item['inventory_item_id'] ?? '') === (string) $inventoryItem->id)>
                        {{ $inventoryItem->sku }} &middot; {{ $inventoryItem->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="form-group col-md-4">
            <label for="quantity-{{ $index }}">{{ __('Quantity') }}</label>
            <input id="quantity-{{ $index }}" name="items[{{ $index }}][quantity]" type="number" min="1"
                max="{{ \App\Models\InventoryItem::MAX_QUANTITY }}" class="form-control"
                value="{{ is_scalar($item['quantity'] ?? null) ? $item['quantity'] : '' }}" required>
        </div>
    </div>
    <button type="button" class="btn btn-sm btn-outline-danger" data-remove-item hidden>{{ __('Remove item') }}</button>
</fieldset>
