<?php

namespace App\Data;

use App\Models\AnnouncementGenerationBatch;

final readonly class AnnouncementBatchStartResult
{
    public function __construct(
        public AnnouncementGenerationBatch $batch,
        public bool $reused,
    ) {}
}
