<?php

namespace App\Support;

use App\Models\StudentImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use ZipArchive;

final class StudentImportMediaPackage
{
    private const MAX_ENTRIES = 4000;

    private const MAX_UNCOMPRESSED_BYTES = 512 * 1024 * 1024;

    private const PHOTO_MAX_BYTES = 5 * 1024 * 1024;

    private const AUDIO_MAX_BYTES = 15 * 1024 * 1024;

    private const PHOTO_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    private const AUDIO_EXTENSIONS = ['mp3', 'wav', 'ogg', 'webm'];

    public function __construct(
        private readonly StudentMediaStorage $mediaStorage,
    ) {}

    public function store(UploadedFile $package, string $token): string
    {
        $this->inspectArchive($package->getRealPath());
        $path = $package->storeAs('student-imports', "{$token}.zip", 'local');

        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages([
                'media_package' => 'The media package could not be stored locally.',
            ]);
        }

        return $path;
    }

    /**
     * @return list<string>
     */
    public function validateRow(StudentImport $studentImport, array $row): array
    {
        $errors = [];

        foreach ([
            'photo_filename' => 'photo',
            'audio_filename' => 'audio',
        ] as $field => $kind) {
            $reference = $row[$field] ?? null;

            if ($reference === null || $reference === '') {
                continue;
            }

            if ($studentImport->media_package_path === null) {
                $errors[] = ucfirst($kind).' filename is present but no media ZIP was uploaded.';

                continue;
            }

            try {
                $this->entry($studentImport, (string) $reference, $kind);
            } catch (ValidationException $exception) {
                $fieldErrors = $exception->errors()[$field] ?? [];
                $errors = [...$errors, ...$fieldErrors];
            }
        }

        return $errors;
    }

    /**
     * @return array{
     *     photo_path: string|null,
     *     audio_path: string|null,
     *     audio_metadata: array{audio_mime_type: string|null, audio_file_size: int|null}
     * }
     */
    public function importRow(StudentImport $studentImport, array $row): array
    {
        $photoPath = null;
        $audioPath = null;
        $audioMetadata = [
            'audio_mime_type' => null,
            'audio_file_size' => null,
        ];

        try {
            if (filled($row['photo_filename'] ?? null)) {
                $photo = $this->temporaryUpload(
                    $studentImport,
                    (string) $row['photo_filename'],
                    'photo',
                );
                try {
                    $photoPath = $this->mediaStorage->storePhoto($photo);
                } finally {
                    @unlink($photo->getRealPath());
                }
            }

            if (filled($row['audio_filename'] ?? null)) {
                $audio = $this->temporaryUpload(
                    $studentImport,
                    (string) $row['audio_filename'],
                    'audio',
                );
                try {
                    $audioPath = $this->mediaStorage->storeAudio($audio);
                    $audioMetadata = $this->mediaStorage->audioMetadata($audio);
                } finally {
                    @unlink($audio->getRealPath());
                }
            }
        } catch (\Throwable $exception) {
            $this->mediaStorage->deleteManaged($photoPath);
            $this->mediaStorage->deleteManaged($audioPath);
            throw $exception;
        }

        return [
            'photo_path' => $photoPath,
            'audio_path' => $audioPath,
            'audio_metadata' => $audioMetadata,
        ];
    }

    public function delete(?string $path): void
    {
        if ($path !== null) {
            Storage::disk('local')->delete($path);
        }
    }

    /**
     * @return array{name: string, size: int, index: int}
     */
    private function entry(
        StudentImport $studentImport,
        string $reference,
        string $kind,
    ): array {
        $field = $kind.'_filename';
        $name = $this->normalizeReference($reference, $kind, $field);
        $zip = $this->openStored($studentImport);

        try {
            $matches = $this->matchingEntries($zip, $name, $kind);

            if (count($matches) === 0) {
                throw ValidationException::withMessages([
                    $field => "{$name} was not found in the media ZIP.",
                ]);
            }

            if (count($matches) > 1) {
                throw ValidationException::withMessages([
                    $field => "{$name} matches more than one file in the media ZIP.",
                ]);
            }

            $index = $matches[0];

            $stat = $zip->statIndex($index);
            if (! is_array($stat)) {
                throw ValidationException::withMessages([
                    $field => "{$name} could not be read from the media ZIP.",
                ]);
            }

            $max = $kind === 'photo' ? self::PHOTO_MAX_BYTES : self::AUDIO_MAX_BYTES;
            if (($stat['size'] ?? 0) <= 0 || ($stat['size'] ?? 0) > $max) {
                throw ValidationException::withMessages([
                    $field => ucfirst($kind).' file size is invalid or exceeds its limit.',
                ]);
            }

            return [
                'name' => (string) ($stat['name'] ?? $name),
                'size' => (int) $stat['size'],
                'index' => $index,
            ];
        } finally {
            $zip->close();
        }
    }

    private function temporaryUpload(
        StudentImport $studentImport,
        string $reference,
        string $kind,
    ): UploadedFile {
        $entry = $this->entry($studentImport, $reference, $kind);
        $zip = $this->openStored($studentImport);
        $stream = $zip->getStream($entry['name']);
        $temporary = tempnam(sys_get_temp_dir(), 'gss-import-media-');

        if (! is_resource($stream) || $temporary === false) {
            $zip->close();
            throw ValidationException::withMessages([
                $kind.'_filename' => ucfirst($kind).' could not be extracted safely.',
            ]);
        }

        $target = fopen($temporary, 'wb');
        if ($target === false) {
            fclose($stream);
            $zip->close();
            @unlink($temporary);
            throw ValidationException::withMessages([
                $kind.'_filename' => ucfirst($kind).' could not be prepared locally.',
            ]);
        }

        stream_copy_to_stream($stream, $target, $entry['size']);
        fclose($stream);
        fclose($target);
        $zip->close();

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($temporary) ?: null;
        $allowedMimes = $kind === 'photo'
            ? ['image/jpeg', 'image/png', 'image/webp']
            : ['audio/mpeg', 'audio/wav', 'audio/x-wav', 'audio/ogg', 'audio/webm', 'video/webm'];

        if (! in_array($mime, $allowedMimes, true)) {
            @unlink($temporary);
            throw ValidationException::withMessages([
                $kind.'_filename' => ucfirst($kind).' content does not match an approved media format.',
            ]);
        }

        return new UploadedFile(
            $temporary,
            basename($entry['name']),
            $mime,
            UPLOAD_ERR_OK,
            true,
        );
    }

    private function normalizeReference(string $reference, string $kind, string $field): string
    {
        $reference = trim(str_replace('\\', '/', $reference));
        $prefix = $kind === 'photo' ? 'photos/' : 'audio/';
        $extensions = $kind === 'photo' ? self::PHOTO_EXTENSIONS : self::AUDIO_EXTENSIONS;

        if (
            $reference === ''
            || strlen($reference) > 255
            || str_contains($reference, '..')
            || str_starts_with($reference, '/')
        ) {
            throw ValidationException::withMessages([
                $field => ucfirst($kind).' filename is not a safe relative path.',
            ]);
        }

        $filenameOnly = ! str_contains($reference, '/');

        if ($filenameOnly) {
            $reference = $prefix.$reference;
        }

        if (! str_starts_with(strtolower($reference), $prefix)) {
            $reference = $prefix.$reference;
        }

        if (! str_starts_with(strtolower($reference), $prefix)) {
            throw ValidationException::withMessages([
                $field => ucfirst($kind)." files must be inside {$prefix} in the ZIP.",
            ]);
        }

        if (! in_array(strtolower(pathinfo($reference, PATHINFO_EXTENSION)), $extensions, true)) {
            throw ValidationException::withMessages([
                $field => ucfirst($kind).' filename has an unsupported extension.',
            ]);
        }

        return $reference;
    }

    /**
     * Resolve exact paths first, then safe folder-prefix equivalents. A
     * basename fallback is only used for filename-only CSV references.
     *
     * @return list<int>
     */
    private function matchingEntries(ZipArchive $zip, string $name, string $kind): array
    {
        $prefix = $kind === 'photo' ? 'photos/' : 'audio/';
        $normalizedName = strtolower(str_replace('\\', '/', $name));
        $relativeName = substr($normalizedName, strlen($prefix));
        $basenameOnly = ! str_contains($relativeName, '/');
        $matches = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            $entryName = is_array($stat) ? (string) ($stat['name'] ?? '') : '';
            $entryName = strtolower(str_replace('\\', '/', $entryName));

            if ($entryName === '' || str_ends_with($entryName, '/')) {
                continue;
            }

            $exact = $entryName === $normalizedName;
            $rootRelative = $entryName === $relativeName;
            $wrapped = str_ends_with($entryName, '/'.$normalizedName)
                || str_ends_with($entryName, '/'.$relativeName);
            $basename = $basenameOnly
                && basename($entryName) === basename($relativeName);

            if ($exact || $rootRelative || $wrapped || $basename) {
                $matches[] = $index;
            }
        }

        return array_values(array_unique($matches));
    }

    private function inspectArchive(string $path): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages([
                'media_package' => 'Select a readable ZIP media package.',
            ]);
        }

        try {
            if ($zip->numFiles > self::MAX_ENTRIES) {
                throw ValidationException::withMessages([
                    'media_package' => 'The media ZIP contains too many files.',
                ]);
            }

            $total = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                $name = is_array($stat) ? (string) ($stat['name'] ?? '') : '';

                if (
                    $name === ''
                    || str_contains($name, '\\')
                    || str_contains($name, '../')
                    || str_starts_with($name, '/')
                ) {
                    throw ValidationException::withMessages([
                        'media_package' => 'The media ZIP contains an unsafe file path.',
                    ]);
                }

                $total += (int) ($stat['size'] ?? 0);
                if ($total > self::MAX_UNCOMPRESSED_BYTES) {
                    throw ValidationException::withMessages([
                        'media_package' => 'The expanded media ZIP is larger than 512 MiB.',
                    ]);
                }
            }
        } finally {
            $zip->close();
        }
    }

    private function openStored(StudentImport $studentImport): ZipArchive
    {
        if ($studentImport->media_package_path === null) {
            throw ValidationException::withMessages([
                'media_package' => 'This import does not have a media ZIP.',
            ]);
        }

        $zip = new ZipArchive;
        $path = Storage::disk('local')->path($studentImport->media_package_path);
        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages([
                'media_package' => 'The stored media ZIP is unavailable. Start the import again.',
            ]);
        }

        return $zip;
    }
}
