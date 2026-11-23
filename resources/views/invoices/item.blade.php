<fieldset class="border rounded p-2 mb-2">
    <legend class="sr-only">{{ __('Invoice line item') }}</legend>
    <div class="form-row align-items-end">
        <div class="form-group col-md-3 mb-2">
            <label for="item-{{ $index }}-description">{{ __('Description') }}</label>
            <input id="item-{{ $index }}-description" name="items[{{ $index }}][description]" class="form-control" maxlength="255" required
                value="{{ is_scalar($item['description'] ?? null) ? $item['description'] : '' }}">
        </div>
        <div class="form-group col-md-3 mb-2">
            <label for="item-{{ $index }}-service">{{ __('Service reporting (optional)') }}</label>
            <select id="item-{{ $index }}-service" name="items[{{ $index }}][service_id]" class="form-control">
                <option value="">{{ __('Unclassified line') }}</option>
                @foreach ($services as $service)
                    <option value="{{ $service->id }}" @selected((string) ($item['service_id'] ?? '') === (string) $service->id)>
                        {{ $service->name }}{{ $service->is_active ? '' : ' ('.__('inactive').')' }}
                    </option>
                @endforeach
            </select>
        </div>
        @if ($canManageInventory)
            <div class="form-group col-md-2 mb-2">
                <label for="item-{{ $index }}-inventory">{{ __('Deduct from inventory (optional)') }}</label>
                <select id="item-{{ $index }}-inventory" name="items[{{ $index }}][inventory_item_id]" class="form-control">
                    <option value="">{{ __('Not an inventory item') }}</option>
                    @foreach ($inventoryItems as $inventoryItem)
                        <option value="{{ $inventoryItem->id }}"
                            @selected((string) ($item['inventory_item_id'] ?? '') === (string) $inventoryItem->id)>
                            {{ $inventoryItem->sku }} &middot; {{ $inventoryItem->name }} ({{ __(':quantity on hand', ['quantity' => $inventoryItem->quantity]) }})
                        </option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="form-group col-md-2 mb-2">
            <label for="item-{{ $index }}-quantity">{{ __('Quantity') }}</label>
            <input id="item-{{ $index }}-quantity" name="items[{{ $index }}][quantity]" class="form-control" type="number" min="1" max="999" required
                value="{{ is_scalar($item['quantity'] ?? null) ? $item['quantity'] : 1 }}">
        </div>
        <div class="form-group col-md-3 mb-2">
            <label for="item-{{ $index }}-price">{{ __('Unit price (RM)') }}</label>
            <input id="item-{{ $index }}-price" name="items[{{ $index }}][unit_price]" class="form-control" type="number" inputmode="decimal" min="0" max="999999.99" step="0.01" required
                value="{{ is_scalar($item['unit_price'] ?? null) ? $item['unit_price'] : '' }}">
        </div>
        <div class="col-md-2 mb-2">
            <button type="button" data-remove-item class="btn btn-outline-danger" hidden>{{ __('Remove') }}</button>
        </div>
    </div>
</fieldset>
