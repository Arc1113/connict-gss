<?php

namespace App\Services;

use App\Data\AnnouncementAwardInput;
use App\Data\AnnouncementCompositionInput;
use App\Data\AnnouncementDurationEstimate;
use App\Data\AnnouncementIssue;
use App\Data\AnnouncementLengthAssessment;
use App\Data\AnnouncementSection;
use App\Data\ComposedAnnouncement;

final class ComposeGraduationAnnouncement
{
    public function __construct(
        private readonly NormalizeAnnouncementText $normalizer,
        private readonly FormatAnnouncementAwardList $awardListFormatter,
        private readonly ApprovedAnnouncementVoices $approvedVoices,
        private readonly BuildAnnouncementSourceFingerprint $fingerprint,
    ) {}

    public function compose(AnnouncementCompositionInput $input): ComposedAnnouncement
    {
        $input = $this->normalizedInput($input);
        $warnings = [];
        $errors = $this->validateSettings($input);
        $sections = [];

        $introduction = $this->plainText(
            $input->introductionText,
            'introduction_text',
            $errors,
        );

        if ($introduction !== null) {
            $sections[] = ['code' => 'introduction', 'content' => $introduction];
        }

        $name = $this->plainText(
            $input->ttsSpokenName,
            'tts_spoken_name',
            $errors,
        ) ?? $this->plainText(
            $input->announcementName,
            'announcement_name',
            $errors,
        );

        if ($name === null) {
            $errors[] = new AnnouncementIssue(
                'missing_spoken_name',
                'An announcement name or TTS spoken name is required.',
                'announcement_name',
            );
        } else {
            $sections[] = ['code' => 'name', 'content' => $name];
        }

        $program = $this->normalizer->normalize($input->program);

        if ($input->includeProgram) {
            $program = $this->plainText($input->program, 'program', $errors);

            if ($program === null) {
                $errors[] = new AnnouncementIssue(
                    'missing_program',
                    'The active template requires a program for this graduate.',
                    'program',
                );
            } else {
                $sections[] = ['code' => 'program', 'content' => $program];
            }
        }

        $major = $input->includeMajor
            ? $this->plainText($input->major, 'major', $errors)
            : null;

        if ($input->includeMajor && $major !== null) {
            if ($program !== null && $this->normalizer->isSameOrContainedPhrase($program, $major)) {
                $warnings[] = new AnnouncementIssue(
                    'major_duplicates_program',
                    'The major was omitted because it repeats the program wording.',
                    'major',
                );
            } else {
                $sections[] = ['code' => 'major', 'content' => $major];
            }
        }

        $honors = $input->includeHonors
            ? $this->plainText($input->honors, 'honors', $errors)
            : null;

        if ($input->includeHonors && $honors !== null) {
            if ($this->duplicatesExistingSection($honors, $sections)) {
                $warnings[] = new AnnouncementIssue(
                    'honors_duplicates_section',
                    'Honors wording was omitted because it repeats another section.',
                    'honors',
                );
            } else {
                $sections[] = ['code' => 'honors', 'content' => $honors];
            }
        }

        $awardCount = 0;

        if ($input->includeAwards) {
            $awardList = $this->awardListFormatter->format(
                $input->awards,
                $input->awardsLeadIn,
            );
            $warnings = [...$warnings, ...$awardList->warnings];
            $errors = [...$errors, ...$awardList->blockingErrors];
            $awardCount = $awardList->awardCount;

            if ($awardList->text !== '') {
                $sections[] = ['code' => 'awards', 'content' => $awardList->text];
            }
        }

        $additionalText = $input->includeAdditionalText
            ? $this->plainText(
                $input->announcementAdditionalText,
                'announcement_additional_text',
                $errors,
            )
            : null;

        if ($input->includeAdditionalText && $additionalText !== null) {
            $sections[] = ['code' => 'additional_text', 'content' => $additionalText];
        }

        $closing = $this->plainText($input->closingText, 'closing_text', $errors);

        if ($closing !== null) {
            $sections[] = ['code' => 'closing', 'content' => $closing];
        }

        $composedSections = $this->punctuateSections($sections);
        $plainText = implode(' ', array_map(
            static fn (AnnouncementSection $section): string => $section->spokenText,
            $composedSections,
        ));
        $characterCount = mb_strlen($plainText, 'UTF-8');
        $lengthAssessment = $this->assessLength($input, $characterCount, $warnings, $errors);
        $durationEstimate = $this->estimateDuration($input, $composedSections, $plainText);

        return new ComposedAnnouncement(
            input: $input,
            plainText: $plainText,
            sections: $composedSections,
            characterCount: $characterCount,
            awardCount: $awardCount,
            warnings: $warnings,
            blockingErrors: $errors,
            sourceHash: $this->fingerprint->handle($input->fingerprintData()),
            voice: $input->voice,
            locale: $input->locale,
            lengthAssessment: $lengthAssessment,
            durationEstimate: $durationEstimate,
        );
    }

