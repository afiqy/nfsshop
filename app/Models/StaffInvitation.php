<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffInvitation extends Model
{
    protected $fillable = [
        'tenant_id', 'email', 'role', 'can_view_customers', 'token_hash',
        'expires_at', 'accepted_at', 'revoked_at', 'invited_by',
    ];

    protected $hidden = ['token_hash'];

    protected $casts = [
        'can_view_customers' => 'boolean',
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];
}
