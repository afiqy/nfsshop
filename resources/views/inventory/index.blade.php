@extends('layouts.app')

@section('title', __('Inventory'))

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">{{ __('Inventory') }}</h1>
        <div>
            <a href="{{ route('suppliers.index') }}" class="btn btn-outline-secondary">{{ __('Suppliers') }}</a>
            <a href="{{ route('purchase-orders.index') }}" class="btn btn-outline-secondary">{{ __('Purchase orders') }}</a>
            <a href="{{ route('inventory.create') }}" class="btn btn-primary">{{ __('Add item') }}</a>
        </div>
    </div>
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr><th scope="col">{{ __('SKU') }}</th><th scope="col">{{ __('Item') }}</th><th scope="col">{{ __('On hand') }}</th><th scope="col">{{ __('Status') }}</th></tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td><a href="{{ route('inventory.show', $item) }}">{{ $item->sku }}</a></td>
                            <td>{{ $item->name }}</td>
                            <td>{{ $item->quantity }}</td>
                            <td>{{ __($item->isLowStock() ? 'Low stock' : 'In stock') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center">{{ __('No inventory items yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $items->links() }}</div>
@endsection
