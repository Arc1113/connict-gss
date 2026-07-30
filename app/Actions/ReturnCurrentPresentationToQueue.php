<?php

namespace App\Actions;

use App\Enums\StageAudioStatus;
use App\Enums\StageDisplayMode;
use App\Enums\StageQueueStatus;
use App\Events\QueueUpdated;
use App\Events\StageHoldingChanged;
use App\Models\StageEvent;
use App\Models\StageQueueItem;
use App\Models\StageState;
use App\Models\User;
use App\Support\ActiveCeremonyResolver;
use App\Support\StageQueueTransitions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class ReturnCurrentPresentationToQueue
{
    public function __construct(
        private ActiveCeremonyResolver $ceremonies,
        private StageQueueTransitions $transitions,
    ) {}

    /**
     * @return array{data: array<string, mixed>, http_status: int}
     */
    public function execute(User $operator, int $expectedVersion): array
    {
        $committed = DB::transaction(function () use (
            $operator,
            $expectedVersion,
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

            if ($state === null || $state->current_queue_item_id === null) {
                return [
                    'status' => 'stage_idle',
                    'ceremony_id' => $ceremony->id,
                ];
            }

            if ($state->version !== $expectedVersion) {
                return [
                    'status' => 'stale_stage_state',
                    'ceremony_id' => $ceremony->id,
                ];
            }

            $current = StageQueueItem::query()
                ->whereBelongsTo($ceremony)
                ->lockForUpdate()
                ->find($state->current_queue_item_id);

            if (
                $current === null
                || $current->queue_position === null
                || ! in_array($current->status, [
                    StageQueueStatus::Presenting,
                    StageQueueStatus::Announced,
                ], true)
            ) {
                return [
                    'status' => 'state_conflict',
                    'ceremony_id' => $ceremony->id,
                ];
            }

            $this->transitions->transition(
                $current,
                StageQueueStatus::Waiting,
            );
            $current->fill([
                'presented_at' => null,
                'announced_at' => null,
                'completed_at' => null,
                'presented_by' => null,
            ])->save();

            $state->fill([
                'current_queue_item_id' => null,
                'display_mode' => StageDisplayMode::Holding,
                'audio_status' => StageAudioStatus::Stopped,
                'version' => $state->version + 1,
            ])->save();

            $event = StageEvent::query()->create([
                'ceremony_id' => $ceremony->id,
                'queue_item_id' => $current->id,
                'event_type' => 'current_presentation_returned',
                'payload' => [
                    'version' => $state->version,
                    'student_id' => $current->student_id,
                    'queue_position' => $current->queue_position,
                    'holding' => true,
                ],
                'performed_by' => $operator->id,
                'created_at' => now(),
            ]);

            return [
                'status' => 'current_returned',
                'ceremony_id' => $ceremony->id,
                'queue_item_id' => $current->id,
                'queue_position' => $current->queue_position,
                'event_id' => $event->id,
                'version' => $state->version,
            ];
        }, attempts: 3);

        if ($committed['status'] !== 'current_returned') {
            $messages = [
                'ceremony_inactive' => 'Exactly one active ceremony is required.',
                'stage_idle' => 'There is no current presentation to correct.',
                'stale_stage_state' => 'Stage state changed before this command. Review the recovered current graduate before trying again.',
                'state_conflict' => 'The persisted stage state requires operator review.',
            ];

            return [
                'data' => [
                    'status' => $committed['status'],
                    'message' => $messages[$committed['status']],
                ],
                'http_status' => 409,
            ];
        }

        $delivered = true;

        try {
            StageHoldingChanged::dispatch(
                $committed['ceremony_id'],
                $committed['version'],
                true,
                $committed['event_id'],
            );
            QueueUpdated::dispatch(
                $committed['ceremony_id'],
                $committed['version'],
                'current_presentation_returned',
                $committed['queue_item_id'],
            );
        } catch (Throwable $exception) {
            $delivered = false;
            Log::warning('Current presentation returned but live delivery failed.', [
                'ceremony_id' => $committed['ceremony_id'],
                'queue_item_id' => $committed['queue_item_id'],
                'exception' => $exception::class,
            ]);
        }

        return [
            'data' => [
                'status' => 'current_returned',
                'message' => 'Graduate returned to the queue without completion. The audience display is holding; present the correct graduate when ready.',
                'queue_position' => $committed['queue_position'],
                'stage_version' => $committed['version'],
                'broadcast_delivered' => $delivered,
            ],
            'http_status' => 200,
        ];
    }
}
