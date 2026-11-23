<?php

namespace App\Console\Commands;

use App\Models\AccountSubscription;
use App\Models\Calendar;
use App\Models\Tenant;
use App\Models\User;
use App\Services\VehicleReminderService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendVehicleReminders extends Command
{
    protected $signature = 'vehicles:send-reminders';

    protected $description = 'Send opted-in vehicle service and renewal reminder emails';

    public function handle(VehicleReminderService $reminders): int
    {
        $failed = false;
        $date = CarbonImmutable::now(Calendar::TIMEZONE);

        foreach (Tenant::query()->lazyById() as $workshop) {
            $ownerId = $workshop->account_owner_id ?: User::query()->where('tenant_id', $workshop->id)
                ->where('role', User::ROLE_ADMIN)->value('id');
            $subscription = $ownerId ? AccountSubscription::query()->where('user_id', $ownerId)->first() : null;
            if (! $subscription?->isUsable()) {
                continue;
            }

            try {
                tenancy()->initialize($workshop);
                $count = $reminders->processDate($date);
                if ($count > 0) {
                    $failed = true;
                    $this->error("{$count} vehicle reminder email(s) failed for workshop {$workshop->id}; review the engagement page.");
                }
            } catch (Throwable $exception) {
                $failed = true;
                Log::error('Vehicle reminder processing failed for workshop.', [
                    'tenant_id' => $workshop->id,
                    'exception_class' => $exception::class,
                ]);
                $this->error("Vehicle reminder processing failed for workshop {$workshop->id}; check application logs.");
            } finally {
                if (tenancy()->initialized) {
                    tenancy()->end();
                }
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
