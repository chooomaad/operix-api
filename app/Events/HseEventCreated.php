<?php

namespace App\Events;

use App\Contracts\HseEvent;
use App\Support\HseEventPayload;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Un évènement HSE vient d'être signalé.
 *
 * UN SEUL évènement pour les trois modules. Incidents, presqu'accidents et
 * rapports environnementaux ne diffèrent que par leur nature et leur sous-type,
 * portés par le contrat [HseEvent] : trois classes distinctes auraient triplé le
 * même code de diffusion, la même autorisation de canal et les mêmes tests.
 *
 * `HseEventUpdated` et `HseEventStatusChanged` suivront la même forme — d'où le
 * choix de nommer l'évènement par ce qui arrive, pas par le module concerné.
 */
class HseEventCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    /**
     * Attendre la validation de la transaction avant de mettre en file.
     *
     * Sans cela, un signalement créé dans une transaction qui échoue ensuite
     * serait tout de même annoncé aux clients : le tableau de bord afficherait une
     * alerte pour une ligne inexistante. Le cahier des charges l'exige
     * explicitement — aucun évènement parasite en cas d'échec de transaction.
     */
    public bool $afterCommit = true;

    public function __construct(public readonly HseEventPayload $payload)
    {
    }

    public static function fromModel(Model&HseEvent $event): self
    {
        return new self(HseEventPayload::fromModel($event));
    }

    /**
     * Diffusé sur le canal privé de l'entreprise concernée.
     *
     * L'autorisation du canal (routes/channels.php) compare `$user->tenant_id` :
     * un abonnement au canal d'une autre entreprise est refusé par le serveur
     * WebSocket. Le cloisonnement ne repose donc pas sur le contenu du message
     * mais sur qui peut écouter — la seule garantie qui tienne.
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("tenant.{$this->payload->tenantId}")];
    }

    public function broadcastAs(): string
    {
        return 'hse.event.created';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->payload->toArray();
    }
}
