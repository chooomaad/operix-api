<?php

namespace App\Models;

use App\Models\Concerns\GeneratesReference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Commande commerciale — modèle GLOBAL plateforme (pas de scope tenant).
 * amount en centimes ; source de vérité serveur.
 */
class Order extends Model
{
    use GeneratesReference, HasFactory;

    protected string $referencePrefix = 'OPX';

    protected $fillable = [
        'reference', 'plan_id', 'company_name', 'contact_name', 'email', 'phone',
        'billing_cycle', 'amount', 'currency', 'status',
        'tenant_id', 'demo_request_id', 'paid_at', 'metadata',
    ];

    protected $casts = [
        'amount'   => 'integer',
        'paid_at'  => 'datetime',
        'metadata' => 'array',
    ];

    public const STATUSES = ['pending', 'paid', 'failed', 'cancelled', 'refunded'];

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }
}
