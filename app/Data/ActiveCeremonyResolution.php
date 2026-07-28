<?php

namespace App\Data;

use App\Models\Ceremony;

final readonly class ActiveCeremonyResolution
{
    public function __construct(
        public ?Ceremony $ceremony,
        public string $status,
    ) {}

    public function isResolved(): bool
    {
        return $this->ceremony !== null;
    }
}
