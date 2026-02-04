<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WorkshopSubscription;
use Stancl\Tenancy\Database\Models\Tenant;
use Illuminate\Support\Arr;

class SuperAdminController extends Controller
{
    public function dashboard()
    {
        $ownersQuery = User::query()->where('role', User::ROLE_ADMIN);

        $owners = $ownersQuery->with('tenant')->orderBy('name')->limit(5)->get();

        $metrics = [
            'total' => (clone $ownersQuery)->count(),
            'active' => (clone $ownersQuery)->where('status', User::STATUS_ACTIVE)->count(),
            'suspended' => (clone $ownersQuery)->where('status', User::STATUS_SUSPENDED)->count(),
        ];

        $tenants = Tenant::query()->get();

        $workshopMetrics = [
            'total' => $tenants->count(),
            'active' => $tenants->filter(function ($tenant) {
                return Arr::get($tenant->data, 'subscription.status') === 'active';
            })->count(),
            'past_due' => $tenants->filter(function ($tenant) {
                return Arr::get($tenant->data, 'subscription.status') === 'past_due';
            })->count(),
        ];

        $billingMetrics = [
            'mrr' => WorkshopSubscription::query()
                ->where('status', WorkshopSubscription::STATUS_ACTIVE)
                ->sum('amount'),
            'overdue' => WorkshopSubscription::query()->sum('overdue_amount'),
            'past_due_accounts' => WorkshopSubscription::query()
                ->where('status', WorkshopSubscription::STATUS_PAST_DUE)
                ->count(),
        ];

        $tenantsWithoutOwner = Tenant::query()
            ->whereNotIn('id', function ($query) {
                $query->select('tenant_id')
                    ->from('users')
                    ->where('role', User::ROLE_ADMIN)
                    ->whereNotNull('tenant_id');
            })
            ->orderBy('id')
            ->limit(10)
            ->get();

        return view('super-admin.dashboard', [
            'recentOwners' => $owners,
            'metrics' => $metrics,
            'workshopMetrics' => $workshopMetrics,
            'billingMetrics' => $billingMetrics,
            'tenantsWithoutOwner' => $tenantsWithoutOwner,
        ]);
    }
}
