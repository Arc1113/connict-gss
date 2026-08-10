<?php

namespace App\Services;

use App\Contracts\TextToSpeechProvider;
use App\Data\AnnouncementSynthesisRequest;
use App\Data\GeneratedSpeechResult;
use App\Data\ProviderHealthResult;
use App\Enums\AnnouncementProvider;

final readonly class TextToSpeechProviderRegistry implements TextToSpeechProvider
{
    public function __construct(
        private AzureSpeechProvider $azure,
        private KokoroSpeechProvider $kokoro,
    ) {}

    public function synthesize(AnnouncementSynthesisRequest $request): GeneratedSpeechResult
    {
        return $this->for($request->provider)->synthesize($request);
    }

    public function health(bool $performNetworkCheck = false): ProviderHealthResult
    {
        $health = array_map(
            fn (AnnouncementProvider $provider): ProviderHealthResult => $this->healthFor(
                $provider,
                $performNetworkCheck,
            ),
            AnnouncementProvider::cases(),
        );

        foreach ($health as $result) {
            if ($result->isConfigured()) {
                return new ProviderHealthResult(
                    $performNetworkCheck ? 'available' : 'configured',
                    $performNetworkCheck,
                );
            }
        }

        return new ProviderHealthResult('disabled', $performNetworkCheck);
    }

    public function healthFor(
        AnnouncementProvider $provider,
        bool $performNetworkCheck = false,
    ): ProviderHealthResult {
        return $this->for($provider)->health($performNetworkCheck);
    }

    private function for(AnnouncementProvider $provider): TextToSpeechProvider
    {
        return match ($provider) {
            AnnouncementProvider::Azure => $this->azure,
            AnnouncementProvider::Kokoro => $this->kokoro,
        };
    }
}
