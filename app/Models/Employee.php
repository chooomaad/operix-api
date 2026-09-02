<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Models\Concerns\Auditable;
class Employee extends Model
{
    use Auditable, BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'department_id', 'matricule', 'nni', 'nom', 'prenom',
        'email', 'phone', 'poste', 'section', 'entreprise', 'type_contrat',
        'category_code', 'nombre_enfants',
        'date_embauche', 'date_fin_contrat', 'photo', 'gender',
        'date_naissance', 'nationalite', 'lieu_naissance', 'adresse',
        'num_cni', 'contact_urgence_nom', 'contact_urgence_tel',
        'is_active', 'induction_status', 'induction_start_date',
        'last_modified_by', 'last_modified_at', 'user_id',
    ];

    protected $casts = [
        'date_embauche'        => 'date',
        'date_fin_contrat'     => 'date',
        'date_naissance'       => 'date',
        'induction_start_date' => 'date',
        'last_modified_at'     => 'datetime',
        'induction_status'     => 'boolean',
        'is_active'            => 'boolean',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function formations()
    {
        return $this->hasMany(Formation::class);
    }

    public function certifications()
    {
        return $this->hasMany(Certification::class);
    }

    public function medicalVisits()
    {
        return $this->hasMany(MedicalVisit::class);
    }

    public function breaches()
    {
        return $this->hasMany(Breach::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->prenom} {$this->nom}");
    }
}
