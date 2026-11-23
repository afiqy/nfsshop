<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class AuthenticatorService
{
    public function verify(User $user, string $code): User
    {
        $key = 'mfa:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['mfa_code' => 'Too many verification attempts. Try again later.']);
        }
        $verified = DB::connection(config('tenancy.database.central_connection'))->transaction(function () use ($user, $code) {
            $locked = User::on(config('tenancy.database.central_connection'))->lockForUpdate()->findOrFail($user->id);
            if (! $locked->mfa_secret) {
                return null;
            }
            if (preg_match('/\A[0-9]{6}\z/', $code)) {
                $counter = app(Google2FA::class)->verifyKeyNewer($locked->mfa_secret, $code, $locked->mfa_last_counter ?? 0);
                if ($counter !== false) {
                    $locked->forceFill(['mfa_last_counter' => $counter])->save();

                    return $locked;
                }
            }
            $hash = hash('sha256', strtoupper($code));
            $codes = $locked->mfa_recovery_codes ?? [];
            $index = array_search($hash, $codes, true);
            if ($index !== false) {
                unset($codes[$index]);
                $locked->forceFill(['mfa_recovery_codes' => array_values($codes)])->save();

                return $locked;
            }

            return null;
        });
        if (! $verified) {
            RateLimiter::hit($key, 300);
            throw ValidationException::withMessages(['mfa_code' => 'Invalid or already used verification code.']);
        }
        RateLimiter::clear($key);

        return $verified;
    }

    public function grant(User $user): array
    {
        return [
            'mfa_verified_user' => $user->id,
            'mfa_verified_secret' => hash('sha256', $user->mfa_secret),
            'mfa_verified_version' => $user->credential_version,
            'mfa_verified_until' => now()->addMinutes(5)->timestamp,
        ];
    }
}
