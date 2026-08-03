<?php

namespace App\Contracts;

use App\Data\GeneratedSpeechResult;
use App\Data\StoredCandidateAudio;

interface CandidateAudioStore
{
    public function store(GeneratedSpeechResult $speech): StoredCandidateAudio;

    public function exists(string $path): bool;

    public function delete(string $path): void;
}
