<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PasswordCredentials;
use App\Support\PasswordPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Throwable;

class PasswordRecoveryController extends Controller
{
    public function requestForm(): Response
    {
        return $this->privateView('auth.forgot-password');
    }

    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);
        $email = strtolower($data['email']);

        DB::connection(config('tenancy.database.central_connection'))->transaction(function () use ($email) {
            $user = User::on(config('tenancy.database.central_connection'))->where('email', $email)->lockForUpdate()->first();
            if (! $user || Password::broker()->getRepository()->recentlyCreatedToken($user)) {
                return;
            }
            $token = Password::broker()->createToken($user);
            try {
                $user->sendPasswordResetNotification($token);
            } catch (Throwable $exception) {
                Password::broker()->deleteToken($user);
                Log::error('Password recovery email failed.', ['exception_class' => $exception::class]);
            }
        });

        return back()->with('success', 'If an account exists for this address, a recovery email may be sent. If it does not arrive, contact support.');
    }

    public function resetForm(Request $request, string $token): Response
    {
        return $this->privateView('auth.reset-password', ['token' => $token, 'email' => $request->query('email', '')]);
    }

    public function reset(Request $request, PasswordCredentials $credentials): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'token' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
            'password' => ['required', 'confirmed', ...PasswordPolicy::rules()],
        ]);
        $email = strtolower($data['email']);
        $success = DB::connection(config('tenancy.database.central_connection'))->transaction(function () use ($email, $data, $credentials) {
            $user = User::on(config('tenancy.database.central_connection'))->where('email', $email)->lockForUpdate()->first();
            if (! $user || ! Password::broker()->tokenExists($user, $data['token'])) {
                return false;
            }
            $credentials->updateLocked($user, $data['password']);

            return true;
        });

        if (! $success) {
            return back()->withErrors(['email' => 'This recovery link is invalid or expired. Request a new link.']);
        }

        return redirect()->route('login')->with('success', 'Password reset. You can now sign in.');
    }

    private function privateView(string $view, array $data = []): Response
    {
        return response()->view($view, $data)->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }
}
