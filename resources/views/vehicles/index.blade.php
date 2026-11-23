@extends('layouts.app')

@section('title', __('Vehicles'))

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">{{ __('Vehicles') }}</h1>
        @if (auth()->user()->role === \App\Models\User::ROLE_ADMIN || auth()->user()->can_create_customer_records)
            <a href="{{ route('vehicles.create') }}" class="btn btn-primary">{{ __('Add vehicle') }}</a>
        @endif
    </div>

    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Plate number') }}</th>
                        <th scope="col">{{ __('Make and model') }}</th>
                        <th scope="col">{{ __('Details') }}</th>
                        <th scope="col">{{ __('Customer') }}</th>
                        @if (auth()->user()->role === \App\Models\User::ROLE_ADMIN || auth()->user()->can_view_vehicle_history)<th scope="col">{{ __('Actions') }}</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($vehicles as $vehicle)
                        <tr>
                            <td>{{ $vehicle->plate_number }}</td>
                            <td>{{ $vehicle->brand }} {{ $vehicle->model }}</td>
                            <td>
                                {{ $vehicle->year ?? __('Year not provided') }}{{ $vehicle->color ? ' · '.$vehicle->color : '' }}
                                @if (auth()->user()->role === \App\Models\User::ROLE_MECHANIC)
                                    <span class="d-block">{{ __('Chassis:') }} {{ $vehicle->chassis_number }} &middot; {{ __('Engine:') }} {{ $vehicle->engine_number }}</span>
                                @endif
                                @if ($vehicle->mileage_km !== null)
                                    <span class="d-block">{{ number_format($vehicle->mileage_km) }} km</span>
                                @endif
                                @if ($vehicle->fuel_type)
                                    <span class="d-block">{{ $vehicle->fuel_type }}</span>
                                @endif
                            </td>
                            <td>{{ $vehicle->customer->name }}</td>
                            @if (auth()->user()->role === \App\Models\User::ROLE_ADMIN || auth()->user()->can_view_vehicle_history)<td>
                                <a class="mr-2" href="{{ route('vehicles.history', $vehicle) }}" aria-label="{{ __('Service history: :plate', ['plate' => $vehicle->plate_number]) }}">{{ __('History') }}</a>
                                @if (auth()->user()->role === \App\Models\User::ROLE_ADMIN)<a href="{{ route('vehicles.edit', $vehicle) }}" aria-label="{{ __('Edit :name', ['name' => $vehicle->plate_number]) }}">{{ __('Edit') }}</a>@endif
                            </td>@endif
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center">{{ __('No vehicles yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $vehicles->links() }}</div>
@endsection
