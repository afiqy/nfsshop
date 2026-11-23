@extends('layouts.app')

@section('title', __('Create purchase order'))

@section('content')
    <a href="{{ route('purchase-orders.index') }}">{{ __('Back to purchase orders') }}</a>
    <h1 class="h3 mt-2 mb-3">{{ __('Create purchase order') }}</h1>
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    @if ($suppliers->isEmpty() || $inventoryItems->isEmpty())
        <div class="alert alert-warning">
            @if ($suppliers->isEmpty())
                {{ __('Add an active') }} <a href="{{ route('suppliers.create') }}" class="alert-link">{{ __('supplier') }}</a> {{ __('first.') }}
            @endif
            @if ($inventoryItems->isEmpty())
                {{ __('Add an') }} <a href="{{ route('inventory.create') }}" class="alert-link">{{ __('inventory item') }}</a> {{ __('first.') }}
            @endif
        </div>
    @else
        @php
            $oldItems = old('items');
            $rows = is_array($oldItems) && $oldItems ? array_slice(array_values($oldItems), 0, 50) : [[]];
        @endphp
        <form action="{{ route('purchase-orders.store') }}" method="POST" class="card shadow-sm p-3">
            @csrf
            <div class="form-group">
                <label for="supplier_id">{{ __('Supplier') }}</label>
                <select id="supplier_id" name="supplier_id" class="form-control" required>
                    <option value="">{{ __('Select an active supplier') }}</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected((string) old('supplier_id') === (string) $supplier->id)>
                            {{ $supplier->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label for="notes">{{ __('Order notes (optional)') }}</label>
                <textarea id="notes" name="notes" rows="3" maxlength="4000" class="form-control">{{ old('notes') }}</textarea>
            </div>
            <h2 class="h5">{{ __('Items to order') }}</h2>
            <div id="purchase-order-items" data-max-items="50">
                @foreach ($rows as $item)
                    @include('purchase-orders.item', ['index' => $loop->index, 'item' => is_array($item) ? $item : []])
                @endforeach
            </div>
            <template id="purchase-order-item-template">
                @include('purchase-orders.item', ['index' => '__INDEX__', 'item' => []])
            </template>
            <div class="mt-2">
                <button type="button" class="btn btn-outline-secondary" id="add-item" hidden>{{ __('Add item') }}</button>
                <button type="submit" class="btn btn-primary">{{ __('Create purchase order') }}</button>
            </div>
        </form>
        <script>
            const items = document.getElementById('purchase-order-items');
            const addItem = document.getElementById('add-item');
            let nextIndex = items.children.length;
            function refreshButtons() {
                addItem.disabled = items.children.length >= Number(items.dataset.maxItems);
                for (const button of items.querySelectorAll('[data-remove-item]')) {
                    button.hidden = items.children.length < 2;
                }
            }
            addItem.hidden = false;
            addItem.addEventListener('click', () => {
                const template = document.getElementById('purchase-order-item-template');
                items.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(nextIndex++)));
                items.lastElementChild.querySelector('select').focus();
                refreshButtons();
            });
            items.addEventListener('click', event => {
                if (!event.target.matches('[data-remove-item]') || items.children.length < 2) return;
                event.target.closest('fieldset').remove();
                addItem.focus();
                refreshButtons();
            });
            refreshButtons();
        </script>
    @endif
@endsection
