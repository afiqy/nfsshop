<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PasswordCredentials
{
    public function updateLocked(User $user, string $password): void
    {
        $user->forceFill([
            'password' => Hash::make($password),
            'remember_token' => Str::random(60),
            'credential_version' => $user->credential_version + 1,
            'force_password_reset' => false,
        ])->save();
        $this->deleteTokens($user->email);
    }

    public function deleteTokens(string ...$emails): void
    {
        DB::connection(config('tenancy.database.central_connection'))
            ->table(config('auth.passwords.users.table'))->whereIn('email', $emails)->delete();
    }
}
