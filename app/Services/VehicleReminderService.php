<?php

namespace App\Services;

use App\Mail\VehicleReminderEmail;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleReminder;
use App\Models\VehicleReminderAttempt;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

class VehicleReminderService
{
    public function sendManually(VehicleReminder $reminder, User $actor): VehicleReminderAttempt
    {
        return $this->send($reminder, $actor, null)
            ?? throw new LogicException('A manual reminder attempt was not created.');
    }

    public function sendScheduled(VehicleReminder $reminder, CarbonImmutable $scheduledFor): ?VehicleReminderAttempt
    {
        return $this->send($reminder, null, $scheduledFor->toDateString());
    }

    public function processDate(CarbonImmutable $date): int
    {
        $today = $date->toDateString();
        $weekAhead = $date->addDays(7)->toDateString();
        $failed = 0;
        $reminders = VehicleReminder::query()->whereNull('completed_at')
            ->whereIn('due_on', [$today, $weekAhead])->lazyById(100);

        foreach ($reminders as $reminder) {
            try {
                if ($attempt = $this->sendScheduled($reminder, $date)) {
                    $failed += $attempt->status === 'failed' ? 1 : 0;
                }
            } catch (Throwable $exception) {
                $failed++;
                Log::error('Scheduled vehicle reminder failed.', [
                    'tenant_id' => tenant('id'),
                    'reminder_id' => $reminder->id,
                    'exception_class' => $exception::class,
                ]);
            }
        }

        return $failed;
    }

    private function send(VehicleReminder $reminder, ?User $actor, ?string $scheduledFor): ?VehicleReminderAttempt
    {
        $attempt = DB::connection('tenant')->transaction(function () use ($reminder, $actor, $scheduledFor) {
            $record = VehicleReminder::query()->lockForUpdate()->findOrFail($reminder->id);
            $customer = Customer::query()->lockForUpdate()->find($record->customer_id);
            $vehicle = Vehicle::query()->lockForUpdate()->find($record->vehicle_id);

            if ($scheduledFor && $record->attempts()->where('scheduled_for', $scheduledFor)->exists()) {
                return null;
            }
            if ($scheduledFor && $record->attempts()->where('status', 'unknown')->exists()) {
                return null;
            }
            if (! $customer || ! $vehicle || ! $this->isSendable($record, $customer, $vehicle)) {
                if ($scheduledFor) {
                    return null;
                }
                throw ValidationException::withMessages([
                    'reminder' => 'The reminder must be open, the customer opted in with a valid email, and the vehicle still owned by that customer.',
                ]);
            }
            if ($record->attempts()->where('status', 'sending')->exists()) {
                if ($scheduledFor) {
                    return null;
                }
                throw ValidationException::withMessages([
                    'reminder' => 'A send is already in progress or its outcome is unknown. Check the provider before resolving it.',
                ]);
            }

            return $record->attempts()->create([
                'requested_by_user_id' => $actor?->id,
                'scheduled_for' => $scheduledFor,
                'recipient_email' => $customer->email,
                'status' => 'sending',
            ]);
        }, 3);

        if (! $attempt) {
            return null;
        }

        DB::connection('tenant')->transaction(function () use ($attempt) {
            $delivery = VehicleReminderAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            if ($delivery->status !== 'sending') {
                return;
            }
            $record = VehicleReminder::query()->lockForUpdate()->findOrFail($delivery->vehicle_reminder_id);
            $customer = Customer::query()->lockForUpdate()->find($record->customer_id);
            $vehicle = Vehicle::query()->lockForUpdate()->find($record->vehicle_id);
            if (! $customer || ! $vehicle || $delivery->recipient_email !== $customer->email
                || ! $this->isSendable($record, $customer, $vehicle)) {
                $delivery->update(['status' => 'failed', 'failure_message' => 'Reminder, consent, ownership or recipient changed before sending.']);

                return;
            }
            try {
                Mail::to($delivery->recipient_email)->send(
                    new VehicleReminderEmail($record, $customer, $vehicle, (string) tenant('workshop_name'))
                );
            } catch (Throwable $exception) {
                $delivery->update(['status' => 'failed', 'failure_message' => 'Mail transport failed. Check application logs before retrying.']);
                Log::error('Vehicle reminder email failed.', [
                    'tenant_id' => tenant('id'),
                    'attempt_id' => $delivery->id,
                    'exception_class' => $exception::class,
                ]);

                return;
            }
            $delivery->update(['status' => 'sent', 'sent_at' => now()]);
        }, 1);

        return $attempt->fresh();
    }

    private function isSendable(VehicleReminder $reminder, Customer $customer, Vehicle $vehicle): bool
    {
        return ! $reminder->completed_at && $customer->engagement_reminders_opt_in
            && $vehicle->customer_id === $customer->id
            && filter_var($customer->email, FILTER_VALIDATE_EMAIL);
    }
}
