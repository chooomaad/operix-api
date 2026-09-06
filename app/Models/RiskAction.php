<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Services\TenantFileService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Action du plan de traitement d'un risque.
 *
 * Le « retard » n'est pas un statut stocké : c'est une propriété dérivée
 * (échéance dépassée et action non terminée), toujours exacte quelle que soit la
 * date de consultation.
 */
class RiskAction extends Model
{
    use Auditable, BelongsToTenant, SoftDeletes;

    public const STATUSES   = ['todo', 'in_progress', 'done'];
    public const PRIORITIES = ['low', 'medium', 'high'];

    protected $attributes = [
        'status'   => 'todo',
        'priority' => 'medium',
    ];

    protected $fillable = [
        'risk_id', 'description', 'type', 'responsible_id', 'due_date', 'priority',
        'status', 'budget', 'proof', 'closed_at', 'validated_by', 'validated_at', 'created_by',
    ];

    protected $casts = [
        'due_date'     => 'date',
        'closed_at'    => 'date',
        'validated_at' => 'datetime',
        'budget'       => 'decimal:2',
    ];

    protected $appends = ['is_overdue', 'proof_url'];

    public function getIsOverdueAttribute(): bool
    {
        return $this->status !== 'done'
            && $this->due_date !== null
            && $this->due_date->isPast()
            && ! $this->due_date->isToday();
    }

    public function getProofUrlAttribute(): ?string
    {
        return app(TenantFileService::class)->url($this->proof);
    }

    public function risk()
    {
        return $this->belongsTo(Risk::class);
    }

    public function responsible()
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function validator()
    {
        return $this->belongsTo(User::class, 'validated_by');
    }
}
