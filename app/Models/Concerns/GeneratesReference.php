<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Génère une référence Operix lisible et unique CÔTÉ SERVEUR (jamais fournie par le client).
 * Format : {PREFIX}-{ANNÉE}-{id zero-paddé 6}. Ex. DEMO-2026-000001, OPX-2026-000042.
 *
 * Le modèle définit son préfixe via la propriété protégée $referencePrefix.
 */
trait GeneratesReference
{
    public static function bootGeneratesReference(): void
    {
        static::created(function (Model $model): void {
            if (empty($model->reference)) {
                $prefix = $model->referencePrefix ?? 'REF';
                $model->reference = sprintf('%s-%s-%06d', $prefix, now()->year, $model->getKey());
                $model->saveQuietly();
            }
        });
    }
}
