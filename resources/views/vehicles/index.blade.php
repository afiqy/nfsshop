@extends('layouts.app')

@section('title', 'Vehicles')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Vehicles</h1>
        <a href="{{ route('vehicles.create') }}" class="btn btn-primary">Add vehicle</a>
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
                        <th scope="col">Customer</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($vehicles as $vehicle)
                        <tr>
                            <td>{{ $vehicle->plate_number }}</td>
                            <td>{{ $vehicle->brand }} {{ $vehicle->model }}</td>
                            <td>{{ $vehicle->customer->name }}</td>
                            <td>
                                <a class="mr-2" href="{{ route('vehicles.history', $vehicle) }}" aria-label="Service history for {{ $vehicle->plate_number }}">History</a>
                                <a href="{{ route('vehicles.edit', $vehicle) }}" aria-label="Edit {{ $vehicle->plate_number }}">Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center">No vehicles yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $vehicles->links() }}</div>
@endsection
