<?php

namespace App\Actions;

use App\Models\AnnouncementTemplate;
use App\Models\Ceremony;
use App\Models\User;
use App\Services\CancelAnnouncementBatchesForSettingsChange;
use App\Services\InvalidateAnnouncementCandidates;
use App\Support\AdministrativeAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class VersionAnnouncementTemplate
{
    public function __construct(
        private readonly InvalidateAnnouncementCandidates $invalidator,
        private readonly CancelAnnouncementBatchesForSettingsChange $batchCanceller,
        private readonly AdministrativeAuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function publish(
        Ceremony $ceremony,
        array $attributes,
        User $actor,
        ?string $ipAddress,
    ): AnnouncementTemplate {
        Gate::forUser($actor)->authorize('manage-announcement-settings');

        return DB::transaction(function () use ($ceremony, $attributes, $actor, $ipAddress): AnnouncementTemplate {
            $lockedCeremony = Ceremony::query()->lockForUpdate()->findOrFail($ceremony->id);
            $current = AnnouncementTemplate::query()
                ->whereBelongsTo($lockedCeremony)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();
            $version = (int) AnnouncementTemplate::query()
                ->whereBelongsTo($lockedCeremony)
                ->max('version') + 1;

            AnnouncementTemplate::query()
                ->whereBelongsTo($lockedCeremony)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $template = AnnouncementTemplate::query()->create([
                ...$attributes,
                'ceremony_id' => $lockedCeremony->id,
                'version' => max(1, $version),
                'is_active' => true,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->invalidator->forCeremony($lockedCeremony->id, $template->id);
            $this->batchCanceller->forCeremony($lockedCeremony->id);
            $this->auditLogger->record(
                $actor,
                'announcement_template.published',
                'announcement_template',
                $template->id,
                $current === null ? null : [
                    'template_id' => $current->id,
                    'version' => $current->version,
                    'voice' => $current->default_voice,
                ],
                [
                    'ceremony_id' => $lockedCeremony->id,
                    'version' => $template->version,
                    'voice' => $template->default_voice,
                    'locale' => $template->default_locale,
                    'speaking_rate_percent' => $template->speaking_rate_percent,
                    'pause_after_name_ms' => $template->pause_after_name_ms,
                    'pause_between_sections_ms' => $template->pause_between_sections_ms,
                ],
                $ipAddress,
            );

            return $template;
        }, 3);
    }
}
