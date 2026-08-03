<?php

namespace App\Enums;

enum AnnouncementGenerationStatus: string
{
    case Pending = 'pending';
    case Generating = 'generating';
    case NeedsReview = 'needs_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Failed = 'failed';
    case Outdated = 'outdated';
    case Cancelled = 'cancelled';
}
