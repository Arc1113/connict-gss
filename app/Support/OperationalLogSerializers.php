<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\ScanLog;
use App\Models\StageEvent;

final readonly class OperationalLogSerializers
{
    public function __construct(
        private SafeOperationalText $text,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function scanLog(ScanLog $log): array
    {
        return [
            'id' => $log->id,
            'time' => $log->scanned_at?->toIso8601String(),
            'ceremony' => $log->ceremony?->name ?? 'Not resolved',
            'result' => [
                'value' => $log->result->value,
                'label' => str($log->result->value)->replace('_', ' ')->title()->toString(),
            ],
            'graduate' => $log->student?->display_name ?? 'Not resolved',
            'operator' => $log->operator?->name ?? 'Not recorded',
            'device' => $log->device?->name ?? 'Not recorded',
            'message' => $log->message,
            'queue_outcome' => $this->queueOutcome($log),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function stageEvent(StageEvent $event): array
    {
        $payload = $this->text->safeArray($event->payload);
        $student = $event->queueItem?->student;

        return [
            'id' => $event->id,
            'time' => $event->created_at?->toIso8601String(),
            'ceremony' => $event->ceremony?->name ?? 'Deleted ceremony',
            'event_type' => [
                'value' => $event->event_type,
                'label' => str($event->event_type)->replace('_', ' ')->title()->toString(),
            ],
            'graduate' => $student?->display_name ?? 'Not applicable',
            'actor' => $event->performedBy?->name ?? 'System',
            'description' => $this->stageDescription(
                $event->event_type,
                $student?->display_name,
                $payload,
            ),
            'stage_version' => $payload['version'] ?? null,
            'details' => collect($payload)
                ->only([
                    'queue_position',
                    'automatic',
                    'paused',
                    'holding',
                    'continued_to_next',
                ])
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function auditLog(AuditLog $log, bool $includeIp = false): array
    {
        return [
            'id' => $log->id,
            'action' => $log->action,
            'entity_type' => $log->entity_type,
            'entity_id' => $log->entity_id,
            'actor' => $log->user?->name ?? 'Deleted user',
            'old_values' => $this->text->safeArray($log->old_values),
            'new_values' => $this->text->safeArray($log->new_values),
            'ip_address' => $includeIp ? $log->ip_address : null,
            'created_at' => $log->created_at?->toIso8601String(),
        ];
    }

    private function queueOutcome(ScanLog $log): string
    {
        return match ($log->result->value) {
            'accepted' => 'Graduate saved to queue or presented by assisted automatic mode',
            'duplicate' => 'Graduate already had active or completed queue history',
            'invalid' => 'No matching graduate was resolved',
            'ineligible' => 'Graduate was not eligible for presentation',
            default => 'Rejected safely without stage change',
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function stageDescription(
        string $type,
        ?string $name,
        array $payload,
    ): string {
        $graduate = $name === null ? 'graduate' : $name;

        return match ($type) {
            'student_queued' => "{$graduate} was added to the stage queue.",
            'student_presented',
            'student_presented_automatically',
            'present_next',
            'present_selected',
            'present_next_after_completion' => "{$graduate} was presented on the audience stage.",
            'student_completed' => ($payload['continued_to_next'] ?? false)
                ? "{$graduate} was completed before the next graduate was presented."
                : "{$graduate} was completed and the stage was cleared.",
            'student_skipped' => "{$graduate} was skipped from the waiting queue.",
            'student_returned_to_queue' => "{$graduate} was returned to the waiting queue.",
            'current_presentation_returned' => "{$graduate} was returned to the queue without completion and the holding screen was shown.",
            'announcement_replayed' => 'The current approved announcement audio was replayed.',
            'holding_screen_activated' => 'The holding screen was activated.',
            'holding_screen_deactivated' => 'The live stage display was restored.',
            'automatic_presentation_paused' => 'Automatic presentation was paused.',
            'automatic_presentation_resumed' => 'Automatic presentation was resumed.',
            'operational_reset' => 'Current ceremony operations were reset while history was preserved.',
            default => str($type)->replace('_', ' ')->title()->toString(),
        };
    }
}
