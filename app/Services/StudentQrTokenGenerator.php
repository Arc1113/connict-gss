<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Support\Str;
use RuntimeException;

final class StudentQrTokenGenerator
{
    public const PREFIX = 'GSS-';

    public const RANDOM_LENGTH = 48;

    public function generate(): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $token = self::PREFIX.Str::upper(Str::random(self::RANDOM_LENGTH));

            if (! Student::query()->where('qr_token', $token)->exists()) {
                return $token;
            }
        }

        throw new RuntimeException('Unable to generate a unique graduate token.');
    }
}
