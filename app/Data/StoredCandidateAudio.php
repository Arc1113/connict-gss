<?php

namespace App\Data;

final readonly class StoredCandidateAudio
{
    public function __construct(
        public string $path,
        public int $size,
        public string $sha256,
    ) {}
}
