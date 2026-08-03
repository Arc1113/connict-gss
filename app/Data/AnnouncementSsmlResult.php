<?php

namespace App\Data;

final readonly class AnnouncementSsmlResult
{
    /**
     * @param  list<int>  $breaksMilliseconds
     * @param  list<AnnouncementIssue>  $warnings
     * @param  list<AnnouncementIssue>  $blockingErrors
     */
    public function __construct(
        public ?string $ssml,
        public string $plainText,
        public string $voice,
        public string $locale,
        public string $speakingRate,
        public array $breaksMilliseconds,
        public array $warnings,
        public array $blockingErrors,
    ) {}

    public function isReady(): bool
    {
        return $this->ssml !== null && $this->blockingErrors === [];
    }
}
