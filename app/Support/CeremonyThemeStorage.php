<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class CeremonyThemeStorage
{
    public function storeLogo(UploadedFile $file): string
    {
        return $this->store($file, 'ceremonies/theme/logos', 'stage_logo');
    }

    public function storeBackground(UploadedFile $file): string
    {
        return $this->store($file, 'ceremonies/theme/backgrounds', 'stage_background');
    }

    public function delete(?string $path): void
    {
        $path = ltrim((string) $path, '/');

        if (str_starts_with($path, 'storage/ceremonies/theme/')) {
            Storage::disk('public')->delete(substr($path, strlen('storage/')));
        }
    }

    private function store(UploadedFile $file, string $directory, string $field): string
    {
        $path = $file->store($directory, 'public');

        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages([
                $field => 'The stage design image could not be stored locally.',
            ]);
        }

        return 'storage/'.$path;
    }
}
