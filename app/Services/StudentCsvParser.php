<?php

namespace App\Services;

use App\Support\StudentImportLimits;
use Illuminate\Validation\ValidationException;

final class StudentCsvParser
{
    /**
     * @return array{
     *     delimiter: string,
     *     headers: array<int, string>,
     *     rows: array<int, array{
     *         row_number: int,
     *         values: array<int, string>,
     *         issues: array<int, string>
     *     }>
     * }
     */
    public function parse(string $content): array
    {
        if (strlen($content) > StudentImportLimits::MAX_FILE_BYTES) {
            $this->fail('The CSV file must not exceed 2 MiB.');
        }

        $content = $this->removeByteOrderMark($content);

        if (trim($content) === '') {
            $this->fail('The CSV file is empty.');
        }

        if (
            str_contains($content, "\0")
            || preg_match('/[\x01-\x08\x0B\x0C\x0E-\x1F\x7F]/', $content) === 1
        ) {
            $this->fail('The selected file contains binary or unsupported control data.');
        }

        if (! mb_check_encoding($content, 'UTF-8')) {
            $this->fail('The CSV file must use UTF-8 encoding.');
        }

        $delimiter = $this->detectDelimiter($content);
        $handle = $this->stream($content);
        $headers = null;
        $rows = [];
        $recordNumber = 0;

        try {
            while (($record = $this->readRecord($handle, $delimiter)) !== false) {
                $recordNumber++;

                if ($this->isBlankRecord($record)) {
                    continue;
                }

                if ($headers === null) {
                    $headers = array_map(
                        fn (?string $value): string => trim((string) $value),
                        $record,
                    );
                    $this->validateHeaders($headers);

                    continue;
                }

                if (count($rows) >= StudentImportLimits::MAX_ROWS) {
                    $this->fail(
                        'The CSV file contains more than 2,000 graduate rows.',
                    );
                }

                $issues = [];

                if (count($record) !== count($headers)) {
                    $issues[] = sprintf(
                        'Expected %d columns but found %d.',
                        count($headers),
                        count($record),
                    );
                }

                if (count($record) > StudentImportLimits::MAX_COLUMNS) {
                    $issues[] = 'The row exceeds the 40-column limit.';
                }

                $values = [];

                foreach (array_slice($record, 0, count($headers)) as $index => $value) {
                    $cell = (string) $value;

                    if (
                        mb_strlen($cell)
                        > StudentImportLimits::MAX_CELL_CHARACTERS
                    ) {
                        $this->fail(
                            sprintf(
                                'Column “%s” contains a value longer than 2,000 characters.',
                                $headers[$index],
                            ),
                        );
                    }

                    $values[$index] = $cell;
                }

                $values = array_pad($values, count($headers), '');
                $rows[] = [
                    'row_number' => $recordNumber,
                    'values' => $values,
                    'issues' => array_values(array_unique($issues)),
                ];
            }
        } finally {
            fclose($handle);
        }

        if ($headers === null) {
            $this->fail('The CSV file does not contain a header row.');
        }

        if ($rows === []) {
            $this->fail('The CSV file has a header but no graduate rows.');
        }

        return [
            'delimiter' => $delimiter,
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    private function detectDelimiter(string $content): string
    {
        $counts = [];

        foreach ([',', ';', "\t"] as $candidate) {
            $handle = $this->stream($content);
            $header = null;

            try {
                while (($record = $this->readRecord($handle, $candidate)) !== false) {
                    if (! $this->isBlankRecord($record)) {
                        $header = $record;

                        break;
                    }
                }
            } finally {
                fclose($handle);
            }

            $counts[$candidate] = $header === null ? 0 : count($header);
        }

        $highest = max($counts);

        if ($highest < 2) {
            $this->fail(
                'The CSV header must contain multiple comma-, semicolon-, or tab-separated columns.',
            );
        }

        $matches = array_keys(
            array_filter($counts, fn (int $count): bool => $count === $highest),
        );

        if (count($matches) !== 1) {
            $this->fail(
                'The CSV delimiter is ambiguous. Use one consistent comma, semicolon, or tab delimiter.',
            );
        }

        return $matches[0];
    }

    /**
     * @param  array<int, string>  $headers
     */
    private function validateHeaders(array $headers): void
    {
        if (count($headers) > StudentImportLimits::MAX_COLUMNS) {
            $this->fail('The CSV header exceeds the 40-column limit.');
        }

        if (in_array('', $headers, true)) {
            $this->fail('Every CSV column must have a header.');
        }

        $normalized = array_map(
            fn (string $header): string => mb_strtolower($header, 'UTF-8'),
            $headers,
        );

        if (count($normalized) !== count(array_unique($normalized))) {
            $this->fail('CSV headers must be unique, ignoring capitalization.');
        }

        foreach ($headers as $header) {
            if (
                mb_strlen($header)
                > StudentImportLimits::MAX_CELL_CHARACTERS
            ) {
                $this->fail('A CSV header exceeds 2,000 characters.');
            }
        }
    }

    /**
     * @param  resource  $handle
     * @return array<int, string|null>|false
     */
    private function readRecord($handle, string $delimiter): array|false
    {
        return fgetcsv(
            stream: $handle,
            length: 0,
            separator: $delimiter,
            enclosure: '"',
            escape: '',
        );
    }

    /**
     * @param  array<int, string|null>  $record
     */
    private function isBlankRecord(array $record): bool
    {
        foreach ($record as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @return resource
     */
    private function stream(string $content)
    {
        $handle = fopen('php://temp', 'w+b');

        if ($handle === false) {
            throw new \RuntimeException('Unable to open a temporary CSV stream.');
        }

        fwrite($handle, $content);
        rewind($handle);

        return $handle;
    }

    private function removeByteOrderMark(string $content): string
    {
        return str_starts_with($content, "\xEF\xBB\xBF")
            ? substr($content, 3)
            : $content;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages([
            'csv_file' => $message,
        ]);
    }
}
