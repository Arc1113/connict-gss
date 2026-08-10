<?php

namespace App\Services;

use App\Contracts\TextToSpeechProvider;
use App\Data\AnnouncementSynthesisRequest;
use App\Data\AzureSpeechSettings;
use App\Data\GeneratedSpeechResult;
use App\Data\ProviderHealthResult;
use App\Enums\AnnouncementProvider;
use App\Exceptions\SpeechSynthesisException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class AzureSpeechProvider implements TextToSpeechProvider
{
    public function __construct(
        private readonly AzureSpeechConfiguration $configuration,
        private readonly ApprovedAnnouncementVoices $approvedVoices,
        private readonly ValidateMp3Candidate $mp3Validator,
    ) {}

    public function synthesize(AnnouncementSynthesisRequest $request): GeneratedSpeechResult
    {
        $settings = $this->configuration->resolve();
        $this->validateRequest($request);
        $maximumAttempts = $settings->maximumRetries + 1;

        for ($attempt = 1; $attempt <= $maximumAttempts; $attempt++) {
            try {
                $response = $this->request($settings->key, $settings)
                    ->withHeaders([
                        'X-Microsoft-OutputFormat' => $request->outputFormat,
                        'Accept' => 'audio/mpeg',
                    ])
                    ->withBody($request->ssml, 'application/ssml+xml')
                    ->post($settings->synthesisEndpoint);
            } catch (ConnectionException $exception) {
                $code = str_contains(strtolower($exception->getMessage()), 'timed out')
                    ? 'azure_timeout'
                    : 'azure_service_unavailable';

                if ($attempt < $maximumAttempts) {
                    $this->delayBeforeRetry($attempt, null, $settings);

                    continue;
                }

                throw $this->failure($code, $attempt);
            }

            if ($response->successful()) {
                $contentLength = (int) $response->header('Content-Length', 0);

                if ($contentLength > $settings->maximumResponseBytes) {
                    throw $this->failure('candidate_validation_failed', $attempt, 200);
                }

                $bytes = $response->body();
                $contentType = (string) $response->header('Content-Type', '');
                $this->mp3Validator->validate(
                    $bytes,
                    $contentType,
                    $settings->maximumResponseBytes,
                    $attempt,
                );

                return new GeneratedSpeechResult(
                    audioBytes: $bytes,
                    contentType: strtolower(trim(explode(';', $contentType)[0])),
                    providerRequestId: $this->safeRequestId($response),
                    attemptCount: $attempt,
                );
            }

            if ($this->isRetryable($response) && $attempt < $maximumAttempts) {
                $this->delayBeforeRetry($attempt, $response, $settings);

                continue;
            }

            throw $this->failureForResponse($response, $attempt);
        }

        throw $this->failure('azure_response_unexpected', $maximumAttempts);
    }

    public function health(bool $performNetworkCheck = false): ProviderHealthResult
    {
        $configurationHealth = $this->configuration->health();

        if (! $performNetworkCheck || ! $configurationHealth->isConfigured()) {
            return $configurationHealth;
        }

        try {
            $settings = $this->configuration->resolve();
            $response = $this->request($settings->key, $settings)
                ->get($settings->healthEndpoint);
        } catch (ConnectionException) {
            return new ProviderHealthResult('unavailable', true);
        }

        return new ProviderHealthResult(match ($response->status()) {
            200 => 'available',
            401, 403 => 'authentication_failed',
            429 => 'rate_limited',
            502, 503 => 'unavailable',
            default => 'unknown',
        }, true);
    }

    private function request(
        string $key,
        AzureSpeechSettings $settings,
    ): PendingRequest {
        return Http::connectTimeout($settings->connectTimeoutSeconds)
            ->timeout($settings->requestTimeoutSeconds)
            ->withoutRedirecting()
            ->withHeaders([
                'Ocp-Apim-Subscription-Key' => $key,
                'User-Agent' => $settings->userAgent,
            ]);
    }

    private function validateRequest(AnnouncementSynthesisRequest $request): void
    {
        $voiceIssues = $this->approvedVoices->validationIssues(
            $request->voice,
            $request->locale,
            AnnouncementProvider::Azure,
        );
        $outputFormat = (string) config('announcements.default_output_format');

        if ($request->provider !== AnnouncementProvider::Azure
            || $voiceIssues !== []
            || $request->outputFormat !== $outputFormat
            || $request->ssml === ''
            || $request->plainText === ''
            || $request->characterCount !== mb_strlen($request->plainText, 'UTF-8')
            || preg_match('/^[0-9a-f]{64}$/', $request->sourceHash) !== 1) {
            throw new SpeechSynthesisException(
                'azure_request_invalid',
                'The announcement synthesis request is invalid.',
            );
        }
    }

    private function isRetryable(Response $response): bool
    {
        return in_array($response->status(), [429, 502, 503], true);
    }

    private function delayBeforeRetry(
        int $attempt,
        ?Response $response,
        AzureSpeechSettings $settings,
    ): void {
        $retryAfter = $response === null
            ? null
            : filter_var($response->header('Retry-After'), FILTER_VALIDATE_INT);

        if (is_int($retryAfter) && $retryAfter >= 0
            && $retryAfter <= $settings->maximumRetryAfterSeconds) {
            $delay = $retryAfter * 1000;
        } else {
            $exponential = $settings->retryBaseDelayMilliseconds * (2 ** ($attempt - 1));
            $jitter = $settings->retryJitterMilliseconds > 0
                ? random_int(0, $settings->retryJitterMilliseconds)
                : 0;
            $delay = min($exponential + $jitter, $settings->maximumRetryDelayMilliseconds);
        }

        if ($delay > 0) {
            usleep($delay * 1000);
        }
    }

    private function failureForResponse(Response $response, int $attempt): SpeechSynthesisException
    {
        return match ($response->status()) {
            400 => $this->failure('azure_invalid_ssml', $attempt, 400),
            401, 403 => $this->failure('azure_authentication_failed', $attempt, $response->status()),
            429 => $this->failure('azure_rate_limited', $attempt, 429),
            502, 503 => $this->failure('azure_service_unavailable', $attempt, $response->status()),
            default => $this->failure('azure_response_unexpected', $attempt, $response->status()),
        };
    }

    private function failure(
        string $code,
        int $attempts,
        ?int $status = null,
    ): SpeechSynthesisException {
        $messages = [
            'azure_request_invalid' => 'The announcement synthesis request is invalid.',
            'azure_authentication_failed' => 'Azure Speech authentication failed.',
            'azure_rate_limited' => 'Azure Speech temporarily rate-limited the request.',
            'azure_invalid_ssml' => 'Azure Speech rejected the announcement SSML.',
            'azure_timeout' => 'Azure Speech did not respond within the preparation timeout.',
            'azure_service_unavailable' => 'Azure Speech is temporarily unavailable.',
            'candidate_validation_failed' => 'Azure Speech returned an invalid or unsupported audio candidate.',
            'azure_response_unexpected' => 'Azure Speech returned an unexpected response.',
        ];

        return new SpeechSynthesisException(
            $code,
            $messages[$code] ?? $messages['azure_response_unexpected'],
            $attempts,
            $status,
        );
    }

    private function safeRequestId(Response $response): ?string
    {
        $requestId = trim((string) (
            $response->header('X-Microsoft-RequestId')
            ?? $response->header('X-RequestId')
        ));

        return preg_match('/^[A-Za-z0-9._:-]{1,255}$/', $requestId) === 1
            ? $requestId
            : null;
    }
}
