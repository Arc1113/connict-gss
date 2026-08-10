<?php

namespace App\Services;

use App\Enums\AnnouncementGenerationStatus;
use App\Enums\EligibilityStatus;
use App\Models\AnnouncementGenerationBatch;
use App\Models\AnnouncementTemplate;
use App\Models\Ceremony;
use App\Models\Student;

final class AnnouncementBatchWorkspace
{
    public function __construct(
        private readonly PreviewGraduationAnnouncement $previewer,
        private readonly AnnouncementUsage $usage,
        private readonly TextToSpeechProviderHealth $providerHealth,
    ) {}

    /** @return array<string, mixed> */
    public function forCeremony(Ceremony $ceremony): array
    {
        $template = AnnouncementTemplate::query()
            ->whereBelongsTo($ceremony)
            ->where('is_active', true)
            ->first();
        $studentPool = Student::query()
            ->whereBelongsTo($ceremony)
            ->where('eligibility_status', '!=', EligibilityStatus::Ineligible->value)
            ->with('announcementAwards')
            ->orderBy('sequence_number')
            ->orderBy('id')
            ->limit(501)
            ->get();
        $poolTruncated = $studentPool->count() > 500;
        $students = $studentPool->take(500);
        $usage = $this->usage->forMonth();

        return [
            'ceremony' => ['id' => $ceremony->id, 'name' => $ceremony->name],
            'template' => $template === null ? null : [
                'id' => $template->id,
                'name' => $template->name,
                'version' => $template->version,
                'provider' => $template->provider->value,
                'voice' => $template->default_voice,
                'locale' => $template->default_locale,
            ],
            'provider' => $template === null ? null : $this->providerHealth->offline($template->provider),
            'limits' => [
                'maximum_students' => max(1, (int) config(
                    'announcements.batch_generation.maximum_students',
                    50,
                )),
                'candidate_pool_truncated' => $poolTruncated,
            ],
            'usage' => [
                'successful_characters' => $usage->successfulCharacters,
                'monthly_budget' => $usage->monthlyBudget,
                'warning_threshold' => $usage->warningThreshold,
                'estimated_remaining' => $usage->estimatedRemaining,
                'warning' => $usage->warning,
                'label' => $usage->label,
            ],
            'students' => $students->map(function (Student $student) use ($template): array {
                $preview = $template === null
                    ? null
                    : $this->previewer->preview($student, $template, $template->default_voice);

                return [
                    'id' => $student->id,
                    'sequence_number' => $student->sequence_number,
                    'display_name' => $student->display_name,
                    'program' => $student->program,
                    'eligibility_status' => $student->eligibility_status->value,
                    'ready' => $preview?->isReady() ?? false,
                    'character_count' => $preview?->composition?->characterCount ?? 0,
                ];
            }),
            'batches' => AnnouncementGenerationBatch::query()
                ->whereBelongsTo($ceremony)
                ->with([
                    'template:id,name,version',
                    'requester:id,name',
                    'items.student:id,display_name,sequence_number',
                    'items.generation:id,status',
                ])
                ->latest('id')
                ->limit(12)
                ->get()
                ->map(fn (AnnouncementGenerationBatch $batch): array => $this->serializeBatch($batch)),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeBatch(AnnouncementGenerationBatch $batch): array
    {
        $awaitingReviewCount = $batch->items->filter(
            fn ($item): bool => $item->generation?->status === AnnouncementGenerationStatus::NeedsReview,
        )->count();
        $approvedCount = $batch->items->filter(
            fn ($item): bool => $item->generation?->status === AnnouncementGenerationStatus::Approved,
        )->count();

        return [
            'id' => $batch->id,
            'status' => $batch->status->value,
            'selected_count' => $batch->selected_count,
            'processed_count' => $batch->processed_count,
            'succeeded_count' => $batch->succeeded_count,
            'reused_count' => $batch->reused_count,
            'failed_count' => $batch->failed_count,
            'awaiting_review_count' => $awaitingReviewCount,
            'approved_count' => $approvedCount,
            'estimated_character_count' => $batch->estimated_character_count,
            'voice' => $batch->provider_voice,
            'provider' => $batch->provider->value,
            'locale' => $batch->locale,
            'template' => [
                'name' => $batch->template->name,
                'version' => $batch->template->version,
            ],
            'requested_by' => $batch->requester?->name,
            'cancellation_requested_at' => $batch->cancellation_requested_at?->toIso8601String(),
            'started_at' => $batch->started_at?->toIso8601String(),
            'completed_at' => $batch->completed_at?->toIso8601String(),
            'created_at' => $batch->created_at?->toIso8601String(),
            'failure' => $batch->last_failure_code === null ? null : [
                'code' => $batch->last_failure_code,
                'message' => $batch->last_failure_message,
            ],
            'items' => $batch->items->map(fn ($item): array => [
                'id' => $item->id,
                'position' => $item->position,
                'status' => $item->status->value,
                'character_count' => $item->character_count,
                'student' => [
                    'id' => $item->student->id,
                    'display_name' => $item->student->display_name,
                    'sequence_number' => $item->student->sequence_number,
                ],
                'failure_message' => $item->failure_message,
                'generation' => $item->generation === null ? null : [
                    'id' => $item->generation->id,
                    'status' => $item->generation->status->value,
                    'status_label' => str($item->generation->status->value)
                        ->replace('_', ' ')
                        ->title()
                        ->toString(),
                ],
            ]),
        ];
    }
}
