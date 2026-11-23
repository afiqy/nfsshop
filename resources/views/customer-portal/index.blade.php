@extends('layouts.app')

@section('title', __('My vehicles and service history'))

@section('content')
    <h1 class="h3 mb-3">{{ __('My vehicles and service history') }}</h1>

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
            {{ __('Your client access is waiting for the workshop owner to approve it.') }}
        </div>
    @elseif (! $customer)
        <div class="alert alert-info" role="status">
            {{ __('Your account is not linked to a workshop customer record yet. Please contact the workshop to confirm your account.') }}
        </div>
    @else
        <p>{{ __('Welcome, :name. Your workshop customer record is linked to this account.', ['name' => $customer->name]) }}</p>
        <p><a href="{{ route('client.invoices.index') }}">{{ __('View my invoices and payment options') }}</a></p>
        <p><a href="{{ route('client.appointment-requests.index') }}">{{ __('Manage appointments and requests') }}</a></p>
        @if (app(\App\Services\SubscriptionEntitlementService::class)->subscriptionFor(auth()->user())?->canUseClientEngagement())
            <p><a href="{{ route('client.engagement') }}">{{ __('View promotions, loyalty and vehicle reminders') }}</a></p>
        @endif
        <form method="POST" action="{{ route('client.reminder-preference') }}" class="mb-4">
            @csrf
            @method('PUT')
            <label for="reminder_opt_in">{{ __('Vehicle service and renewal reminder emails') }}</label>
            <span>{{ __('at :email', ['email' => $customer->email]) }}</span>
            <select id="reminder_opt_in" name="opt_in">
                <option value="0" @selected(! $customer->engagement_reminders_opt_in)>{{ __('No') }}</option>
                <option value="1" @selected($customer->engagement_reminders_opt_in)>{{ __('Yes') }}</option>
            </select>
            <button type="submit" class="btn btn-sm btn-outline-primary">{{ __('Save preference') }}</button>
        </form>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h5 mb-0">{{ __('Vehicles (:count)', ['count' => $vehicles->count()]) }}</h2>
            @if ($vehicles->count() < \App\Http\Controllers\CustomerPortalController::MAX_SELF_SERVICE_VEHICLES)
                <a class="btn btn-primary" href="{{ route('client.vehicle.create') }}">{{ __('Add vehicle') }}</a>
            @else
                <a class="btn btn-primary" href="{{ route('client.vehicle-requests.create') }}">{{ __('Request another vehicle') }}</a>
            @endif
        </div>

        <div class="card shadow-sm mb-4">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Vehicle') }}</th>
                            <th scope="col">{{ __('Registration') }}</th>
                            <th scope="col">{{ __('Details') }}</th>
                            <th scope="col">{{ __('History') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($vehicles as $vehicle)
                            <tr>
                                <td>{{ $vehicle->brand }} {{ $vehicle->model }}</td>
                                <td>{{ $vehicle->plate_number }}</td>
                                <td>{{ $vehicle->year ?? __('Year not provided') }}{{ $vehicle->color ? ' · '.$vehicle->color : '' }}
                                    @if ($vehicle->mileage_km !== null)
                                        <span class="d-block">{{ number_format($vehicle->mileage_km) }} km</span>
                                    @endif
                                    @if ($vehicle->fuel_type)
                                        <span class="d-block">{{ $vehicle->fuel_type }}</span>
                                    @endif
                                </td>
                                <td><a href="{{ route('client.vehicles.history', $vehicle) }}">{{ __('View service history') }}</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center">{{ __('No vehicles linked to your account.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($vehicleRequests->isNotEmpty())
            <h2 class="h5">{{ __('Additional vehicle requests') }}</h2>
            <div class="card shadow-sm">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('Vehicle') }}</th>
                                <th scope="col">{{ __('Registration') }}</th>
                                <th scope="col">{{ __('Requested') }}</th>
                                <th scope="col">{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($vehicleRequests as $vehicleRequest)
                                <tr>
                                    <td>{{ $vehicleRequest->brand }} {{ $vehicleRequest->model }}</td>
                                    <td>{{ $vehicleRequest->plate_number }}</td>
                                    <td>{{ $vehicleRequest->created_at->timezone(\App\Models\Calendar::TIMEZONE)->locale(app()->getLocale())->translatedFormat('d M Y') }}</td>
                                    <td>{{ __(ucfirst($vehicleRequest->status)) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
@endsection
