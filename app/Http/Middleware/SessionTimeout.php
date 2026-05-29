<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SessionTimeout
{
    protected $timeout = 30 * 60; // 30 minutes
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()) {
            $lastActivity = $request->session()->get('last_activity');

            if ($lastActivity && now()->diffInSeconds($lastActivity) > $this->timeout) {
                auth()->logout();
                $request->session()->invalidate();
                return redirect()->route('login')->withErrors([
                    'email' => 'Session expired due to inactivity.',
                ]);
            }

            $request->session()->put('last_activity', now());
        }

        return $next($request);
    }
}
