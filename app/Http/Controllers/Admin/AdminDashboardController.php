<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AudioReadinessStatus;
use App\Enums\CeremonyStatus;
use App\Http\Controllers\Controller;
use App\Models\BackupRecord;
use App\Models\Ceremony;
use App\Models\StageQueueItem;
use App\Models\Student;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless(
            $request->user()->can('viewAny', Ceremony::class)
                || $request->user()->can('viewAny', Student::class),
            403,
        );

        $activeCeremonies = Ceremony::query()
            ->where('status', CeremonyStatus::Active)
            ->orderBy('id')
            ->get(['id', 'name', 'academic_year', 'event_date', 'status']);

        $referenceCeremony = $activeCeremonies->count() === 1
            ? $activeCeremonies->first()
            : Ceremony::query()
                ->orderByRaw('event_date DESC NULLS LAST')
                ->orderByDesc('id')
                ->first(['id', 'name', 'academic_year', 'event_date', 'status']);

        $readiness = null;

        if ($referenceCeremony !== null) {
            $students = Student::query()->where('ceremony_id', $referenceCeremony->id);
            $queue = StageQueueItem::query()->where('ceremony_id', $referenceCeremony->id);

            $readiness = [
                'total' => (clone $students)->count(),
                'eligible' => (clone $students)->where('eligibility_status', 'eligible')->count(),
                'waiting' => (clone $queue)->where('status', 'waiting')->count(),
                'presenting' => (clone $queue)->where('status', 'presenting')->count(),
                'completed' => (clone $queue)->where('status', 'completed')->distinct('student_id')->count('student_id'),
                'needs_review' => (clone $students)->where('eligibility_status', 'pending')->count(),
                'missing_photo' => (clone $students)->whereNull('photo_path')->count(),
                'audio_missing' => (clone $students)
                    ->where('audio_readiness_status', AudioReadinessStatus::Missing)
                    ->count(),
                'audio_uploaded' => (clone $students)
                    ->where('audio_readiness_status', AudioReadinessStatus::Uploaded)
                    ->count(),
                'audio_needs_review' => (clone $students)
                    ->where('audio_readiness_status', AudioReadinessStatus::NeedsReview)
                    ->count(),
                'audio_approved' => (clone $students)
                    ->where('audio_readiness_status', AudioReadinessStatus::Approved)
                    ->count(),
                'audio_rejected' => (clone $students)
                    ->where('audio_readiness_status', AudioReadinessStatus::Rejected)
                    ->count(),
            ];
        }

        $lastBackup = BackupRecord::query()
            ->latest('started_at')
            ->latest('id')
            ->first();

        return Inertia::render('Admin/Index', [
            'activeCeremonyState' => match ($activeCeremonies->count()) {
                0 => 'none',
                1 => 'ready',
                default => 'conflict',
            },
            'referenceCeremony' => $referenceCeremony === null ? null : [
                'id' => $referenceCeremony->id,
                'name' => $referenceCeremony->name,
                'academic_year' => $referenceCeremony->academic_year,
                'event_date' => $referenceCeremony->event_date?->format('Y-m-d'),
                'status' => $referenceCeremony->status->value,
            ],
            'readiness' => $readiness,
            'lastBackup' => $lastBackup === null ? null : [
                'identifier' => $lastBackup->identifier,
                'status' => $lastBackup->status,
                'verification_status' => $lastBackup->verification_status,
                'completed_at' => $lastBackup->completed_at?->toIso8601String(),
                'failure_summary' => $lastBackup->failure_summary,
            ],
        ]);
    }
}
