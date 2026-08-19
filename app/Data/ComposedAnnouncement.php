<?php

namespace App\Data;

final readonly class ComposedAnnouncement
{
    /**
     * @param  list<AnnouncementSection>  $sections
     * @param  list<AnnouncementIssue>  $warnings
     * @param  list<AnnouncementIssue>  $blockingErrors
     */
    public function __construct(
        public AnnouncementCompositionInput $input,
        public string $plainText,
        public string $displayText,
        public array $sections,
        public int $characterCount,
        public int $awardCount,
        public array $warnings,
        public array $blockingErrors,
        public string $sourceHash,
        public string $voice,
        public string $locale,
        public AnnouncementLengthAssessment $lengthAssessment,
        public ?AnnouncementDurationEstimate $durationEstimate,
        /** @var list<array<string, mixed>> */
        public array $appliedRules = [],
    ) {}

    public function isReady(): bool
    {
        return $this->blockingErrors === [];
    }
}
