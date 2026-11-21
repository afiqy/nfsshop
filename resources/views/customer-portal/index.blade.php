@extends('layouts.app')

@section('title', 'My vehicles and service history')

@section('content')
    <h1 class="h3 mb-3">My vehicles and service history</h1>

    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    @if (auth()->user()->status !== \App\Models\User::STATUS_ACTIVE)
        <div class="alert alert-info" role="status">
            Your client access is waiting for the workshop owner to approve it.
        </div>
    @elseif (! $customer)
        <div class="alert alert-info" role="status">
            Your account is not linked to a workshop customer record yet. Please contact the workshop to confirm your account.
        </div>
    @else
        <p>Welcome, {{ $customer->name }}. Your workshop customer record is linked to this account.</p>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h5 mb-0">Vehicles ({{ $vehicles->count() }})</h2>
            @if ($vehicles->count() < \App\Http\Controllers\CustomerPortalController::MAX_SELF_SERVICE_VEHICLES)
                <a class="btn btn-primary" href="{{ route('client.vehicle.create') }}">Add vehicle</a>
            @else
                <a class="btn btn-primary" href="{{ route('client.vehicle-requests.create') }}">Request another vehicle</a>
            @endif
        </div>

        <div class="card shadow-sm mb-4">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Vehicle</th>
                            <th scope="col">Registration</th>
                            <th scope="col">Details</th>
                            <th scope="col">History</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($vehicles as $vehicle)
                            <tr>
                                <td>{{ $vehicle->brand }} {{ $vehicle->model }}</td>
                                <td>{{ $vehicle->plate_number }}</td>
                                <td>{{ $vehicle->year ?? 'Year not provided' }}{{ $vehicle->color ? ' · '.$vehicle->color : '' }}</td>
                                <td><a href="{{ route('client.vehicles.history', $vehicle) }}">View service history</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center">No vehicles linked to your account.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($vehicleRequests->isNotEmpty())
            <h2 class="h5">Additional vehicle requests</h2>
            <div class="card shadow-sm">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Vehicle</th>
                                <th scope="col">Registration</th>
                                <th scope="col">Requested</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($vehicleRequests as $vehicleRequest)
                                <tr>
                                    <td>{{ $vehicleRequest->brand }} {{ $vehicleRequest->model }}</td>
                                    <td>{{ $vehicleRequest->plate_number }}</td>
                                    <td>{{ $vehicleRequest->created_at->timezone(\App\Models\Calendar::TIMEZONE)->format('d M Y') }}</td>
                                    <td>{{ ucfirst($vehicleRequest->status) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
@endsection
