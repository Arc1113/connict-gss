<?php

namespace App\Services;

use App\Models\AnnouncementTemplate;
use App\Models\AnnouncementVoiceSample;
use App\Models\Ceremony;

final class CeremonyAnnouncementSettingsWorkspace
{
    public function __construct(
        private readonly PreviewFictionalAnnouncement $previewer,
        private readonly SerializeAnnouncementPreview $serializer,
        private readonly ApprovedAnnouncementVoices $voices,
        private readonly TextToSpeechProviderHealth $providerHealth,
        private readonly AnnouncementUsage $usage,
    ) {}

    /** @return array<string, mixed> */
    public function forCeremony(Ceremony $ceremony): array
    {
        $active = AnnouncementTemplate::query()
            ->whereBelongsTo($ceremony)
            ->where('is_active', true)
            ->first();
        $editable = $active ?? new AnnouncementTemplate($this->defaults());

        return [
            'ceremony' => [
                'id' => $ceremony->id,
                'name' => $ceremony->name,
            ],
            'settings' => $this->serializeTemplate($editable),
            'next_version' => (int) AnnouncementTemplate::query()
                ->whereBelongsTo($ceremony)
                ->max('version') + 1,
            'history' => AnnouncementTemplate::query()
                ->whereBelongsTo($ceremony)
                ->withCount('generations')
                ->orderByDesc('version')
                ->get()
                ->map(fn (AnnouncementTemplate $template): array => [
                    ...$this->serializeTemplate($template),
                    'generations_count' => $template->generations_count,
                    'created_at' => $template->created_at?->toIso8601String(),
                ]),
            'voices' => collect($this->voices->all())
                ->map(fn (string $locale, string $voice): array => [
                    'value' => $voice,
                    'locale' => $locale,
                    'label' => $voice,
                ])->values(),
            'limits' => [
                'speaking_rate_minimum' => $this->voices->minimumSpeakingRatePercent(),
                'speaking_rate_maximum' => $this->voices->maximumSpeakingRatePercent(),
                'pause_minimum' => $this->voices->minimumPauseMilliseconds(),
                'pause_maximum' => $this->voices->maximumPauseMilliseconds(),
            ],
            'fictional_preview' => $this->serializer->serialize(
                $this->previewer->preview($editable),
            ),
            'provider' => $this->providerHealth->offline(),
            'usage' => $this->serializeUsage(),
            'samples' => AnnouncementVoiceSample::query()
                ->whereBelongsTo($ceremony)
                ->where('expires_at', '>', now())
                ->with(['template:id,name,version', 'generatingUser:id,name'])
                ->latest('id')
                ->limit(12)
                ->get()
                ->map(fn (AnnouncementVoiceSample $sample): array => [
                    'id' => $sample->id,
                    'voice' => $sample->provider_voice,
                    'locale' => $sample->locale,
                    'character_count' => $sample->character_count,
                    'template' => [
                        'name' => $sample->template->name,
                        'version' => $sample->template->version,
                    ],
                    'generated_by' => $sample->generatingUser?->name,
                    'created_at' => $sample->created_at?->toIso8601String(),
                    'expires_at' => $sample->expires_at->toIso8601String(),
                    'audio_url' => route(
                        'admin.ceremonies.announcement-settings.voice-comparison.show',
                        [$ceremony, $sample],
                    ),
                ]),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeTemplate(AnnouncementTemplate $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name ?? 'Ceremony announcement',
            'version' => (int) ($template->version ?? 0),
            'is_active' => (bool) ($template->is_active ?? false),
            'introduction_text' => $template->introduction_text,
            'include_program' => $template->include_program,
            'include_major' => $template->include_major,
            'include_honors' => $template->include_honors,
            'include_awards' => $template->include_awards,
            'include_additional_text' => $template->include_additional_text,
            'awards_lead_in' => $template->awards_lead_in,
            'closing_text' => $template->closing_text,
            'default_voice' => $template->default_voice,
            'default_locale' => $template->default_locale,
            'speaking_rate_percent' => $template->speaking_rate_percent,
            'pause_after_name_ms' => $template->pause_after_name_ms,
            'pause_between_sections_ms' => $template->pause_between_sections_ms,
            'recommended_character_limit' => $template->recommended_character_limit,
            'maximum_character_limit' => $template->maximum_character_limit,
        ];
    }

    /** @return array<string, mixed> */
    private function defaults(): array
    {
        return [
            'name' => 'Ceremony announcement',
            'introduction_text' => 'We proudly present',
            'include_program' => true,
            'include_major' => true,
            'include_honors' => true,
            'include_awards' => true,
            'include_additional_text' => false,
            'awards_lead_in' => 'recipient of',
            'closing_text' => null,
            'default_voice' => 'en-PH-JamesNeural',
            'default_locale' => 'en-PH',
            'speaking_rate_percent' => -5,
            'pause_after_name_ms' => 250,
            'pause_between_sections_ms' => 160,
            'recommended_character_limit' => 450,
            'maximum_character_limit' => 700,
            'version' => 0,
            'is_active' => false,
        ];
    }

    /** @return array<string, mixed> */
    private function serializeUsage(): array
    {
        $usage = $this->usage->forMonth();

        return [
            'successful_characters' => $usage->successfulCharacters,
            'monthly_budget' => $usage->monthlyBudget,
            'estimated_remaining' => $usage->estimatedRemaining,
            'warning' => $usage->warning,
            'label' => $usage->label,
        ];
    }
}
