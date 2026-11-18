<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\SubscriptionEntitlementService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionIsActive
{
    public function __construct(private SubscriptionEntitlementService $entitlements)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->isSuperAdmin()) {
            return $next($request);
        }

        if (! in_array($user->role, [User::ROLE_ADMIN, User::ROLE_ACCOUNT_SUPER_ADMIN, User::ROLE_MECHANIC], true)) {
            return $next($request);
        }

        $subscription = $this->entitlements->subscriptionFor($user);
        $request->attributes->set('account_subscription', $subscription);
        $isUsable = $subscription?->isUsable() ?? false;
        $request->attributes->set('subscription_usable', $isUsable);

        if ($isUsable) {
            return $next($request);
        }

        if ($user->role === User::ROLE_MECHANIC) {
            return redirect()->route('subscription.required');
        }

        if ($user->role === User::ROLE_ADMIN && $request->routeIs('dashboard')) {
            return $next($request);
        }

        $subscriptionRoute = $user->role === User::ROLE_ACCOUNT_SUPER_ADMIN
            ? 'account.subscription'
            : 'workshop.subscription';

        return redirect()
            ->route($subscriptionRoute)
            ->with('warning', 'Your subscription is inactive or expired. Please review your subscription.');
    }
}
