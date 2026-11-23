<?php

namespace App\Console;

use App\Models\Calendar;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('audit:prune')->daily();
        $schedule->command('invoices:send-payment-reminders')->hourly()->withoutOverlapping();
        $schedule->command('appointments:send-notifications')->everyMinute()->withoutOverlapping();
        $schedule->command('vehicles:send-reminders')->dailyAt('09:00')->timezone(Calendar::TIMEZONE)->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
