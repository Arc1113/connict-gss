<?php

namespace App\Data;

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
    ) {}
}
