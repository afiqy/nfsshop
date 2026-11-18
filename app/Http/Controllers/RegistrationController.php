<?php

namespace App\Http\Controllers;

use App\Models\AccountSubscription;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkshopSubscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Illuminate\View\View;

class RegistrationController extends Controller
{
    private const SESSION_KEY = 'registration_wizard';

    public function start(Request $request): View
    {
        $data = $this->getWizardData($request);

        if ($tier = $request->query('tier')) {
            $tier = Str::lower($tier);
            if (in_array($tier, ['basic', 'pro'], true)) {
                $data['plan']['tier'] = $tier;
                $this->storeWizardData($request, $data);
            }
        }

        return view('auth.register.account', [
            'state' => $data,
        ]);
    }

    public function submitAccount(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:30'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $state = $this->getWizardData($request);

        $state['account'] = [
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'phone' => $validated['phone'],
            'password_hash' => Hash::make($validated['password']),
        ];

        unset($state['mode']);

        $this->storeWizardData($request, $state);

        return redirect()->route('register.workshop');
    }

    public function showWorkshop(Request $request): RedirectResponse|View
    {
        $state = $this->getWizardData($request);

        if (! isset($state['account'])) {
            return redirect()->route('register');
        }

        return view('auth.register.workshop', [
            'state' => $state,
        ]);
    }

    public function submitWorkshop(Request $request): RedirectResponse
    {
        $state = $this->getWizardData($request);

        if (! isset($state['account'])) {
            return redirect()->route('register');
        }

        $validated = $request->validate([
            'workshop_name' => ['required', 'string', 'max:255'],
            'workshop_address' => ['required', 'string', 'max:255'],
            'business_reg_no' => ['nullable', 'string', 'max:100'],
            'tax_id_number' => ['nullable', 'string', 'max:100'],
            'services_offered' => ['nullable', 'string', 'max:500'],
        ]);

        $normalizedWorkshopName = $this->normalize($validated['workshop_name']);
        $normalizedAddress = $this->normalize($validated['workshop_address']);

        [$existingTenantId, $existingReason] = $this->detectDuplicateWorkshop($normalizedWorkshopName, $normalizedAddress);

        $services = $this->normalizeServices($validated['services_offered'] ?? '');

        $state['workshop'] = [
            'workshop_name' => $validated['workshop_name'],
            'workshop_address' => $validated['workshop_address'],
            'business_reg_no' => $validated['business_reg_no'] ?? null,
            'tax_id_number' => $validated['tax_id_number'] ?? null,
            'services_offered' => $services,
            'normalized_name' => $normalizedWorkshopName,
            'normalized_address' => $normalizedAddress,
        ];

        if ($existingTenantId) {
            $state['mode'] = 'staff';
            $state['staff'] = [
                'tenant_id' => $existingTenantId,
                'reason' => $existingReason,
            ];

            $this->storeWizardData($request, $state);

            return redirect()->route('register.staff');
        }

        $state['mode'] = 'owner';
        unset($state['staff']);

        $this->storeWizardData($request, $state);

        return redirect()->route('register.plan');
    }

    public function showPlan(Request $request): RedirectResponse|View
    {
        $state = $this->getWizardData($request);

        if (! isset($state['account'])) {
            return redirect()->route('register');
        }

        if (($state['mode'] ?? null) === 'staff') {
            return redirect()->route('register.staff');
        }

        if (! isset($state['workshop'])) {
            return redirect()->route('register.workshop');
        }

        return view('auth.register.plan', [
            'state' => $state,
            'billingOptions' => $this->billingOptions(),
        ]);
    }

    public function submitPlan(Request $request): RedirectResponse
    {
        $state = $this->getWizardData($request);

        if (! isset($state['account']) || ! isset($state['workshop'])) {
            return redirect()->route('register');
        }

        if (($state['mode'] ?? null) === 'staff') {
            return redirect()->route('register.staff');
        }

        $validated = $request->validate([
            'plan_tier' => ['required', 'string', Rule::in(['basic', 'pro'])],
            'billing_option' => ['required', 'string', Rule::in(array_keys($this->billingOptions()))],
        ]);

        $state['plan'] = [
            'tier' => $validated['plan_tier'],
            'billing_option' => $validated['billing_option'],
        ];

        $this->storeWizardData($request, $state);

        $this->finalizeOwnerRegistration($request, $state);

        return redirect()->route('register.complete');
    }

