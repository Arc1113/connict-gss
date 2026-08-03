<?php

namespace App\Services;

use App\Exceptions\SpeechSynthesisException;

final class ValidateMp3Candidate
{
    public function validate(
        string $bytes,
        string $contentType,
        int $maximumBytes,
        int $attemptCount = 0,
    ): void {
        $normalizedContentType = strtolower(trim(explode(';', $contentType)[0]));
        $size = strlen($bytes);
        $hasId3 = str_starts_with($bytes, 'ID3');
        $hasFrameSync = $size >= 2
            && ord($bytes[0]) === 0xFF
            && (ord($bytes[1]) & 0xE0) === 0xE0;

        if (! in_array($normalizedContentType, ['audio/mpeg', 'audio/mp3'], true)
            || $size === 0
            || $size > $maximumBytes
            || (! $hasId3 && ! $hasFrameSync)) {
            throw new SpeechSynthesisException(
                'candidate_validation_failed',
                'Azure Speech returned an invalid or unsupported audio candidate.',
                $attemptCount,
                200,
            );
        }
    }
}
