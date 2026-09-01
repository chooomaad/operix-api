<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Models\Concerns\Auditable;
class Breach extends Model
{
    use Auditable, BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'reference', 'employee_id', 'involved_people', 'date', 'type', 'location',
        'severity', 'description', 'corrective_action',
        'status', 'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'involved_people' => 'array',
    ];

    public function employee() { return $this->belongsTo(Employee::class); }
    public function creator()  { return $this->belongsTo(User::class, 'created_by'); }
}
