<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInventoryAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->attributes->get('account_subscription')?->canManageInventory(), 403,
            'Inventory is available on Pro, Enterprise, or during an active trial.');

        return $next($request);
    }
}
