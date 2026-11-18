@extends('layouts.app')

@section('title', 'New invoice')

@section('content')
    <a href="{{ route('invoices.index') }}">Back to invoices</a>
    <h1 class="h3 mt-2 mb-3">New invoice</h1>
    <p>Beta only. Use test data. These invoices are not submitted to MyInvois.</p>

    @if ($customers->isEmpty())
        <div class="alert alert-warning">
            This workshop needs a customer before you can create an invoice.
            <a href="{{ route('customers.create') }}" class="alert-link">Add a customer</a>.
        </div>
    @else
        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @php
            $maxItems = \App\Models\Invoice::MAX_ITEMS;
            $oldItems = old('items');
            $rows = is_array($oldItems) && $oldItems ? array_slice(array_values($oldItems), 0, $maxItems) : [[]];
        @endphp

        <form action="{{ route('invoices.store') }}" method="POST" class="card shadow-sm p-3">
            @csrf
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="customer_id">Customer</label>
                    <select class="form-control" id="customer_id" name="customer_id" required>
                        <option value="">Select a customer</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" @selected(in_array(old('customer_id'), [$customer->id, (string) $customer->id], true))>{{ $customer->name }} ({{ $customer->email }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-6">
                    <label for="vehicle_id">Vehicle (optional)</label>
                    <select class="form-control" id="vehicle_id" name="vehicle_id">
                        <option value="">No vehicle</option>
                        @foreach ($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}" data-customer="{{ $vehicle->customer_id }}" @selected(in_array(old('vehicle_id'), [$vehicle->id, (string) $vehicle->id], true))>{{ $vehicle->plate_number }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <h2 class="h5">Line items</h2>
            <div id="invoice-items" data-max-items="{{ $maxItems }}">
                @foreach ($rows as $item)
                    @include('invoices.item', ['index' => $loop->index, 'item' => is_array($item) ? $item : []])
                @endforeach
            </div>
            <template id="invoice-item-template">
                @include('invoices.item', ['index' => '__INDEX__', 'item' => []])
            </template>
            <div class="mt-2">
                <button type="button" class="btn btn-outline-secondary" id="add-item" hidden>Add line item</button>
                <button type="submit" class="btn btn-primary">Create invoice</button>
            </div>
        </form>

        <script>
            const customer = document.getElementById('customer_id');
            const vehicle = document.getElementById('vehicle_id');
            const vehicleOptions = [...vehicle.querySelectorAll('option[data-customer]')];
            function updateVehicles() {
                const selected = vehicle.value;
                vehicle.replaceChildren(new Option('No vehicle', ''));
                for (const option of vehicleOptions) {
                    if (option.dataset.customer === customer.value) {
                        vehicle.add(option);
                    }
                }
                if ([...vehicle.options].some(option => option.value === selected)) vehicle.value = selected;
            }
            customer.addEventListener('change', updateVehicles);
            updateVehicles();

            const items = document.getElementById('invoice-items');
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
                const template = document.getElementById('invoice-item-template');
                items.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(nextIndex++)));
                items.lastElementChild.querySelector('input').focus();
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
