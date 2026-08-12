<?php

namespace Tests\Feature\Phase5;

use App\Actions\Admin\ConfirmStudentImport;
use App\Enums\CeremonyStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Ceremony;
use App\Models\Student;
use App\Models\StudentImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\InteractsWithStudentImports;
use Tests\TestCase;
use ZipArchive;

class StudentImportPreviewAndConfirmationTest extends TestCase
{
    use InteractsWithStudentImports;
    use RefreshDatabase;

    private User $encoder;

    private Ceremony $ceremony;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->encoder = User::factory()
            ->role(UserRole::DataEncoder)
            ->create();
        $this->ceremony = Ceremony::factory()->create([
            'name' => 'University Commencement 2027',
            'status' => CeremonyStatus::Prepared,
        ]);
    }

    public function test_mapping_requires_each_required_target_only_once(): void
    {
        $studentImport = $this->importWithRows([
            ['2027-0001', 'Ana Marie', '', 'dela Cruz', 'BS Computer Science', 1, 'eligible'],
        ]);

        $this->actingAs($this->encoder)
            ->from("/admin/students/import/{$studentImport->token}/mapping")
            ->post("/admin/students/import/{$studentImport->token}/preview", [
                'mapping' => [
                    'student_number',
                    'first_name',
                    'first_name',
                    'last_name',
                    '',
                    'sequence_number',
                    'eligibility_status',
                ],
            ])
            ->assertRedirect(
                "/admin/students/import/{$studentImport->token}/mapping",
            )
            ->assertSessionHasErrors([
                'mapping',
                'mapping.1',
                'mapping.2',
            ]);

        $this->assertNull($studentImport->refresh()->mapping);
    }

    public function test_preview_normalizes_realistic_rows_and_never_exposes_qr_tokens(): void
    {
        $studentImport = $this->importWithRows($this->realisticRows());

        $this->saveSuggestedMapping($this->encoder, $studentImport)
            ->assertRedirect(
                "/admin/students/import/{$studentImport->token}/preview",
            );

        $this->actingAs($this->encoder)
            ->get("/admin/students/import/{$studentImport->token}/preview")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Students/Import/Preview')
                ->where('preview.total_count', 3)
                ->where('preview.error_count', 0)
                ->where('preview.warning_count', 3)
                ->where('preview.can_confirm', true)
                ->where(
                    'preview.rows.data.0.normalized.display_name',
                    'Ana Marie dela Cruz',
                )
                ->where(
                    'preview.rows.data.1.normalized.display_name',
                    'Christopher Emmanuel Villanueva-Santos',
                )
                ->where(
                    'preview.rows.data.2.normalized.display_name',
                    'Ma. Lourdes P. Reyes',
                )
                ->where(
                    'preview.rows.data.2.normalized.eligibility_status',
                    'pending',
                )
                ->missing('preview.rows.data.0.normalized.qr_token')
                ->missing('studentImport.source_content'));
    }

    public function test_preview_distinguishes_valid_and_missing_required_rows_and_shows_ignored_columns(): void
    {
        $studentImport = $this->inspectCsv(
            $this->encoder,
            $this->ceremony,
            implode("\n", [
                'student_number,first_name,last_name,display_name,announcement_name,program,sequence_number,eligibility_status,operator_notes',
                '2027-VALID,Ana Marie,dela Cruz,Ana Marie dela Cruz,Ana Marie dela Cruz,Bachelor of Science in Computer Science,41,eligible,Reviewed locally',
                '2027-MISSING,Christopher Emmanuel,,Christopher Emmanuel Villanueva-Santos,Christopher Emmanuel Villanueva-Santos,Bachelor of Science in Electronics Engineering,42,eligible,Needs correction',
            ]),
        );
        $this->saveSuggestedMapping($this->encoder, $studentImport);

        $this->actingAs($this->encoder)
            ->get("/admin/students/import/{$studentImport->token}/preview")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('preview.valid_count', 1)
                ->where('preview.warning_count', 0)
                ->where('preview.error_count', 1)
                ->where('preview.can_confirm', false)
                ->where('preview.ignored_columns', ['operator_notes'])
                ->where('preview.rows.data.0.classification', 'valid')
                ->where('preview.rows.data.1.classification', 'error')
                ->where(
                    'preview.rows.data.1.errors',
                    fn ($errors): bool => str_contains(
                        $errors->implode(' '),
                        'last name field is required',
                    ),
                ));
    }

    public function test_preview_reports_in_file_and_database_conflicts_without_writes(): void
    {
        Student::factory()->create([
            'ceremony_id' => $this->ceremony->id,
            'student_number' => 'EXISTING-001',
            'sequence_number' => 10,
        ]);
        $studentImport = $this->importWithRows([
            [' EXISTING-001 ', 'Ana', '', 'Cruz', 'BSCS', 11, 'eligible'],
            ['NEW-002', 'Chris', '', 'Santos', 'BSEE', 20, 'eligible'],
            ['NEW-002', 'Lourdes', '', 'Reyes', 'BSBA', 20, 'unknown'],
        ]);
        $this->saveSuggestedMapping($this->encoder, $studentImport);

        $this->actingAs($this->encoder)
            ->get("/admin/students/import/{$studentImport->token}/preview")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('preview.total_count', 3)
                ->where('preview.error_count', 3)
                ->where('preview.can_confirm', false)
                ->where('preview.rows.data.0.existing_record', true)
                ->where(
                    'preview.rows.data.0.normalized.student_number',
                    'EXISTING-001',
                )
                ->where(
                    'preview.rows.data.2.errors',
                    fn ($errors): bool => str_contains(
                        $errors->implode(' '),
                        'Eligibility must be pending, eligible, or ineligible.',
                    ),
                ));

        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_preview_paginates_large_imports_without_losing_counts(): void
    {
        $rows = [];

        for ($index = 1; $index <= 55; $index++) {
            $rows[] = [
                "2027-{$index}",
                "Graduate {$index}",
                '',
                'Sample',
                'Bachelor of Science in Computer Science',
                $index,
                'eligible',
            ];
        }

        $studentImport = $this->importWithRows($rows);
        $this->saveSuggestedMapping($this->encoder, $studentImport);

        $this->actingAs($this->encoder)
            ->get("/admin/students/import/{$studentImport->token}/preview?page=2")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('preview.total_count', 55)
                ->where('preview.rows.total', 55)
                ->where('preview.rows.current_page', 2)
                ->where('preview.rows.per_page', 50)
                ->has('preview.rows.data', 5));
    }

    public function test_confirmation_atomically_creates_rows_with_internal_tokens_and_safe_audits(): void
    {
        $studentImport = $this->importWithRows($this->realisticRows());
        $this->saveSuggestedMapping($this->encoder, $studentImport);

        $this->confirmImport($this->encoder, $studentImport)
            ->assertRedirect(
                "/admin/students/import/{$studentImport->token}/summary",
            );

        $this->assertDatabaseCount('students', 3);
        $this->assertSame(
            [
                'Ana Marie dela Cruz',
                'Christopher Emmanuel Villanueva-Santos',
                'Ma. Lourdes P. Reyes',
            ],
            Student::query()
                ->orderBy('sequence_number')
                ->pluck('display_name')
                ->all(),
        );

        $tokens = Student::query()->pluck('qr_token');
        $this->assertCount(3, $tokens->unique());

        foreach ($tokens as $token) {
            $this->assertMatchesRegularExpression(
                '/^GSS-[A-Z0-9]{48}$/',
                $token,
            );
        }

        $studentImport->refresh();
        $this->assertTrue($studentImport->isConsumed());
        $this->assertNull($studentImport->source_content);
        $this->assertSame(3, $studentImport->summary['created_count']);
        $this->assertDatabaseCount('audit_logs', 4);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->encoder->id,
            'action' => 'student_import.completed',
            'entity_type' => 'student_import',
            'entity_id' => $studentImport->id,
        ]);

        $encodedAudits = AuditLog::query()->get()->toJson();

        foreach ($tokens as $token) {
            $this->assertStringNotContainsString($token, $encodedAudits);
        }

        $this->assertStringNotContainsString(
            'Christopher Emmanuel Villanueva-Santos',
            $encodedAudits,
        );

        $this->actingAs($this->encoder)
            ->get("/admin/students/import/{$studentImport->token}/summary")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Students/Import/Summary')
                ->where('summary.created_count', 3)
                ->where('summary.updated_count', 0)
                ->where('summary.skipped_count', 0)
                ->where('summary.ceremony_id', $this->ceremony->id)
                ->where(
                    'summary.started_at',
                    fn (string $value): bool => $value !== '',
                )
                ->missing('studentImport.source_content'));

        $this->confirmImport($this->encoder, $studentImport)
            ->assertForbidden();
        $this->assertDatabaseCount('students', 3);

        DB::table('student_imports')
            ->where('id', $studentImport->id)
            ->update(['expires_at' => now()->utc()->subHour()]);

        $this->actingAs($this->encoder)
            ->get("/admin/students/import/{$studentImport->token}/summary")
            ->assertRedirect('/admin/students/import')
            ->assertSessionHas('warning');
        $this->assertDatabaseMissing('student_imports', [
            'id' => $studentImport->id,
        ]);
    }

    public function test_an_unfinished_import_can_change_ceremonies_and_rechecks_conflicts(): void
    {
        $otherCeremony = Ceremony::factory()->create([
            'name' => 'University Commencement 2028',
            'status' => CeremonyStatus::Draft,
        ]);
        Student::factory()->create([
            'ceremony_id' => $this->ceremony->id,
            'student_number' => '2027-RETARGET',
            'sequence_number' => 17,
        ]);

        $studentImport = $this->importWithRows([
            ['2027-RETARGET', 'Ana', '', 'Cruz', 'BSCS', 17, 'eligible'],
        ]);
        $this->saveSuggestedMapping($this->encoder, $studentImport);

        $this->actingAs($this->encoder)
            ->get("/admin/students/import/{$studentImport->token}/preview")
            ->assertInertia(fn (Assert $page) => $page
                ->where('preview.can_confirm', false)
                ->where('preview.rows.data.0.errors.0', 'That student number already exists in the selected ceremony.'));

        $this->actingAs($this->encoder)
            ->patch("/admin/students/import/{$studentImport->token}/ceremony", [
                'ceremony_id' => $otherCeremony->id,
            ])
            ->assertRedirect("/admin/students/import/{$studentImport->token}/preview")
            ->assertSessionHasNoErrors();

        $this->assertSame($otherCeremony->id, $studentImport->refresh()->ceremony_id);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'student_import.ceremony_changed',
            'entity_id' => $studentImport->id,
        ]);

        $this->actingAs($this->encoder)
            ->get("/admin/students/import/{$studentImport->token}/preview")
            ->assertInertia(fn (Assert $page) => $page
                ->where('studentImport.ceremony.id', $otherCeremony->id)
                ->where('preview.can_confirm', true)
                ->where('preview.error_count', 0));
    }

    public function test_photo_path_alias_resolves_a_nested_zip_folder(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $archivePath = tempnam(sys_get_temp_dir(), 'gss-nested-media-');
        $this->assertNotFalse($archivePath);
        $archive = new ZipArchive;
        $this->assertTrue($archive->open(
            $archivePath,
            ZipArchive::CREATE | ZipArchive::OVERWRITE,
        ));
        $archive->addFromString(
            'graduation photos/2027-NESTED.png',
            (string) base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
                true,
            ),
        );
        $archive->close();

        $csv = implode("\n", [
            'student_number,first_name,last_name,program,sequence_number,eligibility_status,photo_path',
            '2027-NESTED,Ana,Cruz,BS Computer Science,91,eligible,photos/graduation photos/2027-NESTED.png',
        ]);

        try {
            $this->actingAs($this->encoder)
                ->post('/admin/students/import/inspect', [
                    'ceremony_id' => $this->ceremony->id,
                    'csv_file' => UploadedFile::fake()->createWithContent('nested.csv', $csv),
                    'media_package' => new UploadedFile(
                        $archivePath,
                        'photos.zip',
                        'application/zip',
                        null,
                        true,
                    ),
                ])
                ->assertRedirect();

            $studentImport = StudentImport::query()->sole();
            $this->saveSuggestedMapping($this->encoder, $studentImport)
                ->assertSessionHasNoErrors();
            $this->actingAs($this->encoder)
                ->get("/admin/students/import/{$studentImport->token}/preview")
                ->assertInertia(fn (Assert $page) => $page
                    ->where('preview.can_confirm', true)
                    ->where('preview.error_count', 0));
        } finally {
            @unlink($archivePath);
        }
    }

    public function test_confirmation_imports_referenced_photos_and_audio_from_a_local_media_zip(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $photo = UploadedFile::fake()->createWithContent(
            'graduate.png',
            (string) base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
                true,
            ),
        );
        $archivePath = tempnam(sys_get_temp_dir(), 'gss-media-test-');
        $this->assertNotFalse($archivePath);
        $archive = new ZipArchive;
        $this->assertTrue($archive->open(
            $archivePath,
            ZipArchive::CREATE | ZipArchive::OVERWRITE,
        ));
        $archive->addFromString('photos/graduate.png', $photo->getContent());
        $archive->addFromString(
            'audio/graduate.wav',
            (string) file_get_contents(public_path('media/phase3/audio/student-01.wav')),
        );
        $archive->close();

        $csv = implode("\n", [
            'student_number,first_name,last_name,program,sequence_number,eligibility_status,photo_filename,audio_filename',
            '2027-MEDIA,Ana,Cruz,BS Computer Science,88,eligible,graduate.png,graduate.wav',
        ]);

        try {
            $this->actingAs($this->encoder)
                ->post('/admin/students/import/inspect', [
                    'ceremony_id' => $this->ceremony->id,
                    'csv_file' => UploadedFile::fake()->createWithContent(
                        'graduates.csv',
                        $csv,
                    ),
                    'media_package' => new UploadedFile(
                        $archivePath,
                        'graduate-media.zip',
                        'application/zip',
                        null,
                        true,
                    ),
                ])
                ->assertRedirect();

            $studentImport = StudentImport::query()->sole();
            $this->assertNotNull($studentImport->media_package_path);
            $this->saveSuggestedMapping($this->encoder, $studentImport)
                ->assertSessionHasNoErrors();
            $this->confirmImport($this->encoder, $studentImport)
                ->assertSessionHasNoErrors();

            $student = Student::query()->sole();
            $this->assertSame('uploaded', $student->audio_readiness_status->value);
            $this->assertStringStartsWith('storage/graduates/photos/', $student->photo_path);
            $this->assertStringStartsWith('storage/graduates/audio/', $student->audio_path);
            Storage::disk('public')->assertExists(
                substr($student->photo_path, strlen('storage/')),
            );
            Storage::disk('public')->assertExists(
                substr($student->audio_path, strlen('storage/')),
            );
            $this->assertSame(1, $studentImport->refresh()->summary['photo_count']);
            $this->assertSame(1, $studentImport->summary['audio_count']);
        } finally {
            @unlink($archivePath);
        }
    }

    public function test_an_incorrect_media_zip_can_be_replaced_without_uploading_the_csv_again(): void
    {
        Storage::fake('local');

        $wrongArchivePath = tempnam(sys_get_temp_dir(), 'gss-wrong-media-');
        $correctArchivePath = tempnam(sys_get_temp_dir(), 'gss-correct-media-');
        $this->assertNotFalse($wrongArchivePath);
        $this->assertNotFalse($correctArchivePath);

        $wrongArchive = new ZipArchive;
        $this->assertTrue($wrongArchive->open(
            $wrongArchivePath,
            ZipArchive::CREATE | ZipArchive::OVERWRITE,
        ));
        $wrongArchive->addFromString('photos/wrong.png', 'not-the-referenced-photo');
        $wrongArchive->close();

        $correctArchive = new ZipArchive;
        $this->assertTrue($correctArchive->open(
            $correctArchivePath,
            ZipArchive::CREATE | ZipArchive::OVERWRITE,
        ));
        $correctArchive->addFromString(
            'photos/graduate.png',
            (string) base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
                true,
            ),
        );
        $correctArchive->close();

        $csv = implode("\n", [
            'student_number,first_name,last_name,program,sequence_number,eligibility_status,photo_filename',
            '2027-REPLACE,Ana,Cruz,BS Computer Science,89,eligible,graduate.png',
        ]);

        try {
            $this->actingAs($this->encoder)
                ->post('/admin/students/import/inspect', [
                    'ceremony_id' => $this->ceremony->id,
                    'csv_file' => UploadedFile::fake()->createWithContent(
                        'graduates.csv',
                        $csv,
                    ),
                    'media_package' => new UploadedFile(
                        $wrongArchivePath,
                        'wrong-media.zip',
                        'application/zip',
                        null,
                        true,
                    ),
                ])
                ->assertRedirect();

            $studentImport = StudentImport::query()->sole();
            $this->saveSuggestedMapping($this->encoder, $studentImport)
                ->assertSessionHasNoErrors();

            $this->actingAs($this->encoder)
                ->get("/admin/students/import/{$studentImport->token}/preview")
                ->assertInertia(fn (Assert $page) => $page
                    ->where('preview.can_confirm', false)
                    ->where('preview.error_count', 1)
                    ->where('studentImport.has_media_package', true));

            $oldPath = $studentImport->media_package_path;
            Storage::disk('local')->assertExists($oldPath);

            $this->actingAs($this->encoder)
                ->from("/admin/students/import/{$studentImport->token}/preview")
                ->post(
                    "/admin/students/import/{$studentImport->token}/media-package",
                    [
                        'media_package' => new UploadedFile(
                            $correctArchivePath,
                            'correct-media.zip',
                            'application/zip',
                            null,
                            true,
                        ),
                    ],
                )
                ->assertRedirect(
                    "/admin/students/import/{$studentImport->token}/preview",
                )
                ->assertSessionHasNoErrors()
                ->assertSessionHas('success');

            $studentImport->refresh();
            $this->assertNotSame($oldPath, $studentImport->media_package_path);
            Storage::disk('local')->assertMissing($oldPath);
            Storage::disk('local')->assertExists($studentImport->media_package_path);

            $this->actingAs($this->encoder)
                ->get("/admin/students/import/{$studentImport->token}/preview")
                ->assertInertia(fn (Assert $page) => $page
                    ->where('preview.can_confirm', true)
                    ->where('preview.error_count', 0));

            $correctedCsv = implode("\n", [
                'student_number,first_name,last_name,program,sequence_number,eligibility_status,photo_filename',
                '2027-REPLACE,Anna,Cruz,BS Computer Science,89,eligible,graduate.png',
            ]);
            $mediaPackagePath = $studentImport->media_package_path;
            $mapping = $studentImport->mapping;

            $this->actingAs($this->encoder)
                ->post(
                    "/admin/students/import/{$studentImport->token}/csv",
                    [
                        'csv_file' => UploadedFile::fake()->createWithContent(
                            'corrected-graduates.csv',
                            $correctedCsv,
                        ),
                    ],
                )
                ->assertRedirect(
                    "/admin/students/import/{$studentImport->token}/preview",
                )
                ->assertSessionHasNoErrors()
                ->assertSessionHas('success');

            $studentImport->refresh();
            $this->assertSame($mediaPackagePath, $studentImport->media_package_path);
            $this->assertSame($mapping, $studentImport->mapping);
            $this->assertSame('corrected-graduates.csv', $studentImport->original_filename);
            Storage::disk('local')->assertExists($mediaPackagePath);

            $this->actingAs($this->encoder)
                ->get("/admin/students/import/{$studentImport->token}/preview")
                ->assertInertia(fn (Assert $page) => $page
                    ->where('preview.can_confirm', true)
                    ->where('preview.rows.data.0.normalized.first_name', 'Anna'));
        } finally {
            @unlink($wrongArchivePath);
            @unlink($correctArchivePath);
        }
    }

    public function test_confirmation_rechecks_current_conflicts_and_ceremony_status(): void
    {
        $studentImport = $this->importWithRows([
            ['2027-RACE', 'Ana', '', 'Cruz', 'BSCS', 15, 'eligible'],
        ]);
        $this->saveSuggestedMapping($this->encoder, $studentImport);
        Student::factory()->create([
            'ceremony_id' => $this->ceremony->id,
            'student_number' => '2027-RACE',
            'sequence_number' => 99,
        ]);

        $this->confirmImport($this->encoder, $studentImport)
            ->assertSessionHasErrors('confirm');
        $this->assertDatabaseCount('students', 1);
        $this->assertFalse($studentImport->refresh()->isConsumed());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'student_import.failed',
        ]);

        $secondImport = $this->importWithRows([
            ['2027-CLOSED', 'Lourdes', '', 'Reyes', 'BSBA', 16, 'eligible'],
        ]);
        $this->saveSuggestedMapping($this->encoder, $secondImport);
        $this->ceremony->update(['status' => CeremonyStatus::Completed]);

        $this->confirmImport($this->encoder, $secondImport)
            ->assertSessionHasErrors('confirm');
        $this->assertDatabaseCount('students', 1);
        $this->assertFalse($secondImport->refresh()->isConsumed());
    }

    public function test_expired_import_is_deleted_and_requires_a_new_upload(): void
    {
        $studentImport = $this->importWithRows([
            ['2027-EXPIRED', 'Ana', '', 'Cruz', 'BSCS', 1, 'eligible'],
        ]);
        $this->saveSuggestedMapping($this->encoder, $studentImport);
        DB::table('student_imports')
            ->where('id', $studentImport->id)
            ->update(['expires_at' => now()->utc()->subHour()]);
        $this->assertTrue($studentImport->refresh()->isExpired());

        $this->confirmImport($this->encoder, $studentImport)
            ->assertSessionHasErrors('confirm');
        $this->assertDatabaseCount('students', 0);
        $this->assertFalse($studentImport->refresh()->isConsumed());

        $this->actingAs($this->encoder)
            ->get("/admin/students/import/{$studentImport->token}/mapping")
            ->assertRedirect('/admin/students/import')
            ->assertSessionHas('warning');

        $this->assertDatabaseMissing('student_imports', [
            'id' => $studentImport->id,
        ]);
    }

    public function test_an_unexpected_mid_import_failure_rolls_back_every_row_and_completion_audit(): void
    {
        $studentImport = $this->importWithRows($this->realisticRows());
        $this->saveSuggestedMapping($this->encoder, $studentImport);
        $connection = DB::connection();
        $originalDispatcher = $connection->getEventDispatcher();
        $isolatedDispatcher = clone $originalDispatcher;
        $connection->setEventDispatcher($isolatedDispatcher);
        $studentInsertCount = 0;

        DB::listen(function ($query) use (&$studentInsertCount): void {
            if (str_starts_with(strtolower($query->sql), 'insert into "students"')) {
                $studentInsertCount++;

                if ($studentInsertCount === 2) {
                    throw new \RuntimeException('Injected second-row failure.');
                }
            }
        });

        try {
            app(ConfirmStudentImport::class)->execute(
                $studentImport,
                $this->encoder,
                '127.0.0.1',
            );
            $this->fail('The injected transaction failure was not raised.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'The import could not be completed. No graduate records were imported.',
                $exception->errors()['confirm'][0],
            );
        } finally {
            $connection->setEventDispatcher($originalDispatcher);
        }

        $this->assertDatabaseCount('students', 0);
        $this->assertFalse($studentImport->refresh()->isConsumed());
        $this->assertFalse(AuditLog::query()
            ->where('action', 'student_import.completed')
            ->exists());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'student_import.failed',
        ]);
    }

    /**
     * @param  array<int, array<int, string|int|null>>  $rows
     */
    private function importWithRows(array $rows): StudentImport
    {
        return $this->inspectCsv(
            $this->encoder,
            $this->ceremony,
            $this->graduateCsv($rows),
        );
    }

    /**
     * @return array<int, array<int, string|int|null>>
     */
    private function realisticRows(): array
    {
        return [
            [
                '2027-0001',
                'Ana Marie',
                '',
                'dela Cruz',
                'Bachelor of Science in Computer Science',
                1,
                'eligible',
            ],
            [
                '2027-0002',
                'Christopher Emmanuel',
                '',
                'Villanueva-Santos',
                'Bachelor of Science in Electronics Engineering',
                2,
                'eligible',
            ],
            [
                '2027-0003',
                'Ma. Lourdes',
                'P.',
                'Reyes',
                'Bachelor of Science in Business Administration',
                3,
                'review',
            ],
        ];
    }
}
