<?php

namespace App\Exceptions;

use RuntimeException;

final class AnnouncementBatchException extends RuntimeException
{
    public function __construct(
        public readonly string $safeCode,
        string $safeMessage,
    ) {
        parent::__construct($safeMessage);
    }
}
