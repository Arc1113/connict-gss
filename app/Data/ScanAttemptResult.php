<?php

namespace App\Data;

use App\Enums\ScanStatus;

final class ScanAttemptResult
{
    public bool $broadcastDelivered = true;

    /**
     * @param  array<string, mixed>|null  $presentation
     */
    public function __construct(
        public readonly ScanStatus $status,
        public readonly string $message,
        public readonly ?array $presentation = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'status' => $this->status->value,
            'message' => $this->message,
        ];

        if ($this->status === ScanStatus::Accepted && $this->presentation !== null) {
            $data['stage_version'] = $this->presentation['version'];
            $data['student'] = [
                'id' => $this->presentation['student_id'],
                'display_name' => $this->presentation['display_name'],
            ];
            $data['broadcast_delivered'] = $this->broadcastDelivered;
        }

        return $data;
    }
}
