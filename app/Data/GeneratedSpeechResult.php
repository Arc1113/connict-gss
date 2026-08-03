<?php

namespace App\Data;

final readonly class GeneratedSpeechResult
{
    public function __construct(
        public string $audioBytes,
        public string $contentType,
        public ?string $providerRequestId,
        public int $attemptCount,
    ) {}

    public function size(): int
    {
        return strlen($this->audioBytes);
    }
}
