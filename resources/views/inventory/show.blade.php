@extends('layouts.app')

@section('title', 'Inventory item')

@section('content')
    <a href="{{ route('inventory.index') }}">Back to inventory</a>
    <div class="d-flex justify-content-between align-items-center mt-2 mb-3">
        <h1 class="h3 mb-0">{{ $item->name }} ({{ $item->sku }})</h1>
        <a href="{{ route('inventory.edit', $item) }}" class="btn btn-outline-secondary">Edit item</a>
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
    <p>On hand: <strong>{{ $item->quantity }}</strong> units. Low-stock threshold: {{ $item->low_stock_threshold }}.
        @if ($item->isLowStock()) <span class="badge badge-warning">Low stock</span> @endif
    </p>
    <form action="{{ route('inventory.move', $item) }}" method="POST" class="card shadow-sm p-3 mb-4">
        @csrf
        <h2 class="h5">Record a stock movement</h2>
        <div class="form-row">
            <div class="form-group col-md-3">
                <label for="direction">Direction</label>
                <select id="direction" name="direction" class="form-control" required>
                    <option value="">Select direction</option>
                    <option value="in" @selected(old('direction') === 'in')>Stock in</option>
                    <option value="out" @selected(old('direction') === 'out')>Stock out</option>
                </select>
            </div>
            <div class="form-group col-md-3">
                <label for="quantity">Units</label>
                <input id="quantity" name="quantity" type="number" min="1" max="{{ \App\Models\InventoryItem::MAX_QUANTITY }}"
                    class="form-control" value="{{ is_scalar(old('quantity')) ? old('quantity') : '' }}" required>
            </div>
            <div class="form-group col-md-6">
                <label for="note">Reason or reference</label>
                <input id="note" name="note" maxlength="255" class="form-control" value="{{ is_string(old('note')) ? old('note') : '' }}" required>
            </div>
        </div>
        <div><button type="submit" class="btn btn-primary">Record movement</button></div>
    </form>
    <h2 class="h5">Movement history</h2>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr><th scope="col">Date (MYT)</th><th scope="col">Change</th><th scope="col">Balance</th><th scope="col">Recorded by</th><th scope="col">Reason</th></tr>
            </thead>
            <tbody>
                @forelse ($movements as $movement)
                    <tr>
                        <td>{{ $movement->created_at->timezone(\App\Models\Calendar::TIMEZONE)->format('d M Y H:i') }}</td>
                        <td>{{ sprintf('%+d', $movement->quantity_change) }}</td>
                        <td>{{ $movement->balance_after }}</td>
                        <td>User #{{ $movement->recorded_by_user_id }}</td>
                        <td>{{ $movement->note }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center">No stock movements yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $movements->links() }}
@endsection
