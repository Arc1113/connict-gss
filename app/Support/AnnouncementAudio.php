<?php

namespace App\Support;

use App\Enums\AnnouncementGenerationStatus;
use App\Enums\AudioReadinessStatus;
use App\Enums\GraduateAudioSourceType;
use App\Models\Student;
use App\Services\PreviewGraduationAnnouncement;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

final class AnnouncementAudio
{
    public function __construct(
        private readonly PreviewGraduationAnnouncement $previewer,
    ) {}

    /**
     * @return array{value: string, label: string}
     */
    public function readiness(Student $student): array
    {
        $status = $student->audio_readiness_status;

        return [
            'value' => $status->value,
            'label' => $status->label(),
        ];
    }

    public function approvedUrl(Student $student): ?string
    {
        if (! $this->isApprovedAndReadable($student)) {
            return null;
        }

        return $this->localUrl($student->audio_path);
    }

    public function previewUrl(Student $student): ?string
    {
        if (! $this->hasSafeLocalPath($student->audio_path)) {
            return null;
        }

        return $this->localUrl($student->audio_path);
    }

    public function isApprovedAndReadable(Student $student): bool
    {
        if ($student->audio_readiness_status !== AudioReadinessStatus::Approved
            || ! $this->isReadable($student->audio_path)) {
            return false;
        }

        if ($student->audio_source_type !== GraduateAudioSourceType::AzureTts) {
            return true;
        }

        $generation = $student->activeAudioGeneration()->with('template')->first();

        if ($generation === null
            || $generation->status !== AnnouncementGenerationStatus::Approved
            || ! is_string($student->audio_source_hash)
            || ! hash_equals($generation->source_hash, $student->audio_source_hash)) {
            return false;
        }

        $preview = $this->previewer->preview(
            $student,
            $generation->template,
            $generation->provider_voice,
        );

        return $preview->isReady()
            && $preview->composition !== null
            && hash_equals($generation->source_hash, $preview->composition->sourceHash);
    }

    public function isReadable(?string $path): bool
    {
        if (! $this->hasSafeLocalPath($path)) {
            return false;
        }

        $normalized = ltrim(trim((string) $path), '/');

        if (str_starts_with($normalized, 'storage/graduates/audio/')) {
            return Storage::disk('public')->exists(substr($normalized, strlen('storage/')));
        }

        return File::isReadable(public_path($normalized));
    }

    public function safeFilename(?string $path): ?string
    {
        if (! $this->hasSafeLocalPath($path)) {
            return null;
        }

        return basename((string) $path);
    }

    private function hasSafeLocalPath(?string $path): bool
    {
        if ($path === null || trim($path) === '') {
            return false;
        }

        $normalized = ltrim(trim($path), '/');

        return ! str_contains($normalized, '..')
            && ! str_contains($normalized, '\\')
            && (str_starts_with($normalized, 'storage/graduates/audio/')
                || str_starts_with($normalized, 'media/phase3/audio/'));
    }

    private function localUrl(?string $path): ?string
    {
        if (! $this->hasSafeLocalPath($path)) {
            return null;
        }

        return '/'.ltrim(trim((string) $path), '/');
    }
}
