<?php

namespace App\Services;

use App\Models\AccountSubscription;
use App\Models\Tenant;
use App\Models\User;

class SubscriptionEntitlementService
{
    public function subscriptionFor(User $user): ?AccountSubscription
    {
        $accountOwnerId = $user->id;

        if ($user->tenant_id) {
            $accountOwnerId = Tenant::query()
                ->whereKey($user->tenant_id)
                ->value('account_owner_id') ?? $user->id;
        }

        return AccountSubscription::query()
            ->with('plan')
            ->where('user_id', $accountOwnerId)
            ->first();
    }

    public function workshopCount(User $user): int
    {
        return Tenant::query()->where('account_owner_id', $user->id)->count();
    }

    public function canCreateWorkshop(User $user): bool
    {
        $subscription = $this->subscriptionFor($user);

        if (! $subscription?->isUsable()) {
            return false;
        }

        $allowed = $subscription->allowedWorkshops();

        return $this->workshopCount($user) < $allowed;
    }

}