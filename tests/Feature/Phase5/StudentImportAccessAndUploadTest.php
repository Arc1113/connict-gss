<?php

namespace Tests\Feature\Phase5;

use App\Enums\CeremonyStatus;
use App\Enums\UserRole;
use App\Models\Ceremony;
use App\Models\StudentImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\InteractsWithStudentImports;
use Tests\TestCase;

class StudentImportAccessAndUploadTest extends TestCase
{
    use InteractsWithStudentImports;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guests_and_unapproved_operational_roles_cannot_access_imports(): void
    {
        $this->get('/admin/students/import')->assertRedirect('/login');
        $this->get('/admin/students/import/template')->assertRedirect('/login');

        foreach ([
            UserRole::ScannerOperator,
            UserRole::StageOperator,
            UserRole::TechnicalOperator,
        ] as $role) {
            $user = User::factory()->role($role)->create();

            $this->actingAs($user)
                ->get('/admin/students/import')
                ->assertForbidden();
            $this->actingAs($user)
                ->get('/admin/students/import/template')
                ->assertForbidden();
        }
    }

    public function test_inactive_approved_users_cannot_import(): void
    {
        $inactiveEncoder = User::factory()
            ->role(UserRole::DataEncoder)
            ->inactive()
            ->create();

        $this->actingAs($inactiveEncoder)
            ->get('/admin/students/import')
            ->assertRedirect('/login');
        $this->actingAs($inactiveEncoder)
            ->get('/admin/students/import/template')
            ->assertRedirect('/login');
    }

