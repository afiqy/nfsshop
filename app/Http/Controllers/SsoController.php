<?php

namespace App\Http\Controllers;

use App\Models\SocialIdentity;
use App\Models\User;
use App\Services\AuthenticatedSession;
use App\Services\DeviceSessions;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class SsoController extends Controller
{
    private const PROVIDERS = ['google' => 'Google', 'microsoft' => 'Microsoft'];

    public static function configuredProviders(): array
    {
        return array_filter(self::PROVIDERS, fn ($provider, $key) => config("services.$key.client_id")
            && config("services.$key.client_secret"), ARRAY_FILTER_USE_BOTH);
    }

    public function redirect(Request $request, string $provider): RedirectResponse
    {
        abort_unless(array_key_exists($provider, self::PROVIDERS), 404);
        abort_unless(isset(self::configuredProviders()[$provider]), 404);

        $request->session()->put(['sso_intent' => 'login', 'sso_provider' => $provider]);

        $driver = Socialite::driver($provider);
        if ($provider === 'google') {
            $driver->scopes(['openid', 'email', 'profile']);
        }

        return $driver->redirect();
    }

    public function link(Request $request, string $provider): RedirectResponse
    {
        abort_unless(array_key_exists($provider, self::PROVIDERS), 404);
        abort_unless(isset(self::configuredProviders()[$provider]), 404);
        $request->validate(['current_password' => ['required', 'string']]);
        if (! Hash::check($request->input('current_password'), $request->user()->password)) {
            throw ValidationException::withMessages(['current_password' => 'The current password is incorrect.']);
        }
        if ($request->user()->socialIdentities()->where('provider', $provider)->exists()) {
            throw ValidationException::withMessages(['provider' => 'This provider is already linked to your account.']);
        }

        $request->session()->put([
            'sso_intent' => 'link',
            'sso_provider' => $provider,
            'sso_link_user_id' => $request->user()->id,
        ]);

        return Socialite::driver($provider)->redirect();
    }

    public function callback(Request $request, string $provider, AuthenticatedSession $sessions, DeviceSessions $devices): RedirectResponse
    {
        abort_unless(array_key_exists($provider, self::PROVIDERS), 404);
        $intent = $request->session()->pull('sso_intent');
        $expectedProvider = $request->session()->pull('sso_provider');
        $linkUserId = $request->session()->pull('sso_link_user_id');
        if ($expectedProvider !== $provider || ! in_array($intent, ['login', 'link'], true)) {
            return $this->ssoError($intent === 'link', 'The sign-in request expired. Please try again.');
        }

        try {
            $identity = Socialite::driver($provider)->user();
        } catch (InvalidStateException|GuzzleException|RuntimeException $exception) {
            Log::warning('External identity authentication failed.', [
                'provider' => $provider,
                'exception_class' => $exception::class,
            ]);

            return $this->ssoError($intent === 'link', 'The provider could not verify your sign-in. Please try again.');
        }

        $providerId = (string) $identity->getId();
        if ($providerId === '' || strlen($providerId) > 255) {
            return $this->ssoError($intent === 'link', 'The provider did not return a usable identity.');
        }

        if ($intent === 'link') {
            return $this->linkIdentity($request, $provider, $providerId, $linkUserId);
        }

        $linkedIdentity = SocialIdentity::query()->with('user')
            ->where('provider', $provider)->where('provider_user_id', $providerId)->first();
        if ($linkedIdentity) {
            return $this->login($request, $linkedIdentity->user, $sessions, $devices);
        }
        $email = strtolower(trim((string) $identity->getEmail()));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->ssoError(false, 'The provider did not return a usable email address.');
        }
        if (User::query()->where('email', $email)->exists()) {
            return $this->accountConflict();
        }
        $name = trim((string) $identity->getName());

        try {
            $user = DB::transaction(function () use ($provider, $providerId, $email, $name, $identity) {
                $existing = SocialIdentity::query()->with('user')->where('provider', $provider)
                    ->where('provider_user_id', $providerId)->lockForUpdate()->first();
                if ($existing) {
                    return $existing->user;
                }
                if (User::query()->where('email', $email)->exists()) {
                    return null;
                }

                $user = User::create([
                    'name' => $name !== '' ? mb_substr($name, 0, 255) : mb_substr(strstr($email, '@', true) ?: 'Customer', 0, 255),
                    'email' => $email,
                    'password' => Hash::make(Str::random(64)),
                    'role' => User::ROLE_CLIENT,
                    'status' => User::STATUS_ACTIVE,
                ]);
                $verified = data_get($identity->getRaw(), 'email_verified') === true;
                if ($verified) {
                    $user->forceFill(['email_verified' => true, 'email_verified_at' => now()])->save();
                }
                $user->socialIdentities()->create(['provider' => $provider, 'provider_user_id' => $providerId]);

                return $user;
            });
        } catch (QueryException $exception) {
            $linkedIdentity = SocialIdentity::query()->with('user')->where('provider', $provider)
                ->where('provider_user_id', $providerId)->first();
            if ($linkedIdentity) {
                $user = $linkedIdentity->user;
            } elseif (User::query()->where('email', $email)->exists()) {
                return $this->accountConflict();
            } else {
                throw $exception;
            }
        }
        if (! $user) {
            return $this->accountConflict();
        }

        return $this->login($request, $user, $sessions, $devices);
    }

    public function unlink(Request $request, SocialIdentity $identity): RedirectResponse
    {
        abort_if($request->session()->has('impersonator_id'), 403);
        $request->validate(['current_password' => ['required', 'string']]);
        if ($identity->user_id !== $request->user()->id) {
            abort(404);
        }
        if (! Hash::check($request->input('current_password'), $request->user()->password)) {
            throw ValidationException::withMessages(['current_password' => 'The current password is incorrect.']);
        }

        $identity->delete();

        return back()->with('success', 'External sign-in method removed.');
    }

    private function linkIdentity(Request $request, string $provider, string $providerId, mixed $linkUserId): RedirectResponse
    {
        abort_if($request->session()->has('impersonator_id'), 403);
        $user = $request->user();
        $account = $linkUserId ? User::query()->find($linkUserId) : null;
        if (! $user || ! $account || $user->id !== $account->id
            || $account->isLocked() || $account->isSuspended()
            || $request->session()->get('credential_version') !== $account->credential_version) {
            return $this->ssoError(true, 'Your sign-in session changed. Sign in and try linking again.');
        }
        $existing = SocialIdentity::query()->where('provider', $provider)->where('provider_user_id', $providerId)->first();
        if ($existing) {
            $message = $existing->user_id === $account->id
                ? 'This provider is already linked to your account.'
                : 'This provider identity is already linked to another account.';

            return redirect()->route('profile.index')->withErrors(['provider' => $message]);
        }
        try {
            $account->socialIdentities()->create([
                'provider' => $provider,
                'provider_user_id' => $providerId,
            ]);
        } catch (QueryException $exception) {
            $existing = SocialIdentity::query()->where('provider', $provider)
                ->where('provider_user_id', $providerId)->first();
            if (! $existing) {
                throw $exception;
            }

            return redirect()->route('profile.index')->withErrors([
                'provider' => 'This provider identity is already linked to an account.',
            ]);
        }

        return redirect()->route('profile.index')->with('success', ucfirst($provider).' sign-in linked.');
    }

    private function login(Request $request, User $user, AuthenticatedSession $sessions, DeviceSessions $devices): RedirectResponse
    {
        if ($user->isLocked() || $user->isSuspended()) {
            return $this->ssoError(false, 'This account cannot sign in. Contact your workshop administrator.');
        }

        Auth::login($user);

        return $sessions->complete($request, $user, $devices);
    }

    private function ssoError(bool $linking, string $message): RedirectResponse
    {
        return redirect()->route($linking ? 'profile.index' : 'login')->withErrors(['sso' => $message]);
    }

    private function accountConflict(): RedirectResponse
    {
        return $this->ssoError(false, 'This identity could not be used. Sign in normally, then link the provider from your profile.');
    }
}
