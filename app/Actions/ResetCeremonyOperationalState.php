<?php

namespace App\Actions;

use App\Enums\StageAudioStatus;
use App\Enums\StageDisplayMode;
use App\Enums\StageQueueStatus;
use App\Events\QueueUpdated;
use App\Events\StagePresentationCleared;
use App\Models\Ceremony;
use App\Models\StageEvent;
use App\Models\StageQueueItem;
use App\Models\StageState;
use App\Models\User;
use App\Support\AdministrativeAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class ResetCeremonyOperationalState
{
    public function __construct(
        private AdministrativeAuditLogger $audit,
    ) {}

    /**
     * @return array{data: array<string, mixed>, http_status: int}
     */
    public function execute(Ceremony $ceremony, User $actor, ?string $ipAddress): array
    {
        $committed = DB::transaction(function () use ($ceremony, $actor, $ipAddress): array {
            $state = StageState::query()
                ->whereBelongsTo($ceremony)
                ->lockForUpdate()
                ->first();

            if ($state?->current_queue_item_id !== null) {
                $presenting = StageQueueItem::query()
                    ->whereBelongsTo($ceremony)
                    ->whereKey($state->current_queue_item_id)
                    ->whereIn('status', [
                        StageQueueStatus::Presenting,
                        StageQueueStatus::Announced,
                    ])
                    ->exists();

                if ($presenting) {
                    return [
                        'status' => 'blocked_presenting',
                        'http_status' => 409,
                    ];
                }
            }

            $state ??= StageState::query()->create([
                'ceremony_id' => $ceremony->id,
                'display_mode' => StageDisplayMode::Idle,
                'audio_status' => StageAudioStatus::Stopped,
                'automatic_paused' => false,
                'version' => 0,
            ]);

            $cancelled = StageQueueItem::query()
                ->whereBelongsTo($ceremony)
                ->whereIn('status', [
                    StageQueueStatus::Waiting,
                    StageQueueStatus::Ready,
                ])
                ->update([
                    'status' => StageQueueStatus::Cancelled,
                    'queue_position' => null,
                    'updated_at' => now(),
                ]);

            $state->fill([
                'current_queue_item_id' => null,
                'display_mode' => StageDisplayMode::Idle,
                'audio_status' => StageAudioStatus::Stopped,
                'automatic_paused' => false,
                'version' => $state->version + 1,
            ])->save();

            $event = StageEvent::query()->create([
                'ceremony_id' => $ceremony->id,
                'queue_item_id' => null,
                'event_type' => 'operational_reset',
                'payload' => [
                    'version' => $state->version,
                    'cancelled_non_terminal_queue_items' => $cancelled,
                    'history_preserved' => true,
                ],
                'performed_by' => $actor->id,
                'created_at' => now(),
            ]);

            $this->audit->record(
                $actor,
                'ceremony.operational_reset',
                'ceremony',
                $ceremony->id,
                null,
                [
                    'cancelled_non_terminal_queue_items' => $cancelled,
                    'completed_history_preserved' => true,
                    'scan_logs_preserved' => true,
                    'stage_events_preserved' => true,
                    'stage_version' => $state->version,
                ],
                $ipAddress,
            );

            return [
                'status' => 'reset',
                'http_status' => 200,
                'event_id' => $event->id,
                'version' => $state->version,
                'cancelled' => $cancelled,
            ];
        }, attempts: 3);

        if ($committed['status'] !== 'reset') {
            return [
                'data' => [
                    'status' => $committed['status'],
                    'message' => 'Reset is blocked while a graduate is currently presenting. Complete or safely recover the presentation first.',
                ],
                'http_status' => $committed['http_status'],
            ];
        }

        $delivered = true;

        try {
            StagePresentationCleared::dispatch($ceremony->id, [
                'event_id' => $committed['event_id'],
                'version' => $committed['version'],
                'presentation' => null,
                'cleared_at' => now()->toIso8601String(),
            ]);
            QueueUpdated::dispatch(
                $ceremony->id,
                $committed['version'],
                'operational_reset',
            );
        } catch (Throwable $exception) {
            $delivered = false;
            Log::warning('Operational reset committed but real-time delivery failed.', [
                'ceremony_id' => $ceremony->id,
                'event_id' => $committed['event_id'],
                'exception' => $exception::class,
            ]);
        }

        return [
            'data' => [
                'status' => 'reset',
                'message' => "Current operational state reset. {$committed['cancelled']} waiting or ready queue rows were cancelled; history was preserved.",
                'broadcast_delivered' => $delivered,
            ],
            'http_status' => 200,
        ];
    }
}
