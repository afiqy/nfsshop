@extends('layouts.app')

@section('title', __('Suppliers'))

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <a href="{{ route('inventory.index') }}">{{ __('Back to inventory') }}</a>
            <h1 class="h3 mt-2 mb-0">{{ __('Suppliers') }}</h1>
        </div>
        <a href="{{ route('suppliers.create') }}" class="btn btn-primary">{{ __('Add supplier') }}</a>
    </div>
    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Supplier') }}</th>
                        <th scope="col">{{ __('Contact') }}</th>
                        <th scope="col">{{ __('Email / phone') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                        <th scope="col">{{ __('Purchase orders') }}</th>
                        <th scope="col">{{ __('Actions') }}</th>
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
                            <td>{{ __($supplier->is_active ? 'Active' : 'Inactive') }}</td>
                            <td>{{ $supplier->purchase_orders_count }}</td>
                            <td><a href="{{ route('suppliers.edit', $supplier) }}">{{ __('Edit') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center">{{ __('No suppliers yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $suppliers->links() }}</div>
@endsection
