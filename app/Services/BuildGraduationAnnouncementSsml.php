<?php

namespace App\Services;

use App\Data\AnnouncementIssue;
use App\Data\AnnouncementSsmlResult;
use App\Data\ComposedAnnouncement;
use XMLWriter;

final class BuildGraduationAnnouncementSsml
{
    public function __construct(
        private readonly ApprovedAnnouncementVoices $approvedVoices,
    ) {}

    public function build(ComposedAnnouncement $composition): AnnouncementSsmlResult
    {
        $input = $composition->input;
        $speakingRate = ($input->speakingRatePercent > 0 ? '+' : '')
            .$input->speakingRatePercent.'%';
        $blockingErrors = $composition->blockingErrors === []
            ? $this->settingIssues($composition)
            : $composition->blockingErrors;

        if ($blockingErrors !== []) {
            return new AnnouncementSsmlResult(
                ssml: null,
                plainText: $composition->plainText,
                voice: $input->voice,
                locale: $input->locale,
                speakingRate: $speakingRate,
                breaksMilliseconds: [],
                warnings: $composition->warnings,
                blockingErrors: $blockingErrors,
            );
        }

        $writer = new XMLWriter;
        $writer->openMemory();
        $writer->startElementNS(null, 'speak', 'http://www.w3.org/2001/10/synthesis');
        $writer->writeAttribute('version', '1.0');
        $writer->writeAttributeNS(
            'xml',
            'lang',
            'http://www.w3.org/XML/1998/namespace',
            $input->locale,
        );
        $writer->startElement('voice');
        $writer->writeAttribute('name', $input->voice);
        $writer->startElement('prosody');
        $writer->writeAttribute('rate', $speakingRate);

        $breaks = [];
        $lastIndex = array_key_last($composition->sections);

        foreach ($composition->sections as $index => $section) {
            $writer->text($section->spokenText);

            if ($index === $lastIndex) {
                continue;
            }

            $pause = $section->code === 'name'
                ? $input->pauseAfterNameMs
                : $input->pauseBetweenSectionsMs;

            if ($pause > 0) {
                $writer->startElement('break');
                $writer->writeAttribute('time', $pause.'ms');
                $writer->endElement();
                $breaks[] = $pause;
            }

            $writer->text(' ');
        }

        $writer->endElement();
        $writer->endElement();
        $writer->endElement();

        return new AnnouncementSsmlResult(
            ssml: $writer->outputMemory(),
            plainText: $composition->plainText,
            voice: $input->voice,
            locale: $input->locale,
            speakingRate: $speakingRate,
            breaksMilliseconds: $breaks,
            warnings: $composition->warnings,
            blockingErrors: [],
        );
    }

    /**
     * @return list<AnnouncementIssue>
     */
    private function settingIssues(ComposedAnnouncement $composition): array
    {
        $input = $composition->input;
        $issues = $this->approvedVoices->validationIssues($input->voice, $input->locale);

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
}
