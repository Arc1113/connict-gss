<?php

namespace Tests\Feature\Phase8;

use App\Actions\Admin\StudentAdministration;
use App\Enums\AudioReadinessStatus;
use App\Enums\EligibilityStatus;
use App\Enums\GraduateAudioSourceType;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Ceremony;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AnnouncementAudioReadinessTest extends TestCase
{
    use RefreshDatabase;

    private Ceremony $ceremony;

    private User $administrator;

    private User $encoder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');

        $this->ceremony = Ceremony::factory()->create();
        $this->administrator = User::factory()
            ->role(UserRole::Administrator)
            ->create();
        $this->encoder = User::factory()
            ->role(UserRole::DataEncoder)
            ->create();
    }

    public function test_audio_upload_stores_metadata_and_requires_review(): void
    {
        $this->actingAs($this->encoder)
            ->post('/admin/students', $this->payload([
                'audio' => $this->audio('ana-final-name.wav'),
            ]))
            ->assertRedirect();

        $student = Student::query()->sole();
        $storedPath = substr((string) $student->audio_path, strlen('storage/'));

        Storage::disk('public')->assertExists($storedPath);
        $this->assertStringStartsWith('graduates/audio/', $storedPath);
        $this->assertStringNotContainsString('ana-final-name', $storedPath);
        $this->assertSame(AudioReadinessStatus::Uploaded, $student->audio_readiness_status);
        $this->assertSame(GraduateAudioSourceType::ManualUpload, $student->audio_source_type);
        $this->assertNull($student->audio_generation_id);
        $this->assertNull($student->audio_source_hash);
        $this->assertNotNull($student->audio_uploaded_at);
        $this->assertNull($student->audio_approved_at);
        $this->assertNull($student->audio_approved_by);
        $this->assertNotNull($student->audio_mime_type);
        $this->assertGreaterThan(0, $student->audio_file_size);

        $this->actingAs($this->encoder)
            ->get("/admin/students/{$student->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('student.media.audio_status.value', 'uploaded')
                ->where('student.media.audio_stage_label', 'Audio awaiting review - use live announcer')
                ->where('student.media.audio_url', fn (string $url) => str_starts_with(
                    $url,
                    '/storage/graduates/audio/',
                )));
    }

    public function test_authorized_review_workflow_marks_needs_review_approves_and_rejects_audio(): void
    {
        $student = $this->studentWithUploadedAudio();

        $this->actingAs($this->encoder)
            ->post("/admin/students/{$student->id}/audio/needs-review", [
                'audio_review_notes' => 'Check pronunciation of dela Cruz.',
            ])
            ->assertRedirect();

        $this->assertSame(
            AudioReadinessStatus::NeedsReview,
            $student->refresh()->audio_readiness_status,
        );
        $this->assertSame('Check pronunciation of dela Cruz.', $student->audio_review_notes);

        $this->actingAs($this->administrator)
            ->post("/admin/students/{$student->id}/audio/approve", [
                'audio_review_notes' => 'Pronunciation approved on local speakers.',
            ])
            ->assertRedirect();

        $student->refresh();
        $this->assertSame(AudioReadinessStatus::Approved, $student->audio_readiness_status);
        $this->assertNotNull($student->audio_approved_at);
        $this->assertSame($this->administrator->id, $student->audio_approved_by);

        $this->actingAs($this->administrator)
            ->post("/admin/students/{$student->id}/audio/reject", [
                'audio_review_notes' => 'Wrong announcement name.',
            ])
            ->assertRedirect();

        $student->refresh();
        $this->assertSame(AudioReadinessStatus::Rejected, $student->audio_readiness_status);
        $this->assertNull($student->audio_approved_at);
        $this->assertNull($student->audio_approved_by);
        $this->assertSame('Wrong announcement name.', $student->audio_review_notes);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'student.audio_needs_review',
            'entity_id' => $student->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'student.audio_approved',
            'entity_id' => $student->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'student.audio_rejected',
            'entity_id' => $student->id,
        ]);
    }

    public function test_roles_are_enforced_for_audio_review_actions(): void
    {
        $student = $this->studentWithUploadedAudio();
        $stageOperator = User::factory()->role(UserRole::StageOperator)->create();
        $scanner = User::factory()->role(UserRole::ScannerOperator)->create();
        $inactiveAdmin = User::factory()
            ->role(UserRole::Administrator)
            ->inactive()
            ->create();

        $this->actingAs($this->encoder)
            ->post("/admin/students/{$student->id}/audio/approve")
            ->assertForbidden();
        $this->actingAs($stageOperator)
            ->post("/admin/students/{$student->id}/audio/reject", [
                'audio_review_notes' => 'No access.',
            ])
            ->assertForbidden();
        $this->actingAs($scanner)
            ->post("/admin/students/{$student->id}/audio/needs-review")
            ->assertForbidden();
        $this->actingAs($inactiveAdmin)
            ->post("/admin/students/{$student->id}/audio/approve")
            ->assertRedirect('/login');
    }

    public function test_replacement_invalidates_approval_and_failed_replacement_preserves_old_audio(): void
    {
        $student = $this->studentWithUploadedAudio();
        $this->actingAs($this->administrator)
            ->post("/admin/students/{$student->id}/audio/approve", [
                'audio_review_notes' => 'Approved before replacement.',
            ])
            ->assertRedirect();

        $approvedPath = $student->refresh()->audio_path;
        $approvedStoragePath = substr((string) $approvedPath, strlen('storage/'));

        $this->actingAs($this->encoder)
            ->put("/admin/students/{$student->id}", $this->payload([
                'student_number' => $student->student_number,
                'sequence_number' => $student->sequence_number,
                'audio' => $this->audio('replacement.mp3'),
            ]))
            ->assertRedirect();

        $student->refresh();
        $this->assertNotSame($approvedPath, $student->audio_path);
        $this->assertSame(AudioReadinessStatus::Uploaded, $student->audio_readiness_status);
        $this->assertSame(GraduateAudioSourceType::ManualUpload, $student->audio_source_type);
        $this->assertNull($student->audio_generation_id);
        $this->assertNull($student->audio_source_hash);
        $this->assertNull($student->audio_approved_at);
        $this->assertNull($student->audio_approved_by);
        Storage::disk('public')->assertMissing($approvedStoragePath);

        $replacementPath = $student->audio_path;
        $replacementStoragePath = substr((string) $replacementPath, strlen('storage/'));

        $this->actingAs($this->administrator)
            ->post("/admin/students/{$student->id}/audio/approve")
            ->assertRedirect();

        try {
            app(StudentAdministration::class)->update(
                $student,
                $this->payload([
                    'student_number' => str_repeat('X', 65),
                    'sequence_number' => $student->sequence_number,
                ]),
                null,
                $this->audio('abandoned.wav'),
                $this->administrator,
                '127.0.0.1',
            );

            $this->fail('Expected the database to reject the invalid replacement.');
        } catch (QueryException) {
            // The stored replacement must be compensated after rollback.
        }

        $student->refresh();
        $this->assertSame($replacementPath, $student->audio_path);
        $this->assertSame(AudioReadinessStatus::Approved, $student->audio_readiness_status);
        Storage::disk('public')->assertExists($replacementStoragePath);
        $this->assertCount(1, Storage::disk('public')->allFiles('graduates/audio'));
    }

    public function test_audio_audit_values_exclude_paths_and_binary_content(): void
    {
        $student = $this->studentWithUploadedAudio();

        $this->actingAs($this->administrator)
            ->post("/admin/students/{$student->id}/audio/approve", [
                'audio_review_notes' => 'Safe public note.',
            ])
            ->assertRedirect();

        $encodedAudit = AuditLog::query()
            ->where('entity_type', 'student')
            ->get()
            ->toJson();

        $this->assertStringContainsString('Safe public note.', $encodedAudit);
        $this->assertStringNotContainsString('graduates/audio/', $encodedAudit);
        $this->assertStringNotContainsString('storage/', $encodedAudit);
        $this->assertStringNotContainsString('RIFF', $encodedAudit);
        $this->assertStringNotContainsString($student->qr_token, $encodedAudit);
    }

    private function studentWithUploadedAudio(): Student
    {
        $this->actingAs($this->encoder)
            ->post('/admin/students', $this->payload([
                'audio' => $this->audio('announcement.wav'),
            ]))
            ->assertRedirect();

        return Student::query()->sole();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'ceremony_id' => $this->ceremony->id,
            'student_number' => '2027-0101',
            'first_name' => 'Ana Marie',
            'middle_name' => null,
            'last_name' => 'dela Cruz',
            'suffix' => null,
            'display_name' => 'Ana Marie dela Cruz',
            'announcement_name' => 'Ana Marie dela Cruz',
            'pronunciation_guide' => 'DEH-lah Krooz',
            'college' => 'College of Computing',
            'program' => 'Bachelor of Science in Computer Science',
            'major' => null,
            'honors' => 'Magna Cum Laude',
            'sequence_number' => 101,
            'eligibility_status' => EligibilityStatus::Eligible->value,
            ...$overrides,
        ];
    }

    private function audio(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            (string) file_get_contents(public_path('media/phase3/audio/student-01.wav')),
        );
    }
}
