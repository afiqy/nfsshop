@extends('layouts.app')

@section('title', $item->exists ? 'Edit inventory item' : 'Add inventory item')

@section('content')
    <a href="{{ $item->exists ? route('inventory.show', $item) : route('inventory.index') }}">Back to inventory</a>
    <h1 class="h3 mt-2 mb-3">{{ $item->exists ? 'Edit inventory item' : 'Add inventory item' }}</h1>
    <form action="{{ $item->exists ? route('inventory.update', $item) : route('inventory.store') }}" method="POST" class="card shadow-sm p-3">
        @csrf
        @if ($item->exists)
            @method('PUT')
        @endif
        @foreach (['sku' => ['SKU', 64], 'name' => ['Item name', 255]] as $field => [$label, $length])
            @php
                $value = old($field, $item->{$field});
            @endphp
            <div class="form-group">
                <label for="{{ $field }}">{{ $label }}</label>
                <input id="{{ $field }}" name="{{ $field }}" class="form-control @error($field) is-invalid @enderror"
                    maxlength="{{ $length }}" value="{{ is_string($value) ? $value : '' }}" required>
                @error($field)
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        @endforeach
        @php
            $threshold = old('low_stock_threshold', $item->low_stock_threshold ?? 0);
        @endphp
        <div class="form-group">
            <label for="low_stock_threshold">Low-stock threshold (units)</label>
            <input id="low_stock_threshold" name="low_stock_threshold" type="number" min="0"
                max="{{ \App\Models\InventoryItem::MAX_QUANTITY }}" value="{{ is_scalar($threshold) ? $threshold : '' }}"
                class="form-control @error('low_stock_threshold') is-invalid @enderror" required>
            @error('low_stock_threshold')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <small class="form-text text-muted">The item shows as low stock at or below this quantity.</small>
        </div>
        <div><button type="submit" class="btn btn-primary">Save item</button></div>
    </form>
@endsection
