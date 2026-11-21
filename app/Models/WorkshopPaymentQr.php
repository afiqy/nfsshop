<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

class WorkshopPaymentQr extends Model
{
    use TenantConnection;

    public const CATEGORY_BANK = 'bank';

    public const CATEGORY_EWALLET = 'ewallet';

    public const MAX_ACTIVE = 10;

    protected $fillable = [
        'category',
        'label',
        'path',
        'mime_type',
        'created_by_user_id',
        'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];
}
