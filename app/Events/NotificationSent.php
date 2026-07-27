<?php

namespace App\Events;

use App\Models\AppNotification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly AppNotification $notification,
        public readonly int $recipientId,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("user.{$this->recipientId}")];
    }

    public function broadcastAs(): string
    {
        return 'notification.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'id'         => $this->notification->id,
            'type'       => $this->notification->data['type'] ?? 'info',
            'title'      => $this->notification->data['title'] ?? '',
            'body'       => $this->notification->data['body'] ?? '',
            'sent_by'    => $this->notification->data['sent_by_name'] ?? null,
            'created_at' => $this->notification->created_at->toIso8601String(),
            'read_at'    => null,
        ];
    }
}
