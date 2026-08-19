<?php

namespace App\Services;

use App\Contracts\CandidateAudioStore;
use App\Enums\AnnouncementGenerationStatus;
use App\Enums\AnnouncementProvider;
use App\Models\AnnouncementGeneration;
use App\Models\AnnouncementTemplate;
use App\Models\Student;
use App\Models\User;

final class StudentAnnouncementWorkspace
{
    public function __construct(
        private readonly PreviewGraduationAnnouncement $previewer,
        private readonly SerializeAnnouncementPreview $previewSerializer,
        private readonly ApprovedAnnouncementVoices $voices,
        private readonly TextToSpeechProviderHealth $providerHealth,
        private readonly AnnouncementUsage $usage,
        private readonly CandidateAudioStore $candidateStore,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forStudent(Student $student, User $actor): array
    {
        $student->loadMissing(['awards', 'ceremony']);
        $activeTemplate = $student->ceremony->activeAnnouncementTemplate;
        $preview = $this->previewer->preview($student, $activeTemplate);
        $canGenerate = $actor->can('generate-announcement-candidates');
        $canReview = $actor->can('review-announcement-candidates');
        $canViewUsage = $actor->can('view-announcement-usage');

        return [
            'permissions' => [
                'manage_awards' => $actor->can('manage-student-awards'),
                'generate' => $canGenerate,
                'force_generate' => $actor->can('manage-announcement-settings'),
                'review' => $canReview,
                'manage_common_pronunciation' => $actor->can('manage-common-pronunciation'),
                'manage_ceremony_pronunciation' => $actor->can('manage-ceremony-pronunciation'),
            ],
            'source' => [
                'announcement_name' => $student->announcement_name,
                'tts_spoken_name' => $student->tts_spoken_name,
                'pronunciation_guide' => $student->pronunciation_guide,
                'additional_text' => $student->announcement_additional_text,
                'program' => $student->program,
                'major' => $student->major,
                'honors' => $student->honors,
                'active_audio_source' => $student->audio_source_type?->value,
                'audio_readiness' => $student->audio_readiness_status->value,
            ],
            'awards' => $student->awards->values()->map(
                static fn ($award, int $index): array => [
                    'id' => $award->id,
                    'award_name' => $award->award_name,
                    'spoken_text' => $award->spoken_text,
                    'display_order' => $award->display_order,
                    'is_announcement_enabled' => $award->is_announcement_enabled,
                    'can_move_up' => $index > 0,
                    'can_move_down' => $index < $student->awards->count() - 1,
                ],
            ),
            'templates' => AnnouncementTemplate::query()
                ->whereBelongsTo($student->ceremony)
                ->orderByDesc('is_active')
                ->orderByDesc('version')
                ->get()
                ->map(static fn (AnnouncementTemplate $template): array => [
                    'id' => $template->id,
                    'name' => $template->name,
                    'version' => $template->version,
                    'is_active' => $template->is_active,
                    'provider' => $template->provider->value,
                    'default_voice' => $template->default_voice,
                    'default_locale' => $template->default_locale,
                ]),
            'selected' => [
                'template_id' => $activeTemplate?->id,
                'voice' => $activeTemplate?->default_voice,
                'provider' => $activeTemplate?->provider->value,
            ],
            'voices' => collect($this->voices->all($activeTemplate?->provider ?? AnnouncementProvider::Azure))->map(
                static fn (string $locale, string $voice): array => [
                    'value' => $voice,
                    'locale' => $locale,
                    'label' => $voice,
                ],
            )->values(),
            'preview' => $this->previewSerializer->serialize($preview),
            'provider' => $canGenerate && $activeTemplate !== null
                ? $this->providerHealth->offline($activeTemplate->provider)
                : null,
            'usage' => $canViewUsage ? $this->serializeUsage() : null,
            'generations' => $this->history($student),
            'review_queue' => $canReview ? [
                'remaining' => AnnouncementGeneration::query()
                    ->where('ceremony_id', $student->ceremony_id)
                    ->where('status', AnnouncementGenerationStatus::NeedsReview->value)
                    ->count(),
                'url' => route('admin.voice-generation').'#review-queue',
            ] : null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function history(Student $student): array
    {
        return AnnouncementGeneration::query()
            ->whereBelongsTo($student)
            ->with(['template:id,name,version', 'generatingUser:id,name', 'reviewingUser:id,name'])
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(function (AnnouncementGeneration $generation) use ($student): array {
                $hasCandidate = $generation->candidate_audio_path !== null
                    && $this->candidateStore->exists($generation->candidate_audio_path);

                return [
                    'id' => $generation->id,
                    'status' => $generation->status->value,
                    'status_label' => str($generation->status->value)->replace('_', ' ')->title()->toString(),
                    'plain_text' => $generation->plain_text,
                    'display_text' => $generation->display_text ?? $generation->plain_text,
                    'pronunciation_manifest' => $generation->pronunciation_manifest ?? [],
                    'character_count' => $generation->character_count,
                    'voice' => $generation->provider_voice,
                    'provider' => $generation->provider->value,
                    'locale' => $generation->locale,
                    'template' => $generation->template === null ? null : [
                        'name' => $generation->template->name,
                        'version' => $generation->template->version,
                    ],
                    'attempt_count' => $generation->attempt_count,
                    'generated_by' => $generation->generatingUser?->name,
                    'generated_at' => $generation->generated_at?->toIso8601String(),
                    'created_at' => $generation->created_at?->toIso8601String(),
                    'failure_code' => $generation->failure_code,
                    'failure_message' => $generation->failure_message,
                    'reviewed_by' => $generation->reviewingUser?->name,
                    'reviewed_at' => $generation->reviewed_at?->toIso8601String(),
                    'review_notes' => $generation->review_notes,
                    'can_review' => $generation->status === AnnouncementGenerationStatus::NeedsReview,
                    'is_active_audio' => (int) $student->audio_generation_id === (int) $generation->id,
                    'candidate_preview_url' => $hasCandidate
                        ? route('admin.students.announcement-candidates.show', [$student, $generation])
                        : null,
                ];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeUsage(): array
    {
        $usage = $this->usage->forMonth();

        return [
            'successful_characters' => $usage->successfulCharacters,
            'monthly_budget' => $usage->monthlyBudget,
            'estimated_remaining' => $usage->estimatedRemaining,
            'warning' => $usage->warning,
            'label' => $usage->label,
        ];
    }
}
