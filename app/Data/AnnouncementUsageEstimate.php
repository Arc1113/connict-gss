<?php

namespace App\Data;

final readonly class AnnouncementUsageEstimate
{
    public function __construct(
        public int $successfulCharacters,
        public int $monthlyBudget,
        public int $warningThreshold,
        public int $estimatedRemaining,
        public bool $warning,
        public string $label = 'Local estimate only; Azure billing remains authoritative.',
    ) {}
}
