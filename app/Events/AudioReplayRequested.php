<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

final class AudioReplayRequested implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly int $ceremonyId,
        public readonly int $version,
        public readonly int $eventId,
        public readonly int $studentId,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('stage.'.$this->ceremonyId);
    }

    public function broadcastAs(): string
    {
        return 'audio.replay-requested';
    }

    /**
     * @return array<string, int>
     */
    public function broadcastWith(): array
    {
        return [
            'version' => $this->version,
            'event_id' => $this->eventId,
            'student_id' => $this->studentId,
        ];
    }
}
