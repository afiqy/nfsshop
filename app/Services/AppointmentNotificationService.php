<?php

namespace App\Services;

use App\Mail\AppointmentNotificationEmail;
use App\Models\AppointmentNotification;
use App\Models\Calendar;
use App\Models\Customer;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class AppointmentNotificationService
{
    public function __construct(private UserNotificationPreferences $preferences) {}

    public function queueCalendar(string $event, Calendar $calendar): void
    {
        $calendar->loadMissing(['customer', 'vehicle']);
        if (! $calendar->customer || ! $calendar->vehicle) {
            Log::warning('Appointment notification skipped: customer or vehicle is missing.', [
                'tenant_id' => tenant('id'), 'calendar_id' => $calendar->id, 'event' => $event,
            ]);

            return;
        }
        $this->queue($event, $calendar->customer, $calendar->vehicle, $calendar->service_type ?? $calendar->title,
            $calendar->start, $calendar->end, $calendar);
    }

    public function queue(string $event, Customer $customer, Vehicle $vehicle, string $service, string $start, ?string $end, ?Calendar $calendar = null): void
    {
        foreach (['customer', 'workshop'] as $recipient) {
            AppointmentNotification::create([
                'customer_id' => $customer->id, 'vehicle_id' => $vehicle->id, 'calendar_id' => $calendar?->id,
                'event' => $event, 'recipient_type' => $recipient, 'service' => $service,
                'vehicle_plate' => $vehicle->plate_number, 'start' => $start, 'end' => $end,
                'reminder_start' => $event === 'reminder' ? $start : null,
            ]);
        }
    }

    public function queueReminders(): void
    {
        $now = CarbonImmutable::now(Calendar::TIMEZONE);
        Calendar::query()->where('status', Calendar::STATUS_SCHEDULED)
            ->where('start', '>', $now->toDateTimeString())
            ->where('start', '<=', $now->addHours(24)->toDateTimeString())
            ->lazyById()->each(function (Calendar $booking) use ($now) {
                DB::connection('tenant')->transaction(function () use ($booking, $now) {
                    $current = Calendar::query()->with(['customer', 'vehicle'])->lockForUpdate()->findOrFail($booking->id);
                    if ($current->status !== Calendar::STATUS_SCHEDULED || ! $current->customer || ! $current->vehicle
                        || $current->vehicle->customer_id !== $current->customer_id
                        || $current->start <= $now->toDateTimeString() || $current->start > $now->addHours(24)->toDateTimeString()) {
                        return;
                    }
                    foreach (['customer', 'workshop'] as $recipient) {
                        $reminder = AppointmentNotification::query()->firstOrCreate([
                            'calendar_id' => $current->id, 'recipient_type' => $recipient, 'reminder_start' => $current->start,
                        ], [
                            'customer_id' => $current->customer_id, 'vehicle_id' => $current->vehicle_id,
                            'event' => 'reminder', 'service' => $current->service_type ?? $current->title,
                            'vehicle_plate' => $current->vehicle->plate_number, 'start' => $current->start, 'end' => $current->end,
                        ]);
                        if ($reminder->status === 'cancelled') {
                            $reminder->update([
                                'status' => 'pending', 'failure_message' => null,
                                'customer_id' => $current->customer_id, 'vehicle_id' => $current->vehicle_id,
                                'service' => $current->service_type ?? $current->title,
                                'vehicle_plate' => $current->vehicle->plate_number, 'end' => $current->end,
                            ]);
                        }
                    }
                }, 3);
            });
    }

    public function deliverPending(): int
    {
        $failures = 0;
        AppointmentNotification::query()->where('status', 'pending')->lazyById()->each(function (AppointmentNotification $notification) use (&$failures) {
            if (! AppointmentNotification::query()->whereKey($notification->id)->where('status', 'pending')
                ->update(['status' => 'sending'])) {
                return;
            }
            if (! $this->deliver($notification->fresh())) {
                $failures++;
            }
        });

        return $failures;
    }

    private function deliver(AppointmentNotification $notification): bool
    {
        return DB::connection('tenant')->transaction(function () use ($notification) {
            $vehicle = Vehicle::query()->lockForUpdate()->find($notification->vehicle_id);
            $booking = $notification->event === 'reminder'
                ? Calendar::query()->lockForUpdate()->find($notification->calendar_id) : null;
            $customer = Customer::query()->lockForUpdate()->find($notification->customer_id);
            $notification = AppointmentNotification::query()->lockForUpdate()->findOrFail($notification->id);
            if ($notification->status !== 'sending') {
                return true;
            }
            if (! $customer || ! $vehicle || $vehicle->customer_id !== $customer->id) {
                $this->fail($notification, 'The customer or vehicle is no longer available for this notification.');

                return false;
            }
            if ($notification->recipient_type === 'customer'
                && ! $this->preferences->allowsEmail($customer->portal_user_id, UserProfile::EMAIL_APPOINTMENTS)) {
                $notification->update([
                    'status' => 'cancelled',
                    'failure_message' => 'The customer opted out of appointment emails.',
                ]);

                return true;
            }
            if ($notification->event === 'reminder') {
                if (! $booking || $booking->status !== Calendar::STATUS_SCHEDULED || $booking->start !== $notification->reminder_start
                    || $booking->end !== $notification->end
                    || $booking->customer_id !== $customer->id || $booking->vehicle_id !== $vehicle->id
                    || $booking->start <= CarbonImmutable::now(Calendar::TIMEZONE)->toDateTimeString()) {
                    $notification->update(['status' => 'cancelled', 'failure_message' => 'Appointment changed or started before the reminder was sent.']);

                    return true;
                }
            }
            $owner = tenant()->account_owner_id
                ? User::on(config('tenancy.database.central_connection'))->find(tenant()->account_owner_id)
                : null;
            $email = $notification->recipient_type === 'customer'
                ? $customer->email : ($owner?->email ?? tenant()->owner_email);
            if (! is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $this->fail($notification, 'The recipient has no valid email address.');

                return false;
            }
            $notification->update(['recipient_email' => $email]);
            try {
                Mail::to($email)->send(new AppointmentNotificationEmail($notification, tenant()->workshop_name ?? tenant('id')));
            } catch (Throwable $exception) {
                $this->fail($notification, 'Mail transport failed. Check logs before retrying.');
                Log::error('Appointment email delivery failed.', [
                    'tenant_id' => tenant('id'), 'notification_id' => $notification->id, 'exception_class' => $exception::class,
                ]);

                return false;
            }
            $notification->update(['status' => 'sent', 'sent_at' => now(), 'failure_message' => null]);

            return true;
        });
    }

    private function fail(AppointmentNotification $notification, string $message): void
    {
        $notification->update(['status' => 'failed', 'failure_message' => $message]);
        Log::warning('Appointment email could not be sent.', ['tenant_id' => tenant('id'), 'notification_id' => $notification->id, 'reason' => $message]);
    }
}
