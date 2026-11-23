<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffInvitation extends Model
{
    protected $fillable = [
        'tenant_id', 'email', 'role', 'can_view_customers', 'can_create_job_invoices', 'can_record_payments', 'for_existing_user', 'existing_user_id', 'token_hash',
        'expires_at', 'accepted_at', 'revoked_at', 'invited_by',
    ];

    protected $hidden = ['token_hash'];

    protected $casts = [
        'can_view_customers' => 'boolean',
        'can_create_job_invoices' => 'boolean',
        'can_record_payments' => 'boolean',
        'for_existing_user' => 'boolean',
        'existing_user_id' => 'integer',
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];
}
