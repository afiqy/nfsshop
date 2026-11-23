<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\Request;

class DeviceSessions
{
    public function start(Request $request, User $user): void
    {
        $device = UserSession::create([
            'user_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'device_name' => substr((string) $request->userAgent(), 0, 255),
            'last_activity' => now(),
            'managed' => true,
            'credential_version' => $user->credential_version,
        ]);
        $request->session()->put('device_session_id', $device->id);
    }

    public function check(Request $request, User $user): bool
    {
        $actorId = $request->session()->get('impersonator_id', $user->id);
        $deviceId = $request->session()->get('device_session_id');
        if (! $deviceId) {
            $actor = $actorId === $user->id
                ? $user
                : User::on(config('tenancy.database.central_connection'))->findOrFail($actorId);
            $this->start($request, $actor);

            return true;
        }
        $device = UserSession::query()->where('user_id', $actorId)->where('managed', true)->find($deviceId);
        if (! $device || $device->revoked_at) {
            return false;
        }
        if (! $device->last_activity || $device->last_activity->lt(now()->subMinute())) {
            $device->update(['last_activity' => now()]);
        }

        return true;
    }
}
