<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EnvironmentReport extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'reference', 'date', 'location', 'type', 'severity',
        'description', 'impact', 'corrective_action',
        'corrective_action_due', 'status', 'reported_by', 'employees', 'image',
    ];

    protected $casts = [
        'date'                  => 'date',
        'corrective_action_due' => 'date',
        'employees'             => 'array',
    ];

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
