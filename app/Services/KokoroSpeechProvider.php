<?php

namespace App\Services;

use App\Contracts\TextToSpeechProvider;
use App\Data\AnnouncementSynthesisRequest;
use App\Data\GeneratedSpeechResult;
use App\Data\ProviderHealthResult;
use App\Enums\AnnouncementProvider;
use App\Exceptions\SpeechSynthesisException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class KokoroSpeechProvider implements TextToSpeechProvider
{
    public function __construct(
        private readonly ApprovedAnnouncementVoices $voices,
        private readonly ValidateMp3Candidate $mp3Validator,
    ) {}

    public function synthesize(AnnouncementSynthesisRequest $request): GeneratedSpeechResult
    {
        $health = $this->health();

        if (! $health->isConfigured()) {
            throw $this->failure('local_tts_not_configured', 'Local voice generation is disabled or misconfigured.');
        }

        if ($request->provider !== AnnouncementProvider::Kokoro
            || $this->voices->validationIssues(
                $request->voice,
                $request->locale,
                AnnouncementProvider::Kokoro,
            ) !== []
            || trim($request->plainText) === '') {
            throw $this->failure('local_tts_request_invalid', 'The local synthesis request is invalid.');
        }

        try {
            $response = Http::connectTimeout((int) config('announcements.kokoro.connect_timeout_seconds', 2))
                ->timeout((int) config('announcements.kokoro.request_timeout_seconds', 90))
                ->accept('audio/mpeg')
                ->post(config('announcements.kokoro.base_url').'/v1/synthesize', [
                    'text' => $request->plainText,
                    'sections' => $request->sections,
                    'voice' => $request->voice,
                    'speed' => max(0.5, min(2.0, 1 + ($request->speakingRatePercent / 100))),
                    'pause_after_name_ms' => $request->pauseAfterNameMs,
                    'pause_between_sections_ms' => $request->pauseBetweenSectionsMs,
                    'delivery_profile' => $request->deliveryProfile,
                ]);
        } catch (ConnectionException) {
            throw $this->failure('local_tts_unavailable', 'The offline voice service is not reachable.');
        }

        if (! $response->successful()) {
            throw $this->failure(
                $response->status() === 422 ? 'local_tts_request_invalid' : 'local_tts_failed',
                'The offline voice service could not generate this announcement.',
            );
        }

        $bytes = $response->body();
        $contentType = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
        $this->mp3Validator->validate(
            $bytes,
            $contentType,
            (int) config('announcements.kokoro.maximum_response_bytes', 15 * 1024 * 1024),
            1,
        );

        return new GeneratedSpeechResult(
            audioBytes: $bytes,
            contentType: 'audio/mpeg',
            providerRequestId: null,
            attemptCount: 1,
        );
    }

    public function health(bool $performNetworkCheck = false): ProviderHealthResult
    {
        if (! (bool) config('announcements.kokoro.enabled', true)) {
            return new ProviderHealthResult('disabled', false);
        }

        $baseUrl = (string) config('announcements.kokoro.base_url');

        if (! str_starts_with($baseUrl, 'http://')) {
            return new ProviderHealthResult('misconfigured', false);
        }

        if (! $performNetworkCheck) {
            return new ProviderHealthResult('configured', false);
        }

        try {
            $response = Http::connectTimeout(1)->timeout(2)->get($baseUrl.'/health');
        } catch (ConnectionException) {
            return new ProviderHealthResult('unavailable', true);
        }

        return new ProviderHealthResult($response->successful() ? 'available' : 'unavailable', true);
    }

    private function failure(string $code, string $message): SpeechSynthesisException
    {
        return new SpeechSynthesisException($code, $message, 1);
    }
}
