<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    protected $maxAttempts = 5;
    protected $lockoutMinutes = 15;

    public function showLoginForm()
    {
        error_log("Displaying login form to IP: " . request()->ip());
        return view('auth.login');
    }

    public function login(Request $request)
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
            ])->withInput($request->only('email'));
        }

        RateLimiter::clear($throttleKey);

        $user->resetLoginAttempts();

        $request->session()->regenerate();
        $request->session()->put('last_activity', now());

        UserSession::create([
            'user_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'device_name' => $request->header('User-Agent'),
            'last_activity' => now(),
        ]);
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
            UserSession::where('user_id', $request->user()->id)
                ->where('ip_address', $request->ip())
                ->where('user_agent', $request->userAgent())
                ->delete();
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
                Password::min(12)
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised(),
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
