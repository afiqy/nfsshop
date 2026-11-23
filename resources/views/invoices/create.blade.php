@extends('layouts.app')

@section('title', $invoice->exists ? 'Edit invoice' : 'New invoice')

@section('content')
    <a href="{{ route('invoices.index') }}">Back to invoices</a>
    <h1 class="h3 mt-2 mb-3">{{ $invoice->exists ? 'Edit invoice '.$invoice->number() : 'New invoice' }}</h1>
    @unless ($invoice->exists)
        <p>Beta only. Use test data. These invoices are not submitted to MyInvois.</p>
    @endunless

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
            $oldItems = old('items', $initialItems);
            $rows = is_array($oldItems) && $oldItems ? array_slice(array_values($oldItems), 0, $maxItems) : [[]];
        @endphp

        <form action="{{ $invoice->exists ? route('invoices.update', $invoice) : route('invoices.store') }}" method="POST" class="card shadow-sm p-3">
            @csrf
            @if ($invoice->exists)
                @method('PUT')
            @endif
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="customer_id">Customer</label>
                    <select class="form-control" id="customer_id" name="customer_id" required>
                        <option value="">Select a customer</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" @selected(in_array(old('customer_id', $invoice->customer_id), [$customer->id, (string) $customer->id], true))>{{ $customer->name }} ({{ $customer->email }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-6">
                    <label for="vehicle_id">Vehicle (optional)</label>
                    <select class="form-control" id="vehicle_id" name="vehicle_id">
                        <option value="">No vehicle</option>
                        @foreach ($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}" data-customer="{{ $vehicle->customer_id }}" @selected(in_array(old('vehicle_id', $invoice->vehicle_id), [$vehicle->id, (string) $vehicle->id], true))>{{ $vehicle->plate_number }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="due_date">Payment due date (optional)</label>
                <input class="form-control" type="date" id="due_date" name="due_date" min="{{ $invoice->exists ? '' : $today }}"
                    max="9999-12-31" aria-describedby="due-date-help"
                    value="{{ is_string(old('due_date', $invoice->due_date?->format('Y-m-d'))) ? old('due_date', $invoice->due_date?->format('Y-m-d')) : '' }}">
                <small class="form-text text-muted" id="due-date-help">Use Malaysia time. Leave blank if no payment deadline applies.</small>
            </div>

            <h2 class="h5">Line items</h2>
            <p>Optionally tag a service for reporting; enter the actual description and price. An inventory line cannot also be tagged as a service.</p>
            <div id="invoice-items" data-max-items="{{ $maxItems }}">
                @foreach ($rows as $item)
                    @include('invoices.item', [
                        'index' => $loop->index,
                        'item' => is_array($item) ? $item : [],
                        'canManageInventory' => $canManageInventory,
                        'inventoryItems' => $inventoryItems,
                    ])
                @endforeach
            </div>
            <template id="invoice-item-template">
                @include('invoices.item', [
                    'index' => '__INDEX__',
                    'item' => [],
                    'canManageInventory' => $canManageInventory,
                    'inventoryItems' => $inventoryItems,
                ])
            </template>
            @php $pricing = $invoice->pricing ?? []; @endphp
            <div class="form-row mt-3">
                <div class="form-group col-md-4">
                    <label for="discount_percent">Discount (%)</label>
                    <input id="discount_percent" name="discount_percent" type="number" min="0" max="100" step="0.01" required class="form-control" value="{{ old('discount_percent', number_format(($pricing['discount_rate'] ?? 0) / 100, 2, '.', '')) }}">
                </div>
                <div class="form-group col-md-4">
                    <label for="tax_percent">Tax rate (%)</label>
                    <input id="tax_percent" name="tax_percent" type="number" min="0" max="100" step="0.01" required class="form-control" value="{{ old('tax_percent', number_format(($pricing['tax_rate'] ?? ($invoice->exists ? 0 : $defaultTaxRate)) / 100, 2, '.', '')) }}">
                </div>
                <div class="form-group col-md-4">
                    <label for="tax_mode">Tax treatment</label>
                    <select id="tax_mode" name="tax_mode" class="form-control">
                        <option value="exclusive" @selected(old('tax_mode', $pricing['tax_mode'] ?? 'exclusive') === 'exclusive')>Add tax to discounted total</option>
                        <option value="inclusive" @selected(old('tax_mode', $pricing['tax_mode'] ?? 'exclusive') === 'inclusive')>Prices include tax</option>
                    </select>
                </div>
            </div>
            <p class="text-muted">Discount applies first. Tax and discount are rounded to the nearest sen. Confirm the tax treatment with your accountant.</p>
            <div class="mt-2">
                <button type="button" class="btn btn-outline-secondary" id="add-item" hidden>Add line item</button>
                <button type="submit" class="btn btn-primary">{{ $invoice->exists ? 'Save invoice changes' : 'Create invoice' }}</button>
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

        </script>
        @include('invoices.item-controls')
    @endif
@endsection
