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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class SetStageHolding
{
    public function __construct(
        private ActiveCeremonyResolver $ceremonies,
    ) {}

    /**
     * @return array{data: array<string, mixed>, http_status: int}
     */
    public function execute(bool $holding, User $operator): array
    {
        $committed = DB::transaction(function () use ($holding, $operator): array {
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

            if (
                ($holding && $state->display_mode === StageDisplayMode::Holding)
                || (! $holding && $state->display_mode !== StageDisplayMode::Holding)
            ) {
                return [
                    'status' => $holding ? 'already_holding' : 'already_live',
                ];
            }

            $restoredMode = StageDisplayMode::Idle;

            if (! $holding && $state->current_queue_item_id !== null) {
                $current = StageQueueItem::query()
                    ->whereBelongsTo($ceremony)
                    ->lockForUpdate()
                    ->find($state->current_queue_item_id);

                if (
                    $current === null
                    || ! in_array($current->status, [
                        StageQueueStatus::Presenting,
                        StageQueueStatus::Announced,
                    ], true)
                ) {
                    return ['status' => 'state_conflict'];
                }

                $restoredMode = StageDisplayMode::Graduate;
            }

            $state->fill([
                'display_mode' => $holding
                    ? StageDisplayMode::Holding
                    : $restoredMode,
                'version' => $state->version + 1,
            ])->save();

            $event = StageEvent::query()->create([
                'ceremony_id' => $ceremony->id,
                'queue_item_id' => $state->current_queue_item_id,
                'event_type' => $holding
                    ? 'holding_screen_activated'
                    : 'holding_screen_deactivated',
                'payload' => [
                    'version' => $state->version,
                    'holding' => $holding,
                ],
                'performed_by' => $operator->id,
                'created_at' => now(),
            ]);

            return [
                'status' => $holding ? 'holding' : 'live',
                'ceremony_id' => $ceremony->id,
                'queue_item_id' => $state->current_queue_item_id,
                'event_id' => $event->id,
                'version' => $state->version,
            ];
        }, attempts: 3);

        if (! in_array($committed['status'], ['holding', 'live'], true)) {
            $messages = [
                'ceremony_inactive' => 'Exactly one active ceremony is required.',
                'already_holding' => 'The audience display is already showing the holding screen.',
                'already_live' => 'The audience display is already live.',
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
                $committed['status'] === 'holding',
                $committed['event_id'],
            );
            QueueUpdated::dispatch(
                $committed['ceremony_id'],
                $committed['version'],
                $committed['status'] === 'holding'
                    ? 'holding_screen_activated'
                    : 'holding_screen_deactivated',
                $committed['queue_item_id'],
            );
        } catch (Throwable $exception) {
            $delivered = false;
            Log::warning('Holding state committed but live delivery failed.', [
                'ceremony_id' => $committed['ceremony_id'],
                'event_id' => $committed['event_id'],
                'exception' => $exception::class,
            ]);
        }

        return [
            'data' => [
                'status' => $committed['status'],
                'message' => $committed['status'] === 'holding'
                    ? 'Holding screen shown. The current graduate remains active.'
                    : 'Live audience display restored.',
                'stage_version' => $committed['version'],
                'broadcast_delivered' => $delivered,
            ],
            'http_status' => 200,
        ];
    }
}
