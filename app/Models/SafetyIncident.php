<?php

namespace App\Models;

use App\Contracts\HseEvent;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SafetyIncident extends Model implements HseEvent
{
    public function hseKind(): string
    {
        return 'incident';
    }

    /** LTI, Fire, MTC… la qualification portee par le champ `type`. */
    public function hseSubtype(): ?string
    {
        return $this->type;
    }

    use BelongsToTenant, HasFactory, SoftDeletes;

    /**
     * Vocabulaire canonique du champ `type`.
     *
     * SOURCE DE VÉRITÉ UNIQUE : ces valeurs sont exactement celles autorisées par la
     * contrainte PostgreSQL `safety_incidents_type_check`. Toute couche qui écrit ce
     * champ (validation HTTP, import Excel, seeders, front) doit s'y référer plutôt
     * que de redéclarer sa propre liste — quatre listes divergentes coexistaient
     * auparavant, dont deux ne pouvaient produire que des valeurs rejetées par la base
     * (erreurs 500). Voir docs/MOBILE_API_READINESS.md §B3.
     *
     * Si cette liste doit évoluer, la contrainte CHECK doit évoluer dans la même
     * migration — jamais l'une sans l'autre.
     */
    public const TYPES = ['LTI', 'MTC', 'RWC', 'FAC', 'HPI', 'Fire', 'Security', 'Autre'];

    /**
     * Alias tolérés en entrée (import Excel, données héritées) vers le vocabulaire
     * canonique. Clés normalisées en majuscules par le consommateur.
     *
     * N'y figurent QUE des correspondances sémantiquement sûres : variantes de casse
     * et synonymes stricts. Un code inconnu retombe volontairement sur 'Autre' plutôt
     * que d'être rapproché du type « le plus proche » : dans un registre HSE, classer
     * à tort un évènement (une fatalité en accident avec arrêt, par exemple) fausse
     * les indicateurs réglementaires. Mieux vaut un 'Autre' visible qu'une donnée
     * faussement précise.
     *
     * LACUNE CONNUE : le vocabulaire ne comporte aucun type « fatalité ». Les anciens
     * mappings d'import produisaient 'FAT' et 'PP', sans équivalent en base. À arbitrer
     * séparément (ajout d'un type + migration de la contrainte CHECK).
     */
    public const TYPE_ALIASES = [
        'FIRST_AID' => 'FAC',
        'FIRSTAID'  => 'FAC',
        'FA'        => 'FAC',
        'FIRE'      => 'Fire',
        'SECURITY'  => 'Security',
        'AUTRE'     => 'Autre',
        'OTHER'     => 'Autre',
        'MTI'       => 'MTC',
    ];

    /**
     * Normalise une valeur d'entrée vers le vocabulaire canonique.
     * Retourne 'Autre' pour tout code non reconnu.
     */
    public static function normalizeType(?string $value): string
    {
        $value = trim((string) $value);

        if (in_array($value, self::TYPES, true)) {
            return $value;
        }

        return self::TYPE_ALIASES[strtoupper($value)] ?? 'Autre';
    }

    protected $fillable = [
        'reference', 'date', 'time', 'location',
        'type', 'severity', 'description', 'immediate_cause',
        'root_cause', 'corrective_action', 'corrective_action_due',
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