    /**
     * @return list<AnnouncementIssue>
     */
    private function validateSettings(AnnouncementCompositionInput $input): array
    {
        $issues = $this->approvedVoices->validationIssues(
            $input->voice,
            $input->locale,
            $input->provider,
        );

        if ($input->speakingRatePercent < $this->approvedVoices->minimumSpeakingRatePercent()
            || $input->speakingRatePercent > $this->approvedVoices->maximumSpeakingRatePercent()) {
            $issues[] = new AnnouncementIssue(
                'speaking_rate_out_of_range',
                'The speaking rate is outside the server-approved range.',
                'speaking_rate_percent',
            );
        }

        foreach ([
            'pause_after_name_ms' => $input->pauseAfterNameMs,
            'pause_between_sections_ms' => $input->pauseBetweenSectionsMs,
        ] as $field => $pause) {
            if ($pause < $this->approvedVoices->minimumPauseMilliseconds()
                || $pause > $this->approvedVoices->maximumPauseMilliseconds()) {
                $issues[] = new AnnouncementIssue(
                    'pause_out_of_range',
                    'Announcement pauses must be within the server-approved range.',
                    $field,
                );
            }
        }

        return $issues;
    }

    /**
     * @param  list<AnnouncementIssue>  $errors
     */
    private function plainText(?string $value, string $field, array &$errors): ?string
    {
        $normalized = $this->normalizer->normalize($value);

        if ($normalized !== null && $this->isRawPlaceholder($normalized)) {
            $errors[] = new AnnouncementIssue(
                'raw_placeholder_detected',
                'Announcement source fields must not contain placeholder values.',
                $field,
            );

            return null;
        }

        if ($normalized !== null && $this->normalizer->containsKnownSsmlMarkup($normalized)) {
            $errors[] = new AnnouncementIssue(
                'raw_markup_detected',
                'Announcement source fields must contain plain text, not SSML elements.',
                $field,
            );
        }

        return $normalized;
    }

    private function isRawPlaceholder(string $value): bool
    {
        if (in_array($this->normalizer->comparisonKey($value), [
            'null',
            'undefined',
            'none',
            'n a',
        ], true)) {
            return true;
        }

        return preg_match('/^(?:\{[^{}]+\}|\{\{[^{}]+\}\}|\$\{[^{}]+\})$/u', $value) === 1;
    }

    private function normalizedInput(AnnouncementCompositionInput $input): AnnouncementCompositionInput
    {
        $awards = array_values($input->awards);

        usort($awards, static function (AnnouncementAwardInput $first, AnnouncementAwardInput $second): int {
            $order = $first->displayOrder <=> $second->displayOrder;

            if ($order !== 0) {
                return $order;
            }

            if ($first->identifier === $second->identifier) {
                return 0;
            }

            if ($first->identifier === null) {
                return 1;
            }

            if ($second->identifier === null) {
                return -1;
            }

            if (is_int($first->identifier) && is_int($second->identifier)) {
                return $first->identifier <=> $second->identifier;
            }

            return strcmp((string) $first->identifier, (string) $second->identifier);
        });

        $awards = array_map(fn (AnnouncementAwardInput $award): AnnouncementAwardInput => new AnnouncementAwardInput(
            identifier: $award->identifier,
            awardName: $this->normalizer->normalize($award->awardName) ?? '',
            spokenText: $this->normalizer->normalize($award->spokenText),
            displayOrder: $award->displayOrder,
            isAnnouncementEnabled: $award->isAnnouncementEnabled,
        ), $awards);

        return new AnnouncementCompositionInput(
            studentIdentifier: $input->studentIdentifier,
            announcementName: $this->normalizer->normalize($input->announcementName),
            ttsSpokenName: $this->normalizer->normalize($input->ttsSpokenName),
            program: $this->normalizer->normalize($input->program),
            major: $this->normalizer->normalize($input->major),
            honors: $this->normalizer->normalize($input->honors),
            awards: $awards,
            announcementAdditionalText: $this->normalizer->normalize($input->announcementAdditionalText),
            templateIdentifier: $input->templateIdentifier,
            templateVersion: $input->templateVersion,
            introductionText: $this->normalizer->normalize($input->introductionText),
            includeProgram: $input->includeProgram,
            includeMajor: $input->includeMajor,
            includeHonors: $input->includeHonors,
            includeAwards: $input->includeAwards,
            includeAdditionalText: $input->includeAdditionalText,
            awardsLeadIn: $this->normalizer->normalize($input->awardsLeadIn),
            closingText: $this->normalizer->normalize($input->closingText),
            voice: $this->normalizer->normalize($input->voice) ?? '',
            locale: $this->normalizer->normalize($input->locale) ?? '',
            speakingRatePercent: $input->speakingRatePercent,
            pauseAfterNameMs: $input->pauseAfterNameMs,
            pauseBetweenSectionsMs: $input->pauseBetweenSectionsMs,
            recommendedCharacterLimit: $input->recommendedCharacterLimit,
            maximumCharacterLimit: $input->maximumCharacterLimit,
            outputFormat: $this->normalizer->normalize($input->outputFormat) ?? '',
            provider: $input->provider,
        );
    }

