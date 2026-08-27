<?php

namespace App\Models;

use App\Contracts\HseEvent;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EnvironmentReport extends Model implements HseEvent
{
    public function hseKind(): string
    {
        return 'environment';
    }

    /** spill, emission, waste… porte par le champ `type`. */
    public function hseSubtype(): ?string
    {
        return $this->type;
    }

    use BelongsToTenant, SoftDeletes;

    /**
     * Valeurs par defaut alignees sur celles de PostgreSQL.
     *
     * Sans elles, un enregistrement tout juste cree n'a PAS de statut en memoire :
     * la base applique son defaut, mais le modele l'ignore jusqu'a un rechargement.
     * Consequence observee — la reponse 201 de l'API renvoyait `status: null`, et
     * l'evenement temps reel diffusait la meme valeur nulle, alors que la ligne
     * portait bien « open ».
     *
     * Declarer le defaut ici evite un SELECT supplementaire a chaque creation.
     */
    protected $attributes = [
        'status' => 'open',
    ];

    protected $fillable = [
        'reference', 'date', 'location', 'type', 'severity',
        'description', 'impact', 'corrective_action',
        'corrective_action_due', 'status', 'reported_by', 'employees', 'image',
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
