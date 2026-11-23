<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DeviceSessionController extends Controller
{
    public function index(Request $request): Response
    {
        $this->notImpersonating($request);

        return response()->view('profile.devices', [
            'devices' => UserSession::query()->where('user_id', $request->user()->id)->where('managed', true)
                ->where('credential_version', $request->user()->credential_version)->whereNull('revoked_at')
                ->latest('last_activity')->paginate(20),
            'currentId' => $request->session()->get('device_session_id'),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function revoke(Request $request, string $device): RedirectResponse
    {
        $this->notImpersonating($request);
        DB::connection(config('tenancy.database.central_connection'))->transaction(function () use ($request, $device) {
            $user = User::on(config('tenancy.database.central_connection'))->lockForUpdate()->findOrFail($request->user()->id);
            $record = UserSession::query()->where('user_id', $user->id)->where('managed', true)
                ->whereNull('revoked_at')->lockForUpdate()->findOrFail($device);
            $record->update(['revoked_at' => now()]);
            $user->setRememberToken(Str::random(60));
            $user->save();
        });
        if ((string) $request->session()->get('device_session_id') === $device) {
            auth()->logoutCurrentDevice();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('success', 'This device was signed out.');
        }

        return back()->with('success', 'Device revoked. It will be signed out on its next request.');
    }

    private function notImpersonating(Request $request): void
    {
        abort_if($request->session()->has('impersonator_id'), 403, 'Device sessions cannot be managed while impersonating.');
    }
}
