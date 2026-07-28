<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Jeton d'activation — modèle GLOBAL plateforme. Ne contient QUE le hash du token.
 */
class TenantActivation extends Model
{
    protected $fillable = ['tenant_id', 'user_id', 'token_hash', 'expires_at', 'used_at'];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at'    => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isUsable(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }
}
