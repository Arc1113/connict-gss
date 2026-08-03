<?php

namespace App\Contracts;

use App\Data\AnnouncementSynthesisRequest;
use App\Data\GeneratedSpeechResult;
use App\Data\ProviderHealthResult;

interface TextToSpeechProvider
{
    public function synthesize(AnnouncementSynthesisRequest $request): GeneratedSpeechResult;

    public function health(bool $performNetworkCheck = false): ProviderHealthResult;
}
