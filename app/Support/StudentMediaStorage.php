<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class StudentMediaStorage
{
    public function storePhoto(UploadedFile $photo): string
    {
        return $this->store($photo, 'graduates/photos', 'photo');
    }

    public function storeAudio(UploadedFile $audio): string
    {
        return $this->store($audio, 'graduates/audio', 'audio');
    }

    public function promoteGeneratedAudio(string $bytes): string
    {
        $disk = Storage::disk('public');
        $identifier = (string) Str::uuid();
        $temporary = "graduates/audio/.candidate-{$identifier}.tmp";
        $destination = "graduates/audio/{$identifier}.mp3";

        try {
            if (! $disk->put($temporary, $bytes) || ! $disk->move($temporary, $destination)
                || ! $disk->exists($destination)) {
                throw new \RuntimeException('Generated audio promotion failed.');
            }
        } catch (Throwable) {
            $disk->delete([$temporary, $destination]);

            throw ValidationException::withMessages([
                'announcement_review' => 'The candidate could not be promoted safely. The active audio was not changed.',
            ]);
        }

        return 'storage/'.$destination;
    }

    /**
     * @return array{audio_mime_type: string|null, audio_file_size: int|null}
     */
    public function audioMetadata(UploadedFile $audio): array
    {
        return [
            'audio_mime_type' => $audio->getMimeType(),
            'audio_file_size' => $audio->getSize(),
        ];
    }

    public function deleteManaged(?string $databasePath): void
    {
        if ($databasePath === null) {
            return;
        }

        $path = ltrim($databasePath, '/');

        if (! str_starts_with($path, 'storage/graduates/')) {
            return;
        }

        Storage::disk('public')->delete(substr($path, strlen('storage/')));
    }

    private function store(UploadedFile $file, string $directory, string $field): string
    {
        $storedPath = $file->store($directory, 'public');

        if (! is_string($storedPath) || $storedPath === '') {
            throw ValidationException::withMessages([
                $field => "The {$field} could not be stored. Check local storage permissions and try again.",
            ]);
        }

        return 'storage/'.$storedPath;
    }
}
