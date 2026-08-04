<?php

namespace App\Observers;

use App\Models\AnnouncementTemplate;
use App\Services\InvalidateAnnouncementCandidates;

final class AnnouncementTemplateSourceObserver
{
    private const SOURCE_FIELDS = [
        'introduction_text',
        'include_program',
        'include_major',
        'include_honors',
        'include_awards',
        'include_additional_text',
        'awards_lead_in',
        'closing_text',
        'default_voice',
        'default_locale',
        'speaking_rate_percent',
        'pause_after_name_ms',
        'pause_between_sections_ms',
        'recommended_character_limit',
        'maximum_character_limit',
        'version',
        'is_active',
    ];

    public function updated(AnnouncementTemplate $template): void
    {
        if ($template->wasChanged(self::SOURCE_FIELDS)) {
            $invalidator = app(InvalidateAnnouncementCandidates::class);
            $invalidator->forTemplate($template);

            if ($template->wasChanged('is_active') && $template->is_active) {
                $invalidator->forCeremony($template->ceremony_id, $template->id);
            }
        }
    }
}
