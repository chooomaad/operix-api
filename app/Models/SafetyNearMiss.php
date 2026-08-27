<?php

namespace App\Models;

use App\Contracts\HseEvent;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SafetyNearMiss extends Model implements HseEvent
{
    public function hseKind(): string
    {
        return 'near_miss';
    }

    /** Un presqu'accident n'a pas de sous-type : seule sa gravite le qualifie. */
    public function hseSubtype(): ?string
    {
        return null;
    }

    use BelongsToTenant, SoftDeletes;

    protected $table = 'safety_near_miss';

    protected $fillable = [
        'reference', 'date', 'time', 'location',
        'severity', 'description', 'potential_consequence',
        'corrective_action', 'corrective_action_due',
        'status', 'reported_by', 'employees', 'image',
        // Position de l'evenement. tenant_id reste hors fillable : la
        // localisation vient du client, l'appartenance jamais.
        'latitude', 'longitude', 'location_accuracy', 'location_captured_at',
    ];

    protected $casts = [
        // Caste en flottant : sans cela PostgreSQL renvoie les DECIMAL sous
        // forme de chaines, et le JSON exposerait "18.0735000" au lieu d'un
        // nombre — un client devrait alors reconvertir avant tout calcul.
        'latitude'             => 'float',
        'longitude'            => 'float',
        'location_accuracy'    => 'float',
        'location_captured_at' => 'datetime',
        'date'                  => 'date',
        'corrective_action_due' => 'date',
        'employees'             => 'array',
    ];

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
