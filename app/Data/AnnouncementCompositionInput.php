<?php

namespace App\Data;

use App\Enums\AnnouncementProvider;

final readonly class AnnouncementCompositionInput
{
    /**
     * @param  list<AnnouncementAwardInput>  $awards
     */
    public function __construct(
        public int|string $studentIdentifier,
        public ?string $announcementName,
        public ?string $ttsSpokenName,
        public ?string $program,
        public ?string $major,
        public ?string $honors,
        public array $awards,
        public ?string $announcementAdditionalText,
        public int|string $templateIdentifier,
        public int $templateVersion,
        public ?string $introductionText,
        public bool $includeProgram,
        public bool $includeMajor,
        public bool $includeHonors,
        public bool $includeAwards,
        public bool $includeAdditionalText,
        public ?string $awardsLeadIn,
        public ?string $closingText,
        public string $voice,
        public string $locale,
        public int $speakingRatePercent,
        public int $pauseAfterNameMs,
        public int $pauseBetweenSectionsMs,
        public int $recommendedCharacterLimit,
        public int $maximumCharacterLimit,
        public string $outputFormat,
        public AnnouncementProvider $provider = AnnouncementProvider::Azure,
        public ?int $ceremonyIdentifier = null,
        public bool $includeIntroduction = true,
    ) {}

    public function fingerprintData(): AnnouncementSourceFingerprintData
    {
        return new AnnouncementSourceFingerprintData(
            studentIdentifier: $this->studentIdentifier,
            announcementName: $this->announcementName ?? '',
            ttsSpokenName: $this->ttsSpokenName,
            program: $this->program,
            major: $this->major,
            honors: $this->honors,
            awards: array_map(
                static fn (AnnouncementAwardInput $award): array => [
                    'display_order' => $award->displayOrder,
                    'award_name' => $award->awardName,
                    'spoken_text' => $award->spokenText,
                    'is_announcement_enabled' => $award->isAnnouncementEnabled,
                ],
                $this->awards,
            ),
            announcementAdditionalText: $this->announcementAdditionalText,
            templateIdentifier: $this->templateIdentifier,
            templateVersion: $this->templateVersion,
            introductionText: $this->introductionText ?? '',
            includeIntroduction: $this->includeIntroduction,
            includeProgram: $this->includeProgram,
            includeMajor: $this->includeMajor,
            includeHonors: $this->includeHonors,
            includeAwards: $this->includeAwards,
            includeAdditionalText: $this->includeAdditionalText,
            awardsLeadIn: $this->awardsLeadIn,
            closingText: $this->closingText,
            provider: $this->provider,
            voice: $this->voice,
            locale: $this->locale,
            speakingRatePercent: $this->speakingRatePercent,
            pauseAfterNameMs: $this->pauseAfterNameMs,
            pauseBetweenSectionsMs: $this->pauseBetweenSectionsMs,
            outputFormat: $this->outputFormat,
        );
    }
}
