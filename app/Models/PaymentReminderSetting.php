<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

class PaymentReminderSetting extends Model
{
    use TenantConnection;

    protected $fillable = ['enabled'];

    protected $casts = ['enabled' => 'boolean'];
}
