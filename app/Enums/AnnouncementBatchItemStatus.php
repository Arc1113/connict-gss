<?php

namespace App\Enums;

enum AnnouncementBatchItemStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Reused = 'reused';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function terminal(): bool
    {
        return ! in_array($this, [self::Queued, self::Processing], true);
    }
}
