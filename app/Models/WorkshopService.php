<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

class WorkshopService extends Model
{
    use TenantConnection;

    protected $fillable = ['name', 'price_sen', 'is_active'];

    protected $casts = ['price_sen' => 'integer', 'is_active' => 'boolean'];
}
