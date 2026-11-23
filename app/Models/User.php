<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Sanctum\HasApiTokens;
use Stancl\Tenancy\Database\Models\Tenant;
use App\Models\UserProfile;
use App\Models\AuditAlert;
use App\Models\AuditLog;

use App\Traits\Auditable;
use App\Services\PasswordCredentials;

class User extends Authenticatable
{
    protected $attributes = ['credential_version' => 0];

    protected static function booted(): void
    {
        static::created(fn (User $user) => app(PasswordCredentials::class)->deleteTokens($user->email));
    }

    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_ACCOUNT_SUPER_ADMIN = 'account_super_admin';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_MECHANIC = 'mechanic';
    public const ROLE_CLIENT = 'client';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';

    public const STAFF_PERMISSIONS = [
        'can_view_customers', 'can_create_job_invoices', 'can_record_payments',
        'can_view_vehicle_history', 'can_create_customer_records', 'can_review_appointments',
    ];

    use HasApiTokens, HasFactory, Notifiable, Auditable;

    protected array $auditable = [
        'name',
        'email',
        'phone',
        'tenant_id',
        'workshop_name',
        'role',
        'status',
        'suspended_at',
        'suspension_reason',
        'force_password_reset',
        'can_view_customers',
        'can_create_job_invoices',
        'can_record_payments',
        'can_view_vehicle_history',
        'can_create_customer_records',
        'can_review_appointments',
    ];

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'tenant_id',
        'workshop_name',
        'role',
        'status',
        'suspended_at',
        'suspension_reason',
        'force_password_reset',
        'can_view_customers',
        'can_create_job_invoices',
        'can_record_payments',
        'can_view_vehicle_history',
        'can_create_customer_records',
        'can_review_appointments',
        'failed_login_attempts',
        'locked_until',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'mfa_secret',
        'mfa_recovery_codes',
        'mfa_last_counter',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'suspended_at' => 'datetime',
        'force_password_reset' => 'boolean',
        'can_view_customers' => 'boolean',
        'can_create_job_invoices' => 'boolean',
        'can_record_payments' => 'boolean',
        'can_view_vehicle_history' => 'boolean',
        'can_create_customer_records' => 'boolean',
        'can_review_appointments' => 'boolean',
        'locked_until' => 'datetime',
        'credential_version' => 'integer',
        'mfa_secret' => 'encrypted',
        'mfa_recovery_codes' => 'array',
        'mfa_last_counter' => 'integer',
    ];

    //User Model Relationships
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(UserSession::class);
    }

    public function accountSubscription(): HasOne
    {
        return $this->hasOne(AccountSubscription::class);
    }

    public function hasRole(array|string $roles): bool
    {
        if (is_string($roles)) {
            $roles = [$roles];
        }

        return in_array($this->role, $roles, true);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class, 'user_id');
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isAccountSuperAdmin(): bool
    {
        return $this->role === self::ROLE_ACCOUNT_SUPER_ADMIN;
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    public function canViewCustomerLists(): bool
    {
        return $this->role === self::ROLE_ADMIN || ($this->role === self::ROLE_MECHANIC
            && ($this->can_view_customers || $this->can_view_vehicle_history || $this->can_create_customer_records));
    }

    public function isLocked(): bool
    {
        return $this->locked_until?->isFuture() ?? false;
    }

    public function lockAccount(int $minutes = 15): void
    {
        $this->locked_until = now()->addMinutes($minutes);
        $this->failed_login_attempts = 0;
        $this->save();

        $auditLog = AuditLog::record('account.locked', $this, auth()->user(), [
            'old_values' => null,
            'new_values' => ['locked_until' => $this->locked_until->toDateTimeString()],
        ]);

        AuditAlert::raise(
            $auditLog,
            'account.locked',
            'Account locked after repeated failed login attempts.',
            'high',
            ['user_id' => $this->id]
        );
    }

    public function resetLoginAttempts(): void
    {
        $this->failed_login_attempts = 0;
        $this->locked_until = null;
        $this->save();
    }
}