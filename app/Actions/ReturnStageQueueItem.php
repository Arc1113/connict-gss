<?php

namespace App\Actions;

use App\Enums\StageAudioStatus;
use App\Enums\StageDisplayMode;
use App\Enums\StageQueueStatus;
use App\Events\QueueUpdated;
use App\Models\StageEvent;
use App\Models\StageQueueItem;
use App\Models\StageState;
use App\Models\User;
use App\Support\ActiveCeremonyResolver;
use App\Support\StageQueueTransitions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class ReturnStageQueueItem
{
    public function __construct(
        private ActiveCeremonyResolver $ceremonies,
        private StageQueueTransitions $transitions,
    ) {}

    /**
     * @return array{data: array<string, mixed>, http_status: int}
     */
    public function execute(int $queueItemId, User $operator): array
    {
        $committed = DB::transaction(function () use (
            $queueItemId,
            $operator,
        ): array {
            $resolution = $this->ceremonies->resolve(lockForUpdate: true);

            if (! $resolution->isResolved()) {
                return ['status' => 'ceremony_inactive'];
            }

            $ceremony = $resolution->ceremony;
            $state = StageState::query()
                ->whereBelongsTo($ceremony)
                ->lockForUpdate()
                ->first();
            $state ??= StageState::query()->create([
                'ceremony_id' => $ceremony->id,
                'display_mode' => StageDisplayMode::Idle,
                'audio_status' => StageAudioStatus::Stopped,
                'automatic_paused' => false,
                'version' => 0,
            ]);
            $item = StageQueueItem::query()
                ->whereBelongsTo($ceremony)
                ->lockForUpdate()
                ->find($queueItemId);

            if ($item === null || $item->status !== StageQueueStatus::Skipped) {
                return [
                    'status' => 'queue_item_unavailable',
                    'ceremony_id' => $ceremony->id,
                ];
            }

            $this->transitions->transition($item, StageQueueStatus::Waiting);
            $item->fill([
                'queue_position' => $this->transitions->nextPosition($ceremony->id),
                'scanned_at' => now(),
            ])->save();

            $state->version++;
            $state->save();

            StageEvent::query()->create([
                'ceremony_id' => $ceremony->id,
                'queue_item_id' => $item->id,
                'event_type' => 'student_returned_to_queue',
                'payload' => [
                    'version' => $state->version,
                    'queue_position' => $item->queue_position,
                ],
                'performed_by' => $operator->id,
                'created_at' => now(),
            ]);

            return [
                'status' => 'returned',
                'ceremony_id' => $ceremony->id,
                'queue_item_id' => $item->id,
                'queue_position' => $item->queue_position,
                'version' => $state->version,
            ];
        }, attempts: 3);

        if ($committed['status'] !== 'returned') {
            return [
                'data' => [
                    'status' => $committed['status'],
                    'message' => $committed['status'] === 'ceremony_inactive'
                        ? 'Exactly one active ceremony is required.'
                        : 'Only a skipped graduate can be returned to the queue.',
                ],
                'http_status' => 409,
            ];
        }

        $delivered = true;

        try {
            QueueUpdated::dispatch(
                $committed['ceremony_id'],
                $committed['version'],
                'student_returned_to_queue',
                $committed['queue_item_id'],
            );
        } catch (Throwable $exception) {
            $delivered = false;
            Log::warning('Queue return committed but live delivery failed.', [
                'ceremony_id' => $committed['ceremony_id'],
                'queue_item_id' => $committed['queue_item_id'],
                'exception' => $exception::class,
            ]);
        }

        return [
            'data' => [
                'status' => 'returned',
                'message' => 'Graduate returned to the end of the queue.',
                'queue_position' => $committed['queue_position'],
                'stage_version' => $committed['version'],
                'broadcast_delivered' => $delivered,
            ],
            'http_status' => 200,
        ];
    }
}
