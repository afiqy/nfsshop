@extends('layouts.app')
@section('title', __('Device sessions'))
@section('content')
    <a href="{{ route('profile.index') }}">{{ __('Back to profile') }}</a>
    <h1 class="h3 mt-2">{{ __('Device sessions') }}</h1>
    <p>{{ __('Revoking a device signs it out on its next request. Remember-me cookies on all devices are also invalidated.') }}</p>
    @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @forelse ($devices as $device)
        <div class="card p-3 mb-2">
            <div>{{ $device->device_name ?: __('Unknown browser') }} @if ($device->id === $currentId) <strong>{{ __('(this device)') }}</strong> @endif</div>
            <div>{{ __('IP: :ip — Last activity: :activity (:timezone)', [
                'ip' => $device->ip_address,
                'activity' => $device->last_activity?->timezone($userTimezone)->format('d M Y H:i') ?? __('Unknown'),
                'timezone' => $userTimezone,
            ]) }}</div>
            <form method="POST" action="{{ route('devices.revoke', $device->id) }}" class="mt-2">
                @csrf @method('DELETE')
                <button class="btn btn-sm btn-outline-danger">{{ $device->id === $currentId ? __('Revoke this device') : __('Revoke') }}</button>
            </form>
        </div>
    @empty <p>{{ __('No device sessions found.') }}</p> @endforelse
    {{ $devices->links() }}
@endsection
