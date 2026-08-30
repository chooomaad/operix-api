<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Models\Concerns\Auditable;
class Equipment extends Model
{
    use Auditable, BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'code', 'name', 'category',
        'brand', 'model', 'serial_number',
        'purchase_date', 'last_inspection', 'next_inspection',
        'inspection_frequency_days', 'status',
        'location', 'assigned_to', 'photo', 'notes',
    ];

    protected $casts = [
        'purchase_date'   => 'date',
        'last_inspection' => 'date',
        'next_inspection' => 'date',
    ];

    public function assignedEmployee()
    {
        return $this->belongsTo(Employee::class, 'assigned_to');
    }

    public function inspections()
    {
        return $this->hasMany(EquipmentInspection::class);
    }

    public function isInspectionDue(): bool
    {
        return $this->next_inspection && $this->next_inspection->isPast();
    }
}
