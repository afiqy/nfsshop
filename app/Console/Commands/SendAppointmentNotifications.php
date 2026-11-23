<?php

namespace App\Console\Commands;

use App\Models\AccountSubscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AppointmentNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendAppointmentNotifications extends Command
{
    protected $signature = 'appointments:send-notifications';

    protected $description = 'Queue appointment reminders and send pending appointment emails';

    public function handle(AppointmentNotificationService $notifications): int
    {
        $failed = false;
        foreach (Tenant::query()->lazyById() as $workshop) {
            $ownerId = $workshop->account_owner_id ?: User::query()->where('tenant_id', $workshop->id)
                ->where('role', User::ROLE_ADMIN)->value('id');
            $subscription = $ownerId ? AccountSubscription::query()->where('user_id', $ownerId)->first() : null;
            if (! $subscription?->isUsable()) {
                continue;
            }
            try {
                tenancy()->initialize($workshop);
                $notifications->queueReminders();
                $count = $notifications->deliverPending();
                if ($count > 0) {
                    $failed = true;
                    $this->error("{$count} appointment email(s) failed for workshop {$workshop->id}; review the appointment requests page.");
                }
            } catch (Throwable $exception) {
                $failed = true;
                Log::error('Appointment notification processing failed for workshop.', [
                    'tenant_id' => $workshop->id, 'exception_class' => $exception::class,
                ]);
                $this->error("Appointment notification processing failed for workshop {$workshop->id}; check application logs.");
            } finally {
                if (tenancy()->initialized) {
                    tenancy()->end();
                }
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
