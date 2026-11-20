<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

class JobCardUpdate extends Model
{
    use TenantConnection;

    public const TYPE_OPENED = 'opened';

    public const TYPE_STATUS = 'status';

    public const TYPE_NOTE = 'note';

    public const TYPE_PART_USED = 'part_used';

    public const TYPE_PART_RETURNED = 'part_returned';

    public const TYPE_VEHICLE_UPDATE = 'vehicle_update';

    protected $fillable = [
        'actor_user_id',
        'actor_name',
        'type',
        'status',
        'note',
        'mileage_km',
        'vehicle_condition',
    ];

    protected $casts = [
        'note' => 'encrypted',
        'mileage_km' => 'integer',
        'vehicle_condition' => 'encrypted',
    ];
}
