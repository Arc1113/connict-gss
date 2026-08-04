<?php

namespace App\Actions;

use App\Enums\AnnouncementBatchItemStatus;
use App\Enums\AnnouncementBatchStatus;
use App\Events\AnnouncementBatchProgressUpdated;
use App\Exceptions\AnnouncementBatchException;
use App\Models\AnnouncementGenerationBatch;
use App\Models\User;
use App\Support\AdministrativeAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class CancelAnnouncementGenerationBatch
{
    public function __construct(private readonly AdministrativeAuditLogger $auditLogger) {}

    public function cancel(
        AnnouncementGenerationBatch $batch,
        User $actor,
        ?string $ipAddress,
    ): AnnouncementGenerationBatch {
        Gate::forUser($actor)->authorize('manage-announcement-batches');

        $cancelled = DB::transaction(function () use ($batch, $actor, $ipAddress): AnnouncementGenerationBatch {
            $locked = AnnouncementGenerationBatch::query()->lockForUpdate()->findOrFail($batch->id);

            if ($locked->status->terminal()) {
                throw new AnnouncementBatchException(
                    'batch_already_finished',
                    'This batch has already finished and cannot be cancelled.',
                );
            }

            $locked->items()
                ->where('status', AnnouncementBatchItemStatus::Queued->value)
                ->update([
                    'status' => AnnouncementBatchItemStatus::Cancelled->value,
                    'processed_at' => now(),
                ]);
            $hasProcessing = $locked->items()
                ->where('status', AnnouncementBatchItemStatus::Processing->value)
                ->exists();
            $locked->update([
                'cancellation_requested_at' => now(),
                'status' => $hasProcessing
                    ? AnnouncementBatchStatus::Running
                    : AnnouncementBatchStatus::Cancelled,
                'completed_at' => $hasProcessing ? null : now(),
            ]);

            $this->auditLogger->record(
                $actor,
                'announcement_batch.cancelled',
                'announcement_generation_batch',
                $locked->id,
                null,
                [
                    'ceremony_id' => $locked->ceremony_id,
                    'processed_count' => $locked->processed_count,
                    'selected_count' => $locked->selected_count,
                    'in_flight' => $hasProcessing,
                ],
                $ipAddress,
            );

            return $locked->fresh();
        }, 3);

        event(new AnnouncementBatchProgressUpdated($cancelled->id, $cancelled->ceremony_id));

        return $cancelled;
    }
}
