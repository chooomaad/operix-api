<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Visitor extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'nom', 'prenom', 'nni', 'entreprise', 'phone', 'email',
        'badge_number', 'motif', 'personne_visitee', 'department',
        'status', 'checked_in_at', 'checked_out_at',
        'vehicle_plate', 'photo', 'notes', 'created_by',
    ];

    protected $casts = [
        'checked_in_at'  => 'datetime',
        'checked_out_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->prenom} {$this->nom}";
    }

    public function getDurationAttribute(): ?string
    {
        if (!$this->checked_out_at || !$this->checked_in_at) return null;
        $diff = $this->checked_in_at->diff($this->checked_out_at);
        return $diff->h . 'h ' . $diff->i . 'min';
    }
}
