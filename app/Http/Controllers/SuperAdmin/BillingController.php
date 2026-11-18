<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\SubscriptionEntitlementService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function dashboard(Request $request, SubscriptionEntitlementService $entitlements): View
    {
        $subscription = $entitlements->subscriptionFor($request->user());
        $workshopCount = $entitlements->workshopCount($request->user());

        return view('super-admin.billing.dashboard', [
            'subscription' => $subscription,
            'workshopCount' => $workshopCount,
            'allowedWorkshops' => $subscription?->allowedWorkshops(),
        ]);
    }
}
