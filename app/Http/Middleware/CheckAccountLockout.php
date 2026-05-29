<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAccountLockout
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->isLocked()) {
            auth()->logout();
            $request->session()->invalidate();
            return redirect()->route('login')->withErrors([
                'email' => 'Your account is locked. Please try again later.',
            ]);
        }

        return $next($request);
    }
}
