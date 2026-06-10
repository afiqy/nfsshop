<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\WorkshopUpdateRequest;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\WorkshopSubscription;
use Illuminate\Support\Str;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Stancl\Tenancy\Database\Models\Tenant;

class WorkshopController extends Controller
{
    public function index(Request $request): View
    {
        $tenants = Tenant::query()
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
            'filters' => [
                'search' => $request->input('search'),
                'subscription_status' => $request->input('subscription_status'),
            ],
        ]);
    }

    public function create(): View
    {
        return view('super-admin.workshops.add', [
            'plans' => SubscriptionPlan::orderBy('price')->get(),
            'workshop' => [
                'services' => [],
                'subscription' => [],
                'pricing' => ['currency' => 'USD'],
            ],
        ]);
    }

    public function store(WorkshopUpdateRequest $request): RedirectResponse
    {
        $payload = $request->validated();

        $tenant = Tenant::create([
            'id' => Str::slug($payload['workshop_name'].'-'.Str::random(5)),
            'data' => [
                'workshop_name' => $payload['workshop_name'],
                'owner_email' => $payload['owner_email'],
            ],
        ]);

        return redirect()
            ->route('super-admin.workshops.edit', $tenant)
            ->with('success', 'Workshop created successfully. You can now configure its details.');
    }

    public function edit(Tenant $workshop): View
    {
        $workshopData = $this->mapTenantWithMetrics($workshop);

        return view('super-admin.workshops.edit', [
            'workshop' => $workshopData,
            'plans' => SubscriptionPlan::orderBy('price')->get(),
        ]);
    }

    public function update(WorkshopUpdateRequest $request, Tenant $workshop): RedirectResponse
    {
        $payload = $request->validated();

        $data = $workshop->data ?? [];

        $services = array_values(array_filter(array_map('trim', explode(',', $payload['services'] ?? ''))));

        $planId = $payload['subscription_plan_id'] ?? null;

        if (! $planId && ! empty($payload['subscription_plan_label'])) {
            $planId = SubscriptionPlan::query()
                ->where('name', $payload['subscription_plan_label'])
                ->orWhere('slug', Str::slug($payload['subscription_plan_label']))
                ->value('id');
        }

        $plan = $planId ? SubscriptionPlan::find($planId) : null;

        $planLabel = $payload['subscription_plan_label']
            ?? $plan?->name
            ?? ($data['subscription']['plan'] ?? 'Standard');

        $subscriptionPayload = [
            'plan' => $planLabel,
            'plan_id' => $plan?->id,
            'status' => $payload['subscription_status'],
            'monthly_fee' => $payload['monthly_fee'],
            'next_billing_date' => $payload['next_billing_date'],
            'notes' => $payload['subscription_notes'] ?? null,
        ];

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
            'subscription' => $subscriptionPayload,
        ]);

        $workshop->update(['data' => $updated]);

        User::query()
            ->where('tenant_id', $workshop->id)
            ->update(['workshop_name' => $payload['workshop_name']]);

        WorkshopSubscription::updateOrCreate(
            ['tenant_id' => $workshop->id],
            [
                'subscription_plan_id' => $plan?->id,
                'status' => $payload['subscription_status'],
                'amount' => $payload['monthly_fee'],
                'next_billing_date' => $payload['next_billing_date'],
                'notes' => $payload['subscription_notes'] ?? null,
            ]
        );

        return redirect()
            ->route('super-admin.workshops.edit', $workshop)
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

        $subscription = $data['subscription'] ?? [
            'plan' => 'standard',
            'status' => 'active',
            'monthly_fee' => null,
            'next_billing_date' => null,
            'notes' => null,
        ];

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

        $subscriptionRecord = WorkshopSubscription::query()->where('tenant_id', $tenant->id)->with('plan')->first();

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

}
