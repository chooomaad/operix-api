<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GembaWalk extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'reference', 'date', 'zone', 'objective', 'auditor', 'score', 'observation',
        'action_required', 'responsible', 'due_date',
        'priority', 'status', 'image', 'created_by',
    ];

    protected $casts = [
        'date'     => 'date',
        'due_date' => 'date',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
