<?php

namespace App\Services;

use App\Models\Student;

final class StudentQrTokenVersion
{
    public function fingerprint(Student $student): string
    {
        return hash_hmac(
            'sha256',
            implode('|', [
                $student->id,
                $student->qr_token,
                $student->updated_at?->format('U.u') ?? '',
            ]),
            (string) config('app.key'),
        );
    }

    public function matches(Student $student, string $expected): bool
    {
        return hash_equals($this->fingerprint($student), $expected);
    }
}
