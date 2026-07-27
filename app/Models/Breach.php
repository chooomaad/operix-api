<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Breach extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'reference', 'employee_id', 'date', 'type', 'location',
        'severity', 'description', 'corrective_action',
        'status', 'created_by',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function employee() { return $this->belongsTo(Employee::class); }
    public function creator()  { return $this->belongsTo(User::class, 'created_by'); }
}
