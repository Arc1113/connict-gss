<?php

namespace App\Services;

use App\Data\AnnouncementIssue;
use App\Data\AnnouncementPreviewResult;

final class SerializeAnnouncementPreview
{
    /**
     * @return array<string, mixed>
     */
    public function serialize(AnnouncementPreviewResult $preview): array
    {
        $composition = $preview->composition;
        $duration = $composition?->durationEstimate;
        $length = $composition?->lengthAssessment;

        return [
            'ready' => $preview->isReady(),
            'plain_text' => $composition?->plainText,
            'character_count' => $composition?->characterCount ?? 0,
            'award_count' => $composition?->awardCount ?? 0,
            'voice' => $composition?->voice,
            'locale' => $composition?->locale,
            'sections' => $composition === null ? [] : array_map(
                static fn ($section): array => [
                    'type' => $section->code,
                    'text' => $section->content,
                    'spoken_text' => $section->spokenText,
                ],
                $composition->sections,
            ),
            'length' => $length === null ? null : [
                'status' => $length->status,
                'recommended_limit' => $length->recommendedLimit,
                'maximum_limit' => $length->maximumLimit,
            ],
            'duration' => $duration === null ? null : [
                'minimum_seconds' => $duration->minimumSeconds,
                'maximum_seconds' => $duration->maximumSeconds,
                'word_count' => $duration->wordCount,
                'label' => $duration->label,
            ],
            'warnings' => array_map($this->issue(...), $preview->warnings),
            'blocking_errors' => array_map($this->issue(...), $preview->blockingErrors),
        ];
    }

    /**
     * @return array{code: string, message: string, field: ?string}
     */
    private function issue(AnnouncementIssue $issue): array
    {
        return [
            'code' => $issue->code,
            'message' => $issue->message,
            'field' => $issue->field,
        ];
    }
}
