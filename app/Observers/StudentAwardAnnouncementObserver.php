<?php

namespace App\Observers;

use App\Models\StudentAward;
use App\Services\InvalidateAnnouncementCandidates;

final class StudentAwardAnnouncementObserver
{
    public function saved(StudentAward $award): void
    {
        app(InvalidateAnnouncementCandidates::class)->forStudent($award->student_id);
    }

    public function deleted(StudentAward $award): void
    {
        app(InvalidateAnnouncementCandidates::class)->forStudent($award->student_id);
    }
}
