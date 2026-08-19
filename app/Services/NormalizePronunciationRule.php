<?php

namespace App\Services;

use Normalizer;

final class NormalizePronunciationRule
{
    public function matchText(?string $value): string
    {
        $value = $this->sourceText($value);

        return mb_strtolower($value, 'UTF-8');
    }

    public function sourceText(?string $value): string
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', (string) $value));

        if (class_exists(Normalizer::class)) {
            $value = Normalizer::normalize($value, Normalizer::FORM_C) ?: $value;
        }

        return $value;
    }
}
