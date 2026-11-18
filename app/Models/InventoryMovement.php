<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

class InventoryMovement extends Model
{
    use TenantConnection;

    protected $fillable = ['quantity_change', 'balance_after', 'recorded_by_user_id', 'note'];
}
