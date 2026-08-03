<?php

namespace App\Data;

final readonly class AnnouncementLengthAssessment
{
    public function __construct(
        public int $characterCount,
        public int $recommendedLimit,
        public int $maximumLimit,
        public string $status,
    ) {}
}