    public function showStaff(Request $request): RedirectResponse|View
    {
        $state = $this->getWizardData($request);

        if (! isset($state['account']) || ! isset($state['staff'])) {
            return redirect()->route('register');
        }

        $tenant = Tenant::find($state['staff']['tenant_id']);

        return view('auth.register.staff', [
            'state' => $state,
            'tenant' => $tenant,
        ]);
    }

    public function submitStaff(Request $request): RedirectResponse
    {
        $state = $this->getWizardData($request);

        if (! isset($state['account']) || ! isset($state['staff'])) {
            return redirect()->route('register');
        }

        $validated = $request->validate([
            'desired_role' => ['required', 'string', Rule::in([User::ROLE_MECHANIC, User::ROLE_CLIENT])],
        ]);

        $this->finalizeStaffRegistration($state, $validated['desired_role']);

        $this->clearWizard($request);

        return redirect()->route('login')->with('success', 'Request submitted. The workshop owner must activate your access.');
    }

    public function complete(Request $request): RedirectResponse|View
    {
        if (! $request->session()->has(self::SESSION_KEY.'_completed')) {
            return redirect()->route('dashboard');
        }

        $request->session()->forget(self::SESSION_KEY.'_completed');

        return view('auth.register.complete');
    }

    private function finalizeOwnerRegistration(Request $request, array $state): void
    {
        if (! isset($state['account'], $state['workshop'], $state['plan'])) {
            throw new InvalidArgumentException('Registration state is incomplete.');
        }

        $account = $state['account'];
        $workshop = $state['workshop'];
        $plan = $state['plan'];

        [$tenantId, $user] = DB::transaction(function () use ($account, $workshop, $plan) {
            $tenantId = $this->generateTenantId($workshop['workshop_name']);

            $subscriptionPayload = $this->makeSubscriptionPayload($plan['tier'], $plan['billing_option']);

            $tenantData = [
                'workshop_name' => $workshop['workshop_name'],
                'owner_email' => $account['email'],
                'address' => $workshop['workshop_address'],
                'phone' => $account['phone'],
                'business_reg_no' => $workshop['business_reg_no'],
                'tax_id_number' => $workshop['tax_id_number'],
                'services' => $workshop['services_offered'],
                'subscription' => $subscriptionPayload,
            ];

            $tenant = Tenant::create([
                'id' => $tenantId,
                'data' => $tenantData,
            ]);

            $user = User::create([
                'name' => $account['name'],
                'email' => $account['email'],
                'phone' => $account['phone'],
                'password' => $account['password_hash'],
                'tenant_id' => $tenantId,
                'workshop_name' => $workshop['workshop_name'],
                'role' => User::ROLE_ADMIN,
                'status' => User::STATUS_ACTIVE,
            ]);

            $tenant->update(['account_owner_id' => $user->id]);

            $subscriptionPlan = SubscriptionPlan::query()
                ->where('slug', $plan['tier'])
                ->where('is_active', true)
                ->firstOrFail();

            AccountSubscription::create([
                'user_id' => $user->id,
                'subscription_plan_id' => $subscriptionPlan->id,
                'status' => $subscriptionPayload['status'],
                'current_period_ends_at' => $subscriptionPayload['status'] === WorkshopSubscription::STATUS_TRIALING
                    ? $subscriptionPayload['trial_ends_at']
                    : $subscriptionPayload['next_billing_date'],
                'provider_payload' => [
                    'source' => 'registration',
                    'billing_option' => $plan['billing_option'],
                ],
            ]);

            WorkshopSubscription::create([
                'tenant_id' => $tenantId,
                'subscription_plan_id' => $subscriptionPlan->id,
                'status' => $subscriptionPayload['status'],
                'amount' => $subscriptionPayload['monthly_fee'],
                'overdue_amount' => 0,
                'next_billing_date' => $subscriptionPayload['next_billing_date'] ?? null,
                'last_paid_at' => null,
                'payment_gateway' => null,
                'external_subscription_id' => null,
                'notes' => 'Registered via onboarding wizard',
            ]);

            return [$tenantId, $user];
        });

        Auth::login($user);
        $request->session()->regenerate();

        session(['tenant_id' => $tenantId]);
        tenancy()->initialize($tenantId);

        $this->clearWizard($request);
        $request->session()->put(self::SESSION_KEY.'_completed', true);
    }

