@extends('layouts.app')

@section('title', __('Customers'))

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">{{ __('Customers') }}</h1>
        @if (auth()->user()->role === \App\Models\User::ROLE_ADMIN || auth()->user()->can_create_customer_records)
            <a href="{{ route('customers.create') }}" class="btn btn-primary">{{ __('Add customer') }}</a>
        @endif
    </div>

    <form method="GET" action="{{ route('customers.index') }}" class="form-inline mb-3">
        <label for="customer-search" class="sr-only">{{ __('Search customers') }}</label>
        <input id="customer-search" type="search" name="search" value="{{ $search }}" class="form-control mr-2"
            maxlength="100" placeholder="{{ __('Search name, short name, email or phone') }}">
        <button type="submit" class="btn btn-outline-primary">{{ __('Search') }}</button>
    </form>

    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Name') }}</th>
                        <th scope="col">{{ __('Short name') }}</th>
                        <th scope="col">{{ __('Email') }}</th>
                        <th scope="col">{{ __('Phone') }}</th>
                        @if (auth()->user()->role === \App\Models\User::ROLE_MECHANIC)<th scope="col">{{ __('Address') }}</th>@endif
                        @if (auth()->user()->role === \App\Models\User::ROLE_ADMIN)<th scope="col">{{ __('Actions') }}</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($customers as $customer)
                        <tr>
                            <td>{{ $customer->name }}</td>
                            <td>{{ $customer->short_name ?? '—' }}</td>
                            <td>{{ $customer->email }}</td>
                            <td>{{ $customer->phone ?? __('Not provided') }}</td>
                            @if (auth()->user()->role === \App\Models\User::ROLE_MECHANIC)<td>{{ $customer->address ?? __('Not provided') }}</td>@endif
                            @if (auth()->user()->role === \App\Models\User::ROLE_ADMIN)<td><a href="{{ route('customers.edit', $customer) }}" aria-label="{{ __('Edit :name', ['name' => $customer->name]) }}">{{ __('Edit') }}</a></td>@endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center">
                                {{ $search !== '' ? __('No customers match your search.') : __('No customers yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $customers->links() }}</div>
@endsection
