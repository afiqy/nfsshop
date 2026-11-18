@extends('layouts.app')

@section('title', 'Customers')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Customers</h1>
        <a href="{{ route('customers.create') }}" class="btn btn-primary">Add customer</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Email</th>
                        <th scope="col">Phone</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($customers as $customer)
                        <tr>
                            <td>{{ $customer->name }}</td>
                            <td>{{ $customer->email }}</td>
                            <td>{{ $customer->phone ?? 'Not provided' }}</td>
                            <td><a href="{{ route('customers.edit', $customer) }}" aria-label="Edit {{ $customer->name }}">Edit</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center">No customers yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $customers->links() }}</div>
@endsection
