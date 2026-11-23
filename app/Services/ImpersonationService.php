<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class ImpersonationService
{
    public function start(User $target, int $impersonatorId, Session $session): void
    {
        if (! $target->tenant_id) {
            throw new InvalidArgumentException('The selected user is not linked to a tenant.');
        }

        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $impersonator = User::on(config('tenancy.database.central_connection'))->findOrFail($impersonatorId);
        Auth::login($target);
        $session->regenerate();
        $session->put('credential_version', $target->credential_version);
        $session->put('impersonator_id', $impersonatorId);
        $session->put('impersonator_credential_version', $impersonator->credential_version);
        $session->put('tenant_id', $target->tenant_id);

        tenancy()->initialize($target->tenant_id);
    }

    public function stop(Session $session): ?User
    {
        $impersonatorId = $session->pull('impersonator_id');
        $session->forget('impersonator_credential_version');

        if (! $impersonatorId) {
            return null;
        }

        $impersonator = User::find($impersonatorId);

        if (! $impersonator) {
            $session->invalidate();
            return null;
        }

        if (tenancy()->initialized) {
            tenancy()->end();
        }

        Auth::login($impersonator);
        $session->regenerate(true);
        $session->put('credential_version', $impersonator->credential_version);
        $session->forget('tenant_id');

        return $impersonator;
    }
}
