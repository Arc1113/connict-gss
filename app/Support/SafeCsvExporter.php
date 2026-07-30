<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class SafeCsvExporter
{
    public function __construct(
        private SafeOperationalText $text,
    ) {}

    /**
     * @param  array<string, string>  $headers
     * @param  iterable<array<string, mixed>>  $rows
     */
    public function response(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, array_values($headers));

            foreach ($rows as $row) {
                fputcsv(
                    $handle,
                    collect(array_keys($headers))
                        ->map(fn (string $key): string => $this->text->csvCell($row[$key] ?? null))
                        ->all(),
                );
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
