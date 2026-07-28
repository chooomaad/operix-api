<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContractorEmployee extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'contractor_id', 'nom', 'prenom',
        'poste', 'phone', 'cin', 'badge_number',
        'photo', 'date_debut', 'date_fin',
        'habilitation_hsse', 'habilitation_date',
        'is_active',
    ];

    protected $casts = [
        'date_debut'        => 'date',
        'date_fin'          => 'date',
        'habilitation_date' => 'date',
        'habilitation_hsse' => 'boolean',
        'is_active'         => 'boolean',
    ];

    public function contractor()
    {
        return $this->belongsTo(Contractor::class);
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->prenom} {$this->nom}";
    }
}
