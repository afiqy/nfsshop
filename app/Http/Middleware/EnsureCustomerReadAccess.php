<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerReadAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user->status === User::STATUS_ACTIVE && (
            $user->role === User::ROLE_ADMIN ||
            ($user->role === User::ROLE_MECHANIC && $user->can_view_customers)
        ), 403);

        return $next($request);
    }
}
