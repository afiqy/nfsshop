<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSession extends Model
{
    protected $fillable = [
        'user_id',
        'ip_address',
        'user_agent',
        'device_name',
        'last_activity',
        'managed',
        'credential_version',
        'revoked_at',
    ];

    protected $casts = [
        'last_activity' => 'datetime',
        'revoked_at' => 'datetime',
        'managed' => 'boolean',
        'credential_version' => 'integer',
    ];

    public function getConnectionName()
    {
        return config('tenancy.database.central_connection');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}