<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\WorkshopSubscriptionUpdateRequest;
use App\Models\SubscriptionPlan;
use App\Models\WorkshopSubscription;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Stancl\Tenancy\Database\Models\Tenant;

class BillingController extends Controller
{
    public function dashboard(Request $request): View
    {
        $subscriptions = WorkshopSubscription::query()
            ->with(['plan', 'tenant'])
            ->orderByRaw("FIELD(status, 'past_due', 'active', 'trialing', 'canceled')")
            ->orderByDesc('next_billing_date')
            ->get();

        if ($status = $request->input('status')) {
            $subscriptions = $subscriptions->where('status', $status);
        }

        if ($planId = $request->input('plan_id')) {
            $subscriptions = $subscriptions->where('subscription_plan_id', (int) $planId);
        }

        $subscriptions = $subscriptions->values();

        $metrics = [
            'monthly_recurring_revenue' => $subscriptions->where('status', WorkshopSubscription::STATUS_ACTIVE)->sum('amount'),
            'overdue_total' => $subscriptions->sum('overdue_amount'),
            'trialing_count' => $subscriptions->where('status', WorkshopSubscription::STATUS_TRIALING)->count(),
            'past_due_count' => $subscriptions->where('status', WorkshopSubscription::STATUS_PAST_DUE)->count(),
        ];

        $plans = SubscriptionPlan::orderBy('price')->get(['id', 'name']);

        return view('super-admin.billing.dashboard', [
            'subscriptions' => $subscriptions,
            'metrics' => $metrics,
            'filters' => $request->only(['status', 'plan_id']),
            'plans' => $plans,
        ]);
    }

    public function syncTenant(string $tenantId): RedirectResponse
    {
        $tenant = Tenant::findOrFail($tenantId);

        $planLabel = Arr::get($tenant->data, 'subscription.plan');

        $planId = null;

        if ($planLabel) {
            $planId = SubscriptionPlan::query()
                ->where('name', $planLabel)
                ->orWhere('slug', Str::slug($planLabel))
                ->value('id');
        }

        WorkshopSubscription::updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'status' => $tenant->data['subscription']['status'] ?? WorkshopSubscription::STATUS_ACTIVE,
                'amount' => $tenant->data['subscription']['monthly_fee'] ?? null,
                'next_billing_date' => $tenant->data['subscription']['next_billing_date'] ?? null,
                'subscription_plan_id' => $planId,
                'notes' => $tenant->data['subscription']['notes'] ?? null,
            ]
        );

        return back()->with('success', 'Subscription data synced from tenant profile.');
    }

    public function update(WorkshopSubscriptionUpdateRequest $request, WorkshopSubscription $subscription): RedirectResponse
    {
        $subscription->update($request->validated());

        return back()->with('success', 'Subscription record updated.');
    }
}
