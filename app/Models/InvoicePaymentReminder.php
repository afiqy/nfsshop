<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

class InvoicePaymentReminder extends Model
{
    use TenantConnection;

    protected $fillable = ['due_date', 'days_overdue'];

    protected $casts = [
        'due_date' => 'immutable_date',
        'days_overdue' => 'integer',
    ];

    public function attempts(): HasMany
    {
        return $this->hasMany(InvoicePaymentReminderAttempt::class);
    }
}
