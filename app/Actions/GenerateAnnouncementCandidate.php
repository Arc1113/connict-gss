<?php

namespace App\Actions;

use App\Contracts\CandidateAudioStore;
use App\Contracts\TextToSpeechProvider;
use App\Data\AnnouncementCandidateGenerationResult;
use App\Data\AnnouncementSynthesisRequest;
use App\Enums\AnnouncementGenerationStatus;
use App\Enums\AnnouncementProvider;
use App\Exceptions\AnnouncementCandidateGenerationException;
use App\Exceptions\CandidateAudioStorageException;
use App\Exceptions\SpeechSynthesisException;
use App\Models\AnnouncementGeneration;
use App\Models\Student;
use App\Models\User;
use App\Services\PreviewGraduationAnnouncement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Throwable;

final class GenerateAnnouncementCandidate
{
    public function __construct(
        private readonly PreviewGraduationAnnouncement $previewer,
        private readonly TextToSpeechProvider $provider,
        private readonly CandidateAudioStore $candidateStore,
    ) {}

    public function generate(
        Student $student,
        User $actor,
        bool $force = false,
    ): AnnouncementCandidateGenerationResult {
        Gate::forUser($actor)->authorize('generate-announcement-candidates');

        if ($force) {
            Gate::forUser($actor)->authorize('manage-announcement-settings');
        }

        if (! $this->provider->health()->isConfigured()) {
            throw new AnnouncementCandidateGenerationException(
                'azure_not_configured',
                'Azure Speech is disabled or not configured safely for preparation use.',
            );
        }

        /** @var array{result?: AnnouncementCandidateGenerationResult, generation?: AnnouncementGeneration, request?: AnnouncementSynthesisRequest} $prepared */
        $prepared = DB::transaction(function () use ($student, $actor, $force): array {
            $lockedStudent = Student::query()->lockForUpdate()->findOrFail($student->getKey());
            $preview = $this->previewer->preview($lockedStudent);

            if (! $preview->isReady() || $preview->composition === null
                || $preview->ssml?->ssml === null) {
                throw new AnnouncementCandidateGenerationException(
                    'announcement_not_ready',
                    'The announcement must pass preview validation before generation.',
                );
            }

            $composition = $preview->composition;
            $input = $composition->input;

            if (! $force) {
                $existing = AnnouncementGeneration::query()
                    ->whereBelongsTo($lockedStudent)
                    ->where('provider', AnnouncementProvider::Azure->value)
                    ->where('provider_voice', $composition->voice)
                    ->where('locale', $composition->locale)
                    ->where('output_format', $input->outputFormat)
                    ->where('source_hash', $composition->sourceHash)
                    ->where('status', AnnouncementGenerationStatus::NeedsReview->value)
                    ->whereNotNull('candidate_audio_path')
                    ->latest('id')
                    ->first();

                if ($existing !== null
                    && $this->candidateStore->exists((string) $existing->candidate_audio_path)) {
                    return ['result' => new AnnouncementCandidateGenerationResult($existing, true)];
                }
            }

            $generation = AnnouncementGeneration::query()->create([
                'student_id' => $lockedStudent->getKey(),
                'ceremony_id' => $lockedStudent->ceremony_id,
                'announcement_template_id' => $input->templateIdentifier,
                'provider' => AnnouncementProvider::Azure,
                'provider_voice' => $composition->voice,
                'locale' => $composition->locale,
                'plain_text' => $composition->plainText,
                'ssml' => $preview->ssml->ssml,
                'source_hash' => $composition->sourceHash,
                'status' => AnnouncementGenerationStatus::Pending,
                'character_count' => $composition->characterCount,
                'output_format' => $input->outputFormat,
                'attempt_count' => 0,
                'generated_by' => $actor->getKey(),
            ]);
            $generation->update(['status' => AnnouncementGenerationStatus::Generating]);

            return [
                'generation' => $generation,
                'request' => new AnnouncementSynthesisRequest(
                    ssml: $preview->ssml->ssml,
                    plainText: $composition->plainText,
                    characterCount: $composition->characterCount,
                    voice: $composition->voice,
                    locale: $composition->locale,
                    outputFormat: $input->outputFormat,
                    sourceHash: $composition->sourceHash,
                ),
            ];
        }, 3);

        if (isset($prepared['result'])) {
            return $prepared['result'];
        }

        $generation = $prepared['generation'];
        $request = $prepared['request'];

        try {
            $speech = $this->provider->synthesize($request);
            $stored = $this->candidateStore->store($speech);
        } catch (SpeechSynthesisException $exception) {
            $this->markFailed(
                $generation,
                $exception->safeCode,
                $exception->getMessage(),
                $exception->attemptCount,
            );

            throw new AnnouncementCandidateGenerationException(
                $exception->safeCode,
                $exception->getMessage(),
                $generation->fresh(),
            );
        } catch (CandidateAudioStorageException) {
            $this->markFailed(
                $generation,
                'candidate_storage_failed',
                'The private announcement candidate could not be stored safely.',
                $speech->attemptCount,
            );

            throw new AnnouncementCandidateGenerationException(
                'candidate_storage_failed',
                'The private announcement candidate could not be stored safely.',
                $generation->fresh(),
            );
        }

        try {
            DB::transaction(function () use ($generation, $speech, $stored): void {
                $locked = AnnouncementGeneration::query()
                    ->lockForUpdate()
                    ->findOrFail($generation->getKey());
                $locked->update([
                    'status' => AnnouncementGenerationStatus::NeedsReview,
                    'candidate_audio_path' => $stored->path,
                    'provider_request_id' => $speech->providerRequestId,
                    'attempt_count' => $speech->attemptCount,
                    'generated_at' => now(),
                    'failure_code' => null,
                    'failure_message' => null,
                ]);
            }, 3);
        } catch (Throwable) {
            $this->candidateStore->delete($stored->path);
            $this->markFailed(
                $generation,
                'candidate_storage_failed',
                'The private announcement candidate could not be finalized safely.',
                $speech->attemptCount,
            );

            throw new AnnouncementCandidateGenerationException(
                'candidate_storage_failed',
                'The private announcement candidate could not be finalized safely.',
                $generation->fresh(),
            );
        }

        return new AnnouncementCandidateGenerationResult(
            generation: $generation->fresh(),
            reused: false,
        );
    }

    private function markFailed(
        AnnouncementGeneration $generation,
        string $code,
        string $message,
        int $attemptCount,
    ): void {
        AnnouncementGeneration::query()
            ->whereKey($generation->getKey())
            ->update([
                'status' => AnnouncementGenerationStatus::Failed->value,
                'attempt_count' => max(0, $attemptCount),
                'failure_code' => mb_substr($code, 0, 96),
                'failure_message' => mb_substr($message, 0, 500),
                'candidate_audio_path' => null,
                'provider_request_id' => null,
            ]);
    }
}
