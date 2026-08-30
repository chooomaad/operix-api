<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jeton de reinitialisation du PIN, stocke sous forme hachee.
 */
class PinResetToken extends Model
{
    protected $fillable = ['user_id', 'token_hash', 'expires_at', 'used_at'];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at'    => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Utilisable = ni consomme, ni expire. */
    public function isUsable(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }
}
