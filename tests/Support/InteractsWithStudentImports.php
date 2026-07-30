<?php

namespace Tests\Support;

use App\Models\Ceremony;
use App\Models\StudentImport;
use App\Models\User;
use App\Services\StudentImportPreparation;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;

trait InteractsWithStudentImports
{
    /**
     * @param  array<int, array<int, string|int|null>>  $rows
     */
    protected function graduateCsv(array $rows): string
    {
        $stream = fopen('php://temp', 'w+b');

        if ($stream === false) {
            throw new \RuntimeException('Unable to create a test CSV stream.');
        }

        fputcsv($stream, [
            'student_number',
            'first_name',
            'middle_name',
            'last_name',
            'program',
            'sequence_number',
            'eligibility_status',
        ], ',', '"', '');

        foreach ($rows as $row) {
            fputcsv($stream, $row, ',', '"', '');
        }

        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        if ($content === false) {
            throw new \RuntimeException('Unable to read the test CSV stream.');
        }

        return $content;
    }

    protected function inspectCsv(
        User $actor,
        Ceremony $ceremony,
        string $content,
        string $filename = 'graduate-import.csv',
    ): StudentImport {
        $this->actingAs($actor)
            ->post('/admin/students/import/inspect', [
                'ceremony_id' => $ceremony->id,
                'csv_file' => UploadedFile::fake()->createWithContent(
                    $filename,
                    $content,
                ),
            ])
            ->assertRedirect();

        return StudentImport::query()->latest('id')->firstOrFail();
    }

    protected function saveSuggestedMapping(
        User $actor,
        StudentImport $studentImport,
    ): TestResponse {
        $mapping = app(StudentImportPreparation::class)
            ->suggestedMapping($studentImport->headers);

        return $this->actingAs($actor)->post(
            "/admin/students/import/{$studentImport->token}/preview",
            ['mapping' => $mapping],
        );
    }

    protected function confirmImport(
        User $actor,
        StudentImport $studentImport,
    ): TestResponse {
        return $this->actingAs($actor)->post(
            "/admin/students/import/{$studentImport->token}/confirm",
            ['confirmed' => '1'],
        );
    }
}
