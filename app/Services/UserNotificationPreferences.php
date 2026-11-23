<?php

namespace App\Services;

use App\Models\UserProfile;

class UserNotificationPreferences
{
    public function allowsEmail(int|string|null $userId, string $preference): bool
    {
        if ($userId === null) {
            return true;
        }

        $profile = UserProfile::query()->where('user_id', $userId)->first();

        return $profile?->allowsEmail($preference) ?? true;
    }
}
