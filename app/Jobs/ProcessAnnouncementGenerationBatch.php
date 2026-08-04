<?php

namespace App\Jobs;

use App\Actions\GenerateAnnouncementCandidate;
use App\Enums\AnnouncementBatchItemStatus;
use App\Enums\AnnouncementBatchStatus;
use App\Events\AnnouncementBatchProgressUpdated;
use App\Exceptions\AnnouncementCandidateGenerationException;
use App\Models\AnnouncementGenerationBatch;
use App\Models\AnnouncementGenerationBatchItem;
use App\Models\AnnouncementTemplate;
use App\Models\Student;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ProcessAnnouncementGenerationBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 180;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $batchId) {}

    public function handle(GenerateAnnouncementCandidate $generator): void
    {
        $claim = DB::transaction(function (): ?array {
            $batch = AnnouncementGenerationBatch::query()
                ->lockForUpdate()
                ->find($this->batchId);

            if ($batch === null || $batch->status->terminal()
                || $batch->status === AnnouncementBatchStatus::Paused) {
                return null;
            }

            if ($batch->cancellation_requested_at !== null) {
                $this->finishCancellation($batch);

                return null;
            }

            $activeTemplate = AnnouncementTemplate::query()
                ->whereKey($batch->announcement_template_id)
                ->where('is_active', true)
                ->exists();

            if (! $activeTemplate) {
                $batch->update([
                    'status' => AnnouncementBatchStatus::Paused,
                    'last_failure_code' => 'settings_changed',
                    'last_failure_message' => 'Ceremony announcement settings changed. Cancel this batch and start a new one.',
                ]);

                return null;
            }

            $alreadyProcessing = AnnouncementGenerationBatchItem::query()
                ->where('announcement_generation_batch_id', $batch->id)
                ->where('status', AnnouncementBatchItemStatus::Processing->value)
                ->exists();

            if ($alreadyProcessing) {
                return null;
            }

            $item = AnnouncementGenerationBatchItem::query()
                ->where('announcement_generation_batch_id', $batch->id)
                ->where('status', AnnouncementBatchItemStatus::Queued->value)
                ->orderBy('position')
                ->lockForUpdate()
                ->first();

            if ($item === null) {
                $this->finishIfComplete($batch);

                return null;
            }

            $actor = User::query()->find($batch->requested_by);

            if ($actor === null || ! $actor->is_active) {
                $batch->update([
                    'status' => AnnouncementBatchStatus::Paused,
                    'last_failure_code' => 'requester_unavailable',
                    'last_failure_message' => 'The requesting administrator is no longer available. Cancel this batch and start a new one.',
                ]);

                return null;
            }

            $batch->update([
                'status' => AnnouncementBatchStatus::Running,
                'started_at' => $batch->started_at ?? now(),
                'last_failure_code' => null,
                'last_failure_message' => null,
            ]);
            $item->update([
                'status' => AnnouncementBatchItemStatus::Processing,
                'failure_code' => null,
                'failure_message' => null,
            ]);

            return [
                'batch_id' => $batch->id,
                'ceremony_id' => $batch->ceremony_id,
                'item_id' => $item->id,
                'student_id' => $item->student_id,
                'template_id' => $batch->announcement_template_id,
                'voice' => $batch->provider_voice,
                'actor_id' => $actor->id,
            ];
        }, 3);

        $this->broadcast();

        if ($claim === null) {
            return;
        }

        try {
            $result = $generator->generate(
                student: Student::query()->findOrFail($claim['student_id']),
                actor: User::query()->findOrFail($claim['actor_id']),
                template: AnnouncementTemplate::query()->findOrFail($claim['template_id']),
                voice: $claim['voice'],
            );

            $this->completeItem(
                $claim['item_id'],
                $result->generation->id,
                $result->reused
                    ? AnnouncementBatchItemStatus::Reused
                    : AnnouncementBatchItemStatus::Succeeded,
            );
        } catch (AnnouncementCandidateGenerationException $exception) {
            $this->handleKnownFailure($claim['item_id'], $exception);
        } catch (Throwable) {
            $this->failItem(
                $claim['item_id'],
                'batch_item_failed',
                'The batch item could not be processed safely.',
            );
        }

        $this->broadcast();
        $this->dispatchNextIfNeeded();
    }

    public function failed(?Throwable $exception): void
    {
        DB::transaction(function (): void {
            $batch = AnnouncementGenerationBatch::query()->lockForUpdate()->find($this->batchId);

            if ($batch === null || $batch->status->terminal()) {
                return;
            }

            AnnouncementGenerationBatchItem::query()
                ->where('announcement_generation_batch_id', $batch->id)
                ->where('status', AnnouncementBatchItemStatus::Processing->value)
                ->update([
                    'status' => AnnouncementBatchItemStatus::Queued->value,
                    'failure_code' => 'worker_interrupted',
                    'failure_message' => 'The preparation worker stopped. Resume the batch when it is available.',
                ]);
            $batch->update([
                'status' => AnnouncementBatchStatus::Paused,
                'last_failure_code' => 'worker_interrupted',
                'last_failure_message' => 'The preparation worker stopped. Resume the batch when it is available.',
            ]);
        }, 3);

        $this->broadcast();
    }

    private function handleKnownFailure(
        int $itemId,
        AnnouncementCandidateGenerationException $exception,
    ): void {
        if (in_array($exception->safeCode, [
            'azure_rate_limited',
            'azure_timeout',
            'azure_service_unavailable',
        ], true)) {
            DB::transaction(function () use ($itemId, $exception): void {
                $batch = AnnouncementGenerationBatch::query()->lockForUpdate()->findOrFail($this->batchId);
                AnnouncementGenerationBatchItem::query()->whereKey($itemId)->update([
                    'status' => AnnouncementBatchItemStatus::Queued->value,
                    'failure_code' => $exception->safeCode,
                    'failure_message' => mb_substr($exception->getMessage(), 0, 500),
                ]);
                $batch->update([
                    'status' => AnnouncementBatchStatus::Paused,
                    'last_failure_code' => $exception->safeCode,
                    'last_failure_message' => mb_substr($exception->getMessage(), 0, 500),
                ]);
            }, 3);

            return;
        }

        $this->failItem($itemId, $exception->safeCode, $exception->getMessage());
    }

    private function completeItem(
        int $itemId,
        int $generationId,
        AnnouncementBatchItemStatus $status,
    ): void {
        DB::transaction(function () use ($itemId, $generationId, $status): void {
            $batch = AnnouncementGenerationBatch::query()->lockForUpdate()->findOrFail($this->batchId);
            AnnouncementGenerationBatchItem::query()->whereKey($itemId)->update([
                'status' => $status->value,
                'announcement_generation_id' => $generationId,
                'processed_at' => now(),
            ]);
            $this->refreshProgress($batch);
        }, 3);
    }

    private function failItem(int $itemId, string $code, string $message): void
    {
        DB::transaction(function () use ($itemId, $code, $message): void {
            $batch = AnnouncementGenerationBatch::query()->lockForUpdate()->findOrFail($this->batchId);
            AnnouncementGenerationBatchItem::query()->whereKey($itemId)->update([
                'status' => AnnouncementBatchItemStatus::Failed->value,
                'failure_code' => mb_substr($code, 0, 96),
                'failure_message' => mb_substr($message, 0, 500),
                'processed_at' => now(),
            ]);
            $batch->update([
                'last_failure_code' => mb_substr($code, 0, 96),
                'last_failure_message' => mb_substr($message, 0, 500),
            ]);
            $this->refreshProgress($batch);
        }, 3);
    }

    private function refreshProgress(AnnouncementGenerationBatch $batch): void
    {
        $items = AnnouncementGenerationBatchItem::query()
            ->where('announcement_generation_batch_id', $batch->id);
        $succeeded = (clone $items)->where('status', AnnouncementBatchItemStatus::Succeeded->value)->count();
        $reused = (clone $items)->where('status', AnnouncementBatchItemStatus::Reused->value)->count();
        $failed = (clone $items)->where('status', AnnouncementBatchItemStatus::Failed->value)->count();
        $processing = (clone $items)->where('status', AnnouncementBatchItemStatus::Processing->value)->exists();
        $queued = (clone $items)->where('status', AnnouncementBatchItemStatus::Queued->value)->exists();

        $batch->update([
            'processed_count' => $succeeded + $reused + $failed,
            'succeeded_count' => $succeeded,
            'reused_count' => $reused,
            'failed_count' => $failed,
        ]);

        if ($batch->cancellation_requested_at !== null) {
            $this->finishCancellation($batch);
        } elseif (! $processing && ! $queued) {
            $batch->update([
                'status' => AnnouncementBatchStatus::Completed,
                'completed_at' => now(),
            ]);
        } else {
            $batch->update(['status' => AnnouncementBatchStatus::Queued]);
        }
    }

    private function finishCancellation(AnnouncementGenerationBatch $batch): void
    {
        AnnouncementGenerationBatchItem::query()
            ->where('announcement_generation_batch_id', $batch->id)
            ->where('status', AnnouncementBatchItemStatus::Queued->value)
            ->update([
                'status' => AnnouncementBatchItemStatus::Cancelled->value,
                'processed_at' => now(),
            ]);

        $processing = AnnouncementGenerationBatchItem::query()
            ->where('announcement_generation_batch_id', $batch->id)
            ->where('status', AnnouncementBatchItemStatus::Processing->value)
            ->exists();

        if (! $processing) {
            $batch->update([
                'status' => AnnouncementBatchStatus::Cancelled,
                'completed_at' => now(),
            ]);
        }
    }

    private function finishIfComplete(AnnouncementGenerationBatch $batch): void
    {
        $processing = AnnouncementGenerationBatchItem::query()
            ->where('announcement_generation_batch_id', $batch->id)
            ->where('status', AnnouncementBatchItemStatus::Processing->value)
            ->exists();

        if (! $processing) {
            $batch->update([
                'status' => AnnouncementBatchStatus::Completed,
                'completed_at' => now(),
            ]);
        }
    }

    private function dispatchNextIfNeeded(): void
    {
        $shouldDispatch = AnnouncementGenerationBatch::query()
            ->whereKey($this->batchId)
            ->where('status', AnnouncementBatchStatus::Queued->value)
            ->whereNull('cancellation_requested_at')
            ->exists();

        if ($shouldDispatch) {
            self::dispatch($this->batchId)
                ->onQueue((string) config('announcements.batch_generation.queue'));
        }
    }

    private function broadcast(): void
    {
        $ceremonyId = AnnouncementGenerationBatch::query()
            ->whereKey($this->batchId)
            ->value('ceremony_id');

        if ($ceremonyId !== null) {
            event(new AnnouncementBatchProgressUpdated($this->batchId, (int) $ceremonyId));
        }
    }
}