    /**
     * @param  list<array{code: string, content: string}>  $sections
     */
    private function duplicatesExistingSection(string $candidate, array $sections): bool
    {
        foreach ($sections as $section) {
            if ($this->normalizer->isSameOrContainedPhrase($candidate, $section['content'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array{code: string, content: string}>  $sections
     * @return list<AnnouncementSection>
     */
    private function punctuateSections(array $sections): array
    {
        $lastIndex = array_key_last($sections);

        return array_map(function (array $section, int $index) use ($lastIndex): AnnouncementSection {
            $content = $section['content'];
            $terminal = mb_substr($content, -1, 1, 'UTF-8');

            if (in_array($terminal, ['!', '?', '！', '？'], true)) {
                $spokenText = preg_replace('/[!?！？\s]+$/u', '', $content).$terminal;
            } elseif ($index === $lastIndex) {
                $spokenText = $this->normalizer->withoutTrailingClausePunctuation($content).'.';
            } else {
                $spokenText = $this->normalizer->withoutTrailingClausePunctuation($content).',';
            }

            return new AnnouncementSection(
                code: $section['code'],
                content: $content,
                spokenText: $spokenText,
            );
        }, $sections, array_keys($sections));
    }

    /**
     * @param  list<AnnouncementIssue>  $warnings
     * @param  list<AnnouncementIssue>  $errors
     */
    private function assessLength(
        AnnouncementCompositionInput $input,
        int $characterCount,
        array &$warnings,
        array &$errors,
    ): AnnouncementLengthAssessment {
        $recommended = $input->recommendedCharacterLimit;
        $maximum = $input->maximumCharacterLimit;

        if ($recommended <= 0 || $maximum < $recommended) {
            $errors[] = new AnnouncementIssue(
                'invalid_character_limits',
                'Character limits must be positive and maximum must not be below recommended.',
                'maximum_character_limit',
            );
            $recommended = 450;
            $maximum = 700;
        }

        $status = 'normal';

        if ($characterCount > $maximum) {
            $status = 'blocking';
            $errors[] = new AnnouncementIssue(
                'announcement_above_maximum_limit',
                'The composed announcement exceeds the maximum character limit.',
                'announcement',
            );
        } elseif ($characterCount > $recommended) {
            $status = 'warning';
            $warnings[] = new AnnouncementIssue(
                'announcement_above_recommended_limit',
                'The composed announcement exceeds the recommended character limit.',
                'announcement',
            );
        }

        return new AnnouncementLengthAssessment(
            characterCount: $characterCount,
            recommendedLimit: $recommended,
            maximumLimit: $maximum,
            status: $status,
        );
    }

    /**
     * @param  list<AnnouncementSection>  $sections
     */
    private function estimateDuration(
        AnnouncementCompositionInput $input,
        array $sections,
        string $plainText,
    ): ?AnnouncementDurationEstimate {
        if ($plainText === '' || $input->speakingRatePercent < $this->approvedVoices->minimumSpeakingRatePercent()
            || $input->speakingRatePercent > $this->approvedVoices->maximumSpeakingRatePercent()
            || $input->pauseAfterNameMs < $this->approvedVoices->minimumPauseMilliseconds()
            || $input->pauseAfterNameMs > $this->approvedVoices->maximumPauseMilliseconds()
            || $input->pauseBetweenSectionsMs < $this->approvedVoices->minimumPauseMilliseconds()
            || $input->pauseBetweenSectionsMs > $this->approvedVoices->maximumPauseMilliseconds()) {
            return null;
        }

        preg_match_all('/[\p{L}\p{N}]+(?:[’\'-][\p{L}\p{N}]+)*/u', $plainText, $matches);
        $wordCount = count($matches[0]);
        $pauseMilliseconds = 0;
        $lastIndex = array_key_last($sections);

        foreach ($sections as $index => $section) {
            if ($index === $lastIndex) {
                continue;
            }

            $pauseMilliseconds += $section->code === 'name'
                ? $input->pauseAfterNameMs
                : $input->pauseBetweenSectionsMs;
        }

        $rateFactor = 100 + $input->speakingRatePercent;
        $minimumWpm = (int) config('announcements.duration_words_per_minute.minimum', 145);
        $maximumWpm = (int) config('announcements.duration_words_per_minute.maximum', 165);
        $minimumMilliseconds = $this->spokenMilliseconds($wordCount, $maximumWpm, $rateFactor)
            + $pauseMilliseconds;
        $maximumMilliseconds = $this->spokenMilliseconds($wordCount, $minimumWpm, $rateFactor)
            + $pauseMilliseconds;

        return new AnnouncementDurationEstimate(
            minimumSeconds: intdiv($minimumMilliseconds + 999, 1000),
            maximumSeconds: intdiv($maximumMilliseconds + 999, 1000),
            wordCount: $wordCount,
            includedPauseMilliseconds: $pauseMilliseconds,
        );
    }

    private function spokenMilliseconds(int $wordCount, int $wordsPerMinute, int $rateFactor): int
    {
        $numerator = $wordCount * 60_000 * 100;
        $denominator = $wordsPerMinute * $rateFactor;

        return intdiv($numerator + $denominator - 1, $denominator);
    }
}
