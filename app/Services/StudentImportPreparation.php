<?php

namespace App\Services;

use App\Enums\EligibilityStatus;
use App\Models\Student;
use App\Models\StudentImport;
use App\Support\StudentImportMediaPackage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class StudentImportPreparation
{
    /**
     * @var array<string, array{label: string, required: bool}>
     */
    private const TARGETS = [
        'student_number' => ['label' => 'Student number', 'required' => true],
        'first_name' => ['label' => 'First name', 'required' => true],
        'middle_name' => ['label' => 'Middle name', 'required' => false],
        'last_name' => ['label' => 'Last name', 'required' => true],
        'suffix' => ['label' => 'Suffix', 'required' => false],
        'display_name' => ['label' => 'Stage display name', 'required' => false],
        'announcement_name' => ['label' => 'Announcement name', 'required' => false],
        'pronunciation_guide' => ['label' => 'Pronunciation guide', 'required' => false],
        'college' => ['label' => 'College', 'required' => false],
        'program' => ['label' => 'Program', 'required' => true],
        'major' => ['label' => 'Major', 'required' => false],
        'honors' => ['label' => 'Honors', 'required' => false],
        'sequence_number' => ['label' => 'Sequence number', 'required' => true],
        'eligibility_status' => ['label' => 'Eligibility', 'required' => true],
        'photo_filename' => ['label' => 'Photo filename', 'required' => false],
        'audio_filename' => ['label' => 'Audio filename', 'required' => false],
    ];

    /**
     * Exact deterministic aliases only. Ambiguous labels such as "name",
     * "number", and "status" intentionally have no suggestion.
     *
     * @var array<string, string>
     */
    private const HEADER_ALIASES = [
        'student_number' => 'student_number',
        'student_no' => 'student_number',
        'student_id_number' => 'student_number',
        'first_name' => 'first_name',
        'given_name' => 'first_name',
        'middle_name' => 'middle_name',
        'last_name' => 'last_name',
        'family_name' => 'last_name',
        'surname' => 'last_name',
        'suffix' => 'suffix',
        'display_name' => 'display_name',
        'stage_display_name' => 'display_name',
        'announcement_name' => 'announcement_name',
        'pronunciation_guide' => 'pronunciation_guide',
        'college' => 'college',
        'program' => 'program',
        'degree_program' => 'program',
        'major' => 'major',
        'honors' => 'honors',
        'honours' => 'honors',
        'sequence_number' => 'sequence_number',
        'sequence_no' => 'sequence_number',
        'eligibility_status' => 'eligibility_status',
        'eligibility' => 'eligibility_status',
        'photo_filename' => 'photo_filename',
        'photo_file' => 'photo_filename',
        'audio_filename' => 'audio_filename',
        'audio_file' => 'audio_filename',
    ];

    public function __construct(
        private readonly StudentCsvParser $parser,
        private readonly StudentImportMediaPackage $mediaPackage,
    ) {}

    /**
     * @return array<int, array{value: string, label: string, required: bool}>
     */
    public function targetOptions(): array
    {
        $options = [];

        foreach (self::TARGETS as $value => $definition) {
            $options[] = [
                'value' => $value,
                'label' => $definition['label'],
                'required' => $definition['required'],
            ];
        }

        return $options;
    }

    /**
     * @param  array<int, string>  $headers
     * @return array<int, string|null>
     */
    public function suggestedMapping(array $headers): array
    {
        $mapping = [];
        $claimed = [];

        foreach ($headers as $index => $header) {
            $target = self::HEADER_ALIASES[$this->normalizeHeader($header)] ?? null;

            if ($target !== null && ! isset($claimed[$target])) {
                $mapping[$index] = $target;
                $claimed[$target] = true;
            } else {
                $mapping[$index] = null;
            }
        }

        return $mapping;
    }

    /**
     * @param  array<int|string, mixed>  $submitted
     * @param  array<int, string>  $headers
     * @return array<int, string|null>
     */
    public function validateMapping(array $submitted, array $headers): array
    {
        $mapping = [];
        $targetIndexes = [];
        $errors = [];

        foreach ($headers as $index => $header) {
            $target = $submitted[$index] ?? null;
            $target = is_string($target) && $target !== '' ? $target : null;

            if ($target !== null && ! array_key_exists($target, self::TARGETS)) {
                $errors["mapping.{$index}"] = "Choose an approved target for “{$header}”.";
                $target = null;
            }

            $mapping[$index] = $target;

            if ($target === null) {
                continue;
            }

            if (isset($targetIndexes[$target])) {
                $firstIndex = $targetIndexes[$target];
                $label = self::TARGETS[$target]['label'];
                $message = "{$label} can be mapped from only one CSV column.";
                $errors["mapping.{$firstIndex}"] = $message;
                $errors["mapping.{$index}"] = $message;
            } else {
                $targetIndexes[$target] = $index;
            }
        }

        $missing = [];

        foreach (self::TARGETS as $target => $definition) {
            if ($definition['required'] && ! isset($targetIndexes[$target])) {
                $missing[] = $definition['label'];
            }
        }

        if ($missing !== []) {
            $errors['mapping'] = 'Map all required fields: '.implode(', ', $missing).'.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $mapping;
    }

    /**
     * @return array{
     *     rows: array<int, array<string, mixed>>,
     *     total_count: int,
     *     valid_count: int,
     *     warning_count: int,
     *     error_count: int,
     *     can_confirm: bool,
     *     mapping: array<int, array<string, mixed>>,
     *     ignored_columns: array<int, string>
     * }
     */
    public function buildPreview(StudentImport $studentImport): array
    {
        if ($studentImport->source_content === null) {
            throw ValidationException::withMessages([
                'confirm' => 'This import has already been confirmed.',
            ]);
        }

        $parsed = $this->parser->parse($studentImport->source_content);

        if (
            $parsed['headers'] !== $studentImport->headers
            || $parsed['delimiter'] !== $studentImport->delimiter
        ) {
            throw ValidationException::withMessages([
                'csv_file' => 'The stored import no longer matches its inspected headers.',
            ]);
        }

        if (! is_array($studentImport->mapping)) {
            throw ValidationException::withMessages([
                'mapping' => 'Review and save the column mapping before previewing rows.',
            ]);
        }

        $mapping = $this->validateMapping(
            $studentImport->mapping,
            $studentImport->headers,
        );
        $rows = [];

        foreach ($parsed['rows'] as $sourceRow) {
            $normalized = $this->normalizeRow($sourceRow['values'], $mapping);
            $warnings = $normalized['warnings'];
            $generatedFields = $normalized['generated_fields'];
            unset($normalized['warnings'], $normalized['generated_fields']);

            $errors = [
                ...$sourceRow['issues'],
                ...$this->validateNormalizedRow($normalized),
                ...$this->mediaPackage->validateRow($studentImport, $normalized),
            ];

            $rows[] = [
                'row_number' => $sourceRow['row_number'],
                'normalized' => $normalized,
                'warnings' => array_values(array_unique($warnings)),
                'errors' => array_values(array_unique($errors)),
                'generated_fields' => $generatedFields,
                'existing_record' => false,
            ];
        }

        $this->applyInFileDuplicates($rows);
        $this->applyDatabaseConflicts($rows, $studentImport->ceremony_id);

        $validCount = 0;
        $warningCount = 0;
        $errorCount = 0;

        foreach ($rows as &$row) {
            if ($row['errors'] !== []) {
                $row['classification'] = 'error';
                $errorCount++;
            } elseif ($row['warnings'] !== []) {
                $row['classification'] = 'warning';
                $warningCount++;
            } else {
                $row['classification'] = 'valid';
                $validCount++;
            }
        }
        unset($row);

        $mappingSummary = [];
        $ignoredColumns = [];

        foreach ($studentImport->headers as $index => $header) {
            $target = $mapping[$index];

            if ($target === null) {
                $ignoredColumns[] = $header;
            }

            $mappingSummary[] = [
                'source' => $header,
                'target' => $target,
                'target_label' => $target === null
                    ? 'Ignored'
                    : self::TARGETS[$target]['label'],
            ];
        }

        return [
            'rows' => $rows,
            'total_count' => count($rows),
            'valid_count' => $validCount,
            'warning_count' => $warningCount,
            'error_count' => $errorCount,
            'can_confirm' => $errorCount === 0,
            'mapping' => $mappingSummary,
            'ignored_columns' => $ignoredColumns,
        ];
    }

    /**
     * @return array<int, array{
     *     index: int,
     *     header: string,
     *     samples: array<int, string>,
     *     suggested_target: string|null
     * }>
     */
    public function mappingRows(StudentImport $studentImport): array
    {
        if ($studentImport->source_content === null) {
            return [];
        }

        $parsed = $this->parser->parse($studentImport->source_content);
        $suggested = $this->suggestedMapping($parsed['headers']);
        $selected = is_array($studentImport->mapping)
            ? $studentImport->mapping
            : $suggested;
        $rows = [];

        foreach ($parsed['headers'] as $index => $header) {
            $samples = [];

            foreach (array_slice($parsed['rows'], 0, 3) as $sourceRow) {
                $value = trim($sourceRow['values'][$index] ?? '');

                if ($value !== '' && ! in_array($value, $samples, true)) {
                    $samples[] = mb_strimwidth($value, 0, 120, '…', 'UTF-8');
                }
            }

            $rows[] = [
                'index' => $index,
                'header' => $header,
                'samples' => $samples,
                'suggested_target' => $suggested[$index],
                'selected_target' => $selected[$index] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, string>  $values
     * @param  array<int, string|null>  $mapping
     * @return array<string, mixed>
     */
    private function normalizeRow(array $values, array $mapping): array
    {
        $record = [];

        foreach ($mapping as $index => $target) {
            if ($target === null) {
                continue;
            }

            $value = trim($values[$index] ?? '');
            $record[$target] = $value === '' ? null : $value;
        }

        foreach (array_keys(self::TARGETS) as $target) {
            $record[$target] = $record[$target] ?? null;
        }

        if (is_string($record['student_number'])) {
            $record['student_number'] = preg_replace(
                '/\s+/u',
                '',
                $record['student_number'],
            );
        }

        if (is_string($record['sequence_number'])) {
            $record['sequence_number'] = preg_match(
                '/^[1-9][0-9]*$/',
                $record['sequence_number'],
            ) === 1
                ? (int) $record['sequence_number']
                : $record['sequence_number'];
        }

        if (is_string($record['eligibility_status'])) {
            $record['eligibility_status'] = $this->normalizeEligibility(
                $record['eligibility_status'],
            );
        }

        $generatedFields = [];
        $warnings = [];
        $suggestedName = $this->suggestName($record);

        foreach ([
            'display_name' => 'Stage display name',
            'announcement_name' => 'Announcement name',
        ] as $field => $label) {
            if ($record[$field] === null && $suggestedName !== null) {
                $record[$field] = $suggestedName;
                $generatedFields[] = $field;
                $warnings[] = "{$label} was generated from the mapped name components.";
            }
        }

        $record['generated_fields'] = $generatedFields;
        $record['warnings'] = $warnings;

        return $record;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<int, string>
     */
    private function validateNormalizedRow(array $record): array
    {
        $validator = Validator::make($record, [
            'student_number' => ['required', 'string', 'max:64'],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'suffix' => ['nullable', 'string', 'max:32'],
            'display_name' => ['required', 'string', 'max:255'],
            'announcement_name' => ['required', 'string', 'max:255'],
            'pronunciation_guide' => ['nullable', 'string', 'max:255'],
            'college' => ['nullable', 'string', 'max:255'],
            'program' => ['required', 'string', 'max:255'],
            'major' => ['nullable', 'string', 'max:255'],
            'honors' => ['nullable', 'string', 'max:1000'],
            'sequence_number' => [
                'required',
                'integer',
                'min:1',
                'max:4294967295',
            ],
            'eligibility_status' => [
                'required',
                Rule::enum(EligibilityStatus::class),
            ],
            'photo_filename' => ['nullable', 'string', 'max:255'],
            'audio_filename' => ['nullable', 'string', 'max:255'],
        ], [
            'eligibility_status.enum' => 'Eligibility must be pending, eligible, or ineligible.',
            'sequence_number.integer' => 'Sequence number must be a positive whole number.',
        ]);

        $errors = $validator->errors()->all();

        foreach ($record as $field => $value) {
            if (
                is_string($value)
                && preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) === 1
            ) {
                $label = self::TARGETS[$field]['label'] ?? $field;
                $errors[] = "{$label} contains an unsupported control character.";
            }
        }

        return array_values(array_unique($errors));
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function applyInFileDuplicates(array &$rows): void
    {
        $numberRows = [];
        $sequenceRows = [];

        foreach ($rows as $index => $row) {
            $number = $row['normalized']['student_number'];
            $sequence = $row['normalized']['sequence_number'];

            if (is_string($number) && $number !== '') {
                $numberRows[$number][] = $index;
            }

            if (is_int($sequence)) {
                $sequenceRows[(string) $sequence][] = $index;
            }
        }

        foreach ($numberRows as $number => $indexes) {
            if (count($indexes) < 2) {
                continue;
            }

            foreach ($indexes as $index) {
                $rows[$index]['errors'][] =
                    "Student number {$number} appears more than once in this CSV.";
            }
        }

        foreach ($sequenceRows as $sequence => $indexes) {
            if (count($indexes) < 2) {
                continue;
            }

            foreach ($indexes as $index) {
                $rows[$index]['errors'][] =
                    "Sequence number {$sequence} appears more than once in this CSV.";
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function applyDatabaseConflicts(array &$rows, ?int $ceremonyId): void
    {
        if ($ceremonyId === null) {
            foreach ($rows as &$row) {
                $row['errors'][] = 'The selected ceremony no longer exists.';
            }
            unset($row);

            return;
        }

        $numbers = [];
        $sequences = [];

        foreach ($rows as $row) {
            $number = $row['normalized']['student_number'];
            $sequence = $row['normalized']['sequence_number'];

            if (is_string($number) && $number !== '') {
                $numbers[] = $number;
            }

            if (is_int($sequence)) {
                $sequences[] = $sequence;
            }
        }

        if ($numbers === [] && $sequences === []) {
            return;
        }

        $existing = Student::query()
            ->where('ceremony_id', $ceremonyId)
            ->where(function ($query) use ($numbers, $sequences): void {
                if ($numbers !== []) {
                    $query->whereIn(
                        DB::raw(
                            "regexp_replace(student_number, '[[:space:]]+', '', 'g')",
                        ),
                        array_values(array_unique($numbers)),
                    );
                }

                if ($sequences !== []) {
                    $method = $numbers === [] ? 'whereIn' : 'orWhereIn';
                    $query->{$method}(
                        'sequence_number',
                        array_values(array_unique($sequences)),
                    );
                }
            })
            ->get(['id', 'student_number', 'sequence_number']);
        $existingNumbers = [];
        $existingSequences = [];

        foreach ($existing as $student) {
            $normalizedNumber = preg_replace(
                '/\s+/u',
                '',
                $student->student_number,
            );
            $existingNumbers[$normalizedNumber] = $student->id;
            $existingSequences[$student->sequence_number] = $student->id;
        }

        foreach ($rows as &$row) {
            $number = $row['normalized']['student_number'];
            $sequence = $row['normalized']['sequence_number'];

            if (is_string($number) && isset($existingNumbers[$number])) {
                $row['existing_record'] = true;
                $row['errors'][] =
                    'That student number already exists in the selected ceremony.';
            }

            if (is_int($sequence) && isset($existingSequences[$sequence])) {
                $row['errors'][] =
                    'That sequence number already exists in the selected ceremony.';
            }
        }
        unset($row);
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function suggestName(array $record): ?string
    {
        if (
            ! is_string($record['first_name'])
            || $record['first_name'] === ''
            || ! is_string($record['last_name'])
            || $record['last_name'] === ''
        ) {
            return null;
        }

        $parts = array_filter([
            $record['first_name'],
            $record['middle_name'],
            $record['last_name'],
            $record['suffix'],
        ], fn ($value): bool => is_string($value) && $value !== '');

        return implode(' ', $parts);
    }

    private function normalizeEligibility(string $value): string
    {
        $normalized = mb_strtolower(trim($value), 'UTF-8');
        $normalized = preg_replace('/[\s_-]+/u', ' ', $normalized);

        return match ($normalized) {
            'eligible', 'yes', 'y', '1' => EligibilityStatus::Eligible->value,
            'ineligible', 'not eligible', 'no', 'n', '0' => EligibilityStatus::Ineligible->value,
            'pending', 'review', 'for review' => EligibilityStatus::Pending->value,
            default => $normalized,
        };
    }

    private function normalizeHeader(string $header): string
    {
        $normalized = mb_strtolower(trim($header), 'UTF-8');
        $normalized = preg_replace('/[^\pL\pN]+/u', '_', $normalized);

        return trim($normalized, '_');
    }
}
