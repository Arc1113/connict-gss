<?php

namespace App\Data;

use App\Enums\ScanStatus;

final class ScanAttemptResult
{
    public bool $broadcastDelivered = true;

    public function __construct(
        public readonly ScanStatus $status,
        public readonly string $message,
        public readonly ?int $studentId = null,
        public readonly ?string $displayName = null,
        public readonly ?int $queuePosition = null,
        public readonly ?string $queueStatus = null,
        public readonly bool $automaticallyPresented = false,
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

        if ($this->studentId !== null) {
            $data['student'] = [
                'id' => $this->studentId,
                'display_name' => $this->displayName,
            ];
        }

        if ($this->queuePosition !== null) {
            $data['queue_position'] = $this->queuePosition;
        }

        if ($this->queueStatus !== null) {
            $data['queue_status'] = $this->queueStatus;
        }

        if ($this->status === ScanStatus::Queued) {
            $data['automatically_presented'] = $this->automaticallyPresented;
            $data['broadcast_delivered'] = $this->broadcastDelivered;
        }

        return $data;
    }
}
