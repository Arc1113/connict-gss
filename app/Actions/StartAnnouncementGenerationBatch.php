<?php

namespace App\Actions;

use App\Contracts\TextToSpeechProvider;
use App\Data\AnnouncementBatchStartResult;
use App\Enums\AnnouncementBatchItemStatus;
use App\Enums\AnnouncementBatchStatus;
use App\Enums\EligibilityStatus;
use App\Exceptions\AnnouncementBatchException;
use App\Jobs\ProcessAnnouncementGenerationBatch;
use App\Models\AnnouncementGenerationBatch;
use App\Models\AnnouncementTemplate;
use App\Models\Ceremony;
use App\Models\Student;
use App\Models\User;
use App\Services\AnnouncementUsage;
use App\Services\PreviewGraduationAnnouncement;
use App\Support\AdministrativeAuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class StartAnnouncementGenerationBatch
{
    public function __construct(
        private readonly PreviewGraduationAnnouncement $previewer,
        private readonly AnnouncementUsage $usage,
        private readonly AdministrativeAuditLogger $auditLogger,
        private readonly TextToSpeechProvider $provider,
    ) {}

    /** @param list<int> $studentIds */
    public function start(
        Ceremony $ceremony,
        array $studentIds,
        bool $budgetAcknowledged,
        User $actor,
        ?string $ipAddress,
    ): AnnouncementBatchStartResult {
        Gate::forUser($actor)->authorize('manage-announcement-batches');

        if (! $this->provider->health()->isConfigured()) {
            throw new AnnouncementBatchException(
                'azure_not_configured',
                'Azure Speech is disabled or not configured safely for batch preparation.',
            );
        }

        $maximum = max(1, (int) config('announcements.batch_generation.maximum_students', 50));
        $studentIds = array_values(array_unique(array_map('intval', $studentIds)));

        if ($studentIds === [] || count($studentIds) > $maximum) {
            throw new AnnouncementBatchException(
                'invalid_selection',
                "Select between 1 and {$maximum} distinct graduates.",
            );
        }

        $template = AnnouncementTemplate::query()
            ->whereBelongsTo($ceremony)
            ->where('is_active', true)
            ->first();

        if ($template === null) {
            throw new AnnouncementBatchException(
                'missing_active_template',
                'Publish ceremony announcement settings before starting a batch.',
            );
        }

        $students = Student::query()
            ->whereBelongsTo($ceremony)
            ->whereIn('id', $studentIds)
            ->where('eligibility_status', '!=', EligibilityStatus::Ineligible->value)
            ->with('announcementAwards')
            ->orderBy('sequence_number')
            ->orderBy('id')
            ->get();

        if ($students->count() !== count($studentIds)) {
            throw new AnnouncementBatchException(
                'invalid_selection',
                'Every selected graduate must belong to this ceremony and not be ineligible.',
            );
        }

        $prepared = $this->prepare($students, $template);
        $estimatedCharacters = array_sum(array_column($prepared, 'character_count'));
        $usage = $this->usage->forMonth();
        $projected = $usage->successfulCharacters + $estimatedCharacters;

        if ($usage->monthlyBudget > 0
            && $projected >= $usage->warningThreshold
            && ! $budgetAcknowledged) {
            throw new AnnouncementBatchException(
                'budget_acknowledgement_required',
                'Acknowledge the local budget warning before starting this batch.',
            );
        }

        $orderedIds = array_column($prepared, 'student_id');
        $selectionHash = hash('sha256', json_encode([
            'ceremony_id' => $ceremony->id,
            'template_id' => $template->id,
            'voice' => $template->default_voice,
            'student_ids' => $orderedIds,
        ], JSON_THROW_ON_ERROR));

        $result = DB::transaction(function () use (
            $ceremony,
            $template,
            $actor,
            $ipAddress,
            $prepared,
            $estimatedCharacters,
            $selectionHash,
        ): AnnouncementBatchStartResult {
            $lockedCeremony = Ceremony::query()->lockForUpdate()->findOrFail($ceremony->id);
            $activeTemplateId = AnnouncementTemplate::query()
                ->whereBelongsTo($lockedCeremony)
                ->where('is_active', true)
                ->value('id');

            if ((int) $activeTemplateId !== (int) $template->id) {
                throw new AnnouncementBatchException(
                    'settings_changed',
                    'Ceremony settings changed while the batch was prepared. Review and start again.',
                );
            }

            $existing = AnnouncementGenerationBatch::query()
                ->whereBelongsTo($lockedCeremony)
                ->where('selection_hash', $selectionHash)
                ->whereIn('status', [
                    AnnouncementBatchStatus::Queued->value,
                    AnnouncementBatchStatus::Running->value,
                    AnnouncementBatchStatus::Paused->value,
                ])
                ->first();

            if ($existing !== null) {
                return new AnnouncementBatchStartResult($existing, true);
            }

            $batch = AnnouncementGenerationBatch::query()->create([
                'ceremony_id' => $lockedCeremony->id,
                'announcement_template_id' => $template->id,
                'provider_voice' => $template->default_voice,
                'locale' => $template->default_locale,
                'selection_hash' => $selectionHash,
                'status' => AnnouncementBatchStatus::Queued,
                'selected_count' => count($prepared),
                'estimated_character_count' => $estimatedCharacters,
                'requested_by' => $actor->id,
            ]);

            foreach ($prepared as $index => $item) {
                $batch->items()->create([
                    'ceremony_id' => $lockedCeremony->id,
                    'student_id' => $item['student_id'],
                    'position' => $index + 1,
                    'status' => AnnouncementBatchItemStatus::Queued,
                    'character_count' => $item['character_count'],
                ]);
            }

            $this->auditLogger->record(
                $actor,
                'announcement_batch.started',
                'announcement_generation_batch',
                $batch->id,
                null,
                [
                    'ceremony_id' => $lockedCeremony->id,
                    'template_id' => $template->id,
                    'selected_count' => $batch->selected_count,
                    'estimated_character_count' => $batch->estimated_character_count,
                    'voice' => $batch->provider_voice,
                ],
                $ipAddress,
            );

            ProcessAnnouncementGenerationBatch::dispatch($batch->id)
                ->onQueue((string) config('announcements.batch_generation.queue'))
                ->afterCommit();

            return new AnnouncementBatchStartResult($batch, false);
        }, 3);

        return $result;
    }

    /**
     * @param  Collection<int, Student>  $students
     * @return list<array{student_id: int, character_count: int}>
     */
    private function prepare(Collection $students, AnnouncementTemplate $template): array
    {
        $prepared = [];

        foreach ($students as $student) {
            $preview = $this->previewer->preview(
                $student,
                $template,
                $template->default_voice,
            );

            if (! $preview->isReady() || $preview->composition === null) {
                throw new AnnouncementBatchException(
                    'announcement_not_ready',
                    'Every selected graduate must pass announcement preview validation.',
                );
            }

            $prepared[] = [
                'student_id' => $student->id,
                'character_count' => $preview->composition->characterCount,
            ];
        }

        return $prepared;
    }
}
