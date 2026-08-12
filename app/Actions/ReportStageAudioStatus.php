<?php

namespace App\Actions;

use App\Enums\PresentationMode;
use App\Enums\StageAudioStatus;
use App\Enums\StageDisplayMode;
use App\Events\StageAudioStatusChanged;
use App\Models\StageEvent;
use App\Models\StageQueueItem;
use App\Models\StageState;
use App\Models\User;
use App\Support\ActiveCeremonyResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class ReportStageAudioStatus
{
    public function __construct(
        private ActiveCeremonyResolver $ceremonies,
    ) {}

    /** @return array{data: array<string, mixed>, http_status: int} */
    public function execute(
        int $version,
        int $studentId,
        StageAudioStatus $status,
        User $displayOperator,
    ): array {
        $result = DB::transaction(function () use (
            $version,
            $studentId,
            $status,
            $displayOperator,
        ): array {
            $resolution = $this->ceremonies->resolve(lockForUpdate: true);

            if (! $resolution->isResolved()) {
                return ['status' => 'ceremony_inactive'];
            }

            $state = StageState::query()
                ->whereBelongsTo($resolution->ceremony)
                ->lockForUpdate()
                ->first();

            if ($state === null
                || $state->version !== $version
                || $state->display_mode !== StageDisplayMode::Graduate
                || $state->current_queue_item_id === null) {
                return ['status' => 'stale_presentation'];
            }

            $item = StageQueueItem::query()
                ->whereBelongsTo($resolution->ceremony)
                ->whereKey($state->current_queue_item_id)
                ->where('student_id', $studentId)
                ->first();

            if ($item === null) {
                return ['status' => 'stale_presentation'];
            }

            $changed = $state->audio_status !== $status;
            $state->audio_status = $status;
            $state->save();

            if ($changed && $status === StageAudioStatus::Failed) {
                StageEvent::query()->create([
                    'ceremony_id' => $resolution->ceremony->id,
                    'queue_item_id' => $item->id,
                    'event_type' => 'announcement_playback_failed',
                    'payload' => [
                        'version' => $version,
                        'student_id' => $studentId,
                    ],
                    'performed_by' => $displayOperator->id,
                    'created_at' => now(),
                ]);

                if ($resolution->ceremony->presentation_mode === PresentationMode::FullyAutomatic) {
                    $state->automatic_paused = true;
                    $state->save();
                    StageEvent::query()->create([
                        'ceremony_id' => $resolution->ceremony->id,
                        'queue_item_id' => $item->id,
                        'event_type' => 'automatic_mode_paused',
                        'payload' => [
                            'version' => $version,
                            'reason' => 'announcement_playback_failed',
                        ],
                        'performed_by' => $displayOperator->id,
                        'created_at' => now(),
                    ]);
                }
            }

            return [
                'status' => 'recorded',
                'ceremony_id' => $resolution->ceremony->id,
                'version' => $version,
                'student_id' => $studentId,
                'audio_status' => $status->value,
            ];
        }, 3);

        if ($result['status'] !== 'recorded') {
            return [
                'data' => [
                    'status' => $result['status'],
                    'message' => $result['status'] === 'ceremony_inactive'
                        ? 'Exactly one active ceremony is required.'
                        : 'The audio report belongs to an older presentation.',
                ],
                'http_status' => 409,
            ];
        }

        try {
            StageAudioStatusChanged::dispatch(
                $result['ceremony_id'],
                $result['version'],
                $result['student_id'],
                $result['audio_status'],
            );
        } catch (Throwable $exception) {
            Log::warning('Stage audio status broadcast failed after the report was committed.', [
                'ceremony_id' => $result['ceremony_id'],
                'version' => $result['version'],
                'exception' => $exception::class,
            ]);
        }

        return [
            'data' => [
                'status' => 'recorded',
                'audio_status' => $result['audio_status'],
            ],
            'http_status' => 200,
        ];
    }
}
