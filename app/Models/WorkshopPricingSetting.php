<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

class WorkshopPricingSetting extends Model
{
    use TenantConnection;

    protected $fillable = ['id', 'tax_rate_basis_points'];
}
