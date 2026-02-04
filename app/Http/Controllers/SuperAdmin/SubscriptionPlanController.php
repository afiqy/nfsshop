<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\SubscriptionPlanStoreRequest;
use App\Http\Requests\SuperAdmin\SubscriptionPlanUpdateRequest;
use App\Models\SubscriptionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SubscriptionPlanController extends Controller
{
    public function index(): View
    {
        $plans = SubscriptionPlan::query()
            ->orderByDesc('is_active')
            ->orderBy('price')
            ->get();

        return view('super-admin.billing.plans.index', compact('plans'));
    }

    public function create(): View
    {
        return view('super-admin.billing.plans.create');
    }

    public function store(SubscriptionPlanStoreRequest $request): RedirectResponse
    {
        $plan = SubscriptionPlan::create($request->validated());

        return redirect()
            ->route('super-admin.billing.plans.edit', $plan)
            ->with('success', 'Subscription plan created successfully.');
    }

    public function edit(SubscriptionPlan $plan): View
    {
        return view('super-admin.billing.plans.edit', compact('plan'));
    }

    public function update(SubscriptionPlanUpdateRequest $request, SubscriptionPlan $plan): RedirectResponse
    {
        $plan->update($request->validated());

        return redirect()
            ->route('super-admin.billing.plans.edit', $plan)
            ->with('success', 'Subscription plan updated successfully.');
    }

    public function destroy(SubscriptionPlan $plan): RedirectResponse
    {
        if ($plan->workshopSubscriptions()->exists()) {
            return back()->withErrors('Cannot delete plan while workshops are assigned. Deactivate instead.');
        }

        $plan->delete();

        return redirect()
            ->route('super-admin.billing.plans.index')
            ->with('success', 'Subscription plan removed.');
    }
}
