<?php

namespace App\Http\Controllers;

use App\Mail\StaffInvitationEmail;
use App\Models\StaffInvitation;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(): View
    {
        $central = config('tenancy.database.central_connection');

        return view('staff.index', [
            'staff' => User::on($central)->where('tenant_id', tenant('id'))
                ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_MECHANIC])
                ->where(fn ($query) => $query->whereNull('suspension_reason')->orWhere('suspension_reason', '!=', 'Awaiting owner approval'))
                ->orderBy('name')->get(),
            'invitations' => StaffInvitation::on($central)->where('tenant_id', tenant('id'))
                ->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())
                ->latest()->get(),
            'ownerId' => Tenant::findOrFail(tenant('id'))->account_owner_id,
            'ownerEmail' => data_get(tenant()->data, 'owner_email'),
        ]);
    }

    public function invite(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_MECHANIC])],
            'can_view_customers' => ['nullable', 'boolean'],
        ]);
        $email = strtolower(trim($data['email']));
        $central = config('tenancy.database.central_connection');
        $token = Str::random(64);
        $workshopName = data_get(tenant()->data, 'workshop_name', tenant('id'));
        DB::connection($central)->transaction(function () use ($data, $email, $central, $token, $request, $workshopName) {
            Tenant::query()->whereKey(tenant('id'))->lockForUpdate()->firstOrFail();
            if (User::on($central)->where('email', $email)->exists()) {
                throw ValidationException::withMessages(['email' => 'An account already exists for this email. Existing accounts cannot be invited.']);
            }
            StaffInvitation::on($central)->where('tenant_id', tenant('id'))->where('email', $email)
                ->whereNull('accepted_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);
            StaffInvitation::on($central)->create([
                'tenant_id' => tenant('id'),
                'email' => $email,
                'role' => $data['role'],
                'can_view_customers' => $data['role'] === User::ROLE_MECHANIC && $request->boolean('can_view_customers'),
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addDays(7),
                'invited_by' => $request->user()->id,
            ]);

            Mail::to($email)->send(new StaffInvitationEmail(route('staff-invitations.show', $token), $workshopName, $data['role']));
        });

        return redirect()->route('staff.index')->with('success', 'Invitation emailed.');
    }

    public function revoke(string $invitation): RedirectResponse
    {
        $updated = StaffInvitation::on(config('tenancy.database.central_connection'))
            ->where('tenant_id', tenant('id'))->whereKey($invitation)
            ->whereNull('accepted_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);
        abort_unless($updated, 404);

        return back()->with('success', 'Invitation revoked.');
    }

    public function update(Request $request, string $staff): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_MECHANIC])],
            'status' => ['required', Rule::in([User::STATUS_ACTIVE, User::STATUS_SUSPENDED])],
            'can_view_customers' => ['nullable', 'boolean'],
        ]);
        $central = config('tenancy.database.central_connection');
        $user = User::on($central)->where('tenant_id', tenant('id'))
            ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_MECHANIC])
            ->where(fn ($query) => $query->whereNull('suspension_reason')->orWhere('suspension_reason', '!=', 'Awaiting owner approval'))
            ->findOrFail($staff);
        $tenant = Tenant::findOrFail(tenant('id'));
        $isLegacyOwner = ! $tenant->account_owner_id && strtolower($user->email) === strtolower((string) data_get($tenant->data, 'owner_email'));
        abort_if($user->id === $request->user()->id || (int) $tenant->account_owner_id === $user->id || $isLegacyOwner, 403);

        DB::connection($central)->transaction(function () use ($user, $data, $request, $central) {
            $user = User::on($central)->lockForUpdate()->findOrFail($user->id);
            $newSuspension = $data['status'] === User::STATUS_SUSPENDED && ! $user->isSuspended();
            $user->fill([
                'role' => $data['role'],
                'status' => $data['status'],
                'can_view_customers' => $data['role'] === User::ROLE_MECHANIC && $request->boolean('can_view_customers'),
                'suspended_at' => $data['status'] === User::STATUS_SUSPENDED ? ($user->suspended_at ?? now()) : null,
                'suspension_reason' => $data['status'] === User::STATUS_SUSPENDED ? 'Suspended by workshop admin' : null,
            ]);
            if ($newSuspension) {
                $user->setRememberToken(Str::random(60));
                $user->credential_version++;
                UserSession::query()->where('user_id', $user->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
            }
            $user->save();
        });

        return back()->with('success', 'Staff access updated.');
    }
}
