@extends('layouts.app')

@section('title', 'Purchase order')

@section('content')
    <a href="{{ route('purchase-orders.index') }}">Back to purchase orders</a>
    <div class="d-flex justify-content-between align-items-center mt-2 mb-3">
        <div>
            <h1 class="h3 mb-1">Purchase order PO-{{ str_pad((string) $purchaseOrder->id, 6, '0', STR_PAD_LEFT) }}</h1>
            <p class="mb-0">Supplier: {{ $purchaseOrder->supplier_name }} &middot;
                {{ $purchaseOrder->created_at->timezone(\App\Models\Calendar::TIMEZONE)->format('d M Y H:i') }} MYT
            </p>
        </div>
        <strong>{{ \App\Models\PurchaseOrder::LABELS[$purchaseOrder->status] }}</strong>
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
    @if ($purchaseOrder->notes)
        <p>{{ $purchaseOrder->notes }}</p>
    @endif
    <div class="card shadow-sm mb-3">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th scope="col">SKU</th>
                        <th scope="col">Item</th>
                        <th scope="col">Ordered</th>
                        <th scope="col">Received</th>
                        <th scope="col">Remaining</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($purchaseOrder->items as $item)
                        <tr>
                            <td>{{ $item->item_sku }}</td>
                            <td>{{ $item->item_name }}</td>
                            <td>{{ $item->quantity }}</td>
                            <td>{{ $item->received_quantity }}</td>
                            <td>{{ $item->quantity - $item->received_quantity }}</td>
                        </tr>
                        @foreach ($item->movements as $movement)
                            <tr>
                                <td colspan="5" class="text-muted">
                                    Received {{ $movement->quantity_change }} on
                                    {{ $movement->created_at->timezone(\App\Models\Calendar::TIMEZONE)->format('d M Y H:i') }} MYT
                                    &middot; User #{{ $movement->recorded_by_user_id }}
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @if ($purchaseOrder->canReceive())
        <form action="{{ route('purchase-orders.receive', $purchaseOrder) }}" method="POST" class="card shadow-sm p-3 mb-3">
            @csrf
            <h2 class="h5">Receive stock</h2>
            <p>Enter the quantity received now for each line. Inventory and the movement ledger update only when stock is received.</p>
            @foreach ($purchaseOrder->items as $item)
                @php($remaining = $item->quantity - $item->received_quantity)
                <div class="form-group">
                    <label for="receive-{{ $item->id }}">{{ $item->item_sku }} &middot; {{ $item->item_name }} ({{ $remaining }} remaining)</label>
                    <input id="receive-{{ $item->id }}" name="quantities[{{ $item->id }}]" type="number" min="0"
                        max="{{ $remaining }}" value="{{ old('quantities.'.$item->id, 0) }}" class="form-control" required>
                </div>
            @endforeach
            <div><button type="submit" class="btn btn-primary">Record receipt</button></div>
        </form>
    @endif
    @if ($purchaseOrder->status === \App\Models\PurchaseOrder::STATUS_ORDERED)
        <form action="{{ route('purchase-orders.cancel', $purchaseOrder) }}" method="POST">
            @csrf
            @method('PATCH')
            <button type="submit" class="btn btn-outline-danger">Cancel unreceived order</button>
        </form>
    @endif
@endsection
