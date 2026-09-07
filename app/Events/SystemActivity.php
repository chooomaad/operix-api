<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Activité système diffusée à TOUTE l'entreprise en temps réel.
 *
 * Émise par AuditObserver à chaque action auditée (création / modification /
 * suppression) sur le canal privé `tenant.{id}` — auquel tous les utilisateurs de
 * l'entreprise sont abonnés. Le client affiche un toast en haut de l'écran.
 *
 * Le cloisonnement repose sur l'autorisation du canal (routes/channels.php) : un
 * utilisateur d'une autre entreprise ne peut pas s'y abonner.
 */
class SystemActivity implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    /** Attendre le commit : aucune annonce pour une transaction qui échoue. */
    public bool $afterCommit = true;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly int $tenantId,
        public readonly array $payload,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("tenant.{$this->tenantId}")];
    }

    public function broadcastAs(): string
    {
        return 'system.activity';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
