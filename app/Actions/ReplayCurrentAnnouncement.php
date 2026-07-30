<?php

namespace App\Actions;

use App\Enums\StageDisplayMode;
use App\Enums\StageQueueStatus;
use App\Events\AudioReplayRequested;
use App\Events\QueueUpdated;
use App\Models\StageEvent;
use App\Models\StageQueueItem;
use App\Models\StageState;
use App\Models\User;
use App\Support\ActiveCeremonyResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class ReplayCurrentAnnouncement
{
    public function __construct(
        private ActiveCeremonyResolver $ceremonies,
    ) {}

    /**
     * @return array{data: array<string, mixed>, http_status: int}
     */
    public function execute(User $operator): array
    {
        $committed = DB::transaction(function () use ($operator): array {
            $resolution = $this->ceremonies->resolve(lockForUpdate: true);

            if (! $resolution->isResolved()) {
                return ['status' => 'ceremony_inactive'];
            }

            $ceremony = $resolution->ceremony;
            $state = StageState::query()
                ->whereBelongsTo($ceremony)
                ->lockForUpdate()
                ->first();

            if (
                $state === null
                || $state->current_queue_item_id === null
                || $state->display_mode !== StageDisplayMode::Graduate
            ) {
                return ['status' => 'stage_idle'];
            }

            $item = StageQueueItem::query()
                ->with('student')
                ->whereBelongsTo($ceremony)
                ->lockForUpdate()
                ->find($state->current_queue_item_id);

            if (
                $item === null
                || ! in_array($item->status, [
                    StageQueueStatus::Presenting,
                    StageQueueStatus::Announced,
                ], true)
            ) {
                return ['status' => 'state_conflict'];
            }

            if (blank($item->student->audio_path)) {
                return ['status' => 'audio_missing'];
            }

            $state->version++;
            $state->save();

            $event = StageEvent::query()->create([
                'ceremony_id' => $ceremony->id,
                'queue_item_id' => $item->id,
                'event_type' => 'announcement_replayed',
                'payload' => [
                    'version' => $state->version,
                    'student_id' => $item->student_id,
                ],
                'performed_by' => $operator->id,
                'created_at' => now(),
            ]);

            return [
                'status' => 'replayed',
                'ceremony_id' => $ceremony->id,
                'queue_item_id' => $item->id,
                'student_id' => $item->student_id,
                'event_id' => $event->id,
                'version' => $state->version,
            ];
        }, attempts: 3);

        if ($committed['status'] !== 'replayed') {
            $messages = [
                'ceremony_inactive' => 'Exactly one active ceremony is required.',
                'stage_idle' => 'There is no current graduate to replay.',
                'state_conflict' => 'The persisted stage state requires operator review.',
                'audio_missing' => 'No approved local announcement is available for the current graduate.',
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
            AudioReplayRequested::dispatch(
                $committed['ceremony_id'],
                $committed['version'],
                $committed['event_id'],
                $committed['student_id'],
            );
            QueueUpdated::dispatch(
                $committed['ceremony_id'],
                $committed['version'],
                'announcement_replayed',
                $committed['queue_item_id'],
            );
        } catch (Throwable $exception) {
            $delivered = false;
            Log::warning('Audio replay committed but live delivery failed.', [
                'ceremony_id' => $committed['ceremony_id'],
                'event_id' => $committed['event_id'],
                'exception' => $exception::class,
            ]);
        }

        return [
            'data' => [
                'status' => 'replayed',
                'message' => $delivered
                    ? 'Announcement replay requested.'
                    : 'Replay was recorded, but the stage must reconnect before receiving live commands.',
                'stage_version' => $committed['version'],
                'broadcast_delivered' => $delivered,
            ],
            'http_status' => 200,
        ];
    }
}
