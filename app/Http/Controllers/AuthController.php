<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use App\Support\PasswordPolicy;
use App\Services\LoginCaptcha;
use App\Services\DeviceSessions;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    protected $maxAttempts = 5;
    protected $lockoutMinutes = 15;

    public function showLoginForm()
    {
        error_log("Displaying login form to IP: " . request()->ip());
        return view('auth.login');
    }

    public function login(Request $request, LoginCaptcha $captcha, DeviceSessions $devices)
    {
        $request->validate([
            'email' => ['required','email'],
            'password' => ['required'],
        ]);
        error_log("Login attempt for email: " . $request->input('email') . " from IP: " . $request->ip());

        $email = strtolower($request->input('email'));
        $throttleKey = trans('auth.throttle_key') . '|' . $email . '|' . $request->ip();

        $user = User::where('email', $email)->first();
        error_log("User found: " . ($user ? 'Yes' : 'No'));

        if ($user && $user->isLocked()) {
            error_log("User account is locked: " . $user->email);
            return back()->withErrors([
                'email' => 'Your account is locked until '.$user->locked_until->toDayDateTimeString(),
            ])->withInput($request->only('email'));
        }

        if ($user && $user->isSuspended()) {
            return back()->withErrors(['email' => 'Your account is suspended. Contact your workshop admin.'])
                ->withInput($request->only('email'));
        }

        if (RateLimiter::tooManyAttempts($throttleKey, $this->maxAttempts)) {
            error_log("Too many login attempts for email: " . $email);
            if ($user) {
                $user->lockAccount($this->lockoutMinutes);
            }
            RateLimiter::clear($throttleKey);
            return back()->withErrors([
                'email' => 'Too many login attempts. Please try again later.',
            ])->withInput($request->only('email'));
        }

        if (RateLimiter::attempts($throttleKey) >= 3) {
            try {
                $response = $request->input('g-recaptcha-response');
                $captcha->verify(is_string($response) ? $response : null);
            } catch (ValidationException $exception) {
                return back()->withErrors($exception->errors())->withInput($request->only('email'))
                    ->with('captcha_required', true);
            }
        }

        if (! Auth::attempt(['email' => $email, 'password' => $request->password], $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, 60);
            if ($user) {
                $user->increment('failed_login_attempts');
                if ($user->failed_login_attempts >= $this->maxAttempts) {
                    $user->lockAccount($this->lockoutMinutes);
                }
            }

            return back()->withErrors([
                'email' => 'Invalid credentials.',
            ])->withInput($request->only('email'))->with('captcha_required', RateLimiter::attempts($throttleKey) >= 3);
        }

        RateLimiter::clear($throttleKey);

        $user->resetLoginAttempts();

        $request->session()->regenerate();
        $request->session()->put('credential_version', $user->credential_version);
        $request->session()->put('last_activity', now());

        $devices->start($request, $user);
        error_log("User logged in: " . $user->email);
        $fallbackRoute = $user->isSuperAdmin()
            ? route('super-admin.dashboard')
            : ($user->isAccountSuperAdmin()
                ? route('account.workshops.index')
                : ($user->role === User::ROLE_CLIENT
                    ? route('client.portal')
                    : route('dashboard')));

        return redirect()->intended($fallbackRoute);
    }

    public function logout(Request $request)
    {
        if ($request->user()) {
            \App\Models\UserSession::query()->whereKey($request->session()->get('device_session_id'))
                ->where('user_id', $request->session()->get('impersonator_id', $request->user()->id))
                ->update(['revoked_at' => now()]);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => ['required','string','max:255'],
            'email' => ['required','email','unique:users,email'],
            'password' => [
                'required',
                'confirmed',
                ...PasswordPolicy::rules(),
            ],
        ]);

        User::create([
            'name' => $request->name,
            'email' => strtolower($request->email),
            'password' => Hash::make($request->password),
            'role' => 'owner',
        ]);

        return redirect()->route('login')->with('success', 'Account created.');
    }
}
