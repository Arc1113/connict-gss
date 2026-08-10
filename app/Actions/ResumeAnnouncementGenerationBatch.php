<?php

namespace App\Actions;

use App\Enums\AnnouncementBatchStatus;
use App\Enums\AnnouncementProvider;
use App\Events\AnnouncementBatchProgressUpdated;
use App\Exceptions\AnnouncementBatchException;
use App\Jobs\ProcessAnnouncementGenerationBatch;
use App\Models\AnnouncementGenerationBatch;
use App\Models\AnnouncementTemplate;
use App\Models\User;
use App\Services\TextToSpeechProviderRegistry;
use App\Support\AdministrativeAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class ResumeAnnouncementGenerationBatch
{
    public function __construct(
        private readonly TextToSpeechProviderRegistry $provider,
        private readonly AdministrativeAuditLogger $auditLogger,
    ) {}

    public function resume(
        AnnouncementGenerationBatch $batch,
        User $actor,
        ?string $ipAddress,
    ): AnnouncementGenerationBatch {
        Gate::forUser($actor)->authorize('manage-announcement-batches');

        if (! $this->provider->healthFor($batch->provider)->isConfigured()) {
            throw new AnnouncementBatchException(
                $batch->provider === AnnouncementProvider::Azure
                    ? 'azure_not_configured'
                    : 'local_tts_not_configured',
                $batch->provider === AnnouncementProvider::Azure
                    ? 'Azure Speech is disabled or not configured safely for batch preparation.'
                    : 'Local voice generation is disabled or not configured safely for batch preparation.',
            );
        }

        $resumed = DB::transaction(function () use ($batch, $actor, $ipAddress): AnnouncementGenerationBatch {
            $locked = AnnouncementGenerationBatch::query()->lockForUpdate()->findOrFail($batch->id);

            if ($locked->status !== AnnouncementBatchStatus::Paused
                || $locked->cancellation_requested_at !== null) {
                throw new AnnouncementBatchException(
                    'batch_not_paused',
                    'Only a paused, uncancelled batch can be resumed.',
                );
            }

            $templateIsActive = AnnouncementTemplate::query()
                ->whereKey($locked->announcement_template_id)
                ->where('is_active', true)
                ->exists();

            if (! $templateIsActive) {
                throw new AnnouncementBatchException(
                    'settings_changed',
                    'Ceremony settings changed. Cancel this batch and start a new one.',
                );
            }

            $locked->update([
                'status' => AnnouncementBatchStatus::Queued,
                'last_failure_code' => null,
                'last_failure_message' => null,
            ]);

            $this->auditLogger->record(
                $actor,
                'announcement_batch.resumed',
                'announcement_generation_batch',
                $locked->id,
                null,
                [
                    'ceremony_id' => $locked->ceremony_id,
                    'processed_count' => $locked->processed_count,
                    'selected_count' => $locked->selected_count,
                ],
                $ipAddress,
            );

            ProcessAnnouncementGenerationBatch::dispatch($locked->id)
                ->onQueue((string) config('announcements.batch_generation.queue'))
                ->afterCommit();

            return $locked->fresh();
        }, 3);

        event(new AnnouncementBatchProgressUpdated($resumed->id, $resumed->ceremony_id));

        return $resumed;
    }
}
