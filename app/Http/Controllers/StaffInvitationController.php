<?php

namespace App\Http\Controllers;

use App\Models\StaffInvitation;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserSession;
use App\Support\PasswordPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StaffInvitationController extends Controller
{
    public function show(string $token): Response
    {
        $invitation = $this->validInvitation($token)->where('for_existing_user', false)->firstOrFail();
        $tenant = Tenant::findOrFail($invitation->tenant_id);

        return response()->view('auth.staff-invitation', ['invitation' => $invitation, 'tenant' => $tenant, 'token' => $token])
            ->header('Referrer-Policy', 'no-referrer')->header('Cache-Control', 'no-store');
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', ...PasswordPolicy::rules()],
        ]);
        $central = config('tenancy.database.central_connection');

        DB::connection($central)->transaction(function () use ($token, $data, $central) {
            $invitation = $this->validInvitation($token)->where('for_existing_user', false)->lockForUpdate()->firstOrFail();
            $tenant = Tenant::findOrFail($invitation->tenant_id);
            if (User::on($central)->where('email', $invitation->email)->exists()) {
                throw ValidationException::withMessages(['email' => 'An account already exists for this email. Contact the workshop admin.']);
            }

            User::on($central)->create([
                'name' => $data['name'],
                'email' => $invitation->email,
                'password' => Hash::make($data['password']),
                'tenant_id' => $invitation->tenant_id,
                'workshop_name' => $tenant->workshop_name ?? data_get($tenant->data, 'workshop_name', $tenant->id),
                'role' => $invitation->role,
                'status' => User::STATUS_ACTIVE,
                'can_view_customers' => $invitation->can_view_customers,
                'can_create_job_invoices' => $invitation->can_create_job_invoices,
                'can_record_payments' => $invitation->can_record_payments,
                'can_view_vehicle_history' => $invitation->can_view_vehicle_history,
                'can_create_customer_records' => $invitation->can_create_customer_records,
                'can_review_appointments' => $invitation->can_review_appointments,
            ]);
            $invitation->update(['accepted_at' => now()]);
        });

        return redirect()->route('login')->with('success', 'Invitation accepted. You can now sign in.');
    }

    public function showExisting(Request $request, string $token): Response
    {
        abort_if($request->session()->has('impersonator_id'), 403);
        $invitation = $this->validInvitation($token)->where('for_existing_user', true)->where('existing_user_id', $request->user()->id)->firstOrFail();
        $tenant = Tenant::findOrFail($invitation->tenant_id);

        return response()->view('auth.staff-invitation-existing', compact('invitation', 'tenant', 'token'))
            ->header('Referrer-Policy', 'no-referrer')->header('Cache-Control', 'no-store');
    }

    public function acceptExisting(Request $request, string $token): RedirectResponse
    {
        abort_if($request->session()->has('impersonator_id'), 403);
        $central = config('tenancy.database.central_connection');
        $user = DB::connection($central)->transaction(function () use ($request, $token, $central) {
            $user = User::on($central)->lockForUpdate()->findOrFail($request->user()->id);
            $invitation = $this->validInvitation($token)->where('for_existing_user', true)
                ->where('existing_user_id', $request->user()->id)->lockForUpdate()->firstOrFail();
            if (! $user->isEligibleForStaffInvitation() || strtolower($user->email) !== $invitation->email) {
                throw ValidationException::withMessages(['email' => 'This account is no longer eligible for the invitation. Contact the workshop admin.']);
            }
            $tenant = Tenant::findOrFail($invitation->tenant_id);
            $user->forceFill([
                'tenant_id' => $invitation->tenant_id,
                'workshop_name' => $tenant->workshop_name ?? data_get($tenant->data, 'workshop_name', $tenant->id),
                'role' => $invitation->role,
                'can_view_customers' => $invitation->can_view_customers,
                'can_create_job_invoices' => $invitation->can_create_job_invoices,
                'can_record_payments' => $invitation->can_record_payments,
                'can_view_vehicle_history' => $invitation->can_view_vehicle_history,
                'can_create_customer_records' => $invitation->can_create_customer_records,
                'can_review_appointments' => $invitation->can_review_appointments,
                'credential_version' => $user->credential_version + 1,
                'remember_token' => Str::random(60),
            ])->save();
            $invitation->update(['accepted_at' => now()]);

            return $user;
        }, 3);
        Auth::setUser($user);
        $request->session()->put('credential_version', $user->credential_version);
        UserSession::query()->whereKey($request->session()->get('device_session_id'))
            ->where('user_id', $user->id)->update(['credential_version' => $user->credential_version]);

        return redirect()->route('dashboard')->with('success', 'Invitation accepted.');
    }

    private function validInvitation(string $token): Builder
    {
        return StaffInvitation::on(config('tenancy.database.central_connection'))
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now());
    }
}
