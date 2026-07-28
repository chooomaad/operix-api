<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class EquipmentInspection extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'equipment_id', 'date', 'type', 'result',
        'observations', 'actions_required', 'next_inspection',
        'inspector', 'document', 'created_by',
    ];

    protected $casts = [
        'date'            => 'date',
        'next_inspection' => 'date',
    ];

    public function equipment() { return $this->belongsTo(Equipment::class); }
    public function creator()   { return $this->belongsTo(User::class, 'created_by'); }
}
