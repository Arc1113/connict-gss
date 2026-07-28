<?php

namespace App\Actions;

use App\Enums\StageAudioStatus;
use App\Enums\StageDisplayMode;
use App\Enums\StageQueueStatus;
use App\Events\StagePresentationCleared;
use App\Models\StageEvent;
use App\Models\StageQueueItem;
use App\Models\StageState;
use App\Models\User;
use App\Support\ActiveCeremonyResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class CompleteCurrentPresentation
{
    public function __construct(
        private ActiveCeremonyResolver $ceremonies,
    ) {}

    /**
     * @return array{data: array<string, mixed>, http_status: int}
     */
    public function execute(User $operator): array
    {
        /** @var array{data: array<string, mixed>, http_status: int, ceremony_id: int|null, broadcast: array<string, mixed>|null} $committed */
        $committed = DB::transaction(function () use ($operator): array {
            $resolution = $this->ceremonies->resolve(lockForUpdate: true);

            if (! $resolution->isResolved()) {
                return [
                    'data' => [
                        'status' => 'ceremony_inactive',
                        'message' => 'Exactly one active ceremony is required.',
                    ],
                    'http_status' => 409,
                    'ceremony_id' => null,
                    'broadcast' => null,
                ];
            }

            $ceremony = $resolution->ceremony;
            $stageState = StageState::query()
                ->where('ceremony_id', $ceremony->id)
                ->lockForUpdate()
                ->first();

            if ($stageState === null || $stageState->current_queue_item_id === null) {
                return [
                    'data' => [
                        'status' => 'stage_idle',
                        'message' => 'There is no current presentation to complete.',
                    ],
                    'http_status' => 409,
                    'ceremony_id' => $ceremony->id,
                    'broadcast' => null,
                ];
            }

            $queueItem = StageQueueItem::query()
                ->where('ceremony_id', $ceremony->id)
                ->lockForUpdate()
                ->find($stageState->current_queue_item_id);

            if ($queueItem === null || $queueItem->status !== StageQueueStatus::Presenting) {
                return [
                    'data' => [
                        'status' => 'state_conflict',
                        'message' => 'The persisted stage state requires operator review.',
                    ],
                    'http_status' => 409,
                    'ceremony_id' => $ceremony->id,
                    'broadcast' => null,
                ];
            }

            $completedAt = now();
            $queueItem->fill([
                'status' => StageQueueStatus::Completed,
                'completed_at' => $completedAt,
                'presented_by' => $operator->id,
            ])->save();

            $stageState->fill([
                'current_queue_item_id' => null,
                'display_mode' => StageDisplayMode::Idle,
                'audio_status' => StageAudioStatus::Stopped,
                'version' => $stageState->version + 1,
            ])->save();

            $event = StageEvent::query()->create([
                'ceremony_id' => $ceremony->id,
                'queue_item_id' => $queueItem->id,
                'event_type' => 'student_completed',
                'payload' => [
                    'version' => $stageState->version,
                    'student_id' => $queueItem->student_id,
                ],
                'performed_by' => $operator->id,
                'created_at' => $completedAt,
            ]);

            $payload = [
                'event_id' => $event->id,
                'version' => $stageState->version,
                'presentation' => null,
                'cleared_at' => $completedAt->toIso8601String(),
            ];

            return [
                'data' => [
                    'status' => 'completed',
                    'message' => 'Current presentation completed and stage cleared.',
                    'stage_version' => $stageState->version,
                    'broadcast_delivered' => true,
                ],
                'http_status' => 200,
                'ceremony_id' => $ceremony->id,
                'broadcast' => $payload,
            ];
        }, attempts: 3);

        if ($committed['broadcast'] !== null && $committed['ceremony_id'] !== null) {
            try {
                StagePresentationCleared::dispatch(
                    $committed['ceremony_id'],
                    $committed['broadcast'],
                );
            } catch (Throwable $exception) {
                $committed['data']['broadcast_delivered'] = false;

                Log::warning('Stage clear committed but real-time delivery failed.', [
                    'ceremony_id' => $committed['ceremony_id'],
                    'event_id' => $committed['broadcast']['event_id'],
                    'exception' => $exception::class,
                ]);
            }
        }

        return [
            'data' => $committed['data'],
            'http_status' => $committed['http_status'],
        ];
    }
}
