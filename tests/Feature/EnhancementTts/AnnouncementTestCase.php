<?php

namespace Tests\Feature\EnhancementTts;

use App\Data\AnnouncementAwardInput;
use App\Data\AnnouncementCompositionInput;
use App\Data\AnnouncementIssue;
use Tests\TestCase;

abstract class AnnouncementTestCase extends TestCase
{
    protected function mp3(): string
    {
        $decoded = base64_decode(
            'SUQzBAAAAAAAI1RTU0UAAAAPAAADTGF2ZjYwLjE2LjEwMAAAAAAAAAAAAAAA//NkxAAAAANIAAAAAExBTUVVVVVMQU1FMy4xMDBVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVV//NkxHwAAANIAAAAAFVVVVVVVVVMQU1FMy4xMDBVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVV//NkxHwAAANIAAAAAFVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVV',
            true,
        );

        if (! is_string($decoded)) {
            throw new \RuntimeException('The announcement MP3 test fixture is invalid.');
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function compositionInput(array $overrides = []): AnnouncementCompositionInput
    {
        return new AnnouncementCompositionInput(...array_replace([
            'studentIdentifier' => 42,
            'announcementName' => 'Fictional Graduate',
            'ttsSpokenName' => null,
            'program' => 'Bachelor of Science in Fictional Studies',
            'major' => null,
            'honors' => null,
            'awards' => [],
            'announcementAdditionalText' => null,
            'templateIdentifier' => 7,
            'templateVersion' => 1,
            'introductionText' => 'We proudly present',
            'includeProgram' => true,
            'includeMajor' => true,
            'includeHonors' => true,
            'includeAwards' => true,
            'includeAdditionalText' => false,
            'awardsLeadIn' => 'recipient of',
            'closingText' => null,
            'voice' => 'en-PH-JamesNeural',
            'locale' => 'en-PH',
            'speakingRatePercent' => -5,
            'pauseAfterNameMs' => 250,
            'pauseBetweenSectionsMs' => 160,
            'recommendedCharacterLimit' => 450,
            'maximumCharacterLimit' => 700,
            'outputFormat' => 'audio-24khz-48kbitrate-mono-mp3',
        ], $overrides));
    }

    protected function award(
        string $name,
        ?string $spokenText = null,
        int $order = 0,
        int|string|null $identifier = null,
        bool $enabled = true,
    ): AnnouncementAwardInput {
        return new AnnouncementAwardInput(
            identifier: $identifier,
            awardName: $name,
            spokenText: $spokenText,
            displayOrder: $order,
            isAnnouncementEnabled: $enabled,
        );
    }

    /**
     * @param  list<AnnouncementIssue>  $issues
     * @return list<string>
     */
    protected function issueCodes(array $issues): array
    {
        return array_map(static fn ($issue): string => $issue->code, $issues);
    }
}
