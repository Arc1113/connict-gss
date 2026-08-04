<?php

namespace App\Services;

use App\Enums\AnnouncementGenerationStatus;
use App\Enums\AudioReadinessStatus;
use App\Enums\GraduateAudioSourceType;
use App\Models\AnnouncementGeneration;
use App\Models\AnnouncementTemplate;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

final class InvalidateAnnouncementCandidates
{
    public function __construct(
        private readonly PreviewGraduationAnnouncement $previewer,
    ) {}

    public function forStudent(int $studentId): int
    {
        return DB::transaction(function () use ($studentId): int {
            $student = Student::query()->lockForUpdate()->find($studentId);

            if ($student === null) {
                return 0;
            }

            $invalidated = 0;
            $generations = AnnouncementGeneration::query()
                ->whereBelongsTo($student)
                ->whereIn('status', [
                    AnnouncementGenerationStatus::NeedsReview->value,
                    AnnouncementGenerationStatus::Approved->value,
                ])
                ->lockForUpdate()
                ->get();

            foreach ($generations as $generation) {
                $template = AnnouncementTemplate::query()
                    ->where('ceremony_id', $student->ceremony_id)
                    ->find($generation->announcement_template_id);
                $preview = $this->previewer->preview($student, $template, $generation->provider_voice);
                $currentHash = $preview->isReady() ? $preview->composition?->sourceHash : null;

                if (is_string($currentHash) && hash_equals($generation->source_hash, $currentHash)) {
                    continue;
                }

                $generation->update(['status' => AnnouncementGenerationStatus::Outdated]);
                $invalidated++;

                if ($student->audio_source_type === GraduateAudioSourceType::AzureTts
                    && (int) $student->audio_generation_id === (int) $generation->id) {
                    Student::query()->whereKey($student->id)->update([
                        'audio_readiness_status' => AudioReadinessStatus::NeedsReview->value,
                        'audio_approved_at' => null,
                        'audio_approved_by' => null,
                        'audio_review_notes' => 'Generated audio became stale after its announcement source changed.',
                    ]);
                    $student->audio_readiness_status = AudioReadinessStatus::NeedsReview;
                }
            }

            return $invalidated;
        }, 3);
    }

    public function forCeremony(int $ceremonyId, ?int $activeTemplateId = null): int
    {
        $count = 0;

        Student::query()
            ->where('ceremony_id', $ceremonyId)
            ->pluck('id')
            ->each(function (int $studentId) use (&$count): void {
                $count += $this->forStudent($studentId);
            });

        if ($activeTemplateId !== null) {
            $studentIds = AnnouncementGeneration::query()
                ->where('ceremony_id', $ceremonyId)
                ->where('announcement_template_id', '!=', $activeTemplateId)
                ->whereIn('status', [
                    AnnouncementGenerationStatus::NeedsReview->value,
                    AnnouncementGenerationStatus::Approved->value,
                ])
                ->distinct()
                ->pluck('student_id');

            foreach ($studentIds as $studentId) {
                $count += $this->invalidateTemplateSelectionForStudent(
                    (int) $studentId,
                    $activeTemplateId,
                );
            }
        }

        return $count;
    }

    public function forTemplate(AnnouncementTemplate $template): int
    {
        $studentIds = AnnouncementGeneration::query()
            ->where('announcement_template_id', $template->id)
            ->whereIn('status', [
                AnnouncementGenerationStatus::NeedsReview->value,
                AnnouncementGenerationStatus::Approved->value,
            ])
            ->distinct()
            ->pluck('student_id');
        $count = 0;

        foreach ($studentIds as $studentId) {
            $count += DB::transaction(function () use ($studentId, $template): int {
                Student::query()->lockForUpdate()->findOrFail($studentId);
                $generations = AnnouncementGeneration::query()
                    ->where('student_id', $studentId)
                    ->where('announcement_template_id', $template->id)
                    ->whereIn('status', [
                        AnnouncementGenerationStatus::NeedsReview->value,
                        AnnouncementGenerationStatus::Approved->value,
                    ])
                    ->lockForUpdate()
                    ->get();

                foreach ($generations as $generation) {
                    $generation->update(['status' => AnnouncementGenerationStatus::Outdated]);
                    $this->invalidateActiveStudentAudio($generation);
                }

                return $generations->count();
            }, 3);
        }

        return $count;
    }

    private function invalidateTemplateSelectionForStudent(int $studentId, int $activeTemplateId): int
    {
        return DB::transaction(function () use ($studentId, $activeTemplateId): int {
            Student::query()->lockForUpdate()->findOrFail($studentId);
            $generations = AnnouncementGeneration::query()
                ->where('student_id', $studentId)
                ->where('announcement_template_id', '!=', $activeTemplateId)
                ->whereIn('status', [
                    AnnouncementGenerationStatus::NeedsReview->value,
                    AnnouncementGenerationStatus::Approved->value,
                ])
                ->lockForUpdate()
                ->get();

            foreach ($generations as $generation) {
                $generation->update(['status' => AnnouncementGenerationStatus::Outdated]);
                $this->invalidateActiveStudentAudio($generation);
            }

            return $generations->count();
        }, 3);
    }

    private function invalidateActiveStudentAudio(AnnouncementGeneration $generation): void
    {
        Student::query()
            ->whereKey($generation->student_id)
            ->where('audio_source_type', GraduateAudioSourceType::AzureTts->value)
            ->where('audio_generation_id', $generation->id)
            ->update([
                'audio_readiness_status' => AudioReadinessStatus::NeedsReview->value,
                'audio_approved_at' => null,
                'audio_approved_by' => null,
                'audio_review_notes' => 'Generated audio became stale after its announcement template changed.',
            ]);
    }
}
