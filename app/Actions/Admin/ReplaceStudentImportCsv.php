<?php

namespace App\Actions\Admin;

use App\Models\StudentImport;
use App\Services\StudentCsvParser;
use Illuminate\Http\UploadedFile;

final class ReplaceStudentImportCsv
{
    public function __construct(
        private readonly StudentCsvParser $parser,
    ) {}

    public function execute(
        StudentImport $studentImport,
        UploadedFile $file,
    ): StudentImport {
        $content = $file->getContent();
        $parsed = $this->parser->parse($content);
        $headersChanged = $parsed['headers'] !== $studentImport->headers;

        $studentImport->update([
            'original_filename' => $this->safeFilename(
                $file->getClientOriginalName(),
            ),
            'delimiter' => $parsed['delimiter'],
            'headers' => $parsed['headers'],
            'mapping' => $headersChanged ? null : $studentImport->mapping,
            'source_content' => $content,
            'row_count' => count($parsed['rows']),
        ]);

        return $studentImport->refresh();
    }

    private function safeFilename(string $filename): string
    {
        $filename = basename(str_replace('\\', '/', $filename));
        $filename = preg_replace('/[\x00-\x1F\x7F]/', '', $filename) ?? '';
        $filename = trim($filename);

        return $filename === ''
            ? 'graduate-import.csv'
            : mb_strimwidth($filename, 0, 255, '', 'UTF-8');
    }
}
