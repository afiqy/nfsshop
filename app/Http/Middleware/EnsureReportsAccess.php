<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureReportsAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->attributes->get('account_subscription')?->canViewReports(), 403,
            'Reports are available on Pro, Enterprise, or during an active trial.');

        return $next($request);
    }
}
