<?php

namespace Tests\Unit;

use App\Data\AnnouncementSourceFingerprintData;
use App\Enums\AnnouncementProvider;
use App\Services\BuildAnnouncementSourceFingerprint;
use PHPUnit\Framework\TestCase;

class BuildAnnouncementSourceFingerprintTest extends TestCase
{
    public function test_identical_semantic_sources_produce_the_same_lowercase_sha256(): void
    {
        $service = new BuildAnnouncementSourceFingerprint;
        $first = $service->handle($this->source());
        $second = $service->handle($this->source([
            'awards' => [[
                'spoken_text' => 'the Fictional Research Award',
                'is_announcement_enabled' => true,
                'award_name' => 'Fictional Research Award',
                'display_order' => 0,
            ], [
                'is_announcement_enabled' => true,
                'display_order' => 1,
                'spoken_text' => 'the Fictional Leadership Award',
                'award_name' => 'Fictional Leadership Award',
            ]],
        ]));

        $this->assertSame($first, $second);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $first);
    }

    public function test_every_announcement_relevant_change_invalidates_the_hash(): void
    {
        $service = new BuildAnnouncementSourceFingerprint;
        $baseline = $service->handle($this->source());
        $changes = [
            ['announcementName' => 'Another Fictional Graduate'],
            ['ttsSpokenName' => 'A reviewed spoken override'],
            ['program' => 'Bachelor of Arts in Fiction'],
            ['major' => 'Imaginary Systems'],
            ['honors' => 'Summa Cum Laude'],
            ['awards' => [[
                'display_order' => 0,
                'award_name' => 'Fictional Research Award',
                'spoken_text' => 'the Fictional Research Award',
                'is_announcement_enabled' => true,
            ]]],
            ['awards' => [...$this->awards(), [
                'display_order' => 2,
                'award_name' => 'Fictional Service Award',
                'spoken_text' => 'the Fictional Service Award',
                'is_announcement_enabled' => true,
            ]]],
            ['awards' => array_reverse($this->awards())],
            ['awards' => [[
                ...$this->awards()[0],
                'spoken_text' => 'a polished fictional research distinction',
            ], $this->awards()[1]]],
            ['awards' => [[
                ...$this->awards()[0],
                'is_announcement_enabled' => false,
            ], $this->awards()[1]]],
            ['announcementAdditionalText' => 'With an approved fictional distinction'],
            ['templateVersion' => 2],
            ['introductionText' => 'The institution proudly presents'],
            ['includeProgram' => false],
            ['includeMajor' => false],
            ['includeHonors' => false],
            ['includeAwards' => false],
            ['includeAdditionalText' => true],
            ['awardsLeadIn' => 'also recognized with'],
            ['closingText' => 'Congratulations'],
            ['voice' => 'en-PH-RosaNeural'],
            ['locale' => 'fil-PH'],
            ['speakingRatePercent' => -4],
            ['pauseAfterNameMs' => 251],
            ['pauseBetweenSectionsMs' => 161],
            ['outputFormat' => 'riff-24khz-16bit-mono-pcm'],
        ];

        foreach ($changes as $change) {
            $this->assertNotSame(
                $baseline,
                $service->handle($this->source($change)),
                'A relevant source change did not invalidate the fingerprint: '.json_encode($change),
            );
        }
    }

    public function test_canonical_source_omits_timestamps_credentials_and_paths(): void
    {
        $json = (new BuildAnnouncementSourceFingerprint)->canonicalJson($this->source());

        foreach ([
            'created_at',
            'updated_at',
            'azure_key',
            'authorization',
            'candidate_audio_path',
            'approved_audio_path',
            'review_notes',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $json);
        }
    }

    public function test_unicode_is_encoded_consistently_without_ascii_escaping(): void
    {
        $service = new BuildAnnouncementSourceFingerprint;
        $source = $this->source([
            'announcementName' => 'Ma. Élan Niño Dela Cruz',
            'major' => 'Wikang Filipino — Malikhaing Pagsulat',
        ]);

        $this->assertSame($service->handle($source), $service->handle($source));
        $this->assertStringContainsString('Élan Niño', $service->canonicalJson($source));
        $this->assertStringNotContainsString('\\u00c9', $service->canonicalJson($source));
    }

    public function test_local_engine_revision_is_part_of_the_canonical_source(): void
    {
        $service = new BuildAnnouncementSourceFingerprint;
        $azure = $this->source();
        $local = $this->source([
            'provider' => AnnouncementProvider::Kokoro,
            'voice' => 'af_heart',
            'locale' => 'en-US',
        ]);

        $this->assertNotSame($service->handle($azure), $service->handle($local));
        $this->assertStringContainsString(
            '"engine_revision":'.AnnouncementSourceFingerprintData::KOKORO_ENGINE_REVISION,
            $service->canonicalJson($local),
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function source(array $overrides = []): AnnouncementSourceFingerprintData
    {
        $values = array_replace([
            'studentIdentifier' => 42,
            'announcementName' => 'Fictional Graduate',
            'ttsSpokenName' => null,
            'program' => 'Bachelor of Science in Fictional Studies',
            'major' => null,
            'honors' => 'Magna Cum Laude',
            'awards' => $this->awards(),
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
            'provider' => AnnouncementProvider::Azure,
            'voice' => 'en-PH-JamesNeural',
            'locale' => 'en-PH',
            'speakingRatePercent' => -5,
            'pauseAfterNameMs' => 250,
            'pauseBetweenSectionsMs' => 160,
            'outputFormat' => 'audio-24khz-48kbitrate-mono-mp3',
        ], $overrides);

        return new AnnouncementSourceFingerprintData(...$values);
    }

    /**
     * @return list<array{
     *     display_order: int,
     *     award_name: string,
     *     spoken_text: string,
     *     is_announcement_enabled: bool
     * }>
     */
    private function awards(): array
    {
        return [[
            'display_order' => 0,
            'award_name' => 'Fictional Research Award',
            'spoken_text' => 'the Fictional Research Award',
            'is_announcement_enabled' => true,
        ], [
            'display_order' => 1,
            'award_name' => 'Fictional Leadership Award',
            'spoken_text' => 'the Fictional Leadership Award',
            'is_announcement_enabled' => true,
        ]];
    }
}
