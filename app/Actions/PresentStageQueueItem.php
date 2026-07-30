<?php

namespace App\Actions;

use App\Enums\StageAudioStatus;
use App\Enums\StageDisplayMode;
use App\Enums\StageQueueStatus;
use App\Events\QueueUpdated;
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

final readonly class PresentStageQueueItem
{
    public function __construct(
        private ActiveCeremonyResolver $ceremonies,
        private StageQueueTransitions $transitions,
        private StageStatePayload $stagePayload,
    ) {}

    /**
     * @return array{data: array<string, mixed>, http_status: int}
     */
    public function next(User $operator): array
    {
        return $this->execute(null, $operator, 'present_next');
    }

    /**
     * @return array{data: array<string, mixed>, http_status: int}
     */
    public function selected(int $queueItemId, User $operator): array
    {
        return $this->execute($queueItemId, $operator, 'present_selected');
    }

    /**
     * @return array{data: array<string, mixed>, http_status: int}
     */
    private function execute(
        ?int $queueItemId,
        User $operator,
        string $action,
    ): array {
        /**
         * @var array{
         *     data: array<string, mixed>,
         *     http_status: int,
         *     ceremony_id: int|null,
         *     queue_item_id: int|null,
         *     version: int|null,
         *     presentation: array<string, mixed>|null
         * } $committed
         */
        $committed = DB::transaction(function () use (
            $queueItemId,
            $operator,
            $action,
        ): array {
            $resolution = $this->ceremonies->resolve(lockForUpdate: true);

            if (! $resolution->isResolved()) {
                return $this->failure(
                    'ceremony_inactive',
                    'Exactly one active ceremony is required.',
                );
            }

            $ceremony = $resolution->ceremony;
            $stageState = StageState::query()
                ->whereBelongsTo($ceremony)
                ->lockForUpdate()
                ->first();

            if ($stageState === null) {
                $stageState = StageState::query()->create([
                    'ceremony_id' => $ceremony->id,
                    'current_queue_item_id' => null,
                    'display_mode' => StageDisplayMode::Idle,
                    'audio_status' => StageAudioStatus::Stopped,
                    'is_muted' => false,
                    'automatic_paused' => false,
                    'version' => 0,
                ]);
            }

            if (
                $stageState->current_queue_item_id !== null
                || $stageState->display_mode !== StageDisplayMode::Idle
            ) {
                return $this->failure(
                    'stage_busy',
                    $stageState->display_mode === StageDisplayMode::Holding
                        ? 'Restore the audience display before presenting a graduate.'
                        : 'Complete the current graduate before presenting another.',
                    $ceremony->id,
                );
            }

            $query = StageQueueItem::query()
                ->whereBelongsTo($ceremony)
                ->whereIn('status', [
                    StageQueueStatus::Waiting,
                    StageQueueStatus::Ready,
                ])
                ->lockForUpdate();

            $queueItem = $queueItemId === null
                ? $query
                    ->orderBy('queue_position')
                    ->orderBy('scanned_at')
                    ->orderBy('id')
                    ->first()
                : $query->whereKey($queueItemId)->first();

            if ($queueItem === null) {
                return $this->failure(
                    $queueItemId === null ? 'queue_empty' : 'queue_item_unavailable',
                    $queueItemId === null
                        ? 'No graduate is waiting in the queue.'
                        : 'The selected graduate is no longer available to present.',
                    $ceremony->id,
                );
            }

            $presentedAt = now();
            $this->transitions->transition(
                $queueItem,
                StageQueueStatus::Presenting,
            );
            $queueItem->fill([
                'presented_at' => $presentedAt,
                'presented_by' => $operator->id,
            ])->save();

            $stageState->fill([
                'current_queue_item_id' => $queueItem->id,
                'display_mode' => StageDisplayMode::Graduate,
                'audio_status' => StageAudioStatus::Stopped,
                'version' => $stageState->version + 1,
            ])->save();

            $event = StageEvent::query()->create([
                'ceremony_id' => $ceremony->id,
                'queue_item_id' => $queueItem->id,
                'event_type' => $action,
                'payload' => [
                    'version' => $stageState->version,
                    'student_id' => $queueItem->student_id,
                ],
                'performed_by' => $operator->id,
                'created_at' => $presentedAt,
            ]);

            $queueItem->load('student');
            $presentation = $this->stagePayload->presentation(
                $queueItem,
                $stageState->version,
                $event->id,
            );

            return [
                'data' => [
                    'status' => 'presented',
                    'message' => $action === 'present_next'
                        ? 'Next graduate presented.'
                        : 'Selected graduate presented.',
                    'stage_version' => $stageState->version,
                    'broadcast_delivered' => true,
                ],
                'http_status' => 200,
                'ceremony_id' => $ceremony->id,
                'queue_item_id' => $queueItem->id,
                'version' => $stageState->version,
                'presentation' => $presentation,
            ];
        }, attempts: 3);

        if ($committed['presentation'] !== null) {
            try {
                StudentPresented::dispatch(
                    $committed['ceremony_id'],
                    $committed['presentation'],
                );
                QueueUpdated::dispatch(
                    $committed['ceremony_id'],
                    $committed['version'],
                    $action,
                    $committed['queue_item_id'],
                );
            } catch (Throwable $exception) {
                $committed['data']['broadcast_delivered'] = false;
                Log::warning('Presentation committed but live delivery failed.', [
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
     *     presentation: null
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
        ];
    }
}
