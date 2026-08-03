<?php

namespace App\Exceptions;

use RuntimeException;

final class SpeechSynthesisException extends RuntimeException
{
    public function __construct(
        public readonly string $safeCode,
        string $safeMessage,
        public readonly int $attemptCount = 0,
        public readonly ?int $httpStatus = null,
    ) {
        parent::__construct($safeMessage);
    }
}
