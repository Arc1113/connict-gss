<?php

namespace App\Actions;

use App\Contracts\CandidateAudioStore;
use App\Data\AnnouncementCandidateReviewResult;
use App\Enums\AnnouncementGenerationStatus;
use App\Enums\AnnouncementProvider;
use App\Enums\AudioReadinessStatus;
use App\Enums\GraduateAudioSourceType;
use App\Exceptions\CandidateAudioStorageException;
use App\Models\AnnouncementGeneration;
use App\Models\AnnouncementTemplate;
use App\Models\Student;
use App\Models\User;
use App\Services\PreviewGraduationAnnouncement;
use App\Support\AdministrativeAuditLogger;
use App\Support\StudentMediaStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ReviewAnnouncementCandidate
{
    public function __construct(
        private readonly PreviewGraduationAnnouncement $previewer,
        private readonly CandidateAudioStore $candidateStore,
        private readonly StudentMediaStorage $mediaStorage,
        private readonly AdministrativeAuditLogger $auditLogger,
    ) {}

    public function approve(
        Student $student,
        AnnouncementGeneration $generation,
        ?string $notes,
        User $actor,
        ?string $ipAddress,
    ): AnnouncementCandidateReviewResult {
        Gate::forUser($actor)->authorize('review-announcement-candidates');

        $promotedPath = null;
        $oldPath = null;

        try {
            $result = DB::transaction(function () use (
                $student,
                $generation,
                $notes,
                $actor,
                $ipAddress,
                &$promotedPath,
                &$oldPath,
            ): AnnouncementCandidateReviewResult {
                $lockedStudent = Student::query()->lockForUpdate()->findOrFail($student->id);
                $locked = $this->ownedGeneration($lockedStudent, $generation, true);
                $this->requireReviewable($locked);

                $template = AnnouncementTemplate::query()
                    ->where('ceremony_id', $lockedStudent->ceremony_id)
                    ->find($locked->announcement_template_id);
                $preview = $this->previewer->preview(
                    $lockedStudent,
                    $template,
                    $locked->provider_voice,
                );

                if (! $preview->isReady() || $preview->composition === null
                    || ! hash_equals($locked->source_hash, $preview->composition->sourceHash)) {
                    $locked->update(['status' => AnnouncementGenerationStatus::Outdated]);
                    $this->recordReviewAudit($actor, $locked, 'outdated', $notes, $ipAddress);

                    return new AnnouncementCandidateReviewResult($locked->refresh(), true);
                }

                $candidatePath = $locked->candidate_audio_path;

                if (! is_string($candidatePath) || ! $this->candidateStore->exists($candidatePath)) {
                    throw ValidationException::withMessages([
                        'announcement_review' => 'The private candidate is missing or unreadable.',
                    ]);
                }

                try {
                    $candidateBytes = $this->candidateStore->read($candidatePath);
                } catch (CandidateAudioStorageException) {
                    throw ValidationException::withMessages([
                        'announcement_review' => 'The private candidate is missing or unreadable.',
                    ]);
                }
                $promotedPath = $this->mediaStorage->promoteGeneratedAudio($candidateBytes);
                $oldPath = $lockedStudent->audio_path;
                $reviewedAt = now();

                $locked->update([
                    'status' => AnnouncementGenerationStatus::Approved,
                    'reviewed_by' => $actor->id,
                    'reviewed_at' => $reviewedAt,
                    'review_notes' => $notes,
                    'approved_audio_path' => $promotedPath,
                ]);
                $lockedStudent->update([
                    'audio_path' => $promotedPath,
                    'audio_source_type' => $locked->provider === AnnouncementProvider::Kokoro
                        ? GraduateAudioSourceType::LocalTts
                        : GraduateAudioSourceType::AzureTts,
                    'audio_generation_id' => $locked->id,
                    'audio_source_hash' => $locked->source_hash,
                    'audio_readiness_status' => AudioReadinessStatus::Approved,
                    'audio_uploaded_at' => $reviewedAt,
                    'audio_approved_at' => $reviewedAt,
                    'audio_approved_by' => $actor->id,
                    'audio_review_notes' => $notes,
                    'audio_duration_seconds' => null,
                    'audio_mime_type' => 'audio/mpeg',
                    'audio_file_size' => strlen($candidateBytes),
                ]);
                $this->recordReviewAudit($actor, $locked, 'approved', $notes, $ipAddress);

                return new AnnouncementCandidateReviewResult($locked->refresh());
            }, 3);
        } catch (Throwable $exception) {
            $this->mediaStorage->deleteManaged($promotedPath);

            throw $exception;
        }

        if (! $result->stale && $oldPath !== $promotedPath) {
            $this->mediaStorage->deleteManaged($oldPath);
        }

        return $result;
    }

    public function reject(
        Student $student,
        AnnouncementGeneration $generation,
        string $notes,
        User $actor,
        ?string $ipAddress,
    ): AnnouncementGeneration {
        Gate::forUser($actor)->authorize('review-announcement-candidates');

        return DB::transaction(function () use ($student, $generation, $notes, $actor, $ipAddress): AnnouncementGeneration {
            $lockedStudent = Student::query()->lockForUpdate()->findOrFail($student->id);
            $locked = $this->ownedGeneration($lockedStudent, $generation, true);
            $this->requireReviewable($locked);
            $locked->update([
                'status' => AnnouncementGenerationStatus::Rejected,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_notes' => $notes,
            ]);
            $this->recordReviewAudit($actor, $locked, 'rejected', $notes, $ipAddress);

            return $locked->refresh();
        }, 3);
    }

    private function ownedGeneration(Student $student, AnnouncementGeneration $generation, bool $lock): AnnouncementGeneration
    {
        $query = AnnouncementGeneration::query()
            ->whereBelongsTo($student)
            ->where('ceremony_id', $student->ceremony_id)
            ->whereKey($generation->id);

        return ($lock ? $query->lockForUpdate() : $query)->firstOrFail();
    }

    private function requireReviewable(AnnouncementGeneration $generation): void
    {
        if ($generation->status !== AnnouncementGenerationStatus::NeedsReview) {
            throw ValidationException::withMessages([
                'announcement_review' => 'Only a candidate awaiting review can be reviewed.',
            ]);
        }
    }

    private function recordReviewAudit(User $actor, AnnouncementGeneration $generation, string $decision, ?string $notes, ?string $ipAddress): void
    {
        $this->auditLogger->record(
            $actor,
            "announcement_generation.{$decision}",
            'announcement_generation',
            $generation->id,
            ['status' => AnnouncementGenerationStatus::NeedsReview->value],
            [
                'status' => $generation->status->value,
                'student_id' => $generation->student_id,
                'source_hash_matches' => $decision !== 'outdated',
                'review_notes' => $notes,
            ],
            $ipAddress,
        );
    }
}