    private function finalizeStaffRegistration(array $state, string $role): void
    {
        $account = $state['account'];
        $tenantId = $state['staff']['tenant_id'];
        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            throw new InvalidArgumentException('Workshop not found.');
        }

        User::create([
            'name' => $account['name'],
            'email' => $account['email'],
            'phone' => $account['phone'],
            'password' => $account['password_hash'],
            'tenant_id' => $tenantId,
            'workshop_name' => Arr::get($tenant->data, 'workshop_name', $tenantId),
            'role' => $role,
            'status' => User::STATUS_SUSPENDED,
            'suspended_at' => Carbon::now(),
            'suspension_reason' => 'Awaiting owner approval',
            'force_password_reset' => false,
        ]);
    }

    private function detectDuplicateWorkshop(string $name, string $address): array
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $data = $tenant->data ?? [];
            $existingName = isset($data['workshop_name']) ? $this->normalize($data['workshop_name']) : null;
            $existingAddress = isset($data['address']) ? $this->normalize($data['address']) : null;

            if ($existingName && $existingName === $name) {
                return [$tenant->id, 'workshop_name'];
            }

            if ($existingAddress && $existingAddress === $address) {
                return [$tenant->id, 'workshop_address'];
            }
        }

        return [null, null];
    }

    private function makeSubscriptionPayload(string $tier, string $billingOption): array
    {
        $now = Carbon::now();
        $payload = [
            'plan' => $tier,
            'status' => WorkshopSubscription::STATUS_TRIALING,
            'trial_type' => 'free_trial',
            'trial_starts_at' => $now->toDateString(),
            'trial_ends_at' => $now->clone()->addMonth()->toDateString(),
            'billing_cycle' => null,
            'monthly_fee' => null,
            'next_billing_date' => null,
        ];

        switch ($billingOption) {
            case 'free_trial':
                $payload['trial_type'] = 'free_trial';
                $payload['trial_ends_at'] = $now->clone()->addMonth()->toDateString();
                $payload['status'] = WorkshopSubscription::STATUS_TRIALING;
                break;
            case 'trial_run':
                $payload['trial_type'] = 'trial_run';
                $payload['trial_ends_at'] = $now->clone()->addMonths(3)->toDateString();
                $payload['status'] = WorkshopSubscription::STATUS_TRIALING;
                break;
            case 'paid_monthly':
                $payload['status'] = AccountSubscription::STATUS_PENDING;
                $payload['trial_type'] = 'none';
                $payload['billing_cycle'] = 'monthly';
                $payload['next_billing_date'] = $now->clone()->addMonth()->toDateString();
                break;
            case 'paid_quarterly':
                $payload['status'] = AccountSubscription::STATUS_PENDING;
                $payload['trial_type'] = 'none';
                $payload['billing_cycle'] = 'quarterly';
                $payload['next_billing_date'] = $now->clone()->addMonths(3)->toDateString();
                break;
            case 'paid_annual':
                $payload['status'] = AccountSubscription::STATUS_PENDING;
                $payload['trial_type'] = 'none';
                $payload['billing_cycle'] = 'annual';
                $payload['next_billing_date'] = $now->clone()->addYear()->toDateString();
                break;
        }

        return $payload;
    }

    private function billingOptions(): array
    {
        return [
            'free_trial' => 'Free Trial (1 month)',
            'trial_run' => 'Trial Run (3 months, 50% off)',
            'paid_monthly' => 'Monthly Subscription',
            'paid_quarterly' => 'Quarterly Subscription',
            'paid_annual' => 'Annual Subscription',
        ];
    }

    private function normalize(string $value): string
    {
        return Str::lower(trim($value));
    }

    private function normalizeServices(string $services): array
    {
        if ($services === '') {
            return [];
        }

        return collect(explode(',', $services))
            ->map(fn ($service) => trim($service))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function generateTenantId(string $workshopName): string
    {
        $base = Str::slug($workshopName);
        $slug = $base !== '' ? $base : 'workshop';
        $counter = 1;

        while (Tenant::find($slug)) {
            $slug = $base !== '' ? $base . '-' . $counter : 'workshop-' . $counter;
            $counter++;
        }

        return $slug;
    }

    private function getWizardData(Request $request): array
    {
        return $request->session()->get(self::SESSION_KEY, []);
    }

    private function storeWizardData(Request $request, array $state): void
    {
        $request->session()->put(self::SESSION_KEY, $state);
    }

    private function clearWizard(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }
}
