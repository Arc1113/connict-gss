<?php

namespace App\Observers;

use App\Models\Student;
use App\Services\InvalidateAnnouncementCandidates;

final class StudentAnnouncementSourceObserver
{
    private const SOURCE_FIELDS = [
        'ceremony_id',
        'announcement_name',
        'tts_spoken_name',
        'program',
        'major',
        'honors',
        'announcement_additional_text',
    ];

    public function updated(Student $student): void
    {
        if ($student->wasChanged(self::SOURCE_FIELDS)) {
            $invalidator = app(InvalidateAnnouncementCandidates::class);
            $invalidator->forStudent($student->id);
            $invalidator->manualAudioForStudent(
                $student->id,
                'Uploaded audio needs review because the graduate announcement wording changed.',
            );
        }
    }
}
