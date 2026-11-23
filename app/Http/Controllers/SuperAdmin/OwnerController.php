<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\OwnerStoreRequest;
use App\Http\Requests\SuperAdmin\OwnerUpdateRequest;
use App\Models\User;
use App\Services\ImpersonationService;
use App\Services\PasswordCredentials;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Stancl\Tenancy\Database\Models\Tenant;

class OwnerController extends Controller
{
    public function __construct(private readonly ImpersonationService $impersonationService)
    {
    }

    public function index(Request $request): View
    {
        $query = $this->buildOwnerQuery($request);

        $owners = $query
            ->with('tenant')
            ->paginate(15)
            ->withQueryString();

        $tenantsWithoutOwner = Tenant::query()
            ->when(User::query()->where('role', User::ROLE_ADMIN)->whereNotNull('tenant_id')->exists(), function ($tenantQuery) {
                $tenantQuery->whereNotIn('id', function ($subQuery) {
                    $subQuery->select('tenant_id')
                        ->from('users')
                        ->where('role', User::ROLE_ADMIN)
                        ->whereNotNull('tenant_id');
                });
            })
            ->orderBy('id')
            ->get();

        return view('super-admin.owners.index', [
            'owners' => $owners,
            'filters' => $request->only(['search', 'status']),
            'tenantsWithoutOwner' => $tenantsWithoutOwner,
        ]);
    }

    public function create(): View
    {
        return view('super-admin.owners.create', [
            'tenants' => Tenant::orderBy('id')->get(['id', 'data']),
        ]);
    }

