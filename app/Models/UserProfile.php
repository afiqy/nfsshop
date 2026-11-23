<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class UserProfile extends Model
{
    use HasFactory;

    public const EMAIL_APPOINTMENTS = 'appointment_emails';

    public const EMAIL_INVOICES = 'invoice_emails';

    public const EMAIL_PAYMENT_REMINDERS = 'payment_reminder_emails';

    public const EMAIL_PREFERENCES = [
        self::EMAIL_APPOINTMENTS,
        self::EMAIL_INVOICES,
        self::EMAIL_PAYMENT_REMINDERS,
    ];

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'avatar',
        'preferred_language',
        'timezone',
        'notification_preferences',
        'preferred_contact_method',
        'bio',
    ];

    protected $casts = [
        'notification_preferences' => 'array',
    ];

    public function getConnectionName()
    {
        return config('tenancy.database.central_connection');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function allowsEmail(string $preference): bool
    {
        if (! in_array($preference, self::EMAIL_PREFERENCES, true)) {
            throw new InvalidArgumentException('Unknown email notification preference.');
        }

        return data_get($this->notification_preferences, $preference) !== false;
    }
}
