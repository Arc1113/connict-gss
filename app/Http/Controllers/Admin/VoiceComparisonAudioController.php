<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\CandidateAudioStorageException;
use App\Http\Controllers\Controller;
use App\Models\AnnouncementVoiceSample;
use App\Models\Ceremony;
use App\Services\TemporaryVoiceSampleStore;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class VoiceComparisonAudioController extends Controller
{
    public function __invoke(
        Ceremony $ceremony,
        AnnouncementVoiceSample $sample,
        TemporaryVoiceSampleStore $store,
    ): Response {
        Gate::authorize('manage-announcement-settings');
        abort_unless((int) $sample->ceremony_id === (int) $ceremony->id, 404);
        abort_if($sample->expires_at->isPast(), 404);
        abort_unless($store->exists($sample->candidate_audio_path), 404);

        try {
            $bytes = $store->read($sample->candidate_audio_path);
        } catch (CandidateAudioStorageException) {
            abort(404);
        }

        return response($bytes, 200, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'Content-Disposition' => 'inline; filename="fictional-voice-sample.mp3"',
            'Content-Type' => 'audio/mpeg',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
