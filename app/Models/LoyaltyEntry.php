<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

class LoyaltyEntry extends Model
{
    use TenantConnection;

    protected $fillable = ['customer_id', 'points', 'reason', 'recorded_by_user_id'];
}
