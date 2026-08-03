<?php

namespace App\Services;

use App\Models\AnnouncementTemplate;
use App\Models\Ceremony;

final class ResolveActiveAnnouncementTemplate
{
    public function resolve(Ceremony $ceremony): ?AnnouncementTemplate
    {
        return AnnouncementTemplate::query()
            ->whereBelongsTo($ceremony)
            ->where('is_active', true)
            ->first();
    }
}
