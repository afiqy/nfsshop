<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuthenticatorService;
use App\Services\SubscriptionEntitlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class AuthenticatorController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $this->user($request);
        $setup = $request->session()->get('mfa_setup');
        $secret = $setup && $setup['user'] === $user->id && $setup['version'] === $user->credential_version
            && $setup['expires'] > now()->timestamp && ! $user->mfa_secret
            ? Crypt::decryptString($setup['secret']) : null;

        return response()->view('auth.authenticator', [
            'user' => $user, 'secret' => $secret, 'canEnroll' => $this->canEnroll($user),
        ])->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function start(Request $request): RedirectResponse
    {
        $this->notImpersonating($request);
        $data = $request->validate(['password' => ['required', 'string']]);
        $user = $this->user($request);
        abort_unless($this->canEnroll($user), 403);
        if ($user->mfa_secret) {
            throw ValidationException::withMessages(['mfa' => 'An authenticator is already enabled.']);
        }
        if (! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => 'Current password is incorrect.']);
        }
        $request->session()->put('mfa_setup', [
            'user' => $user->id, 'version' => $user->credential_version,
            'secret' => Crypt::encryptString(app(Google2FA::class)->generateSecretKey(32)),
            'expires' => now()->addMinutes(10)->timestamp,
        ]);

        return redirect()->route('authenticator.show');
    }

    public function confirm(Request $request, AuthenticatorService $authenticator): Response
    {
        $this->notImpersonating($request);
        $data = $request->validate(['mfa_code' => ['required', 'string', 'regex:/\A[0-9]{6}\z/']]);
        $user = $this->user($request);
        abort_unless($this->canEnroll($user), 403);
        $setup = $request->session()->get('mfa_setup');
        if (! $setup || $setup['user'] !== $user->id || $setup['version'] !== $user->credential_version
            || $setup['expires'] <= now()->timestamp) {
            throw ValidationException::withMessages(['mfa' => 'Setup expired. Start again.']);
        }
        $key = 'mfa:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['mfa_code' => 'Too many verification attempts. Try again later.']);
        }
        $secret = Crypt::decryptString($setup['secret']);
        $counter = app(Google2FA::class)->verifyKeyNewer($secret, $data['mfa_code'], 0);
        if ($counter === false) {
            RateLimiter::hit($key, 300);
            throw ValidationException::withMessages(['mfa_code' => 'Invalid verification code.']);
        }
        $codes = array_map(fn () => strtoupper(bin2hex(random_bytes(10))), range(1, 10));
        $user = DB::connection(config('tenancy.database.central_connection'))->transaction(function () use ($user, $secret, $counter, $codes) {
            $locked = User::on(config('tenancy.database.central_connection'))->lockForUpdate()->findOrFail($user->id);
            if ($locked->mfa_secret || $locked->credential_version !== $user->credential_version) {
                throw ValidationException::withMessages(['mfa' => 'Account security changed. Start setup again.']);
            }
            $locked->forceFill([
                'mfa_secret' => $secret, 'mfa_last_counter' => $counter,
                'mfa_recovery_codes' => array_map(fn ($code) => hash('sha256', $code), $codes),
            ])->save();

            return $locked;
        });
        RateLimiter::clear($key);
        $request->session()->forget('mfa_setup');
        $request->session()->put($authenticator->grant($user));

        return response()->view('auth.authenticator-recovery-codes', ['codes' => $codes])
            ->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function verify(Request $request, AuthenticatorService $authenticator): RedirectResponse
    {
        $data = $request->validate(['mfa_code' => ['required', 'string', 'max:20']]);
        $user = $authenticator->verify($this->user($request), $data['mfa_code']);
        $request->session()->put($authenticator->grant($user));

        return redirect()->route('authenticator.show')->with('success', 'Verified for five minutes. Return to your action and submit it again.');
    }

    public function disable(Request $request, AuthenticatorService $authenticator): RedirectResponse
    {
        $this->notImpersonating($request);
        $data = $request->validate(['password' => ['required', 'string'], 'mfa_code' => ['required', 'string', 'max:20']]);
        $user = $this->user($request);
        if (! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => 'Current password is incorrect.']);
        }
        $verified = $authenticator->verify($user, $data['mfa_code']);
        DB::connection(config('tenancy.database.central_connection'))->transaction(function () use ($verified) {
            $locked = User::on(config('tenancy.database.central_connection'))->lockForUpdate()->findOrFail($verified->id);
            if ($locked->credential_version !== $verified->credential_version || $locked->mfa_secret !== $verified->mfa_secret) {
                throw ValidationException::withMessages(['mfa' => 'Account security changed. Try again.']);
            }
            $locked->forceFill(['mfa_secret' => null, 'mfa_last_counter' => null, 'mfa_recovery_codes' => null])->save();
        });
        $request->session()->forget(['mfa_verified_user', 'mfa_verified_secret', 'mfa_verified_version', 'mfa_verified_until']);

        return redirect()->route('authenticator.show')->with('success', 'Authenticator disabled.');
    }

    private function user(Request $request): User
    {
        return User::on(config('tenancy.database.central_connection'))->findOrFail($request->user()->id);
    }

    private function canEnroll(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }
        if (! in_array($user->role, [User::ROLE_ADMIN, User::ROLE_ACCOUNT_SUPER_ADMIN], true)) {
            return false;
        }
        $subscription = app(SubscriptionEntitlementService::class)->subscriptionFor($user);

        return ($subscription?->isUsable() ?? false) && $subscription->plan?->slug === 'enterprise';
    }

    private function notImpersonating(Request $request): void
    {
        abort_if($request->session()->has('impersonator_id'), 403, 'Authenticator settings cannot be changed while impersonating.');
    }
}
