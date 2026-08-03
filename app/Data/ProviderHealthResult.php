<?php

namespace App\Data;

final readonly class ProviderHealthResult
{
    public function __construct(
        public string $status,
        public bool $networkChecked,
    ) {}

    public function isConfigured(): bool
    {
        return in_array($this->status, ['configured', 'available'], true);
    }
}
