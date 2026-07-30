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

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Waiting, self::Ready => in_array(
                $next,
                [self::Presenting, self::Skipped, self::Cancelled],
                true,
            ),
            self::Skipped => in_array(
                $next,
                [self::Waiting, self::Cancelled],
                true,
            ),
            self::Presenting, self::Announced => in_array(
                $next,
                [self::Waiting, self::Completed],
                true,
            ),
            self::Completed, self::Cancelled => false,
        };
    }
}
