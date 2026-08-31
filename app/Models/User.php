<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

use App\Models\Concerns\Auditable;
class User extends Authenticatable
{
    use Auditable, HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected static function booted(): void
    {
        static::creating(function (self $user): void {
            $user->role ??= 'agent';
        });

        static::saved(function (self $user): void {
            if ($user->role && Schema::hasTable('roles')) {
                $user->syncRoles([$user->role]);
            }
        });
    }

    protected $fillable = [
        'tenant_id',
        'name', 'email', 'password',
        'role', 'matricule', 'phone', 'avatar', 'is_active', 'last_login_at', 'last_seen_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at'     => 'datetime',
            'last_seen_at'      => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['company_admin', 'hsse_manager', 'super_admin'], true);
    }

    public function isCompanyAdmin(): bool
    {
        return $this->role === 'company_admin';
    }

    public function isHsseManager(): bool
    {
        return $this->role === 'hsse_manager';
    }

    public function isSupervisor(): bool
    {
        return $this->role === 'supervisor';
    }

    public function hasApplicationRole(string ...$roles): bool
    {
        foreach ($roles as $role) {
            if ($this->role === $role || $this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isAgent(): bool
    {
        return $this->role === 'agent';
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
