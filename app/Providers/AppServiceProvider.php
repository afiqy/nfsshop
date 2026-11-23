<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Auth\Notifications\ResetPassword;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->loadMigrationsFrom([
            database_path('migrations'),
            database_path('migrations/tenant'),
        ]);
    }


    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Config::set('laravelpwa', config('laravel-pwa'));
        ResetPassword::createUrlUsing(fn ($user, $token) => rtrim(config('app.url'), '/').route('password.reset', [
            'token' => $token,
            'email' => $user->getEmailForPasswordReset(),
        ], false));
    }
}
