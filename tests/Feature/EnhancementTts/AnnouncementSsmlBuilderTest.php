<?php

namespace Tests\Feature\EnhancementTts;

use App\Data\AnnouncementSsmlResult;
use App\Services\BuildGraduationAnnouncementSsml;
use App\Services\ComposeGraduationAnnouncement;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AnnouncementSsmlBuilderTest extends AnnouncementTestCase
{
    use RefreshDatabase;

    public function test_ssml_is_valid_namespaced_deterministic_xml_with_controlled_metadata(): void
    {
        $result = $this->build();
        $document = new DOMDocument;

        $this->assertTrue($document->loadXML($result->ssml));
        $this->assertSame('http://www.w3.org/2001/10/synthesis', $document->documentElement->namespaceURI);
        $this->assertSame('1.0', $document->documentElement->getAttribute('version'));
        $this->assertSame('en-PH', $document->documentElement->getAttributeNS(
            'http://www.w3.org/XML/1998/namespace',
            'lang',
        ));
        $this->assertSame('-5%', $result->speakingRate);
        $this->assertSame($result->ssml, $this->build()->ssml);
    }

    public function test_dynamic_xml_characters_and_quotes_are_escaped_without_changing_plain_text(): void
    {
        $result = $this->build([
            'announcementName' => 'Ana & Ben <Cruz> "D’Angelo"',
            'program' => 'Arts > Science & Design',
            'honors' => 'Dean’s "Choice"',
        ]);

        $this->assertStringContainsString('&amp;', $result->ssml);
        $this->assertStringContainsString('&lt;Cruz&gt;', $result->ssml);
        $this->assertStringContainsString('Ana & Ben <Cruz> "D’Angelo"', $result->plainText);
        $this->assertSame($result->plainText, $this->xmlSpokenText($result->ssml));
    }

    public function test_known_raw_ssml_is_rejected_instead_of_interpreted(): void
    {
        foreach (['<voice name="evil">Name</voice>', '<break time="9999ms"/>'] as $name) {
            $result = $this->build(['announcementName' => $name]);

            $this->assertNull($result->ssml);
            $this->assertContains('raw_markup_detected', $this->issueCodes($result->blockingErrors));
        }
    }

    public function test_allowlisted_voice_locale_pairs_are_accepted(): void
    {
        foreach (config('announcements.voices') as $voice => $locale) {
            $this->assertTrue($this->build(['voice' => $voice, 'locale' => $locale])->isReady());
        }
    }

    public function test_arbitrary_voice_attribute_injection_and_locale_mismatch_are_blocked(): void
    {
        $voice = $this->build(['voice' => 'en-PH-JamesNeural" xml:lang="evil']);
        $locale = $this->build(['voice' => 'en-PH-JamesNeural', 'locale' => 'fil-PH']);

        $this->assertNull($voice->ssml);
        $this->assertContains('unsupported_voice', $this->issueCodes($voice->blockingErrors));
        $this->assertNull($locale->ssml);
        $this->assertContains('unsupported_locale', $this->issueCodes($locale->blockingErrors));
    }

    public function test_rate_and_pause_boundaries_are_serialized_as_controlled_values(): void
    {
        $minimum = $this->build([
            'speakingRatePercent' => -20,
            'pauseAfterNameMs' => 0,
            'pauseBetweenSectionsMs' => 0,
        ]);
        $maximum = $this->build([
            'speakingRatePercent' => 10,
            'pauseAfterNameMs' => 1500,
            'pauseBetweenSectionsMs' => 1500,
        ]);
        $zero = $this->build(['speakingRatePercent' => 0]);

        $this->assertSame('-20%', $minimum->speakingRate);
        $this->assertSame([], $minimum->breaksMilliseconds);
        $this->assertSame('+10%', $maximum->speakingRate);
        $this->assertSame('0%', $zero->speakingRate);
        $this->assertContains(1500, $maximum->breaksMilliseconds);
        $this->assertStringContainsString('time="1500ms"', $maximum->ssml);
    }

    public function test_out_of_range_rates_and_pauses_are_blocking(): void
    {
        foreach ([
            ['speakingRatePercent' => -21, 'expected' => 'speaking_rate_out_of_range'],
            ['speakingRatePercent' => 11, 'expected' => 'speaking_rate_out_of_range'],
            ['pauseAfterNameMs' => -1, 'expected' => 'pause_out_of_range'],
            ['pauseBetweenSectionsMs' => 1501, 'expected' => 'pause_out_of_range'],
        ] as $case) {
            $expected = $case['expected'];
            unset($case['expected']);
            $result = $this->build($case);

            $this->assertNull($result->ssml);
            $this->assertContains($expected, $this->issueCodes($result->blockingErrors));
        }
    }

    public function test_breaks_are_section_aware_and_not_inserted_inside_award_lists(): void
    {
        $result = $this->build([
            'awards' => [
                $this->award('One', 'the First Award', 0, 1),
                $this->award('Two', 'the Second Award', 1, 2),
            ],
        ]);

        $this->assertSame([160, 250, 160], $result->breaksMilliseconds);
        $this->assertSame(3, substr_count($result->ssml, '<break'));
        $this->assertStringNotContainsString('azure_key', $result->ssml);
        $this->assertStringNotContainsString('Authorization', $result->ssml);
        $this->assertStringNotContainsString('https://', $result->ssml);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function build(array $overrides = []): AnnouncementSsmlResult
    {
        $composition = app(ComposeGraduationAnnouncement::class)->compose(
            $this->compositionInput($overrides),
        );

        return app(BuildGraduationAnnouncementSsml::class)->build($composition);
    }

    private function xmlSpokenText(string $ssml): string
    {
        $document = new DOMDocument;
        $document->loadXML($ssml);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('s', 'http://www.w3.org/2001/10/synthesis');
        $prosody = $xpath->query('//s:prosody')->item(0);

        return trim((string) $prosody?->textContent);
    }
}
