<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAuthenticatorVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }
        $user = User::on(config('tenancy.database.central_connection'))->findOrFail($request->user()->id);
        if (! $user->mfa_secret) {
            return $next($request);
        }
        $session = $request->session();
        if ($session->get('mfa_verified_user') !== $user->id
            || $session->get('mfa_verified_version') !== $user->credential_version
            || $session->get('mfa_verified_secret') !== hash('sha256', $user->mfa_secret)
            || $session->get('mfa_verified_until', 0) <= now()->timestamp) {
            return back()->withErrors(['mfa' => 'Action not performed. Verify your authenticator from your profile, then submit the action again.']);
        }

        return $next($request);
    }
}
