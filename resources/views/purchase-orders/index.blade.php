@extends('layouts.app')

@section('title', 'Purchase orders')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <a href="{{ route('inventory.index') }}">Back to inventory</a>
            <h1 class="h3 mt-2 mb-0">Purchase orders</h1>
        </div>
        <a href="{{ route('purchase-orders.create') }}" class="btn btn-primary">Create purchase order</a>
    </div>
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th scope="col">Order</th>
                        <th scope="col">Supplier</th>
                        <th scope="col">Items</th>
                        <th scope="col">Created</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($purchaseOrders as $purchaseOrder)
                        <tr>
                            <td><a href="{{ route('purchase-orders.show', $purchaseOrder) }}">PO-{{ str_pad((string) $purchaseOrder->id, 6, '0', STR_PAD_LEFT) }}</a></td>
                            <td>{{ $purchaseOrder->supplier_name }}</td>
                            <td>{{ $purchaseOrder->items_count }}</td>
                            <td>{{ $purchaseOrder->created_at->timezone(\App\Models\Calendar::TIMEZONE)->format('d M Y') }}</td>
                            <td>{{ \App\Models\PurchaseOrder::LABELS[$purchaseOrder->status] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center">No purchase orders yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $purchaseOrders->links() }}</div>
@endsection
