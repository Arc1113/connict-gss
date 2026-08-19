<?php

namespace App\Data;

use App\Enums\AnnouncementProvider;

final readonly class AnnouncementSourceFingerprintData
{
    public const COMPOSITION_SCHEMA_VERSION = 2;

    public const KOKORO_ENGINE_REVISION = 2;

    /**
     * @param  list<array{
     *     display_order: int,
     *     award_name: string,
     *     spoken_text: string|null,
     *     is_announcement_enabled: bool
     * }>  $awards
     */
    public function __construct(
        public int|string $studentIdentifier,
        public string $announcementName,
        public ?string $ttsSpokenName,
        public ?string $program,
        public ?string $major,
        public ?string $honors,
        public array $awards,
        public ?string $announcementAdditionalText,
        public int|string $templateIdentifier,
        public int $templateVersion,
        public string $introductionText,
        public bool $includeProgram,
        public bool $includeMajor,
        public bool $includeHonors,
        public bool $includeAwards,
        public bool $includeAdditionalText,
        public ?string $awardsLeadIn,
        public ?string $closingText,
        public AnnouncementProvider $provider,
        public string $voice,
        public string $locale,
        public int $speakingRatePercent,
        public int $pauseAfterNameMs,
        public int $pauseBetweenSectionsMs,
        public ?string $outputFormat = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toCanonicalArray(): array
    {
        return [
            'composition_schema_version' => self::COMPOSITION_SCHEMA_VERSION,
            'schema' => 1,
            'student' => [
                'identifier' => $this->studentIdentifier,
                'announcement_name' => $this->announcementName,
                'tts_spoken_name' => $this->ttsSpokenName,
                'program' => $this->program,
                'major' => $this->major,
                'honors' => $this->honors,
                'awards' => array_map(
                    static fn (array $award): array => [
                        'display_order' => (int) $award['display_order'],
                        'award_name' => (string) $award['award_name'],
                        'spoken_text' => $award['spoken_text'] === null
                            ? null
                            : (string) $award['spoken_text'],
                        'is_announcement_enabled' => (bool) $award['is_announcement_enabled'],
                    ],
                    $this->awards,
                ),
                'announcement_additional_text' => $this->announcementAdditionalText,
            ],
            'template' => [
                'identifier' => $this->templateIdentifier,
                'version' => $this->templateVersion,
                'introduction_text' => $this->introductionText,
                'include_program' => $this->includeProgram,
                'include_major' => $this->includeMajor,
                'include_honors' => $this->includeHonors,
                'include_awards' => $this->includeAwards,
                'include_additional_text' => $this->includeAdditionalText,
                'awards_lead_in' => $this->awardsLeadIn,
                'closing_text' => $this->closingText,
            ],
            'synthesis' => [
                'provider' => $this->provider->value,
                'engine_revision' => $this->provider === AnnouncementProvider::Kokoro
                    ? self::KOKORO_ENGINE_REVISION
                    : 1,
                'voice' => $this->voice,
                'locale' => $this->locale,
                'speaking_rate_percent' => $this->speakingRatePercent,
                'pause_after_name_ms' => $this->pauseAfterNameMs,
                'pause_between_sections_ms' => $this->pauseBetweenSectionsMs,
                'output_format' => $this->outputFormat,
            ],
        ];
    }
}
