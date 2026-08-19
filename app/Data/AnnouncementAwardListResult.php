<?php

namespace App\Data;

final readonly class AnnouncementAwardListResult
{
    /**
     * @param  list<AnnouncementIssue>  $warnings
     * @param  list<AnnouncementIssue>  $blockingErrors
     */
    public function __construct(
        public string $text,
        public string $displayText,
        public int $awardCount,
        public array $warnings,
        public array $blockingErrors,
    ) {}
}
