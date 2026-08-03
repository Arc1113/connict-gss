<?php

namespace App\Data;

final readonly class AzureSpeechSettings
{
    public function __construct(
        public string $key,
        public string $region,
        public string $synthesisEndpoint,
        public string $healthEndpoint,
        public int $connectTimeoutSeconds,
        public int $requestTimeoutSeconds,
        public int $maximumRetries,
        public int $retryBaseDelayMilliseconds,
        public int $retryJitterMilliseconds,
        public int $maximumRetryDelayMilliseconds,
        public int $maximumRetryAfterSeconds,
        public int $maximumResponseBytes,
        public string $userAgent,
    ) {}
}
