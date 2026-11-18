<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\WorkshopUpdateRequest;
use App\Models\AccountSubscription;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkshopSubscription;
use App\Services\SubscriptionEntitlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;

class WorkshopController extends Controller
{
    public function index(Request $request): View
    {
        $tenants = Tenant::query()
            ->when(
                ! $request->user()->isSuperAdmin(),
                fn ($query) => $query->where('account_owner_id', $request->user()->id)
            )
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Tenant $tenant) {
                return $this->mapTenantWithMetrics($tenant);
            });

        if ($search = trim($request->input('search', ''))) {
            $tenants = $tenants->filter(function (array $workshop) use ($search) {
                $haystack = strtolower(
                    $workshop['id'].' '.
                    $workshop['name'].' '.
                    $workshop['owner_email'].' '.
                    implode(' ', $workshop['owners']->pluck('name')->all())
                );

                return str_contains($haystack, strtolower($search));
            });
        }

        if ($status = $request->input('subscription_status')) {
            $tenants = $tenants->filter(function (array $workshop) use ($status) {
                return ($workshop['subscription']['status'] ?? null) === $status;
            });
        }

        $tenants = $tenants->values();

        return view('super-admin.workshops.index', [
            'workshops' => $tenants,
            'accountArea' => $request->user()->isAccountSuperAdmin(),
            'filters' => [
                'search' => $request->input('search'),
                'subscription_status' => $request->input('subscription_status'),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        return view('super-admin.workshops.add', [
            'accountArea' => $request->user()->isAccountSuperAdmin(),
            'workshop' => [
                'services' => [],
                'subscription' => [],
                'pricing' => ['currency' => 'USD'],
            ],
        ]);
    }

    public function store(
        WorkshopUpdateRequest $request,
        SubscriptionEntitlementService $entitlements
    ): RedirectResponse
    {
        $payload = $request->validated();

        if (! $entitlements->canCreateWorkshop($request->user())) {
            return back()
                ->withInput()
                ->withErrors(['workshop_limit' => 'Your subscription does not allow another workshop branch.']);
        }

        $tenant = Tenant::create([
            'id' => Str::slug($payload['workshop_name'].'-'.Str::random(5)),
            'account_owner_id' => $request->user()->id,
            'data' => [
                'workshop_name' => $payload['workshop_name'],
                'owner_email' => $payload['owner_email'],
            ],
        ]);

        return redirect()
            ->route($request->user()->isAccountSuperAdmin()
                ? 'account.workshops.edit'
                : 'super-admin.workshops.edit', $tenant)
            ->with('success', 'Workshop created successfully. You can now configure its details.');
    }

    public function edit(Request $request, Tenant $workshop): View
    {
        $this->authorizeWorkshopAccess($request, $workshop);

        $workshopData = $this->mapTenantWithMetrics($workshop);

        return view('super-admin.workshops.edit', [
            'workshop' => $workshopData,
            'accountArea' => $request->user()->isAccountSuperAdmin(),
        ]);
    }

    public function update(WorkshopUpdateRequest $request, Tenant $workshop): RedirectResponse
    {
        $this->authorizeWorkshopAccess($request, $workshop);

        $payload = $request->validated();

        $data = $workshop->data ?? [];

        $services = array_values(array_filter(array_map('trim', explode(',', $payload['services'] ?? ''))));

        $updated = array_merge($data, [
            'workshop_name' => $payload['workshop_name'],
            'owner_email' => $payload['owner_email'],
            'address' => $payload['address'] ?? null,
            'phone' => $payload['phone'] ?? null,
            'services' => $services,
            'staff_notes' => $payload['staff_notes'] ?? null,
            'pricing' => [
                'currency' => $payload['currency'] ?? Arr::get($data, 'pricing.currency', 'USD'),
                'tax_rate' => $payload['tax_rate'],
                'labor_rate' => $payload['labor_rate'],
                'callout_fee' => $payload['callout_fee'],
            ],
        ]);

        $workshop->update(['data' => $updated]);

        User::query()
            ->where('tenant_id', $workshop->id)
            ->update(['workshop_name' => $payload['workshop_name']]);

        return redirect()
            ->route($request->user()->isAccountSuperAdmin()
                ? 'account.workshops.edit'
                : 'super-admin.workshops.edit', $workshop)
            ->with('success', 'Workshop details updated successfully.');
    }

    protected function mapTenantWithMetrics(Tenant $tenant): array
    {
        $data = $tenant->data ?? [];

        $users = User::query()
            ->where('tenant_id', $tenant->id)
            ->get();

        $owners = $users->where('role', User::ROLE_ADMIN)->values();
        $staffCount = $users->whereIn('role', [User::ROLE_ADMIN, User::ROLE_MECHANIC])->count();
        $clientCount = $users->where('role', User::ROLE_CLIENT)->count();

        $accountSubscription = $tenant->account_owner_id
            ? AccountSubscription::query()
                ->with('plan')
                ->where('user_id', $tenant->account_owner_id)
                ->first()
            : null;

        $subscription = $accountSubscription
            ? [
                'plan' => $accountSubscription->plan->name,
                'status' => $accountSubscription->status,
                'monthly_fee' => null,
                'next_billing_date' => $accountSubscription->current_period_ends_at?->toDateString(),
                'notes' => null,
            ]
            : ($data['subscription'] ?? [
                'plan' => '—',
                'status' => 'pending',
                'monthly_fee' => null,
                'next_billing_date' => null,
                'notes' => null,
            ]);

        $nextBillingDateFormatted = null;
        $nextBillingDateValue = null;

        if (! empty($subscription['next_billing_date'])) {
            try {
                $parsed = Carbon::parse($subscription['next_billing_date']);
                $nextBillingDateFormatted = $parsed->format('M d, Y');
                $nextBillingDateValue = $parsed->format('Y-m-d');
            } catch (\Throwable $e) {
                $nextBillingDateFormatted = null;
                $nextBillingDateValue = null;
            }
        }

        $subscription['next_billing_date_formatted'] = $nextBillingDateFormatted;
        $subscription['next_billing_date_value'] = $nextBillingDateValue;

        $pricing = $data['pricing'] ?? [
            'currency' => 'USD',
            'tax_rate' => null,
            'labor_rate' => null,
            'callout_fee' => null,
        ];

        $subscriptionRecord = $accountSubscription
            ?? WorkshopSubscription::query()->where('tenant_id', $tenant->id)->with('plan')->first();

        return [
            'id' => $tenant->id,
            'name' => $data['workshop_name'] ?? $tenant->id,
            'owner_email' => $data['owner_email'] ?? $owners->first()->email ?? null,
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
            'services' => $data['services'] ?? [],
            'staff_notes' => $data['staff_notes'] ?? null,
            'pricing' => $pricing,
            'subscription' => $subscription,
            'subscriptionRecord' => $subscriptionRecord,
            'subscriptionPlan' => $subscriptionRecord?->plan,
            'owners' => $owners,
            'metrics' => [
                'staff_count' => $staffCount,
                'client_count' => $clientCount,
                'created_at' => $tenant->created_at,
                'updated_at' => $tenant->updated_at,
            ],
            'raw' => $tenant,
        ];
    }

    private function authorizeWorkshopAccess(Request $request, Tenant $workshop): void
    {
        abort_unless(
            $request->user()->isSuperAdmin()
                || (int) $workshop->account_owner_id === $request->user()->id,
            404
        );
    }

}