    public function store(OwnerStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data) {
            $tenantId = $data['tenant_id'] ?? $this->generateTenantId($data['workshop_name']);

            $tenant = Tenant::find($tenantId);

            $tenantPayload = [
                'workshop_name' => $data['workshop_name'],
                'owner_email' => $data['email'],
                'address' => $data['workshop_address'] ?? null,
                'phone' => $data['workshop_phone'] ?? null,
            ];

            if (! $tenant) {
                $tenant = Tenant::create([
                    'id' => $tenantId,
                    'data' => $tenantPayload,
                ]);
            } else {
                $tenant->update([
                    'data' => array_merge($tenant->data ?? [], $tenantPayload),
                ]);
            }

            User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => User::ROLE_ADMIN,
                'tenant_id' => $tenant->id,
                'workshop_name' => $data['workshop_name'],
                'status' => User::STATUS_ACTIVE,
            ]);
        });

        return redirect()->route('super-admin.owners.index')->with('success', 'Workshop owner created successfully.');
    }

    public function edit(User $owner): View
    {
        $this->ensureOwner($owner);

        return view('super-admin.owners.edit', [
            'owner' => $owner->load('tenant'),
            'tenants' => Tenant::orderBy('id')->get(['id', 'data']),
        ]);
    }

    public function update(OwnerUpdateRequest $request, User $owner, PasswordCredentials $credentials): RedirectResponse
    {
        $this->ensureOwner($owner);

        $data = $request->validated();

        DB::transaction(function () use ($data, $owner, $credentials) {
            $owner = User::query()->lockForUpdate()->findOrFail($owner->id);
            $tenantId = $data['tenant_id'] ?? $owner->tenant_id ?? $this->generateTenantId($data['workshop_name']);

            $tenant = Tenant::find($tenantId);

            $tenantPayload = [
                'workshop_name' => $data['workshop_name'],
                'owner_email' => $data['email'],
                'address' => $data['workshop_address'] ?? null,
                'phone' => $data['workshop_phone'] ?? null,
            ];

            if (! $tenant) {
                $tenant = Tenant::create([
                    'id' => $tenantId,
                    'data' => $tenantPayload,
                ]);
            } else {
                $tenant->update([
                    'data' => array_merge($tenant->data ?? [], $tenantPayload),
                ]);
            }

            $updates = [
                'name' => $data['name'],
                'email' => $data['email'],
                'tenant_id' => $tenant->id,
                'workshop_name' => $data['workshop_name'],
            ];

            if ($owner->email !== $data['email']) {
                $credentials->deleteTokens($owner->email, $data['email']);
            }
            $owner->update($updates);
            if (! empty($data['password'])) {
                $credentials->updateLocked($owner, $data['password']);
            }
        });

        return redirect()->route('super-admin.owners.index')->with('success', 'Workshop owner updated successfully.');
    }

    public function destroy(User $owner): RedirectResponse
    {
        $this->ensureOwner($owner);

        if ($owner->id === Auth::id()) {
            return redirect()->back()->withErrors('You cannot delete your own account.');
        }

        $owner->delete();

        return redirect()->route('super-admin.owners.index')->with('success', 'Workshop owner deleted successfully.');
    }

    public function suspend(Request $request, User $owner): RedirectResponse
    {
        $this->ensureOwner($owner);

        $owner->update([
            'status' => User::STATUS_SUSPENDED,
            'suspended_at' => Carbon::now(),
            'suspension_reason' => $request->input('reason'),
        ]);

        return redirect()->back()->with('success', 'Owner account suspended.');
    }

    public function activate(User $owner): RedirectResponse
    {
        $this->ensureOwner($owner);

        $owner->update([
            'status' => User::STATUS_ACTIVE,
            'suspended_at' => null,
            'suspension_reason' => null,
        ]);

        return redirect()->back()->with('success', 'Owner account reactivated.');
    }

    public function resetPassword(User $owner, PasswordCredentials $credentials): RedirectResponse
    {
        $this->ensureOwner($owner);

        $temporaryPassword = Str::random(12);

        DB::transaction(function () use ($owner, $credentials, $temporaryPassword) {
            $owner = User::query()->lockForUpdate()->findOrFail($owner->id);
            $credentials->updateLocked($owner, $temporaryPassword);
            $owner->update(['force_password_reset' => true]);
        });

        return redirect()->back()->with('success', "Temporary password generated: {$temporaryPassword}");
    }

    public function impersonate(Request $request, User $owner): RedirectResponse
    {
        $this->ensureOwner($owner);

        $impersonatorId = Auth::id();

        if (! $impersonatorId) {
            return redirect()->route('login');
        }

        try {
            $this->impersonationService->start($owner, (int) $impersonatorId, $request->session());
        } catch (\InvalidArgumentException $exception) {
            return redirect()->back()->withErrors($exception->getMessage());
        }

        return redirect()->route('dashboard')->with('success', 'You are now impersonating the owner.');
    }

    public function leaveImpersonation(Request $request): RedirectResponse
    {
        $impersonator = $this->impersonationService->stop($request->session());

        if (! $impersonator) {
            if (! Auth::check()) {
                return redirect()->route('login');
            }

            return redirect()->route('dashboard')->withErrors('Impersonation session not found.');
        }

        return redirect()->route('super-admin.owners.index')->with('success', 'Impersonation ended.');
    }

    protected function ensureOwner(User $user): void
    {
        abort_unless($user->role === User::ROLE_ADMIN, 404, 'Workshop owner not found.');
    }

    protected function generateTenantId(string $workshopName, ?string $prefix = null): string
    {
        $base = Str::slug($workshopName ?: 'workshop');

        if ($prefix) {
            $base = Str::slug($prefix);
        }

        $slug = $base !== '' ? $base : 'workshop';
        $counter = 1;

        while (Tenant::find($slug)) {
            $slug = $base !== '' ? $base.'-'.$counter : 'workshop-'.$counter;
            $counter++;
        }

        return $slug;
    }

    protected function buildOwnerQuery(Request $request)
    {
        $query = User::query()
            ->where('role', User::ROLE_ADMIN)
            ->orderByDesc('created_at');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('workshop_name', 'like', "%{$search}%");
            });
        }

        $status = $request->input('status');

        if (in_array($status, [User::STATUS_ACTIVE, User::STATUS_SUSPENDED], true)) {
            $query->where('status', $status);
        }

        return $query;
    }
}
