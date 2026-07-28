<?php

namespace App\Enums;

enum StageQueueStatus: string
{
    case Waiting = 'waiting';
    case Ready = 'ready';
    case Presenting = 'presenting';
    case Announced = 'announced';
    case Completed = 'completed';
    case Skipped = 'skipped';
    case Cancelled = 'cancelled';

    public function isActive(): bool
    {
        return match ($this) {
            self::Waiting, self::Ready, self::Presenting, self::Announced => true,
            self::Completed, self::Skipped, self::Cancelled => false,
        };
    }
}
