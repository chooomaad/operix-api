<?php

namespace App\Listeners;

use App\Events\HseEventCreated;
use App\Events\NotificationSent;
use App\Models\AppNotification;
use App\Models\User;
use App\Support\HseEventPayload;
use App\Support\Permissions;
use App\Support\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Str;

/**
 * Persiste un evenement HSE dans le centre de notifications des destinataires.
 *
 * POURQUOI un listener, et pas seulement la diffusion existante ?
 *
 * HseEventCreated part deja sur le canal de l'entreprise (tenant.{id}) : c'est le
 * flux « live » du tableau de bord, ephemere par nature. Mais le cahier des
 * charges est clair — « le WebSocket ne doit pas etre la seule trace d'une
 * notification importante ». Un responsable hors ligne au moment du signalement
 * doit le retrouver dans son centre de notifications a sa reconnexion.
 *
 * Ce listener cree donc une notification PERSISTEE par destinataire habilite, et
 * la pousse en temps reel sur son canal personnel (user.{id}). La source de
 * verite reste PostgreSQL ; Ably n'est que le transport.
 *
 * File d'attente : ShouldQueue deleste ce travail de la reponse HTTP. L'operation
 * metier (creation de l'incident) est deja committee avant la diffusion de
 * l'evenement — elle ne depend donc jamais de la disponibilite de la file ni
 * d'Ably.
 *
 * Enregistrement : AUTO-DECOUVERTE de Laravel (methode handle typee sur
 * HseEventCreated). Ne PAS ajouter d'Event::listen explicite en plus — ce serait
 * un second abonnement, donc une notification en double a chaque evenement.
 */
class SendHseEventNotifications implements ShouldQueue
{
    /** Nature de l'evenement -> permission qui donne le droit d'en etre notifie. */
    private const VIEW_PERMISSION = [
        'incident'    => 'incidents.view',
        'near_miss'   => 'near_miss.view',
        'environment' => 'environment.view',
    ];

    /** Nature de l'evenement -> route de la ressource cote web. */
    private const RESOURCE_PATH = [
        'incident'    => '/incidents',
        'near_miss'   => '/near-miss',
        'environment' => '/environment',
    ];

    public function __construct(private readonly TenantContext $tenant)
    {
    }

    public function handle(HseEventCreated $event): void
    {
        $payload    = $event->payload;
        $permission = self::VIEW_PERMISSION[$payload->kind] ?? null;

        if ($permission === null) {
            return;
        }

        $recipients = $this->recipientsFor($payload, $permission);

        if ($recipients->isEmpty()) {
            return;
        }

        $data = $this->notificationData($payload);

        // La creation d'AppNotification renseigne tenant_id depuis le contexte
        // (BelongsToTenant). Dans un worker de file, ce contexte n'existe pas : on
        // le pose explicitement pour la duree de l'ecriture, puis on le retire.
        $previous = $this->tenant->id();
        $this->tenant->set($payload->tenantId);

        try {
            foreach ($recipients as $recipient) {
                $notification = AppNotification::create([
                    'id'              => (string) Str::uuid(),
                    'type'            => $data['type'],
                    'notifiable_type' => User::class,
                    'notifiable_id'   => $recipient->id,
                    'data'            => $data,
                ]);

                // Pousse la notification persistee sur le canal personnel du
                // destinataire. Meme si Ably est indisponible, la notification
                // existe deja en base et apparaitra a la prochaine ouverture.
                NotificationSent::dispatch($notification, $recipient->id);
            }
        } finally {
            $this->tenant->set($previous);
        }
    }

    /**
     * Utilisateurs de l'entreprise habilites a voir ce type d'evenement.
     *
     * On EXCLUT l'auteur du signalement : se notifier soi-meme d'une action qu'on
     * vient de faire n'a pas de sens. Le modele User ne porte pas le global scope
     * tenant — le filtrage par tenant_id est donc explicite et obligatoire.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function recipientsFor(HseEventPayload $payload, string $permission): \Illuminate\Support\Collection
    {
        $roles      = Permissions::rolesFor($permission);
        $reporterId = $payload->reporter['id'] ?? null;

        return User::query()
            ->where('tenant_id', $payload->tenantId)
            ->where('is_active', true)
            ->whereIn('role', $roles)
            ->when($reporterId !== null, fn ($q) => $q->where('id', '!=', $reporterId))
            ->get();
    }

    /**
     * Contenu de la notification. Volontairement minimal et sans donnee sensible :
     * de quoi afficher un titre, un corps court et un lien vers la ressource, que
     * le client recharge ensuite via l'API avec SES permissions.
     *
     * @return array<string, mixed>
     */
    private function notificationData(HseEventPayload $payload): array
    {
        return [
            'title'         => $this->titleFor($payload),
            'body'          => $this->bodyFor($payload),
            'type'          => $this->typeFor($payload->severity),
            'resource_kind' => $payload->kind,
            'resource_id'   => $payload->id,
            'severity'      => $payload->severity,
            'link'          => (self::RESOURCE_PATH[$payload->kind] ?? '') . '/' . $payload->id,
        ];
    }

    private function titleFor(HseEventPayload $payload): string
    {
        $label = match ($payload->kind) {
            'incident'    => 'Nouvel incident',
            'near_miss'   => 'Nouveau presqu\'accident',
            'environment' => 'Nouvelle observation environnementale',
            default       => 'Nouvel evenement HSE',
        };

        return $payload->subtype ? "{$label} — {$payload->subtype}" : $label;
    }

    private function bodyFor(HseEventPayload $payload): string
    {
        $parts = array_filter([
            $payload->reference,
            $payload->location,
        ]);

        return $parts === [] ? 'Un nouvel evenement a ete declare.' : implode(' · ', $parts);
    }

    /** Une alerte critique doit se distinguer au premier coup d'oeil. */
    private function typeFor(?string $severity): string
    {
        return match ($severity) {
            'critical', 'high' => 'alert',
            'medium'           => 'warning',
            default            => 'info',
        };
    }
}
