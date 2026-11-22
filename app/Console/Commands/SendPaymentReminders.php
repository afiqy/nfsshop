<?php

namespace App\Console\Commands;

use App\Models\AccountSubscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InvoicePaymentReminderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendPaymentReminders extends Command
{
    protected $signature = 'invoices:send-payment-reminders';

    protected $description = 'Send due payment reminders for opted-in workshops';

    public function handle(InvoicePaymentReminderService $reminders): int
    {
        $failed = false;
        foreach (Tenant::query()->lazyById() as $workshop) {
            $ownerId = $workshop->account_owner_id;
            if (! $ownerId) {
                $ownerId = User::query()->where('tenant_id', $workshop->id)
                    ->where('role', User::ROLE_ADMIN)->value('id');
            }
            $subscription = $ownerId ? AccountSubscription::query()->where('user_id', $ownerId)->first() : null;
            if (! $subscription?->isUsable()) {
                continue;
            }

            try {
                tenancy()->initialize($workshop);
                $reminders->processToday();
            } catch (Throwable $exception) {
                $failed = true;
                Log::error('Payment reminder processing failed for workshop.', [
                    'tenant_id' => $workshop->id,
                    'exception_class' => $exception::class,
                ]);
                $this->error("Payment reminder processing failed for workshop {$workshop->id}; check application logs.");
            } finally {
                if (tenancy()->initialized) {
                    tenancy()->end();
                }
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
