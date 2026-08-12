<?php

namespace App\Actions;

use App\Enums\PresentationMode;
use App\Enums\StageAudioStatus;
use App\Enums\StageDisplayMode;
use App\Enums\StageQueueStatus;
use App\Events\QueueUpdated;
use App\Events\StagePresentationCleared;
use App\Events\StudentPresented;
use App\Models\StageEvent;
use App\Models\StageQueueItem;
use App\Models\StageState;
use App\Models\User;
use App\Support\ActiveCeremonyResolver;
use App\Support\StageQueueTransitions;
use App\Support\StageStatePayload;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class CompleteAndPresentNext
{
    public function __construct(
        private ActiveCeremonyResolver $ceremonies,
        private StageQueueTransitions $transitions,
        private StageStatePayload $stagePayload,
    ) {}

    /**
     * @return array{data: array<string, mixed>, http_status: int}
     */
    public function execute(
        User $operator,
        int $expectedVersion,
        ?int $expectedStudentId = null,
        bool $fullyAutomaticOnly = false,
    ): array {
        /**
         * @var array{
         *     data: array<string, mixed>,
         *     http_status: int,
         *     ceremony_id: int|null,
         *     queue_item_id: int|null,
         *     version: int|null,
         *     presentation: array<string, mixed>|null,
         *     clear_payload: array<string, mixed>|null
         * } $committed
         */
        $committed = DB::transaction(function () use (
            $operator,
            $expectedVersion,
            $expectedStudentId,
            $fullyAutomaticOnly,
        ): array {
            $resolution = $this->ceremonies->resolve(lockForUpdate: true);

            if (! $resolution->isResolved()) {
                return $this->failure(
                    'ceremony_inactive',
                    'Exactly one active ceremony is required.',
                );
            }

            $ceremony = $resolution->ceremony;
            if ($fullyAutomaticOnly && $ceremony->presentation_mode !== PresentationMode::FullyAutomatic) {
                return $this->failure('not_fully_automatic', 'Fully automatic presentation is not active.', $ceremony->id);
            }
            $state = StageState::query()
                ->whereBelongsTo($ceremony)
                ->lockForUpdate()
                ->first();

            if ($state === null || $state->current_queue_item_id === null) {
                return $this->failure(
                    'stage_idle',
                    'There is no current presentation to complete.',
                    $ceremony->id,
                );
            }

            if ($state->version !== $expectedVersion) {
                return $this->failure(
                    'stale_stage_state',
                    'Stage state changed before this command. Review the recovered current graduate before trying again.',
                    $ceremony->id,
                );
            }

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
                return $this->failure(
                    'state_conflict',
                    'The persisted stage state requires operator review.',
                    $ceremony->id,
                );
            }

            if ($expectedStudentId !== null && $current->student_id !== $expectedStudentId) {
                return $this->failure('stale_presentation', 'The automatic completion belongs to an older graduate.', $ceremony->id);
            }

            if ($fullyAutomaticOnly && $state->automatic_paused) {
                return $this->failure('automatic_paused', 'Fully automatic presentation is paused for operator recovery.', $ceremony->id);
            }

            $next = StageQueueItem::query()
                ->whereBelongsTo($ceremony)
                ->whereIn('status', [
                    StageQueueStatus::Waiting,
                    StageQueueStatus::Ready,
                ])
                ->orderBy('queue_position')
                ->orderBy('scanned_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();
            $changedAt = now();
            $nextVersion = $state->version + 1;

            $current->fill([
                'status' => StageQueueStatus::Completed,
                'completed_at' => $changedAt,
                'presented_by' => $operator->id,
            ])->save();

            $completedEvent = StageEvent::query()->create([
                'ceremony_id' => $ceremony->id,
                'queue_item_id' => $current->id,
                'event_type' => 'student_completed',
                'payload' => [
                    'version' => $nextVersion,
                    'student_id' => $current->student_id,
                    'continued_to_next' => $next !== null,
                ],
                'performed_by' => $operator->id,
                'created_at' => $changedAt,
            ]);

            if ($next === null) {
                $state->fill([
                    'current_queue_item_id' => null,
                    'display_mode' => StageDisplayMode::Idle,
                    'audio_status' => StageAudioStatus::Stopped,
                    'version' => $nextVersion,
                ])->save();

                return [
                    'data' => [
                        'status' => 'completed_queue_empty',
                        'message' => 'Current presentation completed. The queue is empty and the stage is clear.',
                        'stage_version' => $nextVersion,
                        'broadcast_delivered' => true,
                    ],
                    'http_status' => 200,
                    'ceremony_id' => $ceremony->id,
                    'queue_item_id' => $current->id,
                    'version' => $nextVersion,
                    'presentation' => null,
                    'clear_payload' => [
                        'event_id' => $completedEvent->id,
                        'version' => $nextVersion,
                        'presentation' => null,
                        'cleared_at' => $changedAt->toIso8601String(),
                    ],
                ];
            }

            $this->transitions->transition(
                $next,
                StageQueueStatus::Presenting,
            );
            $next->fill([
                'presented_at' => $changedAt,
                'presented_by' => $operator->id,
            ])->save();

            $state->fill([
                'current_queue_item_id' => $next->id,
                'display_mode' => StageDisplayMode::Graduate,
                'audio_status' => StageAudioStatus::Stopped,
                'version' => $nextVersion,
            ])->save();

            $presentedEvent = StageEvent::query()->create([
                'ceremony_id' => $ceremony->id,
                'queue_item_id' => $next->id,
                'event_type' => 'present_next_after_completion',
                'payload' => [
                    'version' => $nextVersion,
                    'student_id' => $next->student_id,
                    'completed_queue_item_id' => $current->id,
                ],
                'performed_by' => $operator->id,
                'created_at' => $changedAt,
            ]);

            $next->load('student');

            return [
                'data' => [
                    'status' => 'completed_and_presented_next',
                    'message' => 'Current presentation completed and the next graduate is now presented.',
                    'stage_version' => $nextVersion,
                    'broadcast_delivered' => true,
                ],
                'http_status' => 200,
                'ceremony_id' => $ceremony->id,
                'queue_item_id' => $next->id,
                'version' => $nextVersion,
                'presentation' => $this->stagePayload->presentation(
                    $next,
                    $nextVersion,
                    $presentedEvent->id,
                ),
                'clear_payload' => null,
            ];
        }, attempts: 3);

        if ($committed['ceremony_id'] !== null && $committed['version'] !== null) {
            try {
                if ($committed['presentation'] !== null) {
                    StudentPresented::dispatch(
                        $committed['ceremony_id'],
                        $committed['presentation'],
                    );
                } elseif ($committed['clear_payload'] !== null) {
                    StagePresentationCleared::dispatch(
                        $committed['ceremony_id'],
                        $committed['clear_payload'],
                    );
                }

                QueueUpdated::dispatch(
                    $committed['ceremony_id'],
                    $committed['version'],
                    $committed['presentation'] === null
                        ? 'student_completed'
                        : 'student_completed_and_next_presented',
                    $committed['queue_item_id'],
                );
            } catch (Throwable $exception) {
                $committed['data']['broadcast_delivered'] = false;
                Log::warning('Completion and next presentation committed but live delivery failed.', [
                    'ceremony_id' => $committed['ceremony_id'],
                    'queue_item_id' => $committed['queue_item_id'],
                    'exception' => $exception::class,
                ]);
            }
        }

        return [
            'data' => $committed['data'],
            'http_status' => $committed['http_status'],
        ];
    }

    /**
     * @return array{
     *     data: array{status: string, message: string},
     *     http_status: int,
     *     ceremony_id: int|null,
     *     queue_item_id: null,
     *     version: null,
     *     presentation: null,
     *     clear_payload: null
     * }
     */
    private function failure(
        string $status,
        string $message,
        ?int $ceremonyId = null,
    ): array {
        return [
            'data' => ['status' => $status, 'message' => $message],
            'http_status' => 409,
            'ceremony_id' => $ceremonyId,
            'queue_item_id' => null,
            'version' => null,
            'presentation' => null,
            'clear_payload' => null,
        ];
    }
}
