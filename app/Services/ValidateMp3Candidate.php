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
        if (! in_array($normalizedContentType, ['audio/mpeg', 'audio/mp3'], true)
            || $size === 0
            || $size > $maximumBytes
            || ! $this->containsConsecutiveAudioFrames($bytes)) {
            throw new SpeechSynthesisException(
                'candidate_validation_failed',
                'Azure Speech returned an invalid or unsupported audio candidate.',
                $attemptCount,
                200,
            );
        }
    }

    private function containsConsecutiveAudioFrames(string $bytes): bool
    {
        $size = strlen($bytes);
        $audioStart = $this->audioStart($bytes);

        if ($audioStart === null || $audioStart > $size - 4) {
            return false;
        }

        $scanLimit = min($size - 4, $audioStart + 4096);

        for ($offset = $audioStart; $offset <= $scanLimit; $offset++) {
            $frameLength = $this->frameLength($bytes, $offset);

            if ($frameLength === null || $offset + $frameLength > $size) {
                continue;
            }

            $nextOffset = $offset + $frameLength;

            if ($nextOffset === $size || $this->frameLength($bytes, $nextOffset) !== null) {
                return true;
            }
        }

        return false;
    }

    private function audioStart(string $bytes): ?int
    {
        if (! str_starts_with($bytes, 'ID3')) {
            return 0;
        }

        if (strlen($bytes) < 10) {
            return null;
        }

        $tagSize = 0;

        for ($index = 6; $index <= 9; $index++) {
            $byte = ord($bytes[$index]);

            if (($byte & 0x80) !== 0) {
                return null;
            }

            $tagSize = ($tagSize << 7) | $byte;
        }

        $hasFooter = (ord($bytes[5]) & 0x10) !== 0;

        return 10 + $tagSize + ($hasFooter ? 10 : 0);
    }

    private function frameLength(string $bytes, int $offset): ?int
    {
        if ($offset < 0 || $offset + 4 > strlen($bytes)) {
            return null;
        }

        $first = ord($bytes[$offset]);
        $second = ord($bytes[$offset + 1]);
        $third = ord($bytes[$offset + 2]);

        if ($first !== 0xFF || ($second & 0xE0) !== 0xE0) {
            return null;
        }

        $version = ($second >> 3) & 0x03;
        $layer = ($second >> 1) & 0x03;
        $bitrateIndex = ($third >> 4) & 0x0F;
        $sampleRateIndex = ($third >> 2) & 0x03;

        if ($version === 0x01 || $layer !== 0x01
            || $bitrateIndex === 0 || $bitrateIndex === 0x0F
            || $sampleRateIndex === 0x03) {
            return null;
        }

        $mpegOneBitrates = [0, 32, 40, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320];
        $mpegTwoBitrates = [0, 8, 16, 24, 32, 40, 48, 56, 64, 80, 96, 112, 128, 144, 160];
        $sampleRates = match ($version) {
            0x03 => [44100, 48000, 32000],
            0x02 => [22050, 24000, 16000],
            default => [11025, 12000, 8000],
        };
        $bitrate = ($version === 0x03 ? $mpegOneBitrates : $mpegTwoBitrates)[$bitrateIndex];
        $coefficient = $version === 0x03 ? 144 : 72;
        $padding = ($third >> 1) & 0x01;

        return (int) floor(($coefficient * $bitrate * 1000) / $sampleRates[$sampleRateIndex]) + $padding;
    }
}
