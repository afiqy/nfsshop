@extends('layouts.app')

@section('title', 'Inventory')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Inventory</h1>
        <div>
            <a href="{{ route('suppliers.index') }}" class="btn btn-outline-secondary">Suppliers</a>
            <a href="{{ route('purchase-orders.index') }}" class="btn btn-outline-secondary">Purchase orders</a>
            <a href="{{ route('inventory.create') }}" class="btn btn-primary">Add item</a>
        </div>
    </div>
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr><th scope="col">SKU</th><th scope="col">Item</th><th scope="col">On hand</th><th scope="col">Status</th></tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td><a href="{{ route('inventory.show', $item) }}">{{ $item->sku }}</a></td>
                            <td>{{ $item->name }}</td>
                            <td>{{ $item->quantity }}</td>
                            <td>{{ $item->isLowStock() ? 'Low stock' : 'In stock' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center">No inventory items yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $items->links() }}</div>
@endsection
