<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionEntitlementService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClientEngagementAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $subscription = $request->attributes->get('account_subscription')
            ?? app(SubscriptionEntitlementService::class)->subscriptionFor($request->user());
        abort_unless($subscription?->canUseClientEngagement(), 403,
            'Client engagement is available on Pro, Enterprise, or during an active trial.');

        return $next($request);
    }
}
