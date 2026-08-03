<?php

namespace App\Exceptions;

use App\Models\AnnouncementGeneration;
use RuntimeException;

final class AnnouncementCandidateGenerationException extends RuntimeException
{
    public function __construct(
        public readonly string $safeCode,
        string $safeMessage,
        public readonly ?AnnouncementGeneration $generation = null,
    ) {
        parent::__construct($safeMessage);
    }
}
