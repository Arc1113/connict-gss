<?php

namespace App\Services;

use Normalizer;

final class NormalizeAnnouncementText
{
    public function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = preg_replace('/\s+/u', ' ', trim($value));

        return $normalized === null || $normalized === '' ? null : $normalized;
    }

    public function comparisonKey(?string $value): string
    {
        $normalized = $this->normalize($value);

        if ($normalized === null) {
            return '';
        }

        $unicode = Normalizer::normalize($normalized, Normalizer::FORM_KC);
        $folded = mb_strtolower($unicode === false ? $normalized : $unicode, 'UTF-8');
        $withoutPunctuation = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $folded);

        return trim((string) preg_replace('/\s+/u', ' ', (string) $withoutPunctuation));
    }

    public function isSameOrContainedPhrase(?string $first, ?string $second): bool
    {
        $firstKey = $this->comparisonKey($first);
        $secondKey = $this->comparisonKey($second);

        if ($firstKey === '' || $secondKey === '') {
            return false;
        }

        if ($firstKey === $secondKey) {
            return true;
        }

        return str_contains(" {$firstKey} ", " {$secondKey} ")
            || str_contains(" {$secondKey} ", " {$firstKey} ");
    }

    public function containsKnownSsmlMarkup(?string $value): bool
    {
        $normalized = $this->normalize($value);

        if ($normalized === null) {
            return false;
        }

        return preg_match(
            '/<\s*\/?\s*(?:speak|voice|prosody|break|phoneme|sub|say-as|audio|mstts:[\p{L}\p{N}_-]+)\b[^>]*>/iu',
            $normalized,
        ) === 1;
    }

    public function withoutTrailingClausePunctuation(string $value): string
    {
        return (string) preg_replace('/[\s,;:.…。]+$/u', '', $value);
    }
}
