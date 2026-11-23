@extends('layouts.app')

@section('title', __('Client accounts'))

@section('content')
    <h1 class="h3 mb-3">{{ __('Client accounts and vehicle requests') }}</h1>
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

    <section class="mb-4" aria-labelledby="unlinked-accounts">
        <h2 class="h5" id="unlinked-accounts">{{ __('Client accounts to link') }}</h2>
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Account') }}</th>
                            <th scope="col">{{ __('Status') }}</th>
                            <th scope="col">{{ __('Matching customer record') }}</th>
                            <th scope="col">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($unlinkedUsers as $entry)
                            @php($client = $entry['user'])
                            @php($matchedCustomer = $entry['customer'])
                            <tr>
                                <td>{{ $client->name }}<span class="d-block text-muted">{{ $client->email }}</span></td>
                                <td>{{ __(ucfirst($client->status)) }}</td>
                                <td>
                                    @if ($matchedCustomer)
                                        {{ $matchedCustomer->short_name ?: $matchedCustomer->name }}
                                        @if ($matchedCustomer->portal_user_id && (int) $matchedCustomer->portal_user_id !== $client->id)
                                            <span class="d-block text-danger">{{ __('Already linked to another account') }}</span>
                                        @endif
                                    @else
                                        {{ __('No exact email match. Add a customer record with this email first.') }}
                                    @endif
                                </td>
                                <td>
                                    @if ($matchedCustomer && (! $matchedCustomer->portal_user_id || (int) $matchedCustomer->portal_user_id === $client->id))
                                        <form method="POST" action="{{ route('client-accounts.activate', $client) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-primary">
                                                {{ $client->status === \App\Models\User::STATUS_SUSPENDED ? __('Confirm match and activate') : __('Confirm match') }}
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-muted">{{ __('No action available') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center">{{ __('All client accounts are linked.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section aria-labelledby="vehicle-requests">
        <h2 class="h5" id="vehicle-requests">{{ __('Pending additional vehicle requests') }}</h2>
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Customer') }}</th>
                            <th scope="col">{{ __('Vehicle') }}</th>
                            <th scope="col">{{ __('Identifiers') }}</th>
                            <th scope="col">{{ __('Requested') }}</th>
                            <th scope="col">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($vehicleRequests as $vehicleRequest)
                            <tr>
                                <td>{{ $vehicleRequest->customer->short_name ?: $vehicleRequest->customer->name }}</td>
                                <td>{{ $vehicleRequest->brand }} {{ $vehicleRequest->model }}{{ $vehicleRequest->year ? ' · '.$vehicleRequest->year : '' }}{{ $vehicleRequest->color ? ' · '.$vehicleRequest->color : '' }}
                                    @if ($vehicleRequest->mileage_km !== null)
                                        <span class="d-block">{{ number_format($vehicleRequest->mileage_km) }} km</span>
                                    @endif
                                    @if ($vehicleRequest->fuel_type)
                                        <span class="d-block">{{ $vehicleRequest->fuel_type }}</span>
                                    @endif
                                </td>
                                <td>{{ $vehicleRequest->plate_number }}<span class="d-block text-muted">{{ __('Chassis:') }} {{ $vehicleRequest->chassis_number }}</span><span class="d-block text-muted">{{ __('Engine:') }} {{ $vehicleRequest->engine_number }}</span></td>
                                <td>{{ $vehicleRequest->created_at->timezone(\App\Models\Calendar::TIMEZONE)->locale(app()->getLocale())->translatedFormat('d M Y') }}</td>
                                <td>
                                    <form method="POST" action="{{ route('client-vehicle-requests.approve', $vehicleRequest) }}" class="mb-1">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-sm btn-primary">{{ __('Approve and add') }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('client-vehicle-requests.reject', $vehicleRequest) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('Reject') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center">{{ __('No pending vehicle requests.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection
