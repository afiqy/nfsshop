<fieldset class="border rounded p-2 mb-2">
    <legend class="sr-only">Invoice line item</legend>
    <div class="form-row align-items-end">
        <div class="form-group col-md-5 mb-2">
            <label for="item-{{ $index }}-description">Description</label>
            <input id="item-{{ $index }}-description" name="items[{{ $index }}][description]" class="form-control" maxlength="255" required
                value="{{ is_scalar($item['description'] ?? null) ? $item['description'] : '' }}">
        </div>
        <div class="form-group col-md-2 mb-2">
            <label for="item-{{ $index }}-quantity">Quantity</label>
            <input id="item-{{ $index }}-quantity" name="items[{{ $index }}][quantity]" class="form-control" type="number" min="1" max="999" required
                value="{{ is_scalar($item['quantity'] ?? null) ? $item['quantity'] : 1 }}">
        </div>
        <div class="form-group col-md-3 mb-2">
            <label for="item-{{ $index }}-price">Unit price (RM)</label>
            <input id="item-{{ $index }}-price" name="items[{{ $index }}][unit_price]" class="form-control" type="number" inputmode="decimal" min="0" max="999999.99" step="0.01" required
                value="{{ is_scalar($item['unit_price'] ?? null) ? $item['unit_price'] : '' }}">
        </div>
        <div class="col-md-2 mb-2">
            <button type="button" data-remove-item class="btn btn-outline-danger" hidden>Remove</button>
        </div>
    </div>
</fieldset>
