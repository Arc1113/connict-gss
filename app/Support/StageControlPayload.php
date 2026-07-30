<?php

namespace App\Support;

use App\Enums\StageDisplayMode;
use App\Enums\StageQueueStatus;
use App\Models\Ceremony;
use App\Models\StageEvent;
use App\Models\StageQueueItem;
use App\Models\StageState;

final class StageControlPayload
{
    public function __construct(
        private readonly AnnouncementAudio $announcementAudio,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forCeremony(Ceremony $ceremony): array
    {
        $state = StageState::query()
            ->whereBelongsTo($ceremony)
            ->first();

        $waiting = StageQueueItem::query()
            ->with('student')
            ->whereBelongsTo($ceremony)
            ->whereIn('status', [
                StageQueueStatus::Waiting,
                StageQueueStatus::Ready,
            ])
            ->orderBy('queue_position')
            ->orderBy('scanned_at')
            ->orderBy('id')
            ->get()
            ->map(fn (StageQueueItem $item): array => $this->queueItem($item))
            ->values()
            ->all();

        $skipped = StageQueueItem::query()
            ->with('student')
            ->whereBelongsTo($ceremony)
            ->where('status', StageQueueStatus::Skipped)
            ->latest('updated_at')
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (StageQueueItem $item): array => $this->queueItem($item))
            ->values()
            ->all();

        $current = null;

        if ($state?->current_queue_item_id !== null) {
            $item = StageQueueItem::query()
                ->with('student')
                ->whereBelongsTo($ceremony)
                ->find($state->current_queue_item_id);

            if ($item !== null) {
                $current = $this->queueItem($item);
            }
        }

        $recent = StageEvent::query()
            ->with('queueItem.student')
            ->whereBelongsTo($ceremony)
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (StageEvent $event): array => [
                'id' => $event->id,
                'type' => $event->event_type,
                'graduate' => $event->queueItem?->student?->display_name,
                'occurred_at' => $event->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return [
            'status' => 'ready',
            'ceremony' => [
                'id' => $ceremony->id,
                'name' => $ceremony->name,
                'presentation_mode' => $ceremony->presentation_mode->value,
            ],
            'control' => [
                'version' => $state?->version ?? 0,
                'automatic_paused' => $state?->automatic_paused ?? false,
                'display_mode' => $state?->display_mode->value
                    ?? StageDisplayMode::Idle->value,
                'current' => $current,
                'waiting' => $waiting,
                'skipped' => $skipped,
                'recent_actions' => $recent,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function queueItem(StageQueueItem $item): array
    {
        return [
            'id' => $item->id,
            'status' => $item->status->value,
            'queue_position' => $item->queue_position,
            'scanned_at' => $item->scanned_at?->toIso8601String(),
            'presented_at' => $item->presented_at?->toIso8601String(),
            'student' => [
                'id' => $item->student->id,
                'display_name' => $item->student->display_name,
                'program' => $item->student->program,
                'major' => $item->student->major,
                'honors' => $item->student->honors,
                'has_audio' => $this->announcementAudio->isApprovedAndReadable($item->student),
                'audio_status' => [
                    'value' => $item->student->audio_readiness_status->value,
                    'label' => $item->student->audio_readiness_status->label(),
                    'stage_label' => $item->student->audio_readiness_status->stageLabel(),
                ],
            ],
        ];
    }
}
