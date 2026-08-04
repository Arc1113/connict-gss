<?php

namespace App\Data;

use App\Models\AnnouncementGeneration;

final readonly class AnnouncementCandidateReviewResult
{
    public function __construct(
        public AnnouncementGeneration $generation,
        public bool $stale = false,
    ) {}
}
