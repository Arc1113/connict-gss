<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

final class QueueUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly int $ceremonyId,
        public readonly int $version,
        public readonly string $action,
        public readonly ?int $queueItemId = null,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('stage-control.'.$this->ceremonyId);
    }

    public function broadcastAs(): string
    {
        return 'queue.updated';
    }

    /**
     * @return array<string, int|string|null>
     */
    public function broadcastWith(): array
    {
        return [
            'version' => $this->version,
            'action' => $this->action,
            'queue_item_id' => $this->queueItemId,
        ];
    }
}
