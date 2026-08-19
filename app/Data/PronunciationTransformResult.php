<?php

namespace App\Data;

final readonly class PronunciationTransformResult
{
    /**
     * @param  list<array<string, mixed>>  $appliedRules
     */
    public function __construct(
        public string $text,
        public array $appliedRules,
    ) {}
}

