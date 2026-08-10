<?php

namespace App\Services;

use App\Contracts\TextToSpeechProvider;
use App\Enums\AnnouncementProvider;

final class TextToSpeechProviderHealth
{
    public function __construct(
        private readonly TextToSpeechProvider $provider,
        private readonly TextToSpeechProviderRegistry $registry,
    ) {}

    /**
     * @return array{status: string, network_checked: bool, configured: bool}
     */
    public function offline(?AnnouncementProvider $provider = null): array
    {
        $health = $provider === null
            ? $this->provider->health()
            : $this->registry->healthFor($provider);

        return [
            'status' => $health->status,
            'network_checked' => $health->networkChecked,
            'configured' => $health->isConfigured(),
        ];
    }

    /** @return array<string, array{status: string, network_checked: bool, configured: bool}> */
    public function providers(): array
    {
        $result = [];

        foreach (AnnouncementProvider::cases() as $provider) {
            $result[$provider->value] = $this->offline($provider);
        }

        return $result;
    }
}
