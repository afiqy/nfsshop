<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AuthenticatedSession
{
    public function complete(Request $request, User $user, DeviceSessions $devices): RedirectResponse
    {
        $user->resetLoginAttempts();
        $request->session()->regenerate();
        $request->session()->forget(['sso_intent', 'sso_provider', 'sso_link_user_id']);
        $request->session()->put('credential_version', $user->credential_version);
        $request->session()->put('last_activity', now());
        $devices->start($request, $user);

        return redirect()->intended($user->isSuperAdmin()
            ? route('super-admin.dashboard')
            : ($user->isAccountSuperAdmin()
                ? route('account.workshops.index')
                : ($user->role === User::ROLE_CLIENT ? route('client.portal') : route('dashboard'))));
    }
}
