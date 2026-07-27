<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contractor extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_name', 'contact_nom', 'contact_phone',
        'contact_email', 'activite', 'num_registre', 'nni',
        'contract_start', 'contract_end', 'status',
        'zones_autorisees', 'logo', 'notes',
    ];

    protected $casts = [
        'contract_start' => 'date',
        'contract_end'   => 'date',
    ];

    public function employees()
    {
        return $this->hasMany(ContractorEmployee::class);
    }

    public function permits()
    {
        return $this->hasMany(PermitToWork::class);
    }

    public function isExpired(): bool
    {
        return $this->contract_end && $this->contract_end->isPast();
    }
}
