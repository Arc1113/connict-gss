<?php

namespace App\Data;

final readonly class AnnouncementAwardInput
{
    public function __construct(
        public int|string|null $identifier,
        public string $awardName,
        public ?string $spokenText,
        public int $displayOrder,
        public bool $isAnnouncementEnabled,
    ) {}
}
