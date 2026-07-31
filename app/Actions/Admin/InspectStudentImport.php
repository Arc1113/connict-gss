<?php

namespace App\Actions\Admin;

use App\Models\StudentImport;
use App\Models\User;
use App\Services\StudentCsvParser;
use App\Support\StudentImportLimits;
use App\Support\StudentImportMediaPackage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

final class InspectStudentImport
{
    public function __construct(
        private readonly StudentCsvParser $parser,
        private readonly StudentImportMediaPackage $mediaPackage,
    ) {}

    public function execute(
        UploadedFile $file,
        ?UploadedFile $package,
        int $ceremonyId,
        User $actor,
    ): StudentImport {
        StudentImport::query()
            ->where('expires_at', '<=', now()->utc())
            ->get()
            ->each
            ->delete();

        $content = $file->getContent();
        $parsed = $this->parser->parse($content);

        $token = (string) Str::uuid();
        $packagePath = $package === null
            ? null
            : $this->mediaPackage->store($package, $token);

        try {
            return StudentImport::query()->create([
                'token' => $token,
                'user_id' => $actor->id,
                'ceremony_id' => $ceremonyId,
                'original_filename' => $this->safeFilename(
                    $file->getClientOriginalName(),
                ),
                'mode' => 'create_only',
                'delimiter' => $parsed['delimiter'],
                'headers' => $parsed['headers'],
                'mapping' => null,
                'source_content' => $content,
                'media_package_path' => $packagePath,
                'row_count' => count($parsed['rows']),
                'expires_at' => now()->utc()->addMinutes(
                    StudentImportLimits::EXPIRATION_MINUTES,
                ),
            ]);
        } catch (\Throwable $exception) {
            $this->mediaPackage->delete($packagePath);
            throw $exception;
        }
    }

    private function safeFilename(string $filename): string
    {
        $filename = basename(str_replace('\\', '/', $filename));
        $filename = preg_replace(
            '/[\x00-\x1F\x7F]/',
            '',
            $filename,
        ) ?? '';
        $filename = trim($filename);

        return $filename === ''
            ? 'graduate-import.csv'
            : mb_strimwidth($filename, 0, 255, '', 'UTF-8');
    }
}
