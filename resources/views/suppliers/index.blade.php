@extends('layouts.app')

@section('title', 'Suppliers')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <a href="{{ route('inventory.index') }}">Back to inventory</a>
            <h1 class="h3 mt-2 mb-0">Suppliers</h1>
        </div>
        <a href="{{ route('suppliers.create') }}" class="btn btn-primary">Add supplier</a>
    </div>
    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th scope="col">Supplier</th>
                        <th scope="col">Contact</th>
                        <th scope="col">Email / phone</th>
                        <th scope="col">Status</th>
                        <th scope="col">Purchase orders</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($suppliers as $supplier)
                        <tr>
                            <td>{{ $supplier->name }}</td>
                            <td>{{ $supplier->contact_name ?? '—' }}</td>
                            <td>
                                {{ $supplier->email ?? '—' }}
                                @if ($supplier->phone)
                                    <span class="d-block">{{ $supplier->phone }}</span>
                                @endif
                            </td>
                            <td>{{ $supplier->is_active ? 'Active' : 'Inactive' }}</td>
                            <td>{{ $supplier->purchase_orders_count }}</td>
                            <td><a href="{{ route('suppliers.edit', $supplier) }}">Edit</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center">No suppliers yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $suppliers->links() }}</div>
@endsection
