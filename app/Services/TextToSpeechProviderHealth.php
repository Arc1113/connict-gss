<?php

namespace App\Services;

use App\Contracts\TextToSpeechProvider;

final class TextToSpeechProviderHealth
{
    public function __construct(
        private readonly TextToSpeechProvider $provider,
    ) {}

    /**
     * @return array{status: string, network_checked: bool, configured: bool}
     */
    public function offline(): array
    {
        $health = $this->provider->health();

        return [
            'status' => $health->status,
            'network_checked' => $health->networkChecked,
            'configured' => $health->isConfigured(),
        ];
    }
}
