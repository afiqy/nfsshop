<?php

namespace App\Http\Controllers;

use App\Models\StaffInvitation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class StaffInvitationController extends Controller
{
    public function show(string $token): Response
    {
        $invitation = $this->validInvitation($token)->firstOrFail();
        $tenant = Tenant::findOrFail($invitation->tenant_id);

        return response()->view('auth.staff-invitation', ['invitation' => $invitation, 'tenant' => $tenant, 'token' => $token])
            ->header('Referrer-Policy', 'no-referrer')->header('Cache-Control', 'no-store');
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'confirmed', 'min:8'],
        ]);
        $central = config('tenancy.database.central_connection');

        DB::connection($central)->transaction(function () use ($token, $data, $central) {
            $invitation = $this->validInvitation($token)->lockForUpdate()->firstOrFail();
            $tenant = Tenant::findOrFail($invitation->tenant_id);
            if (User::on($central)->where('email', $invitation->email)->exists()) {
                throw ValidationException::withMessages(['email' => 'An account already exists for this email. Contact the workshop admin.']);
            }

            User::on($central)->create([
                'name' => $data['name'],
                'email' => $invitation->email,
                'password' => Hash::make($data['password']),
                'tenant_id' => $invitation->tenant_id,
                'workshop_name' => data_get($tenant->data, 'workshop_name', $tenant->id),
                'role' => $invitation->role,
                'status' => User::STATUS_ACTIVE,
                'can_view_customers' => $invitation->can_view_customers,
            ]);
            $invitation->update(['accepted_at' => now()]);
        });

        return redirect()->route('login')->with('success', 'Invitation accepted. You can now sign in.');
    }

    private function validInvitation(string $token): Builder
    {
        return StaffInvitation::on(config('tenancy.database.central_connection'))
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now());
    }
}
