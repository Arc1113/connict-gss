<?php

namespace App\Services;

use App\Models\AnnouncementTemplate;
use App\Models\Ceremony;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ActivateAnnouncementTemplate
{
    public function activate(
        AnnouncementTemplate $template,
        ?User $actor = null,
    ): AnnouncementTemplate {
        return DB::transaction(function () use ($template, $actor): AnnouncementTemplate {
            Ceremony::query()->lockForUpdate()->findOrFail($template->ceremony_id);

            $locked = AnnouncementTemplate::query()
                ->where('ceremony_id', $template->ceremony_id)
                ->lockForUpdate()
                ->findOrFail($template->id);

            AnnouncementTemplate::query()
                ->where('ceremony_id', $locked->ceremony_id)
                ->where('is_active', true)
                ->whereKeyNot($locked->id)
                ->update(['is_active' => false]);

            $locked->is_active = true;
            $locked->updated_by = $actor?->id;
            $locked->save();

            return $locked->refresh();
        }, 3);
    }
}
