<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Stancl\Tenancy\Database\Models\Tenant;
use Stancl\Tenancy\Tenancy;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenantContext
{
    public function __construct(private Tenancy $tenancy)
    {
    }

    /**
     * Ensure the authenticated user operates within their tenant database.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            if (Auth::user()->isSuperAdmin() || Auth::user()->isAccountSuperAdmin()) {
                if ($this->tenancy->initialized) {
                    $this->tenancy->end();
                }

                if ($request->session()->has('tenant_id')) {
                    $request->session()->forget('tenant_id');
                }

                return $next($request);
            }

            $tenantId = Auth::user()->tenant_id ?? session('tenant_id');

            if ($tenantId) {
                if (! $this->tenancy->initialized || $this->tenancy->tenant?->getTenantKey() !== $tenantId) {
                    if (! Tenant::find($tenantId)) {
                        Auth::logout();
                        $request->session()->invalidate();
                        $request->session()->regenerateToken();

                        return redirect()
                            ->route('login')
                            ->withErrors(['email' => 'We could not find the workshop linked to your account. Please log in again.']);
                    }

                    $this->tenancy->initialize($tenantId);
                }

                if (session('tenant_id') !== $tenantId) {
                    session(['tenant_id' => $tenantId]);
                }
            }
        }

        return $next($request);
    }
}
