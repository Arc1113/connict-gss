<?php

namespace App\Exceptions;

use RuntimeException;

final class CandidateAudioStorageException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The private announcement candidate could not be stored safely.');
    }
}
