<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tenant = entreprise cliente Operix (TCN, Entreprise B, Entreprise C…).
 *
 * Modèle central de l'isolation multi-tenant. Chaque ressource métier appartient
 * à un tenant via une colonne `tenant_id` (voir le trait BelongsToTenant).
 *
 * Le `super_admin` (équipe Operix) est un rôle PLATEFORME : il n'appartient à
 * aucun tenant (users.tenant_id = NULL) et n'accède aux données cross-tenant que
 * via les opérations plateforme explicites (/superadmin/*), jamais via les routes métier.
 */
class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'short_name', 'slug',
        'status', 'plan', 'max_employees', 'demo_expires_at',
        'logo', 'primary_color', 'country', 'timezone', 'locale', 'settings',
    ];

    protected $casts = [
        'settings'        => 'array',
        'demo_expires_at' => 'datetime',
        'max_employees'   => 'integer',
    ];

    // ── Relations (utilisées par le back-office Super Admin) ────────────────────
    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    // ── Helpers de statut ──────────────────────────────────────────────────────
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }
}
