<?php

namespace App\Models;

use App\Models\Concerns\GeneratesReference;
use Illuminate\Database\Eloquent\Model;

/**
 * Demande de démo (lead commercial) — modèle GLOBAL plateforme (pas de scope tenant).
 */
class DemoRequest extends Model
{
    use GeneratesReference;

    protected string $referencePrefix = 'DEMO';

    protected $fillable = [
        'reference', 'company_name', 'contact_name', 'email', 'phone',
        'employee_count', 'message', 'status', 'tenant_id', 'handled_by', 'ip_address',
    ];

    protected $casts = [
        'employee_count' => 'integer',
    ];

    public const STATUSES = ['new', 'contacted', 'approved', 'rejected', 'converted'];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
