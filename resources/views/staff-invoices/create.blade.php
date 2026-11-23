@extends('layouts.app')
@section('title', 'Invoice completed job')
@section('content')
    <a href="{{ route('my-jobs.show', $job) }}">Back to job</a>
    <h1 class="h3 mt-2">Invoice completed job #{{ $job->id }}</h1>
    <p>Customer: {{ $job->customer?->name }}. Vehicle: {{ $job->vehicle?->plate_number }}. This invoice is not submitted to MyInvois.</p>
    @if ($errors->any())<div class="alert alert-danger" role="alert">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
    @php
        $maxItems = \App\Models\Invoice::MAX_ITEMS;
        $oldItems = old('items', [[]]);
        $rows = is_array($oldItems) && $oldItems ? array_slice(array_values($oldItems), 0, $maxItems) : [[]];
    @endphp
    <form method="POST" action="{{ route('staff-invoices.store', $job) }}" class="card p-3">
        @csrf
        <label for="due_date">Payment due date (optional, Malaysia time)</label>
        <input id="due_date" type="date" name="due_date" min="{{ $today }}" max="9999-12-31" value="{{ old('due_date') }}" class="form-control mb-3">
        <h2 class="h5">Line items</h2>
        <p>Enter the service or parts charges. Job parts already deducted from stock are not deducted again.</p>
        <div id="invoice-items" data-max-items="{{ $maxItems }}">
            @foreach ($rows as $item)
                @include('invoices.item', ['index' => $loop->index, 'item' => is_array($item) ? $item : [], 'canManageInventory' => false, 'inventoryItems' => collect()])
            @endforeach
        </div>
        <template id="invoice-item-template">@include('invoices.item', ['index' => '__INDEX__', 'item' => [], 'canManageInventory' => false, 'inventoryItems' => collect()])</template>
        <div class="form-row">
            <div class="col-md-4 form-group"><label for="discount_percent">Discount (%)</label><input id="discount_percent" name="discount_percent" type="number" min="0" max="100" step="0.01" class="form-control" value="{{ old('discount_percent', '0.00') }}" required></div>
            <div class="col-md-4 form-group"><label for="tax_percent">Tax rate (%)</label><input id="tax_percent" name="tax_percent" type="number" min="0" max="100" step="0.01" class="form-control" value="{{ old('tax_percent', number_format($defaultTaxRate / 100, 2, '.', '')) }}" required></div>
            <div class="col-md-4 form-group"><label for="tax_mode">Tax treatment</label><select id="tax_mode" name="tax_mode" class="form-control"><option value="exclusive" @selected(old('tax_mode') !== 'inclusive')>Add tax</option><option value="inclusive" @selected(old('tax_mode') === 'inclusive')>Prices include tax</option></select></div>
        </div>
        <div><button type="button" class="btn btn-outline-secondary" id="add-item" hidden>Add line item</button> <button class="btn btn-primary">Create invoice</button></div>
    </form>
    @include('invoices.item-controls')
@endsection
