<?php

namespace App\Services;

use App\Models\AnnouncementVoiceSample;

final class PruneVoiceComparisonSamples
{
    public function __construct(
        private readonly TemporaryVoiceSampleStore $store,
    ) {}

    public function expired(): int
    {
        $samples = AnnouncementVoiceSample::query()
            ->where('expires_at', '<=', now())
            ->get(['id', 'candidate_audio_path']);

        AnnouncementVoiceSample::query()->whereKey($samples->pluck('id'))->delete();
        $samples->each(fn (AnnouncementVoiceSample $sample) => $this->store->delete(
            $sample->candidate_audio_path,
        ));

        return $samples->count();
    }
}
