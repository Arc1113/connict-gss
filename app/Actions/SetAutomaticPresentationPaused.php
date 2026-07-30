<?php

namespace App\Actions;

use App\Enums\PresentationMode;
use App\Enums\StageAudioStatus;
use App\Enums\StageDisplayMode;
use App\Events\QueueUpdated;
use App\Models\StageEvent;
use App\Models\StageState;
use App\Models\User;
use App\Support\ActiveCeremonyResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class SetAutomaticPresentationPaused
{
    public function __construct(
        private ActiveCeremonyResolver $ceremonies,
    ) {}

    /**
     * @return array{data: array<string, mixed>, http_status: int}
     */
    public function execute(bool $paused, User $operator): array
    {
        $committed = DB::transaction(function () use ($paused, $operator): array {
            $resolution = $this->ceremonies->resolve(lockForUpdate: true);

            if (! $resolution->isResolved()) {
                return ['status' => 'ceremony_inactive'];
            }

            $ceremony = $resolution->ceremony;

            if ($ceremony->presentation_mode !== PresentationMode::Automatic) {
                return ['status' => 'controlled_mode'];
            }

            $state = StageState::query()
                ->whereBelongsTo($ceremony)
                ->lockForUpdate()
                ->first();

            if ($state === null) {
                $state = StageState::query()->create([
                    'ceremony_id' => $ceremony->id,
                    'current_queue_item_id' => null,
                    'display_mode' => StageDisplayMode::Idle,
                    'audio_status' => StageAudioStatus::Stopped,
                    'is_muted' => false,
                    'automatic_paused' => false,
                    'version' => 0,
                ]);
            }

            if ($state->automatic_paused === $paused) {
                return [
                    'status' => $paused ? 'already_paused' : 'already_resumed',
                ];
            }

            $state->fill([
                'automatic_paused' => $paused,
                'version' => $state->version + 1,
            ])->save();

            StageEvent::query()->create([
                'ceremony_id' => $ceremony->id,
                'queue_item_id' => null,
                'event_type' => $paused
                    ? 'automatic_mode_paused'
                    : 'automatic_mode_resumed',
                'payload' => [
                    'version' => $state->version,
                    'automatic_paused' => $paused,
                ],
                'performed_by' => $operator->id,
                'created_at' => now(),
            ]);

            return [
                'status' => $paused ? 'automatic_paused' : 'automatic_resumed',
                'ceremony_id' => $ceremony->id,
                'version' => $state->version,
            ];
        }, attempts: 3);

        if (! in_array(
            $committed['status'],
            ['automatic_paused', 'automatic_resumed'],
            true,
        )) {
            $messages = [
                'ceremony_inactive' => 'Exactly one active ceremony is required.',
                'controlled_mode' => 'Automatic controls are unavailable in controlled mode.',
                'already_paused' => 'Automatic presentation is already paused.',
                'already_resumed' => 'Automatic presentation is already active.',
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
            QueueUpdated::dispatch(
                $committed['ceremony_id'],
                $committed['version'],
                $committed['status'],
            );
        } catch (Throwable $exception) {
            $delivered = false;
            Log::warning('Automatic pause state committed but live delivery failed.', [
                'ceremony_id' => $committed['ceremony_id'],
                'exception' => $exception::class,
            ]);
        }

        return [
            'data' => [
                'status' => $committed['status'],
                'message' => $committed['status'] === 'automatic_paused'
                    ? 'Automatic presentation paused. New scans will wait.'
                    : 'Automatic presentation resumed for the next eligible scan while idle.',
                'stage_version' => $committed['version'],
                'broadcast_delivered' => $delivered,
            ],
            'http_status' => 200,
        ];
    }
}
