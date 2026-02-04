<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Stancl\Tenancy\Database\Models\Tenant;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            $user = Auth::user();

            if ($user->isSuspended()) {
                if (tenancy()->initialized) {
                    tenancy()->end();
                }

                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()->withErrors([
                    'email' => 'Your account has been suspended. Please contact support.',
                ]);
            }

            if ($user->isSuperAdmin()) {
                if (tenancy()->initialized) {
                    tenancy()->end();
                }

                $request->session()->forget('tenant_id');

                return redirect()->intended(route('super-admin.dashboard'))->with('success', 'Logged in successfully');
            }

            $tenantId = $user->tenant_id;

            if (! $tenantId || ! Tenant::find($tenantId)) {
                if (tenancy()->initialized) {
                    tenancy()->end();
                }

                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()->withErrors([
                    'email' => 'Your account is not linked to an active workshop. Please contact support.',
                ]);
            }

            session(['tenant_id' => $tenantId]);

            if (! tenancy()->initialized || tenant('id') !== $tenantId) {
                tenancy()->initialize($tenantId);
            }

            return redirect()->intended(route('dashboard'))->with('success', 'Logged in successfully');
        }

        return back()->withErrors([
            'email' => 'Invalid credentials',
        ])->onlyInput('email');
    }

    public function showRegisterForm()
    {
        return redirect()->route('register');
    }

    public function register(Request $request)
    {
        return redirect()->route('register');
    }

    public function logout(Request $request)
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Logged out successfully');
    }

}
