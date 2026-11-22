@extends('layouts.app')

@section('title', 'Vehicles')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Vehicles</h1>
        @if (auth()->user()->role === \App\Models\User::ROLE_ADMIN)
            <a href="{{ route('vehicles.create') }}" class="btn btn-primary">Add vehicle</a>
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
                        <th scope="col">Plate number</th>
                        <th scope="col">Make and model</th>
                        <th scope="col">Details</th>
                        <th scope="col">Customer</th>
                        @if (auth()->user()->role === \App\Models\User::ROLE_ADMIN)<th scope="col">Actions</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($vehicles as $vehicle)
                        <tr>
                            <td>{{ $vehicle->plate_number }}</td>
                            <td>{{ $vehicle->brand }} {{ $vehicle->model }}</td>
                            <td>
                                {{ $vehicle->year ?? 'Year not provided' }}{{ $vehicle->color ? ' · '.$vehicle->color : '' }}
                                @if (auth()->user()->role === \App\Models\User::ROLE_MECHANIC)
                                    <span class="d-block">Chassis: {{ $vehicle->chassis_number }} &middot; Engine: {{ $vehicle->engine_number }}</span>
                                @endif
                                @if ($vehicle->mileage_km !== null)
                                    <span class="d-block">{{ number_format($vehicle->mileage_km) }} km</span>
                                @endif
                                @if ($vehicle->fuel_type)
                                    <span class="d-block">{{ $vehicle->fuel_type }}</span>
                                @endif
                            </td>
                            <td>{{ $vehicle->customer->name }}</td>
                            @if (auth()->user()->role === \App\Models\User::ROLE_ADMIN)<td>
                                <a class="mr-2" href="{{ route('vehicles.history', $vehicle) }}" aria-label="Service history for {{ $vehicle->plate_number }}">History</a>
                                <a href="{{ route('vehicles.edit', $vehicle) }}" aria-label="Edit {{ $vehicle->plate_number }}">Edit</a>
                            </td>@endif
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center">No vehicles yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $vehicles->links() }}</div>
@endsection
