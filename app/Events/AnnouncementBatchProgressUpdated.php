<?php

namespace App\Events;

use App\Models\AnnouncementGenerationBatch;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

final class AnnouncementBatchProgressUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly int $batchId,
        public readonly int $ceremonyId,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('announcement-batches.'.$this->ceremonyId);
    }

    public function broadcastAs(): string
    {
        return 'announcement-batch.updated';
    }

    /** @return array<string, int|string|null> */
    public function broadcastWith(): array
    {
        $batch = AnnouncementGenerationBatch::query()->find($this->batchId);

        return [
            'batch_id' => $this->batchId,
            'status' => $batch?->status->value,
            'processed_count' => $batch?->processed_count,
            'selected_count' => $batch?->selected_count,
        ];
    }
}
