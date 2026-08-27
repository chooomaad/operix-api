<?php

namespace App\Support;

use App\Contracts\HseEvent;
use Illuminate\Database\Eloquent\Model;

/**
 * Charge utile diffusée pour un évènement HSE.
 *
 * DEUX RAISONS d'exister plutôt que de diffuser le modèle directement.
 *
 * 1. Sécurité. Un modèle Eloquent sérialisé expose toutes ses colonnes, y compris
 *    `tenant_id`, les clés étrangères et les champs internes. Construire la charge
 *    utile explicitement rend impossible l'ajout accidentel d'un champ sensible :
 *    une nouvelle colonne n'apparaît dans la diffusion que si quelqu'un l'écrit ici.
 *
 * 2. Robustesse en file d'attente. `SerializesModels` ne transporte que la clé
 *    primaire et RECHARGE le modèle au moment du traitement. Or ce rechargement
 *    passe par le global scope tenant, dont le contexte n'existe pas dans un
 *    worker : selon le mode d'exécution, le modèle serait introuvable et la
 *    diffusion perdue sans erreur visible. Un objet de valeur immuable traverse la
 *    file tel quel, sans requête ni contexte.
 *
 * L'objet est figé à l'instant de l'évènement — c'est aussi ce qu'on veut :
 * la diffusion décrit ce qui s'est passé, pas l'état courant de la ressource.
 */
final class HseEventPayload
{
    /**
     * @param array{latitude: float, longitude: float, accuracy: float|null, captured_at: string|null}|null $locationPoint
     * @param array{id: int, name: string}|null $reporter
     */
    public function __construct(
        public readonly int $id,
        public readonly int $tenantId,
        public readonly string $kind,
        public readonly ?string $subtype,
        public readonly ?string $reference,
        public readonly ?string $severity,
        public readonly ?string $status,
        public readonly ?string $location,
        public readonly ?array $locationPoint,
        public readonly ?array $reporter,
        public readonly string $createdAt,
    ) {
    }

    public static function fromModel(Model&HseEvent $event): self
    {
        return new self(
            id: (int) $event->getKey(),
            tenantId: (int) $event->getAttribute('tenant_id'),
            kind: $event->hseKind(),
            subtype: $event->hseSubtype(),
            reference: $event->getAttribute('reference'),
            severity: $event->getAttribute('severity'),
            status: $event->getAttribute('status'),
            // Désignation humaine du lieu (« Quai 3 »). Elle reste la donnée utile
            // pour une équipe d'intervention ; les coordonnées la complètent.
            location: $event->getAttribute('location'),
            locationPoint: self::pointFrom($event),
            reporter: self::reporterFrom($event),
            createdAt: $event->getAttribute('created_at')?->toIso8601String()
                ?? now()->toIso8601String(),
        );
    }

    /**
     * @return array{latitude: float, longitude: float, accuracy: float|null, captured_at: string|null}|null
     */
    private static function pointFrom(Model $event): ?array
    {
        $latitude = $event->getAttribute('latitude');

        // Même forme que dans les resources HTTP : un objet, ou rien. Un client
        // n'a pas à vérifier deux champs pour savoir si l'évènement est situé.
        if ($latitude === null) {
            return null;
        }

        return [
            'latitude'    => (float) $latitude,
            'longitude'   => (float) $event->getAttribute('longitude'),
            'accuracy'    => $event->getAttribute('location_accuracy'),
            'captured_at' => $event->getAttribute('location_captured_at')?->toIso8601String(),
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private static function reporterFrom(Model $event): ?array
    {
        $reporter = $event->getAttribute('reporter');

        if ($reporter === null) {
            return null;
        }

        // Identifiant et nom seulement. Ni e-mail, ni matricule, ni téléphone :
        // une alerte au tableau de bord n'a pas besoin des coordonnées de l'agent,
        // et ces champs sont par ailleurs restreints par permission.
        return [
            'id'   => (int) $reporter->id,
            'name' => (string) $reporter->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'             => $this->id,
            // Le tenant du destinataire, jamais celui d'un autre : le canal est
            // déjà cloisonné, cette valeur ne révèle donc rien qu'il ignore. Elle
            // permet à un client abonné à plusieurs canaux de router le message
            // sans le déduire du nom du canal.
            'tenant_id'      => $this->tenantId,
            'kind'           => $this->kind,
            'subtype'        => $this->subtype,
            'reference'      => $this->reference,
            'severity'       => $this->severity,
            'status'         => $this->status,
            'location'       => $this->location,
            'location_point' => $this->locationPoint,
            'reporter'       => $this->reporter,
            'created_at'     => $this->createdAt,
        ];
    }
}
