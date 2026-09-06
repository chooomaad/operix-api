<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Services\TenantFileService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Dotation EPI remise à un EMPLOYÉ, via (person_type, person_id). Une remise porte
 * plusieurs articles (`items`) et plusieurs catégories (`categories`) ; la quantité
 * est dérivée automatiquement du nombre de catégories.
 */
class PpeIssuance extends Model
{
    use BelongsToTenant, SoftDeletes;

    /** Articles EPI proposés (source de vérité partagée avec le front). */
    public const ITEMS = [
        'helmet', 'safety_glasses', 'face_shield', 'ear_plugs', 'ear_muffs',
        'respirator', 'dust_mask', 'gloves', 'safety_boots', 'hi_vis_vest',
        'coverall', 'harness', 'rain_gear', 'knee_pads',
    ];

    public const CATEGORIES = ['head', 'eyes', 'hearing', 'respiratory', 'hands', 'feet', 'body', 'fall', 'other'];

    protected $fillable = [
        'person_type', 'person_id', 'items', 'categories',
        'issued_at', 'return_due', 'condition', 'document', 'observations',
    ];

    protected $casts = [
        'items'      => 'array',
        'categories' => 'array',
        'issued_at'  => 'date',
        'return_due' => 'date',
        'quantity'   => 'integer',
    ];

    protected $appends = ['image_url'];

    protected static function booted(): void
    {
        // La quantité reflète le nombre de catégories cochées (règle métier :
        // « head + eyes + body → 3 »). Recalculée à chaque enregistrement pour
        // qu'aucune couche cliente ne puisse la désynchroniser.
        static::saving(function (self $ppe): void {
            $categories = $ppe->categories;
            $ppe->quantity = is_array($categories) ? count($categories) : 0;
        });
    }

    /** URL du justificatif (bon de remise) stocké dans `document`. */
    public function getImageUrlAttribute(): ?string
    {
        return app(TenantFileService::class)->url($this->document);
    }
}
