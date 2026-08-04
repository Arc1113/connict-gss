<?php

namespace App\Enums;

enum AnnouncementBatchStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Paused = 'paused';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function terminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }
}
