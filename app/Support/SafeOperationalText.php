<?php

namespace App\Support;

final class SafeOperationalText
{
    /**
     * @var list<string>
     */
    private const BLOCKED_KEYS = [
        'app_key',
        'audio_path',
        'csv',
        'file_path',
        'media_path',
        'password',
        'qr_token',
        'raw_token',
        'raw_token_hash',
        'remember_token',
        'secret',
        'session',
        'storage_path',
        'token',
    ];

    public function csvCell(mixed $value): string
    {
        $text = $this->scalar($value);
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        if ($text !== '' && preg_match('/^[=\+\-@\t\r\n]/', $text) === 1) {
            return "'".$text;
        }

        return $text;
    }

    public function scalar(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            return collect($this->safeArray($value))
                ->map(fn (mixed $item, string|int $key): string => is_string($key)
                    ? str_replace('_', ' ', $key).': '.$this->scalar($item)
                    : $this->scalar($item))
                ->filter()
                ->implode('; ');
        }

        return '[redacted]';
    }

    /**
     * @param  array<string|int, mixed>|null  $values
     * @return array<string|int, mixed>
     */
    public function safeArray(?array $values): array
    {
        if ($values === null) {
            return [];
        }

        $safe = [];

        foreach ($values as $key => $value) {
            $normalized = strtolower((string) $key);

            if ($this->isBlockedKey($normalized)) {
                $safe[$key] = '[redacted]';

                continue;
            }

            if (is_string($value) && $this->looksLikePathOrSecret($normalized, $value)) {
                $safe[$key] = '[redacted]';

                continue;
            }

            $safe[$key] = is_array($value) ? $this->safeArray($value) : $value;
        }

        return $safe;
    }

    private function isBlockedKey(string $key): bool
    {
        foreach (self::BLOCKED_KEYS as $blocked) {
            if (str_contains($key, $blocked)) {
                return true;
            }
        }

        return false;
    }

    private function looksLikePathOrSecret(string $key, string $value): bool
    {
        if (str_contains($key, 'path') || str_contains($key, 'file')) {
            return true;
        }

        return preg_match('/(-----BEGIN|AKIA|reverb.*secret|app_key)/i', $value) === 1;
    }
}
