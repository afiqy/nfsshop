<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    use HasFactory;

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
}