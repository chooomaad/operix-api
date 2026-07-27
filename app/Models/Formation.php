<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Formation extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'employee_id', 'titre', 'organisme',
        'date_debut', 'date_fin', 'duree_jours',
        'type', 'statut', 'certificat', 'observations',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin'   => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
