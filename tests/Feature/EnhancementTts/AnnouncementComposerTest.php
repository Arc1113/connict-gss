<?php

namespace Tests\Feature\EnhancementTts;

use App\Data\ComposedAnnouncement;
use App\Enums\PronunciationRuleSection;
use App\Enums\AnnouncementProvider;
use App\Services\BuildAnnouncementSourceFingerprint;
use App\Services\ComposeGraduationAnnouncement;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AnnouncementComposerTest extends AnnouncementTestCase
{
    use RefreshDatabase;

    public function test_it_uses_the_spoken_override_and_normalizes_unicode_whitespace(): void
    {
        $result = $this->compose([
            'announcementName' => 'Displayed Name',
            'ttsSpokenName' => "  Ma.  Élan\nNiño O’Connor  ",
        ]);

        $this->assertSame(
            'We proudly present, Ma. Élan Niño O’Connor, Bachelor of Science in Fictional Studies.',
            $result->plainText,
        );
        $this->assertSame(
            'We proudly present, Displayed Name, Bachelor of Science in Fictional Studies.',
            $result->displayText,
        );
        $this->assertSame(mb_strlen($result->plainText), $result->characterCount);
        $this->assertSame('en-PH-JamesNeural', $result->voice);
        $this->assertSame('en-PH', $result->locale);
    }

    public function test_award_override_changes_speech_without_changing_display_spelling(): void
    {
        $this->pronunciationRule(
            'Leadership Medal',
            'lee-der-ship medal',
            PronunciationRuleSection::Award,
        );
        $result = $this->compose([
            'includeAwards' => true,
            'awards' => [
                $this->award('Excellence Award', 'ek-suh-lens award', 0, 1),
                $this->award('Leadership Medal', null, 1, 2),
            ],
        ]);

        $this->assertStringContainsString('ek-suh-lens award', $result->plainText);
        $this->assertStringContainsString('lee-der-ship medal', $result->plainText);
        $this->assertStringContainsString('Excellence Award', $result->displayText);
        $this->assertStringContainsString('Leadership Medal', $result->displayText);
        $this->assertStringNotContainsString('ek-suh-lens', $result->displayText);
        $this->assertCount(1, $result->appliedRules);
    }

    public function test_whitespace_override_falls_back_to_announcement_name(): void
    {
        $result = $this->compose([
            'announcementName' => ' Ana Dela Cruz ',
            'ttsSpokenName' => " \n ",
        ]);

        $this->assertStringContainsString('Ana Dela Cruz', $result->plainText);
        $this->assertNotContains('missing_spoken_name', $this->issueCodes($result->blockingErrors));
    }

    public function test_missing_name_and_required_program_are_blocking_without_fabricated_values(): void
    {
        $result = $this->compose([
            'announcementName' => null,
            'ttsSpokenName' => 'undefined',
            'program' => null,
        ]);

        $codes = $this->issueCodes($result->blockingErrors);
        $this->assertContains('raw_placeholder_detected', $codes);
        $this->assertContains('missing_spoken_name', $codes);
        $this->assertContains('missing_program', $codes);
        $this->assertStringNotContainsString('undefined', $result->plainText);
        $this->assertStringNotContainsString('null', $result->plainText);
    }

    public function test_program_and_optional_sections_follow_flags_in_approved_order(): void
    {
        $this->pronunciationRule('Magna Cum Laude', 'mag-na koom lau-deh');
        $result = $this->compose([
            'major' => 'Creative Computing',
            'honors' => 'Magna Cum Laude',
            'announcementAdditionalText' => 'with an approved fictional distinction',
            'includeAdditionalText' => true,
            'closingText' => 'Congratulations!',
        ]);

        $this->assertSame(
            ['introduction', 'name', 'program', 'major', 'honors', 'additional_text', 'closing'],
            array_column($result->sections, 'code'),
        );
        $this->assertSame(
            'We proudly present, Fictional Graduate, Bachelor of Science in Fictional Studies, Creative Computing, mag-na koom lau-deh, with an approved fictional distinction, Congratulations!',
            $result->plainText,
        );
        $this->assertStringContainsString('Magna Cum Laude', $result->displayText);
        $this->assertCount(1, $result->appliedRules);

        $omitted = $this->compose([
            'includeProgram' => false,
            'includeMajor' => false,
            'includeHonors' => false,
            'includeAdditionalText' => false,
            'program' => null,
            'major' => 'Not spoken',
            'honors' => 'Not spoken',
            'announcementAdditionalText' => 'Not spoken',
        ]);

        $this->assertSame(['introduction', 'name'], array_column($omitted->sections, 'code'));
        $this->assertTrue($omitted->isReady());
    }

    public function test_introduction_can_be_omitted_for_a_course_continuation(): void
    {
        $result = $this->compose(['includeIntroduction' => false]);

        $this->assertSame(
            'Fictional Graduate, Bachelor of Science in Fictional Studies.',
            $result->plainText,
        );
        $this->assertSame(['name', 'program'], array_column($result->sections, 'code'));
    }

    public function test_disabled_optional_source_fields_do_not_affect_readiness(): void
    {
        $result = $this->compose([
            'includeProgram' => false,
            'includeMajor' => false,
            'includeHonors' => false,
            'includeAdditionalText' => false,
            'program' => '<break time="500ms"/>',
            'major' => '<voice name="other">Major</voice>',
            'honors' => 'undefined',
            'announcementAdditionalText' => '{{placeholder}}',
        ]);

        $this->assertTrue($result->isReady());
        $this->assertSame('We proudly present, Fictional Graduate.', $result->plainText);
    }

    public function test_major_deduplication_is_conservative_and_deterministic(): void
    {
        $exact = $this->compose(['major' => ' bachelor of science in fictional studies ']);
        $contained = $this->compose(['major' => 'Fictional Studies']);
        $distinct = $this->compose(['major' => 'Fictional Systems']);

        $this->assertNotContains('major', array_column($exact->sections, 'code'));
        $this->assertNotContains('major', array_column($contained->sections, 'code'));
        $this->assertContains('major_duplicates_program', $this->issueCodes($exact->warnings));
        $this->assertContains('major', array_column($distinct->sections, 'code'));
    }

    public function test_honors_duplicate_is_omitted_but_distinct_honors_are_preserved(): void
    {
        $duplicate = $this->compose(['honors' => 'Fictional Studies']);
        $distinct = $this->compose(['honors' => 'Mágna Cum Laude']);

        $this->assertNotContains('honors', array_column($duplicate->sections, 'code'));
        $this->assertContains('honors_duplicates_section', $this->issueCodes($duplicate->warnings));
        $this->assertStringContainsString('Mágna Cum Laude', $distinct->plainText);
    }

    public function test_terminal_punctuation_is_normalized_without_duplicate_marks(): void
    {
        $period = $this->compose(['closingText' => 'Welcome...']);
        $question = $this->compose(['closingText' => 'Are you ready?']);

        $this->assertStringEndsWith('Welcome.', $period->plainText);
        $this->assertStringNotContainsString(',.', $period->plainText);
        $this->assertStringEndsWith('Are you ready?', $question->plainText);
    }

    public function test_length_boundaries_warn_and_block_without_truncating(): void
    {
        $baseline = $this->compose();
        $atRecommended = $this->compose([
            'recommendedCharacterLimit' => $baseline->characterCount,
            'maximumCharacterLimit' => $baseline->characterCount + 1,
        ]);
        $warning = $this->compose([
            'recommendedCharacterLimit' => $baseline->characterCount - 1,
            'maximumCharacterLimit' => $baseline->characterCount,
        ]);
        $blocking = $this->compose([
            'recommendedCharacterLimit' => $baseline->characterCount - 2,
            'maximumCharacterLimit' => $baseline->characterCount - 1,
        ]);

        $this->assertSame('normal', $atRecommended->lengthAssessment->status);
        $this->assertSame('warning', $warning->lengthAssessment->status);
        $this->assertSame('blocking', $blocking->lengthAssessment->status);
        $this->assertSame($baseline->plainText, $blocking->plainText);
        $this->assertContains(
            'announcement_above_maximum_limit',
            $this->issueCodes($blocking->blockingErrors),
        );
    }

    public function test_duration_estimate_is_stable_and_rate_adjusted(): void
    {
        $introduction = trim(str_repeat('Fictional ceremony wording ', 20));
        $default = $this->compose(['introductionText' => $introduction]);
        $faster = $this->compose([
            'introductionText' => $introduction,
            'speakingRatePercent' => 10,
        ]);

        $this->assertNotNull($default->durationEstimate);
        $this->assertEquals(
            $default->durationEstimate,
            $this->compose(['introductionText' => $introduction])->durationEstimate,
        );
        $this->assertLessThan(
            $default->durationEstimate->maximumSeconds,
            $faster->durationEstimate->maximumSeconds,
        );
        $this->assertStringStartsWith('Approximate', $default->durationEstimate->label);
    }

    public function test_local_duration_does_not_add_azure_only_section_pauses(): void
    {
        $local = $this->compose([
            'provider' => AnnouncementProvider::Kokoro,
            'voice' => 'am_michael',
            'locale' => 'en-US',
            'pauseAfterNameMs' => 1500,
            'pauseBetweenSectionsMs' => 1500,
        ]);

        $this->assertNotNull($local->durationEstimate);
        $this->assertSame(0, $local->durationEstimate->includedPauseMilliseconds);
    }

    public function test_identical_normalized_input_has_one_hash_and_spoken_changes_invalidate_it(): void
    {
        $first = $this->compose(['announcementName' => ' Fictional   Graduate ']);
        $second = $this->compose(['announcementName' => "Fictional\nGraduate"]);
        $changed = $this->compose(['announcementName' => 'Another Graduate']);

        $this->assertSame($first->sourceHash, $second->sourceHash);
        $this->assertNotSame($first->sourceHash, $changed->sourceHash);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $first->sourceHash);
        $this->assertStringContainsString(
            '"composition_schema_version":2',
            app(BuildAnnouncementSourceFingerprint::class)->canonicalJson(
                $first->input->fingerprintData(),
            ),
        );
    }

    public function test_empty_blocked_output_has_no_misleading_duration(): void
    {
        $result = $this->compose([
            'introductionText' => null,
            'announcementName' => null,
            'includeProgram' => false,
        ]);

        $this->assertSame('', $result->plainText);
        $this->assertNull($result->durationEstimate);
        $this->assertContains('missing_spoken_name', $this->issueCodes($result->blockingErrors));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function compose(array $overrides = []): ComposedAnnouncement
    {
        return app(ComposeGraduationAnnouncement::class)->compose(
            $this->compositionInput($overrides),
        );
    }
}
