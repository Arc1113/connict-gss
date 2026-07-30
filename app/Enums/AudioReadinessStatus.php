<?php

namespace App\Enums;

enum AudioReadinessStatus: string
{
    case Missing = 'missing';
    case Uploaded = 'uploaded';
    case NeedsReview = 'needs_review';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Missing => 'Missing',
            self::Uploaded => 'Uploaded',
            self::NeedsReview => 'Needs review',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }

    public function stageLabel(): string
    {
        return match ($this) {
            self::Approved => 'Approved audio ready',
            self::Missing => 'Live announcer required',
            self::Rejected => 'Audio rejected - use live announcer',
            self::Uploaded,
            self::NeedsReview => 'Audio awaiting review - use live announcer',
        };
    }
}
