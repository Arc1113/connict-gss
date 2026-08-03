<?php

namespace App\Services;

use App\Contracts\CandidateAudioStore;
use App\Data\GeneratedSpeechResult;
use App\Data\StoredCandidateAudio;
use App\Exceptions\CandidateAudioStorageException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class AtomicPrivateCandidateAudioStore implements CandidateAudioStore
{
    public function __construct(
        private readonly ValidateMp3Candidate $validator,
        private readonly AtomicPrivateFileWriter $writer,
    ) {}

    public function store(GeneratedSpeechResult $speech): StoredCandidateAudio
    {
        $maximumBytes = (int) config('announcements.azure.maximum_response_bytes');
        $this->validator->validate(
            $speech->audioBytes,
            $speech->contentType,
            $maximumBytes,
            $speech->attemptCount,
        );

        $diskName = (string) config('announcements.azure.candidate_disk', 'local');
        $directory = trim((string) config(
            'announcements.azure.candidate_directory',
            'announcement-candidates',
        ), '/');

        if ($directory === '' || str_contains($directory, '..')
            || preg_match('/^[A-Za-z0-9_\/-]+$/', $directory) !== 1) {
            throw new CandidateAudioStorageException;
        }

        $relativePath = $directory.'/'.Str::uuid().'/'.Str::uuid().'.mp3';

        try {
            $disk = Storage::disk($diskName);
            $destination = $disk->path($relativePath);
            $this->writer->write($destination, $speech->audioBytes);
        } catch (Throwable) {
            throw new CandidateAudioStorageException;
        }

        return new StoredCandidateAudio(
            path: $relativePath,
            size: $speech->size(),
            sha256: hash('sha256', $speech->audioBytes),
        );
    }

    public function exists(string $path): bool
    {
        return $this->isSafePath($path)
            && Storage::disk((string) config('announcements.azure.candidate_disk', 'local'))
                ->exists($path);
    }

    public function delete(string $path): void
    {
        if ($this->isSafePath($path)) {
            Storage::disk((string) config('announcements.azure.candidate_disk', 'local'))
                ->delete($path);
        }
    }

    private function isSafePath(string $path): bool
    {
        $directory = trim((string) config(
            'announcements.azure.candidate_directory',
            'announcement-candidates',
        ), '/');

        return $directory !== ''
            && ! str_contains($path, '..')
            && str_starts_with($path, $directory.'/')
            && preg_match('/^[A-Za-z0-9_\/.\-]+$/', $path) === 1;
    }
}
