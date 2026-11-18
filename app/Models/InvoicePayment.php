<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

class InvoicePayment extends Model
{
    use TenantConnection;

    protected $fillable = ['amount_sen', 'recorded_by_user_id', 'reference'];

    protected $casts = ['amount_sen' => 'integer'];
}
