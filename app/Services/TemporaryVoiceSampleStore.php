<?php

namespace App\Services;

use App\Data\GeneratedSpeechResult;
use App\Data\StoredCandidateAudio;
use App\Exceptions\CandidateAudioStorageException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class TemporaryVoiceSampleStore
{
    public function __construct(
        private readonly ValidateMp3Candidate $validator,
        private readonly AtomicPrivateFileWriter $writer,
    ) {}

    public function store(GeneratedSpeechResult $speech): StoredCandidateAudio
    {
        $this->validator->validate(
            $speech->audioBytes,
            $speech->contentType,
            (int) config('announcements.azure.maximum_response_bytes'),
            $speech->attemptCount,
        );
        $path = $this->directory().'/'.Str::uuid().'.mp3';

        try {
            $this->writer->write(Storage::disk('local')->path($path), $speech->audioBytes);
        } catch (Throwable) {
            throw new CandidateAudioStorageException;
        }

        return new StoredCandidateAudio(
            path: $path,
            size: $speech->size(),
            sha256: hash('sha256', $speech->audioBytes),
        );
    }

    public function exists(string $path): bool
    {
        return $this->safe($path) && Storage::disk('local')->exists($path);
    }

    public function read(string $path): string
    {
        if (! $this->safe($path)) {
            throw new CandidateAudioStorageException;
        }

        try {
            $bytes = Storage::disk('local')->get($path);
            $this->validator->validate(
                $bytes,
                'audio/mpeg',
                (int) config('announcements.azure.maximum_response_bytes'),
            );

            return $bytes;
        } catch (Throwable) {
            throw new CandidateAudioStorageException;
        }
    }

    public function delete(?string $path): void
    {
        if (is_string($path) && $this->safe($path)) {
            Storage::disk('local')->delete($path);
        }
    }

    private function safe(string $path): bool
    {
        return ! str_contains($path, '..')
            && preg_match('/^[A-Za-z0-9_\/.\-]+$/', $path) === 1
            && str_starts_with($path, $this->directory().'/');
    }

    private function directory(): string
    {
        return trim((string) config(
            'announcements.voice_comparison.directory',
            'announcement-voice-samples',
        ), '/');
    }
}
