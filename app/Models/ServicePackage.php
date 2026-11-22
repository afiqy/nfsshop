<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

class ServicePackage extends Model
{
    use TenantConnection;

    protected $fillable = ['name', 'services', 'price_sen', 'is_active'];

    protected $casts = ['services' => 'array', 'price_sen' => 'integer', 'is_active' => 'boolean'];
}
