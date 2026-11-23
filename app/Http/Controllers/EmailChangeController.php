<?php

namespace App\Http\Controllers;

use App\Mail\ConfirmEmailChange;
use App\Models\User;
use App\Services\PasswordCredentials;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class EmailChangeController extends Controller
{
    public function requestChange(Request $request): RedirectResponse
    {
        abort_if($request->session()->has('impersonator_id'), 403);
        if (is_string($request->input('new_email'))) {
            $request->merge(['new_email' => strtolower(trim($request->input('new_email')))]);
        }
        $data = $request->validate([
            'new_email' => ['required', 'string', 'email', 'max:255', Rule::unique(config('tenancy.database.central_connection').'.users', 'email')],
            'current_password' => ['required', 'string'],
        ]);
        $connection = DB::connection(config('tenancy.database.central_connection'));
        $connection->transaction(function () use ($request, $data, $connection) {
            $user = User::on(config('tenancy.database.central_connection'))->lockForUpdate()->findOrFail($request->user()->id);
            if (! Hash::check($data['current_password'], $user->password)) {
                throw ValidationException::withMessages(['current_password' => 'Current password is incorrect.']);
            }
            $token = bin2hex(random_bytes(32));
            $connection->table('pending_email_changes')->updateOrInsert(['user_id' => $user->id], [
                'old_email' => $user->email,
                'new_email' => $data['new_email'],
                'token_hash' => hash('sha256', $token),
                'credential_version' => $user->credential_version,
                'expires_at' => now()->addHour(),
            ]);
            try {
                $url = rtrim(config('app.url'), '/').route('profile.email.confirm', ['token' => $token], false);
                Mail::to($data['new_email'])->send(new ConfirmEmailChange($url));
            } catch (Throwable $exception) {
                Log::error('Email change verification delivery failed.', ['exception_class' => $exception::class]);
                throw ValidationException::withMessages(['new_email' => 'Could not send verification email. Please try again later.']);
            }
        });

        return back()->with('success', 'A confirmation link was sent to the new address. Your account email is unchanged until you confirm it.');
    }

    public function show(Request $request, string $token): Response
    {
        abort_if($request->session()->has('impersonator_id'), 403);
        abort_unless(preg_match('/\A[a-f0-9]{64}\z/', $token), 404);

        return response()->view('profile.confirm-email', ['token' => $token])
            ->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function confirm(Request $request, PasswordCredentials $credentials): RedirectResponse
    {
        abort_if($request->session()->has('impersonator_id'), 403);
        $data = $request->validate(['token' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/']]);
        $connection = DB::connection(config('tenancy.database.central_connection'));
        try {
            $connection->transaction(function () use ($request, $data, $connection, $credentials) {
                $user = User::on(config('tenancy.database.central_connection'))->lockForUpdate()->findOrFail($request->user()->id);
                $pending = $connection->table('pending_email_changes')->where('user_id', $user->id)->lockForUpdate()->first();
                if (! $pending || ! hash_equals($pending->token_hash, hash('sha256', $data['token']))
                    || $pending->expires_at <= now()->toDateTimeString()
                    || $pending->old_email !== $user->email || (int) $pending->credential_version !== $user->credential_version) {
                    throw ValidationException::withMessages(['token' => 'This confirmation link is invalid or expired. Request a new link.']);
                }
                if (User::on(config('tenancy.database.central_connection'))->where('email', $pending->new_email)->exists()) {
                    throw ValidationException::withMessages(['token' => 'This email is already in use. Request a new link with a different address.']);
                }
                $credentials->deleteTokens($user->email, $pending->new_email);
                $user->forceFill(['email' => $pending->new_email, 'email_verified' => true, 'email_verified_at' => now()])->save();
                $connection->table('pending_email_changes')->where('user_id', $user->id)->delete();
            });
        } catch (QueryException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) !== 1062) {
                throw $exception;
            }
            throw ValidationException::withMessages(['token' => 'This email is already in use. Request a new link with a different address.']);
        }

        return redirect()->route('profile.index')->with('success', 'Account email changed.');
    }
}
