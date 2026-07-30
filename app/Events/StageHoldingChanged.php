<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

final class StageHoldingChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly int $ceremonyId,
        public readonly int $version,
        public readonly bool $holding,
        public readonly int $eventId,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('stage.'.$this->ceremonyId);
    }

    public function broadcastAs(): string
    {
        return 'stage.holding-changed';
    }

    /**
     * @return array<string, int|bool>
     */
    public function broadcastWith(): array
    {
        return [
            'version' => $this->version,
            'holding' => $this->holding,
            'event_id' => $this->eventId,
        ];
    }
}
