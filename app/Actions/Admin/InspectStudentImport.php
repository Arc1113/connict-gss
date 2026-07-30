<?php

namespace App\Actions\Admin;

use App\Models\StudentImport;
use App\Models\User;
use App\Services\StudentCsvParser;
use App\Support\StudentImportLimits;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

final class InspectStudentImport
{
    public function __construct(
        private readonly StudentCsvParser $parser,
    ) {}

    public function execute(
        UploadedFile $file,
        int $ceremonyId,
        User $actor,
    ): StudentImport {
        StudentImport::query()
            ->where('expires_at', '<=', now()->utc())
            ->delete();

        $content = $file->getContent();
        $parsed = $this->parser->parse($content);

        return StudentImport::query()->create([
            'token' => (string) Str::uuid(),
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
            'row_count' => count($parsed['rows']),
            'expires_at' => now()->utc()->addMinutes(
                StudentImportLimits::EXPIRATION_MINUTES,
            ),
        ]);
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
