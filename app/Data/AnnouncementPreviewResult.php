<?php

namespace App\Data;

final readonly class AnnouncementPreviewResult
{
    /**
     * @param  list<AnnouncementIssue>  $warnings
     * @param  list<AnnouncementIssue>  $blockingErrors
     */
    public function __construct(
        public ?AnnouncementCompositionInput $input,
        public ?ComposedAnnouncement $composition,
        public ?AnnouncementSsmlResult $ssml,
        public array $warnings,
        public array $blockingErrors,
    ) {}

    public function isReady(): bool
    {
        return $this->composition?->isReady() === true
            && $this->ssml?->isReady() === true
            && $this->blockingErrors === [];
    }
}
