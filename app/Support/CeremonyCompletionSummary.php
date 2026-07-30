<?php

namespace App\Support;

use App\Enums\AudioReadinessStatus;
use App\Enums\EligibilityStatus;
use App\Enums\ScanResult;
use App\Enums\StageQueueStatus;
use App\Models\Ceremony;
use App\Models\ScanLog;
use App\Models\StageEvent;
use App\Models\StageQueueItem;
use App\Models\Student;
use Illuminate\Support\Carbon;

final class CeremonyCompletionSummary
{
    /**
     * @return array<string, mixed>
     */
    public function forCeremony(Ceremony $ceremony): array
    {
        $students = Student::query()->whereBelongsTo($ceremony);
        $queue = StageQueueItem::query()->whereBelongsTo($ceremony);
        $scans = ScanLog::query()->whereBelongsTo($ceremony);

        $completedStudentIds = (clone $queue)
            ->where('status', StageQueueStatus::Completed)
            ->distinct()
            ->pluck('student_id');
        $activeOrAcceptedStudentIds = (clone $queue)
            ->whereIn('status', [
                StageQueueStatus::Waiting,
                StageQueueStatus::Ready,
                StageQueueStatus::Presenting,
                StageQueueStatus::Announced,
                StageQueueStatus::Completed,
            ])
            ->distinct()
            ->pluck('student_id');

        $firstPresentation = StageEvent::query()
            ->whereBelongsTo($ceremony)
            ->whereIn('event_type', [
                'student_presented',
                'student_presented_automatically',
                'present_next',
                'present_selected',
            ])
            ->oldest('created_at')
            ->value('created_at');
        $lastPresentation = StageEvent::query()
            ->whereBelongsTo($ceremony)
            ->whereIn('event_type', [
                'student_presented',
                'student_presented_automatically',
                'present_next',
                'present_selected',
                'student_completed',
            ])
            ->latest('created_at')
            ->value('created_at');

        return [
            'ceremony' => [
                'id' => $ceremony->id,
                'name' => $ceremony->name,
                'academic_year' => $ceremony->academic_year,
                'status' => $ceremony->status->value,
                'activated_at' => $ceremony->activated_at?->toIso8601String(),
                'completed_at' => $ceremony->completed_at?->toIso8601String(),
            ],
            'counts' => [
                'total_graduates' => (clone $students)->count(),
                'eligible_graduates' => (clone $students)
                    ->where('eligibility_status', EligibilityStatus::Eligible)
                    ->count(),
                'waiting' => (clone $queue)->where('status', StageQueueStatus::Waiting)->count(),
                'ready' => (clone $queue)->where('status', StageQueueStatus::Ready)->count(),
                'presenting' => (clone $queue)->where('status', StageQueueStatus::Presenting)->count(),
                'completed' => $completedStudentIds->count(),
                'skipped' => (clone $queue)->where('status', StageQueueStatus::Skipped)->count(),
                'cancelled' => (clone $queue)->where('status', StageQueueStatus::Cancelled)->count(),
                'never_scanned' => (clone $students)
                    ->where('eligibility_status', EligibilityStatus::Eligible)
                    ->whereNotIn('id', $activeOrAcceptedStudentIds)
                    ->count(),
                'invalid_scan_attempts' => (clone $scans)->where('result', ScanResult::Invalid)->count(),
                'duplicate_scan_attempts' => (clone $scans)->where('result', ScanResult::Duplicate)->count(),
                'missing_audio' => (clone $students)
                    ->where('audio_readiness_status', AudioReadinessStatus::Missing)
                    ->count(),
                'approved_audio' => (clone $students)
                    ->where('audio_readiness_status', AudioReadinessStatus::Approved)
                    ->count(),
                'rejected_or_unapproved_audio' => (clone $students)
                    ->where('audio_readiness_status', '!=', AudioReadinessStatus::Approved)
                    ->count(),
                'missing_photo' => (clone $students)->whereNull('photo_path')->count(),
                'qr_ready' => (clone $students)->whereNotNull('qr_token')->where('qr_token', '!=', '')->count(),
            ],
            'timing' => [
                'ceremony_start' => $ceremony->activated_at?->toIso8601String(),
                'first_presentation' => $this->iso($firstPresentation),
                'last_presentation' => $this->iso($lastPresentation),
                'completion_timestamp' => $ceremony->completed_at?->toIso8601String(),
                'total_presented_count' => $completedStudentIds->count(),
            ],
            'definitions' => $this->definitions(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function definitions(): array
    {
        return [
            'completed' => 'Distinct graduates with a completed queue item for the selected ceremony.',
            'never_scanned' => 'Eligible graduates with no active or completed queue history in the selected ceremony.',
            'duplicate_scan_attempts' => 'Scan logs explicitly classified with the duplicate result.',
            'rejected_or_unapproved_audio' => 'Graduates whose current audio readiness is not approved.',
        ];
    }

    private function iso(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return Carbon::parse($value)->toIso8601String();
    }
}
