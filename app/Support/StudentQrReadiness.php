<?php

namespace App\Support;

use App\Enums\EligibilityStatus;
use App\Enums\StageQueueStatus;
use App\Models\Student;

final class StudentQrReadiness
{
    /**
     * @return array<string, mixed>
     */
    public function summarize(
        Student $student,
        ?string $regeneratedAt = null,
    ): array {
        $tokenAvailable = is_string($student->qr_token)
            && $student->qr_token !== '';
        $isPresenting = (bool) ($student->getAttribute('is_presenting') ?? false)
            || $student->latestQueueItem?->status === StageQueueStatus::Presenting;
        $completedCount = (int) ($student->getAttribute(
            'completed_queue_items_count',
        ) ?? 0);
        $isEligible = $student->eligibility_status === EligibilityStatus::Eligible;
        $printReasons = [];

        if (! $tokenAvailable) {
            $printReasons[] = 'A secure token is not available.';
        }

        if (! $isEligible) {
            $printReasons[] = 'Only eligible graduates can receive print output.';
        }

        if ($completedCount > 0) {
            $printReasons[] = 'The graduate already has completed presentation history.';
        }

        $regenerationReasons = [];

        if (! $tokenAvailable) {
            $regenerationReasons[] = 'No current token is available to replace.';
        }

        if ($isPresenting) {
            $regenerationReasons[] = 'Token replacement is blocked while the graduate is presenting.';
        }

        if ($completedCount > 0) {
            $regenerationReasons[] = 'Token replacement is blocked after a completed presentation.';
        }

        return [
            'status' => ! $tokenAvailable
                ? 'missing'
                : ($regeneratedAt === null ? 'ready' : 'regenerated'),
            'label' => ! $tokenAvailable
                ? 'Missing token'
                : ($regeneratedAt === null ? 'Ready' : 'Regenerated — reprint'),
            'regenerated_at' => $regeneratedAt,
            'printable' => $printReasons === [],
            'print_blockers' => $printReasons,
            'regeneratable' => $regenerationReasons === [],
            'regeneration_blockers' => $regenerationReasons,
        ];
    }
}
