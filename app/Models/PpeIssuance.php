<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Services\TenantFileService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Dotation EPI remise à une personne (employee/contractor/visitor/intern), via
 * (person_type, person_id). Même écosystème générique que les formations,
 * certifications et visites médicales (cf. PersonRecordController).
 */
class PpeIssuance extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'person_type', 'person_id', 'designation', 'category', 'size',
        'quantity', 'issued_at', 'return_due', 'condition', 'document', 'observations',
    ];

    protected $casts = [
        'issued_at'  => 'date',
        'return_due' => 'date',
        'quantity'   => 'integer',
    ];

    protected $appends = ['image_url'];

    /** URL du justificatif (bon de remise) stocké dans `document`. */
    public function getImageUrlAttribute(): ?string
    {
        return app(TenantFileService::class)->url($this->document);
    }
}
