<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MedicalVisit extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'employee_id', 'date', 'type', 'resultat',
        'restrictions', 'prochaine_visite', 'medecin', 'document', 'observations',
    ];

    protected $casts = [
        'date'             => 'date',
        'prochaine_visite' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
