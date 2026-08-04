<?php

namespace App\Services;

use App\Enums\AnnouncementBatchItemStatus;
use App\Enums\AnnouncementBatchStatus;
use App\Events\AnnouncementBatchProgressUpdated;
use App\Models\AnnouncementGenerationBatch;
use Illuminate\Support\Facades\DB;

final class CancelAnnouncementBatchesForSettingsChange
{
    public function forCeremony(int $ceremonyId): void
    {
        $batches = AnnouncementGenerationBatch::query()
            ->where('ceremony_id', $ceremonyId)
            ->whereIn('status', [
                AnnouncementBatchStatus::Queued->value,
                AnnouncementBatchStatus::Running->value,
                AnnouncementBatchStatus::Paused->value,
            ])
            ->lockForUpdate()
            ->get();

        foreach ($batches as $batch) {
            $batch->items()
                ->where('status', AnnouncementBatchItemStatus::Queued->value)
                ->update([
                    'status' => AnnouncementBatchItemStatus::Cancelled->value,
                    'processed_at' => now(),
                ]);
            $hasProcessing = $batch->items()
                ->where('status', AnnouncementBatchItemStatus::Processing->value)
                ->exists();
            $batch->update([
                'status' => $hasProcessing
                    ? AnnouncementBatchStatus::Running
                    : AnnouncementBatchStatus::Cancelled,
                'cancellation_requested_at' => now(),
                'completed_at' => $hasProcessing ? null : now(),
                'last_failure_code' => 'settings_changed',
                'last_failure_message' => 'The batch stopped because ceremony announcement settings changed.',
            ]);

            DB::afterCommit(fn () => event(new AnnouncementBatchProgressUpdated(
                $batch->id,
                $ceremonyId,
            )));
        }
    }
}
