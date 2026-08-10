<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AnnouncementGenerationStatus;
use App\Enums\AudioReadinessStatus;
use App\Enums\CeremonyStatus;
use App\Http\Controllers\Controller;
use App\Models\AnnouncementGeneration;
use App\Models\Ceremony;
use App\Models\Student;
use App\Services\TextToSpeechProviderHealth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class VoiceGenerationController extends Controller
{
    public function __invoke(Request $request, TextToSpeechProviderHealth $health): Response
    {
        Gate::authorize('generate-announcement-candidates');

        $activeCeremonies = Ceremony::query()
            ->where('status', CeremonyStatus::Active)
            ->with('activeAnnouncementTemplate')
            ->limit(2)
            ->get();
        $ceremony = $activeCeremonies->count() === 1 ? $activeCeremonies->first() : null;

        $studentQuery = Student::query()->when(
            $ceremony,
            fn ($query) => $query->whereBelongsTo($ceremony),
            fn ($query) => $query->whereRaw('1 = 0'),
        );
        $generationQuery = AnnouncementGeneration::query()->when(
            $ceremony,
            fn ($query) => $query->whereBelongsTo($ceremony),
            fn ($query) => $query->whereRaw('1 = 0'),
        );
        $reviewQueue = $ceremony === null
            ? collect()
            : AnnouncementGeneration::query()
                ->where('announcement_generations.ceremony_id', $ceremony->id)
                ->where('announcement_generations.status', AnnouncementGenerationStatus::NeedsReview->value)
                ->join('students', 'students.id', '=', 'announcement_generations.student_id')
                ->with(['student:id,display_name,sequence_number', 'template:id,name,version'])
                ->orderBy('students.sequence_number')
                ->orderBy('students.id')
                ->orderBy('announcement_generations.id')
                ->select('announcement_generations.*')
                ->limit(20)
                ->get();

        return Inertia::render('Admin/VoiceGeneration/Index', [
            'workspace' => [
                'ceremony' => $ceremony === null ? null : [
                    'id' => $ceremony->id,
                    'name' => $ceremony->name,
                    'template' => $ceremony->activeAnnouncementTemplate === null ? null : [
                        'version' => $ceremony->activeAnnouncementTemplate->version,
                        'provider' => $ceremony->activeAnnouncementTemplate->provider->value,
                        'voice' => $ceremony->activeAnnouncementTemplate->default_voice,
                    ],
                ],
                'providers' => $health->providers(),
                'counts' => [
                    'missing' => (clone $studentQuery)->where('audio_readiness_status', AudioReadinessStatus::Missing)->count(),
                    'needs_review' => (clone $generationQuery)->where('status', AnnouncementGenerationStatus::NeedsReview)->count(),
                    'approved' => (clone $studentQuery)->where('audio_readiness_status', AudioReadinessStatus::Approved)->count(),
                ],
                'review_queue' => $reviewQueue->map(static fn (AnnouncementGeneration $generation): array => [
                    'id' => $generation->id,
                    'student' => [
                        'id' => $generation->student->id,
                        'name' => $generation->student->display_name,
                        'sequence_number' => $generation->student->sequence_number,
                    ],
                    'provider' => $generation->provider->value,
                    'voice' => $generation->provider_voice,
                    'template' => $generation->template === null ? null : [
                        'name' => $generation->template->name,
                        'version' => $generation->template->version,
                    ],
                    'generated_at' => $generation->generated_at?->toIso8601String(),
                    'review_url' => route('admin.students.show', $generation->student_id)
                        .'#announcement-candidate-'.$generation->id,
                ])->values(),
                'permissions' => [
                    'settings' => $request->user()->can('manage-announcement-settings'),
                    'batches' => $request->user()->can('manage-announcement-batches'),
                    'review' => $request->user()->can('review-announcement-candidates'),
                ],
            ],
        ]);
    }
}
