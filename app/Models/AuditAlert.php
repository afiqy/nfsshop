<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'audit_log_id',
        'type',
        'severity',
        'message',
        'meta',
        'read_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'read_at' => 'datetime',
    ];

    public function auditLog(): BelongsTo
    {
        return $this->belongsTo(AuditLog::class);
    }

    public static function raise(AuditLog $auditLog, string $type, string $message, string $severity = 'medium', array $meta = []): self
    {
        return static::create([
            'audit_log_id' => $auditLog->id,
            'type' => $type,
            'severity' => $severity,
            'message' => $message,
            'meta' => $meta,
        ]);
    }
}