<?php

namespace App\Services;

use App\Data\AnnouncementAwardInput;
use App\Data\AnnouncementCompositionInput;
use App\Data\AnnouncementIssue;
use App\Data\AnnouncementPreviewResult;
use App\Models\AnnouncementTemplate;
use App\Models\Student;

final class PreviewGraduationAnnouncement
{
    public function __construct(
        private readonly ResolveActiveAnnouncementTemplate $activeTemplateResolver,
        private readonly ComposeGraduationAnnouncement $composer,
        private readonly BuildGraduationAnnouncementSsml $ssmlBuilder,
    ) {}

    public function preview(
        Student $student,
        ?AnnouncementTemplate $template = null,
    ): AnnouncementPreviewResult {
        $template ??= $this->activeTemplateResolver->resolve($student->ceremony);

        if ($template === null) {
            return $this->blocked('missing_active_template', 'The ceremony has no active announcement template.');
        }

        if ((int) $template->ceremony_id !== (int) $student->ceremony_id) {
            return $this->blocked(
                'template_ceremony_mismatch',
                'The announcement template belongs to a different ceremony.',
            );
        }

        $awards = $student->awards()->get()->map(
            static fn ($award): AnnouncementAwardInput => new AnnouncementAwardInput(
                identifier: $award->getKey(),
                awardName: $award->award_name,
                spokenText: $award->spoken_text,
                displayOrder: $award->display_order,
                isAnnouncementEnabled: $award->is_announcement_enabled,
            ),
        )->all();

        $input = new AnnouncementCompositionInput(
            studentIdentifier: $student->getKey(),
            announcementName: $student->announcement_name,
            ttsSpokenName: $student->tts_spoken_name,
            program: $student->program,
            major: $student->major,
            honors: $student->honors,
            awards: $awards,
            announcementAdditionalText: $student->announcement_additional_text,
            templateIdentifier: $template->getKey(),
            templateVersion: $template->version,
            introductionText: $template->introduction_text,
            includeProgram: $template->include_program,
            includeMajor: $template->include_major,
            includeHonors: $template->include_honors,
            includeAwards: $template->include_awards,
            includeAdditionalText: $template->include_additional_text,
            awardsLeadIn: $template->awards_lead_in,
            closingText: $template->closing_text,
            voice: $template->default_voice,
            locale: $template->default_locale,
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
        $ssml = $this->ssmlBuilder->build($composition);

        return new AnnouncementPreviewResult(
            input: $composition->input,
            composition: $composition,
            ssml: $ssml,
            warnings: $composition->warnings,
            blockingErrors: $composition->blockingErrors,
        );
    }

    private function blocked(string $code, string $message): AnnouncementPreviewResult
    {
        return new AnnouncementPreviewResult(
            input: null,
            composition: null,
            ssml: null,
            warnings: [],
            blockingErrors: [new AnnouncementIssue($code, $message, 'template')],
        );
    }
}
