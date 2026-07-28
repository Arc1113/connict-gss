<?php

namespace App\Support;

use App\Enums\StageDisplayMode;
use App\Models\Ceremony;
use App\Models\StageEvent;
use App\Models\StageQueueItem;
use App\Models\StageState;

final class StageStatePayload
{
    /**
     * @return array<string, mixed>
     */
    public function forCeremony(Ceremony $ceremony): array
    {
        $state = StageState::query()
            ->where('ceremony_id', $ceremony->id)
            ->first();

        if ($state === null) {
            return $this->idle($ceremony, 0);
        }

        $presentation = null;

        if (
            $state->display_mode === StageDisplayMode::Graduate
            && $state->current_queue_item_id !== null
        ) {
            $queueItem = StageQueueItem::query()
                ->with('student')
                ->where('ceremony_id', $ceremony->id)
                ->find($state->current_queue_item_id);

            if ($queueItem !== null) {
                $eventId = StageEvent::query()
                    ->where('ceremony_id', $ceremony->id)
                    ->where('queue_item_id', $queueItem->id)
                    ->where('event_type', 'student_presented')
                    ->latest('id')
                    ->value('id');

                $presentation = $this->presentation(
                    $queueItem,
                    $state->version,
                    is_int($eventId) ? $eventId : 0,
                );
            }
        }

        return [
            'status' => 'ready',
            'ceremony' => [
                'id' => $ceremony->id,
                'name' => $ceremony->name,
            ],
            'state' => [
                'version' => $state->version,
                'mode' => $presentation === null ? 'idle' : 'graduate',
                'presentation' => $presentation,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function idle(Ceremony $ceremony, int $version): array
    {
        return [
            'status' => 'ready',
            'ceremony' => [
                'id' => $ceremony->id,
                'name' => $ceremony->name,
            ],
            'state' => [
                'version' => $version,
                'mode' => 'idle',
                'presentation' => null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentation(
        StageQueueItem $queueItem,
        int $version,
        int $eventId,
    ): array {
        $student = $queueItem->relationLoaded('student')
            ? $queueItem->student
            : $queueItem->student()->firstOrFail();

        return [
            'event_id' => $eventId,
            'version' => $version,
            'student_id' => $student->id,
            'display_name' => $student->display_name,
            'program' => $student->program,
            'major' => $student->major,
            'honors' => $student->honors,
            'photo_url' => $this->localMediaUrl($student->photo_path),
            'audio_url' => $this->localMediaUrl($student->audio_path),
            'presented_at' => $queueItem->presented_at?->toIso8601String(),
        ];
    }

    private function localMediaUrl(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        $path = ltrim(trim($path), '/');

        if (str_contains($path, '..')) {
            return null;
        }

        return '/'.$path;
    }
}
