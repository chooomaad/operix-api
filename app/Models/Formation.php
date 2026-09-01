<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Services\TenantFileService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Formation extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'employee_id', 'person_type', 'person_id', 'titre', 'organisme',
        'date_debut', 'date_fin', 'duree_jours',
        'type', 'statut', 'certificat', 'observations',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin'   => 'date',
    ];

    protected $appends = ['image_url'];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /** URL du justificatif (image/PDF) stocké dans `certificat`. */
    public function getImageUrlAttribute(): ?string
    {
        return app(TenantFileService::class)->url($this->certificat);
    }
}
