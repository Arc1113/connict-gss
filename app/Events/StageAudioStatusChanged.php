<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

final class StageAudioStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly int $ceremonyId,
        public readonly int $version,
        public readonly int $studentId,
        public readonly string $status,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('stage-control.'.$this->ceremonyId);
    }

    public function broadcastAs(): string
    {
        return 'stage.audio-status';
    }

    /** @return array<string, int|string> */
    public function broadcastWith(): array
    {
        return [
            'version' => $this->version,
            'student_id' => $this->studentId,
            'status' => $this->status,
        ];
    }
}
