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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\InteractsWithStudentImports;
use Tests\TestCase;

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
