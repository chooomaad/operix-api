<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Services\TenantFileService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MedicalVisit extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'employee_id', 'date', 'type', 'resultat',
        'restrictions', 'prochaine_visite', 'medecin', 'document', 'observations',
    ];

    protected $casts = [
        'date'             => 'date',
        'prochaine_visite' => 'date',
    ];

    protected $appends = ['image_url'];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /** URL du justificatif (image/PDF) stocké dans `document`. */
    public function getImageUrlAttribute(): ?string
    {
        return app(TenantFileService::class)->url($this->document);
    }
}
