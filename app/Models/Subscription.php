<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Abonnement — modèle GLOBAL plateforme (pas de scope tenant).
 * Source de vérité de l'offre d'un tenant (plan_id).
 */
class Subscription extends Model
{
    protected $fillable = [
        'tenant_id', 'plan_id', 'order_id',
        'status', 'billing_cycle',
        'starts_at', 'trial_ends_at', 'renews_at', 'expires_at', 'cancelled_at',
        'provider', 'provider_subscription_id',
    ];

    protected $casts = [
        'starts_at'     => 'datetime',
        'trial_ends_at' => 'datetime',
        'renews_at'     => 'datetime',
        'expires_at'    => 'datetime',
        'cancelled_at'  => 'datetime',
    ];

    public const STATUSES = ['trialing', 'active', 'past_due', 'cancelled', 'expired'];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['trialing', 'active'], true);
    }
}
