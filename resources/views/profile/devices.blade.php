@extends('layouts.app')
@section('title', 'Device sessions')
@section('content')
    <a href="{{ route('profile.index') }}">Back to profile</a>
    <h1 class="h3 mt-2">Device sessions</h1>
    <p>Revoking a device signs it out on its next request. Remember-me cookies on all devices are also invalidated.</p>
    @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @forelse ($devices as $device)
        <div class="card p-3 mb-2">
            <div>{{ $device->device_name ?: 'Unknown browser' }} @if ($device->id === $currentId) <strong>(this device)</strong> @endif</div>
            <div>IP: {{ $device->ip_address }} — Last activity: {{ $device->last_activity?->timezone(\App\Models\Calendar::TIMEZONE)->format('d M Y H:i') ?? 'Unknown' }} (Malaysia time)</div>
            <form method="POST" action="{{ route('devices.revoke', $device->id) }}" class="mt-2">
                @csrf @method('DELETE')
                <button class="btn btn-sm btn-outline-danger">Revoke{{ $device->id === $currentId ? ' this device' : '' }}</button>
            </form>
        </div>
    @empty <p>No device sessions found.</p> @endforelse
    {{ $devices->links() }}
@endsection
