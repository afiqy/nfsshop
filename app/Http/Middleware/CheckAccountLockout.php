<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAccountLockout
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->isSuspended()) {
            abort(403, 'Your account is suspended. Contact your workshop admin.');
        }

        if ($user && $user->isLocked()) {
            auth()->logout();
            $request->session()->invalidate();
            return redirect()->route('login')->withErrors([
                'email' => 'Your account is locked. Please try again later.',
            ]);
        }

        if ($user) {
            $version = $request->session()->get('credential_version');
            $impersonatorId = $request->session()->get('impersonator_id');
            $impersonator = $impersonatorId
                ? User::on(config('tenancy.database.central_connection'))->find($impersonatorId)
                : null;
            if ($impersonatorId && (! $impersonator || $request->session()->get('impersonator_credential_version') !== $impersonator->credential_version)) {
                auth()->logoutCurrentDevice();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors(['email' => 'Your password changed. Please sign in again.']);
            }
            if ($version === null && ($user->credential_version === 0 || auth()->viaRemember())) {
                $request->session()->put('credential_version', $user->credential_version);
            } elseif ($version !== $user->credential_version) {
                auth()->logoutCurrentDevice();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors(['email' => 'Your password changed. Please sign in again.']);
            }

            if ($user->force_password_reset && ! $request->routeIs('profile.index', 'profile.password.update', 'impersonation.leave')) {
                return redirect()->route('profile.index')->with('warning', 'Change your temporary password before continuing.');
            }
        }

        return $next($request);
    }
}
