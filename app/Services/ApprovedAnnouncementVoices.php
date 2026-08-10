<?php

namespace App\Services;

use App\Data\AnnouncementIssue;
use App\Enums\AnnouncementProvider;

final class ApprovedAnnouncementVoices
{
    /**
     * @return array<string, string>
     */
    public function all(AnnouncementProvider $provider = AnnouncementProvider::Azure): array
    {
        $voices = $provider === AnnouncementProvider::Azure
            ? config('announcements.voices', [])
            : config('announcements.provider_voices.'.$provider->value, []);

        return is_array($voices) ? $voices : [];
    }

    /**
     * @return list<AnnouncementIssue>
     */
    public function validationIssues(
        string $voice,
        string $locale,
        AnnouncementProvider $provider = AnnouncementProvider::Azure,
    ): array {
        $voices = $this->all($provider);

        if (! array_key_exists($voice, $voices)) {
            return [new AnnouncementIssue(
                'unsupported_voice',
                'Select a server-approved announcement voice.',
                'voice',
            )];
        }

        if ($voices[$voice] !== $locale) {
            return [new AnnouncementIssue(
                'unsupported_locale',
                'The selected locale does not match the approved voice.',
                'locale',
            )];
        }

        return [];
    }

    public function minimumSpeakingRatePercent(): int
    {
        return (int) config('announcements.speaking_rate_percent.minimum', -20);
    }

    public function maximumSpeakingRatePercent(): int
    {
        return (int) config('announcements.speaking_rate_percent.maximum', 10);
    }

    public function minimumPauseMilliseconds(): int
    {
        return (int) config('announcements.pause_ms.minimum', 0);
    }

    public function maximumPauseMilliseconds(): int
    {
        return (int) config('announcements.pause_ms.maximum', 1500);
    }
}
