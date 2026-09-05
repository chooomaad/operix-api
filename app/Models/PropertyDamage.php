<?php

namespace App\Models;

use App\Contracts\HseEvent;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Dommage matériel (Property Damage) — 5e évènement HSSE.
 *
 * Partage la forme et le contrat HseEvent des autres évènements : diffusion temps
 * réel et agrégations (top-persons, activité récente) le traitent sans code dédié.
 */
class PropertyDamage extends Model implements HseEvent
{
    use Auditable, BelongsToTenant, SoftDeletes;

    public function hseKind(): string
    {
        return 'property_damage';
    }

    /** vehicle, equipment, container… porté par le champ `type`. */
    public function hseSubtype(): ?string
    {
        return $this->type;
    }

    protected $attributes = [
        'status' => 'open',
    ];

    protected $fillable = [
        'reference', 'date', 'time', 'location', 'type', 'severity',
        'description', 'estimated_cost', 'immediate_cause', 'corrective_action',
        'corrective_action_due', 'status', 'reported_by', 'involved_people',
        'image', 'report_file',
        // Position de l'evenement. tenant_id reste hors fillable : la
        // localisation vient du client, l'appartenance jamais.
        'latitude', 'longitude', 'location_accuracy', 'location_captured_at',
    ];

    protected $casts = [
        'latitude'             => 'float',
        'longitude'            => 'float',
        'location_accuracy'    => 'float',
        'location_captured_at' => 'datetime',
        'date'                  => 'date',
        'corrective_action_due' => 'date',
        'estimated_cost'        => 'decimal:2',
        'involved_people'       => 'array',
    ];

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
