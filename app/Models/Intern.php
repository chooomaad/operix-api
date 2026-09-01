<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Intern extends Model
{
    use BelongsToTenant, SoftDeletes, Auditable;

    protected $fillable = [
        'tenant_id', 'reference', 'nom', 'prenom', 'etablissement', 'encadrant',
        'phone', 'email', 'date_debut', 'date_fin', 'status', 'is_active', 'photo', 'notes',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin'   => 'date',
        'is_active'  => 'boolean',
    ];

    protected $appends = ['full_name'];

    public function getFullNameAttribute(): string
    {
        return trim("{$this->prenom} {$this->nom}");
    }
}
