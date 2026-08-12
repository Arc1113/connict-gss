<?php

namespace App\Services;

use App\Enums\EligibilityStatus;
use App\Models\Student;

final class AnnouncementCourseOpener
{
    public function isOpener(Student $student): bool
    {
        $programKey = $this->key($student->program);

        if ($programKey === '') {
            return true;
        }

        return ! Student::query()
            ->where('ceremony_id', $student->ceremony_id)
            ->where('eligibility_status', EligibilityStatus::Eligible->value)
            ->where('id', '<>', $student->getKey())
            ->get(['id', 'program', 'sequence_number'])
            ->contains(function (Student $candidate) use ($programKey, $student): bool {
                if ($this->key($candidate->program) !== $programKey) {
                    return false;
                }

                return [$candidate->sequence_number ?? PHP_INT_MAX, $candidate->id]
                    < [$student->sequence_number ?? PHP_INT_MAX, $student->id];
            });
    }

    private function key(?string $program): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $program ?? '') ?? ''));
    }
}
