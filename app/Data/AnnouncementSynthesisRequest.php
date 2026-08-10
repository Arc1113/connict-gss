<?php

namespace App\Data;

use App\Enums\AnnouncementProvider;

final readonly class AnnouncementSynthesisRequest
{
    public function __construct(
        public string $ssml,
        public string $plainText,
        public int $characterCount,
        public string $voice,
        public string $locale,
        public string $outputFormat,
        public string $sourceHash,
        public AnnouncementProvider $provider = AnnouncementProvider::Azure,
        public int $speakingRatePercent = 0,
        public int $pauseAfterNameMs = 0,
        public int $pauseBetweenSectionsMs = 0,
        /** @var list<array{code: string, text: string}> */
        public array $sections = [],
    ) {}
}
