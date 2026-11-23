@extends('layouts.app')

@section('title', __('Inventory item'))

@section('content')
    <a href="{{ route('inventory.index') }}">{{ __('Back to inventory') }}</a>
    <div class="d-flex justify-content-between align-items-center mt-2 mb-3">
        <h1 class="h3 mb-0">{{ $item->name }} ({{ $item->sku }})</h1>
        <a href="{{ route('inventory.edit', $item) }}" class="btn btn-outline-secondary">{{ __('Edit item') }}</a>
    </div>
    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <p>{{ __('On hand: :quantity units. Low-stock threshold: :threshold.', ['quantity' => $item->quantity, 'threshold' => $item->low_stock_threshold]) }}
        @if ($item->isLowStock()) <span class="badge badge-warning">{{ __('Low stock') }}</span> @endif
    </p>
    <form action="{{ route('inventory.move', $item) }}" method="POST" class="card shadow-sm p-3 mb-4">
        @csrf
        <h2 class="h5">{{ __('Record a stock movement') }}</h2>
        <div class="form-row">
            <div class="form-group col-md-3">
                <label for="direction">{{ __('Direction') }}</label>
                <select id="direction" name="direction" class="form-control" required>
                    <option value="">{{ __('Select direction') }}</option>
                    <option value="in" @selected(old('direction') === 'in')>{{ __('Stock in') }}</option>
                    <option value="out" @selected(old('direction') === 'out')>{{ __('Stock out') }}</option>
                </select>
            </div>
            <div class="form-group col-md-3">
                <label for="quantity">{{ __('Units') }}</label>
                <input id="quantity" name="quantity" type="number" min="1" max="{{ \App\Models\InventoryItem::MAX_QUANTITY }}"
                    class="form-control" value="{{ is_scalar(old('quantity')) ? old('quantity') : '' }}" required>
            </div>
            <div class="form-group col-md-6">
                <label for="note">{{ __('Reason or reference') }}</label>
                <input id="note" name="note" maxlength="255" class="form-control" value="{{ is_string(old('note')) ? old('note') : '' }}" required>
            </div>
        </div>
        <div><button type="submit" class="btn btn-primary">{{ __('Record movement') }}</button></div>
    </form>
    <h2 class="h5">{{ __('Movement history') }}</h2>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr><th scope="col">{{ __('Date (MYT)') }}</th><th scope="col">{{ __('Change') }}</th><th scope="col">{{ __('Balance') }}</th><th scope="col">{{ __('Recorded by') }}</th><th scope="col">{{ __('Reason') }}</th></tr>
            </thead>
            <tbody>
                @forelse ($movements as $movement)
                    <tr>
                        <td>{{ $movement->created_at->timezone(\App\Models\Calendar::TIMEZONE)->locale(app()->getLocale())->translatedFormat('d M Y H:i') }}</td>
                        <td>{{ sprintf('%+d', $movement->quantity_change) }}</td>
                        <td>{{ $movement->balance_after }}</td>
                        <td>{{ __('User #:id', ['id' => $movement->recorded_by_user_id]) }}</td>
                        <td>
                            {{ $movement->note }}
                            @if ($movement->purchaseOrderItem)
                                <span class="d-block">
                                    <a href="{{ route('purchase-orders.show', $movement->purchaseOrderItem->purchase_order_id) }}">
                                        PO-{{ str_pad((string) $movement->purchaseOrderItem->purchase_order_id, 6, '0', STR_PAD_LEFT) }}
                                    </a>
                                </span>
                            @endif
                            @if ($movement->invoice)
                                <span class="d-block">
                                    <a href="{{ route('invoices.show', $movement->invoice) }}">{{ $movement->invoice->number() }}</a>
                                </span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center">{{ __('No stock movements yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $movements->links() }}
@endsection