    public function test_administrators_and_data_encoders_can_open_the_import_workspace(): void
    {
        Ceremony::factory()->create([
            'name' => 'College of Computing Commencement',
            'status' => CeremonyStatus::Prepared,
        ]);
        Ceremony::factory()->create([
            'name' => 'Archived rehearsal',
            'status' => CeremonyStatus::Archived,
        ]);

        foreach ([UserRole::Administrator, UserRole::DataEncoder] as $role) {
            $user = User::factory()->role($role)->create();

            $this->actingAs($user)
                ->get('/admin/students/import')
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Admin/Students/Import/Index')
                    ->has('ceremonies', 1)
                    ->where('ceremonies.0.name', 'College of Computing Commencement')
                    ->where('limits.max_rows', 2000)
                    ->where('limits.encoding', 'UTF-8'));
        }
    }

    public function test_imports_are_owner_bound_even_between_approved_users(): void
    {
        $owner = User::factory()->role(UserRole::DataEncoder)->create();
        $otherEncoder = User::factory()->role(UserRole::DataEncoder)->create();
        $administrator = User::factory()->role(UserRole::Administrator)->create();
        $ceremony = Ceremony::factory()->create();
        $studentImport = $this->inspectCsv(
            $owner,
            $ceremony,
            $this->graduateCsv([
                ['2027-0001', 'Ana Marie', '', 'dela Cruz', 'BS Computer Science', 1, 'eligible'],
            ]),
        );

        foreach ([$otherEncoder, $administrator] as $otherUser) {
            $this->actingAs($otherUser)
                ->get("/admin/students/import/{$studentImport->token}/mapping")
                ->assertForbidden();
            $this->actingAs($otherUser)
                ->post(
                    "/admin/students/import/{$studentImport->token}/preview",
                    ['mapping' => []],
                )
                ->assertForbidden();
            $this->actingAs($otherUser)
                ->post(
                    "/admin/students/import/{$studentImport->token}/confirm",
                    ['confirmed' => '1'],
                )
                ->assertForbidden();
        }
    }

    public function test_upload_rejects_unavailable_ceremonies_and_unsafe_csv_content(): void
    {
        $encoder = User::factory()->role(UserRole::DataEncoder)->create();
        $completed = Ceremony::factory()->create([
            'status' => CeremonyStatus::Completed,
        ]);

        $this->actingAs($encoder)
            ->from('/admin/students/import')
            ->post('/admin/students/import/inspect', [
                'ceremony_id' => $completed->id,
                'csv_file' => UploadedFile::fake()->createWithContent(
                    'graduates.csv',
                    $this->graduateCsv([
                        ['2027-0001', 'Ana', '', 'Cruz', 'BSCS', 1, 'eligible'],
                    ]),
                ),
            ])
            ->assertRedirect('/admin/students/import')
            ->assertSessionHasErrors('ceremony_id');

        foreach ([
            'empty.csv' => '',
            'binary.csv' => "student_number,first_name\0last_name\n1,Ana",
            'headers-only.csv' => "student_number,first_name\n",
            'duplicate-headers.csv' => "Name,name\nAna,Cruz\n",
            'ambiguous.csv' => "first,last;program\nAna,Cruz;BSCS\n",
        ] as $filename => $content) {
            $this->actingAs($encoder)
                ->from('/admin/students/import')
                ->post('/admin/students/import/inspect', [
                    'ceremony_id' => Ceremony::factory()->create()->id,
                    'csv_file' => UploadedFile::fake()->createWithContent(
                        $filename,
                        $content,
                    ),
                ])
                ->assertRedirect('/admin/students/import')
                ->assertSessionHasErrors('csv_file');
        }

        $this->assertDatabaseCount('student_imports', 0);
    }

    public function test_upload_enforces_file_row_column_and_cell_limits(): void
    {
        $encoder = User::factory()->role(UserRole::DataEncoder)->create();
        $ceremony = Ceremony::factory()->create();
        $tooManyColumns = implode(
            ',',
            array_map(fn (int $index): string => "field_{$index}", range(1, 41)),
        )."\n".implode(',', array_fill(0, 41, 'value'))."\n";
        $tooLargeCell = "student_number,first_name\n1,".
            str_repeat('x', 2001)."\n";
        $tooManyRows = "student_number,first_name\n".
            implode("\n", array_map(
                fn (int $index): string => "{$index},Graduate {$index}",
                range(1, 2001),
            ));

        foreach ([
            'columns.csv' => $tooManyColumns,
            'cell.csv' => $tooLargeCell,
            'rows.csv' => $tooManyRows,
        ] as $filename => $content) {
            $this->actingAs($encoder)
                ->post('/admin/students/import/inspect', [
                    'ceremony_id' => $ceremony->id,
                    'csv_file' => UploadedFile::fake()->createWithContent(
                        $filename,
                        $content,
                    ),
                ])
                ->assertSessionHasErrors('csv_file');
        }

        $this->actingAs($encoder)
            ->post('/admin/students/import/inspect', [
                'ceremony_id' => $ceremony->id,
                'csv_file' => UploadedFile::fake()->create(
                    'oversized.csv',
                    2049,
                    'text/csv',
                ),
            ])
            ->assertSessionHasErrors('csv_file');

        $this->assertDatabaseCount('student_imports', 0);
    }

    public function test_upload_accepts_utf8_bom_quoted_fields_and_safe_filename_handling(): void
    {
        $encoder = User::factory()->role(UserRole::DataEncoder)->create();
        $ceremony = Ceremony::factory()->create();
        $csv = "\xEF\xBB\xBFstudent_number,first_name,middle_name,last_name,program,sequence_number,eligibility_status,notes\r\n"
            ."2027-0001,\"Ma. Lourdes\",P.,Reyes,\"Bachelor of Science in Business Administration\",1,yes,\"Dean's list, reviewed\"\r\n"
            ."\r\n";

        $studentImport = $this->inspectCsv(
            $encoder,
            $ceremony,
            $csv,
            '../private'.chr(7).'-graduates.csv',
        );

        $this->assertSame('private-graduates.csv', $studentImport->original_filename);
        $this->assertSame(1, $studentImport->row_count);
        $this->assertSame(',', $studentImport->delimiter);
        $this->assertNotNull($studentImport->source_content);
        $this->assertStringContainsString('Ma. Lourdes', $studentImport->source_content);
        $this->assertStringNotContainsString(
            'Ma. Lourdes',
            (string) StudentImport::query()
                ->getQuery()
                ->where('id', $studentImport->id)
                ->value('source_content'),
        );
    }

    public function test_template_is_local_safe_and_excludes_secrets_and_media_paths(): void
    {
        $encoder = User::factory()->role(UserRole::DataEncoder)->create();

        $response = $this->actingAs($encoder)
            ->get('/admin/students/import/template')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('cache-control', 'no-store, private');
        $content = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBFstudent_number", $content);
        $this->assertStringContainsString('eligibility_status', $content);
        $this->assertStringContainsString('photo_filename', $content);
        $this->assertStringContainsString('audio_filename', $content);
        $this->assertStringNotContainsString('qr_token', $content);
        $this->assertStringNotContainsString('photo_path', $content);
        $this->assertStringNotContainsString('audio_path', $content);
    }
}
