<?php

namespace App\Services;

use App\Data\AzureSpeechSettings;
use App\Data\ProviderHealthResult;
use App\Exceptions\SpeechSynthesisException;

final class AzureSpeechConfiguration
{
    private const MAXIMUM_RESPONSE_BYTES = 15 * 1024 * 1024;

    public function health(): ProviderHealthResult
    {
        if (! (bool) config('announcements.azure.enabled', false)) {
            return new ProviderHealthResult('disabled', false);
        }

        try {
            $this->resolve();
        } catch (SpeechSynthesisException) {
            return new ProviderHealthResult('misconfigured', false);
        }

        return new ProviderHealthResult('configured', false);
    }

    public function resolve(): AzureSpeechSettings
    {
        if (! (bool) config('announcements.azure.enabled', false)) {
            throw $this->notConfigured();
        }

        $key = trim((string) config('announcements.azure.key'));
        $region = strtolower(trim((string) config('announcements.azure.region')));

        if (preg_match('/^[A-Za-z0-9+\/_=-]{8,512}$/', $key) !== 1
            || preg_match('/^[a-z0-9-]{2,64}$/', $region) !== 1) {
            throw $this->notConfigured();
        }

        [$synthesisEndpoint, $healthEndpoint] = $this->endpoints($region);
        $connectTimeout = (int) config('announcements.azure.connect_timeout_seconds');
        $requestTimeout = (int) config('announcements.azure.request_timeout_seconds');
        $maximumRetries = (int) config('announcements.azure.maximum_retries');
        $retryBaseDelay = (int) config('announcements.azure.retry_base_delay_ms');
        $retryJitter = (int) config('announcements.azure.retry_jitter_ms');
        $maximumRetryDelay = (int) config('announcements.azure.maximum_retry_delay_ms');
        $maximumRetryAfter = (int) config('announcements.azure.maximum_retry_after_seconds');
        $maximumResponseBytes = (int) config('announcements.azure.maximum_response_bytes');
        $userAgent = trim((string) config('announcements.azure.user_agent'));

        if ($connectTimeout < 1 || $connectTimeout > 15
            || $requestTimeout < $connectTimeout || $requestTimeout > 60
            || $maximumRetries < 0 || $maximumRetries > 3
            || $retryBaseDelay < 0 || $retryBaseDelay > 2000
            || $retryJitter < 0 || $retryJitter > 1000
            || $maximumRetryDelay < 0 || $maximumRetryDelay > 5000
            || $maximumRetryAfter < 0 || $maximumRetryAfter > 10
            || $maximumResponseBytes < 1 || $maximumResponseBytes > self::MAXIMUM_RESPONSE_BYTES
            || $userAgent === '' || strlen($userAgent) > 254
            || preg_match('/^[\x20-\x7E]+$/', $userAgent) !== 1) {
            throw $this->notConfigured();
        }

        return new AzureSpeechSettings(
            key: $key,
            region: $region,
            synthesisEndpoint: $synthesisEndpoint,
            healthEndpoint: $healthEndpoint,
            connectTimeoutSeconds: $connectTimeout,
            requestTimeoutSeconds: $requestTimeout,
            maximumRetries: $maximumRetries,
            retryBaseDelayMilliseconds: $retryBaseDelay,
            retryJitterMilliseconds: $retryJitter,
            maximumRetryDelayMilliseconds: $maximumRetryDelay,
            maximumRetryAfterSeconds: $maximumRetryAfter,
            maximumResponseBytes: $maximumResponseBytes,
            userAgent: $userAgent,
        );
    }

    /**
     * @return array{string, string}
     */
    private function endpoints(string $region): array
    {
        $configured = trim((string) config('announcements.azure.endpoint'));

        if ($configured === '') {
            $host = $region.'.tts.speech.microsoft.com';

            return [
                "https://{$host}/cognitiveservices/v1",
                "https://{$host}/cognitiveservices/voices/list",
            ];
        }

        $parts = parse_url($configured);

        if (! is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || ! isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || (isset($parts['port']) && (int) $parts['port'] !== 443)) {
            throw $this->notConfigured();
        }

        $host = strtolower((string) $parts['host']);
        $regionalHost = $region.'.tts.speech.microsoft.com';
        $customResource = preg_match(
            '/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.cognitiveservices\.azure\.com$/',
            $host,
        ) === 1;

        if ($host !== $regionalHost && ! $customResource) {
            throw $this->notConfigured();
        }

        $path = '/'.trim((string) ($parts['path'] ?? ''), '/');

        if (! in_array($path, ['/', '/cognitiveservices/v1'], true)) {
            throw $this->notConfigured();
        }

        $synthesis = "https://{$host}/cognitiveservices/v1";
        $healthPath = $customResource
            ? '/tts/cognitiveservices/voices/list'
            : '/cognitiveservices/voices/list';

        return [$synthesis, "https://{$host}{$healthPath}"];
    }

    private function notConfigured(): SpeechSynthesisException
    {
        return new SpeechSynthesisException(
            'azure_not_configured',
            'Azure Speech is disabled or not configured safely for preparation use.',
        );
    }
}
