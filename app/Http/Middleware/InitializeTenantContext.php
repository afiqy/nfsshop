<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Stancl\Tenancy\Database\Models\Tenant;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenantContext
{
    /**
     * Ensure the authenticated user operates within their tenant database.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            if (Auth::user()->isSuperAdmin()) {
                if (tenancy()->initialized) {
                    tenancy()->end();
                }

                if ($request->session()->has('tenant_id')) {
                    $request->session()->forget('tenant_id');
                }

                return $next($request);
            }

            $tenantId = Auth::user()->tenant_id ?? session('tenant_id');

            if ($tenantId) {
                if (! tenancy()->initialized || tenant('id') !== $tenantId) {
                    if (! Tenant::find($tenantId)) {
                        Auth::logout();
                        $request->session()->invalidate();
                        $request->session()->regenerateToken();

                        return redirect()
                            ->route('login')
                            ->withErrors(['email' => 'We could not find the workshop linked to your account. Please log in again.']);
                    }

                    tenancy()->initialize($tenantId);
                }

                if (session('tenant_id') !== $tenantId) {
                    session(['tenant_id' => $tenantId]);
                }
            }
        }

        return $next($request);
    }
}
