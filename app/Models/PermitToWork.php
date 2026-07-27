<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PermitToWork extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'permit_to_work';

    protected $fillable = [
        'reference', 'type', 'title', 'description',
        'location', 'contractor_id', 'contractor_name',
        'valid_from', 'valid_to', 'status',
        'risks', 'precautions', 'ppe_required', 'workers',
        'requested_by', 'approved_by', 'approved_at',
        'approval_notes', 'closure_notes', 'closed_at',
    ];

    protected $casts = [
        'valid_from'   => 'datetime',
        'valid_to'     => 'datetime',
        'approved_at'  => 'datetime',
        'closed_at'    => 'datetime',
        'risks'        => 'array',
        'precautions'  => 'array',
        'ppe_required' => 'array',
        'workers'      => 'array',
    ];

    public function contractor()
    {
        return $this->belongsTo(Contractor::class);
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && now()->between($this->valid_from, $this->valid_to);
    }
}
