<?php

namespace App\Http\Middleware;

use App\Models\UserProfile;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SetUserPreferences
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $profile = $user
            ? UserProfile::query()->where('user_id', $user->id)->first()
            : null;
        $locale = $profile?->preferred_language ?? 'en';
        $timezone = $profile?->timezone ?? 'UTC';

        if (! in_array($locale, ['en', 'ms'], true)) {
            Log::warning('Invalid profile language preference; using English.', ['user_id' => $user?->id]);
            $locale = 'en';
        }

        if (! in_array($timezone, timezone_identifiers_list(), true)) {
            Log::warning('Invalid profile timezone preference; using UTC.', ['user_id' => $user?->id]);
            $timezone = 'UTC';
        }

        App::setLocale($locale);
        view()->share('userTimezone', $timezone);

        return $next($request);
    }
}
