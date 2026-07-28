<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Paiement — modèle GLOBAL plateforme (pas de scope tenant).
 * Ne stocke que des données assainies (traçabilité/réconciliation/audit).
 */
class Payment extends Model
{
    protected $fillable = [
        'order_id', 'provider', 'provider_transaction_id',
        'amount', 'currency', 'status', 'paid_at', 'sanitized_payload',
    ];

    protected $casts = [
        'amount'            => 'integer',
        'paid_at'           => 'datetime',
        'sanitized_payload' => 'array',
    ];

    public const STATUSES = ['pending', 'succeeded', 'failed', 'refunded'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function isSucceeded(): bool
    {
        return $this->status === 'succeeded';
    }
}
