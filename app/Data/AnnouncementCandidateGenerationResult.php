<?php

namespace App\Data;

use App\Models\AnnouncementGeneration;

final readonly class AnnouncementCandidateGenerationResult
{
    public function __construct(
        public AnnouncementGeneration $generation,
        public bool $reused,
    ) {}
}
