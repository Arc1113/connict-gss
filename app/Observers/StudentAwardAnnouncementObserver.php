<?php

namespace App\Observers;

use App\Models\StudentAward;
use App\Services\InvalidateAnnouncementCandidates;

final class StudentAwardAnnouncementObserver
{
    public function saved(StudentAward $award): void
    {
        $this->invalidate($award);
    }

    public function deleted(StudentAward $award): void
    {
        $this->invalidate($award);
    }

    private function invalidate(StudentAward $award): void
    {
        $invalidator = app(InvalidateAnnouncementCandidates::class);
        $invalidator->forStudent($award->student_id);
        $invalidator->manualAudioForStudent(
            $award->student_id,
            'Uploaded audio needs review because the announced awards changed.',
        );
    }
}
