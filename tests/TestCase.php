<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Contracts\Auth\Authenticatable;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    public function actingAs(Authenticatable $user, $guard = null)
    {
        $previousId = $this->app['auth']->guard($guard)->user()?->getAuthIdentifier();
        if ($previousId !== $user->getAuthIdentifier()) {
            $this->app['session.store']->forget([
                'device_session_id', 'credential_version', 'mfa_verified_user', 'mfa_verified_secret',
                'mfa_verified_version', 'mfa_verified_until',
            ]);
        }

        return parent::actingAs($user, $guard);
    }
}
