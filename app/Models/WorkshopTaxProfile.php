<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

class WorkshopTaxProfile extends Model
{
    use TenantConnection;

    public const PROFILE_ID = 1;

    public $incrementing = false;

    protected $fillable = ['id', 'details'];

    protected $hidden = ['details'];

    protected $casts = ['details' => 'encrypted:array'];
}
