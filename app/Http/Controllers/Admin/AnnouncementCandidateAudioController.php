<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\CandidateAudioStore;
use App\Exceptions\CandidateAudioStorageException;
use App\Http\Controllers\Controller;
use App\Models\AnnouncementGeneration;
use App\Models\Student;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class AnnouncementCandidateAudioController extends Controller
{
    public function __invoke(
        Student $student,
        AnnouncementGeneration $generation,
        CandidateAudioStore $candidateStore,
    ): Response {
        Gate::authorize('view', $student);
        Gate::authorize('generate-announcement-candidates');

        abort_unless((int) $generation->student_id === (int) $student->id, 404);
        $path = $generation->candidate_audio_path;
        abort_unless(is_string($path) && $candidateStore->exists($path), 404);

        try {
            $bytes = $candidateStore->read($path);
        } catch (CandidateAudioStorageException) {
            abort(404);
        }

        return response($bytes, 200, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'Content-Disposition' => 'inline; filename="announcement-candidate.mp3"',
            'Content-Type' => 'audio/mpeg',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
