<?php

namespace App\Data;

final readonly class AnnouncementDurationEstimate
{
    public function __construct(
        public int $minimumSeconds,
        public int $maximumSeconds,
        public int $wordCount,
        public int $includedPauseMilliseconds,
        public string $label = 'Approximate duration; synthesized audio may differ.',
    ) {}
}
