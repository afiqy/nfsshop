<?php

namespace App\Http\Controllers;

use App\Models\User;
use Stancl\Tenancy\Database\Models\Tenant;

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

        $workshopMetrics = ['total' => $tenants->count()];

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
            'tenantsWithoutOwner' => $tenantsWithoutOwner,
        ]);
    }
}
