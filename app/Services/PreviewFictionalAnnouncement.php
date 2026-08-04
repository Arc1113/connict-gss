<?php

namespace App\Services;

use App\Data\AnnouncementAwardInput;
use App\Data\AnnouncementCompositionInput;
use App\Data\AnnouncementIssue;
use App\Data\AnnouncementPreviewResult;
use App\Models\AnnouncementTemplate;

final class PreviewFictionalAnnouncement
{
    public function __construct(
        private readonly ComposeGraduationAnnouncement $composer,
        private readonly BuildGraduationAnnouncementSsml $ssmlBuilder,
    ) {}

    public function preview(AnnouncementTemplate $template, ?string $voice = null): AnnouncementPreviewResult
    {
        $selectedVoice = $voice ?? $template->default_voice;
        $selectedLocale = (string) config("announcements.voices.{$selectedVoice}", '');

        if ($selectedLocale === '') {
            return new AnnouncementPreviewResult(
                input: null,
                composition: null,
                ssml: null,
                warnings: [],
                blockingErrors: [new AnnouncementIssue(
                    'unsupported_voice',
                    'Select a server-approved announcement voice.',
                    'voice',
                )],
            );
        }

        $input = new AnnouncementCompositionInput(
            studentIdentifier: 'fictional-settings-preview',
            announcementName: 'Maria Santos',
            ttsSpokenName: null,
            program: 'Bachelor of Science in Information Technology',
            major: 'Web and Mobile Applications',
            honors: 'magna cum laude',
            awards: [new AnnouncementAwardInput(
                identifier: 'fictional-leadership-award',
                awardName: 'Leadership Excellence Award',
                spokenText: 'the Leadership Excellence Award',
                displayOrder: 0,
                isAnnouncementEnabled: true,
            )],
            announcementAdditionalText: 'recognized for community service',
            templateIdentifier: $template->getKey() ?? 'unpublished-settings-preview',
            templateVersion: max(1, (int) $template->version),
            introductionText: $template->introduction_text,
            includeProgram: $template->include_program,
            includeMajor: $template->include_major,
            includeHonors: $template->include_honors,
            includeAwards: $template->include_awards,
            includeAdditionalText: $template->include_additional_text,
            awardsLeadIn: $template->awards_lead_in,
            closingText: $template->closing_text,
            voice: $selectedVoice,
            locale: $selectedLocale,
            speakingRatePercent: $template->speaking_rate_percent,
            pauseAfterNameMs: $template->pause_after_name_ms,
            pauseBetweenSectionsMs: $template->pause_between_sections_ms,
            recommendedCharacterLimit: $template->recommended_character_limit,
            maximumCharacterLimit: $template->maximum_character_limit,
            outputFormat: (string) config(
                'announcements.default_output_format',
                'audio-24khz-48kbitrate-mono-mp3',
            ),
        );
        $composition = $this->composer->compose($input);

        return new AnnouncementPreviewResult(
            input: $composition->input,
            composition: $composition,
            ssml: $this->ssmlBuilder->build($composition),
            warnings: $composition->warnings,
            blockingErrors: $composition->blockingErrors,
        );
    }
}
