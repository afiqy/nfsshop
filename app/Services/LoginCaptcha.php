<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class LoginCaptcha
{
    public function verify(?string $response): void
    {
        $siteKey = config('services.recaptcha.site_key');
        $secret = config('services.recaptcha.secret_key');
        $hostname = parse_url((string) config('app.url'), PHP_URL_HOST);
        if (! $siteKey || ! $secret || ! $hostname) {
            Log::error('Login reCAPTCHA is not configured.');
            throw ValidationException::withMessages(['captcha' => 'Login verification is not configured. Contact support.']);
        }
        if (! $response || strlen($response) > 4096) {
            throw ValidationException::withMessages(['captcha' => 'Complete the CAPTCHA before signing in.']);
        }
        try {
            $verification = Http::asForm()->timeout(5)->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => $secret, 'response' => $response,
            ]);
        } catch (ConnectionException $exception) {
            Log::warning('Login reCAPTCHA verification is unavailable.', ['exception_class' => $exception::class]);
            throw ValidationException::withMessages(['captcha' => 'CAPTCHA verification is unavailable. Try again later.']);
        }
        if (! $verification->successful()) {
            Log::warning('Login reCAPTCHA verification returned an error.', ['status' => $verification->status()]);
            throw ValidationException::withMessages(['captcha' => 'CAPTCHA verification is unavailable. Try again later.']);
        }
        if ($verification->json('success') !== true || $verification->json('hostname') !== $hostname) {
            throw ValidationException::withMessages(['captcha' => 'CAPTCHA verification failed. Please try again.']);
        }
    }
}
