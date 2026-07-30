<?php

namespace App\Support;

use App\Enums\StageQueueStatus;
use App\Models\StageQueueItem;
use DomainException;

final class StageQueueTransitions
{
    public function nextPosition(int $ceremonyId): int
    {
        return ((int) StageQueueItem::query()
            ->where('ceremony_id', $ceremonyId)
            ->max('queue_position')) + 1;
    }

    public function transition(
        StageQueueItem $queueItem,
        StageQueueStatus $next,
    ): void {
        if (! $queueItem->status->canTransitionTo($next)) {
            throw new DomainException(sprintf(
                'Queue status %s cannot transition to %s.',
                $queueItem->status->value,
                $next->value,
            ));
        }

        $queueItem->status = $next;
    }
}
