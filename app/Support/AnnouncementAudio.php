<?php

namespace App\Support;

use App\Enums\AudioReadinessStatus;
use App\Models\Student;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

final class AnnouncementAudio
{
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
        return $student->audio_readiness_status === AudioReadinessStatus::Approved
            && $this->isReadable($student->audio_path);
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
