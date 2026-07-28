<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Plan commercial (global, plateforme — PAS de tenant_id).
 * Prix en centimes EUR (source de vérité commerciale).
 */
class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug', 'name', 'description',
        'price_monthly', 'price_yearly', 'currency',
        'max_employees', 'storage_limit_mb', 'features',
        'contact_sales', 'is_public', 'sort_order', 'active',
    ];

    protected $casts = [
        'features'      => 'array',
        'contact_sales' => 'boolean',
        'is_public'     => 'boolean',
        'active'        => 'boolean',
        'price_monthly' => 'integer',
        'price_yearly'  => 'integer',
        'max_employees' => 'integer',
        'storage_limit_mb' => 'integer',
        'sort_order'    => 'integer',
    ];

    /** Un plan est-il achetable en libre-service (checkout automatique) ? */
    public function isPurchasable(): bool
    {
        return $this->active && ! $this->contact_sales && $this->price_monthly !== null;
    }

    /** Montant (centimes EUR) pour un cycle donné — calculé SERVEUR, jamais côté client. */
    public function amountFor(string $billingCycle): ?int
    {
        return match ($billingCycle) {
            'monthly' => $this->price_monthly,
            'yearly'  => $this->price_yearly,
            default   => null,
        };
    }
}
