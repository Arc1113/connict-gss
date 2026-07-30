<?php

namespace Tests\Feature\Phase4;

use App\Actions\Admin\StudentAdministration;
use App\Enums\CeremonyStatus;
use App\Enums\EligibilityStatus;
use App\Enums\PresentationMode;
use App\Enums\UserRole;
use App\Events\StudentPresented;
use App\Models\AuditLog;
use App\Models\Ceremony;
use App\Models\StageState;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StudentMediaAndAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    private Ceremony $ceremony;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->administrator = User::factory()
            ->role(UserRole::Administrator)
            ->create();
        $this->ceremony = Ceremony::factory()->create();
    }

    public function test_local_photo_upload_replacement_and_removal_are_safe_and_audited(): void
    {
        $this->actingAs($this->administrator)
            ->post('/admin/students', $this->payload([
                'photo' => $this->photo('approved photo (FINAL) #1.png'),
            ]))
            ->assertRedirect();

        $student = Student::query()->sole();
        $firstPath = $this->storagePath($student->photo_path);

        Storage::disk('public')->assertExists($firstPath);
        $this->assertStringStartsWith('graduates/photos/', $firstPath);
        $this->assertStringNotContainsString('FINAL', $firstPath);
        $this->assertStringNotContainsString('..', $firstPath);

        $this->actingAs($this->administrator)
            ->put("/admin/students/{$student->id}", $this->payload([
                'qr_token' => '',
                'photo' => $this->photo('replacement.png'),
            ]))
            ->assertRedirect();

        $student->refresh();
        $replacementPath = $this->storagePath($student->photo_path);

        $this->assertNotSame($firstPath, $replacementPath);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($replacementPath);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'student.photo_replaced',
            'entity_id' => $student->id,
        ]);

        $this->actingAs($this->administrator)
            ->delete("/admin/students/{$student->id}/photo")
            ->assertRedirect();

        $this->assertNull($student->refresh()->photo_path);
        Storage::disk('public')->assertMissing($replacementPath);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'student.photo_removed',
            'entity_id' => $student->id,
        ]);
    }

    public function test_invalid_or_oversized_media_is_rejected_without_orphaned_files(): void
    {
        $this->actingAs($this->administrator)
            ->from('/admin/students/create')
            ->post('/admin/students', $this->payload([
                'photo' => UploadedFile::fake()->create(
                    'not-an-image.jpg',
                    20,
                    'text/plain',
                ),
            ]))
            ->assertSessionHasErrors('photo');

        $this->actingAs($this->administrator)
            ->post('/admin/students', $this->payload([
                'photo' => $this->photo('oversized.png')->size(6 * 1024),
            ]))
            ->assertSessionHasErrors('photo');

        $this->actingAs($this->administrator)
            ->post('/admin/students', $this->payload([
                'audio' => UploadedFile::fake()->create(
                    'not-audio.mp3',
                    20,
                    'text/plain',
                ),
            ]))
            ->assertSessionHasErrors('audio');

        $this->actingAs($this->administrator)
            ->post('/admin/students', $this->payload([
                'audio' => $this->audio('oversized.wav')->size(16 * 1024),
            ]))
            ->assertSessionHasErrors('audio');

        $this->assertDatabaseCount('students', 0);
        Storage::disk('public')->assertDirectoryEmpty('graduates');
    }

    public function test_audio_upload_preview_path_and_removal_remain_local(): void
    {
        $audio = $this->audio('ana announcement (final) #1.wav');

        $this->actingAs($this->administrator)
            ->post('/admin/students', $this->payload(['audio' => $audio]))
            ->assertRedirect();

        $student = Student::query()->sole();
        $audioPath = $this->storagePath($student->audio_path);

        Storage::disk('public')->assertExists($audioPath);
        $this->assertStringStartsWith('graduates/audio/', $audioPath);
        $this->assertStringNotContainsString('final', $audioPath);
        $this->assertStringNotContainsString('..', $audioPath);

        $this->actingAs($this->administrator)
            ->get("/admin/students/{$student->id}/edit")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where(
                    'student.audio_url',
                    fn (string $url) => str_starts_with(
                        $url,
                        '/storage/graduates/audio/',
                    ),
                ));

        $this->actingAs($this->administrator)
            ->delete("/admin/students/{$student->id}/audio")
            ->assertRedirect();

        $this->assertNull($student->refresh()->audio_path);
        Storage::disk('public')->assertMissing($audioPath);
    }

    public function test_a_failed_replacement_keeps_the_current_file_and_removes_the_new_file(): void
    {
        $this->actingAs($this->administrator)
            ->post('/admin/students', $this->payload([
                'photo' => $this->photo('current-approved.png'),
            ]))
            ->assertRedirect();

        $student = Student::query()->sole();
        $currentPath = $this->storagePath($student->photo_path);
        $auditCount = AuditLog::query()->count();

        try {
            app(StudentAdministration::class)->update(
                $student,
                $this->payload([
                    'student_number' => str_repeat('X', 65),
                ]),
                $this->photo('abandoned replacement (final).png'),
                null,
                $this->administrator,
                '127.0.0.1',
            );

            $this->fail('The database was expected to reject the oversized value.');
        } catch (QueryException) {
            // The action must compensate for storage written before the transaction.
        }

        $this->assertSame(
            'storage/'.$currentPath,
            $student->refresh()->photo_path,
        );
        $this->assertSame($auditCount, AuditLog::query()->count());
        Storage::disk('public')->assertExists($currentPath);
        $this->assertCount(
            1,
            Storage::disk('public')->allFiles('graduates/photos'),
        );
    }

    public function test_a_postgresql_uniqueness_race_returns_a_friendly_error_and_cleans_media(): void
    {
        Student::factory()->create([
            'ceremony_id' => $this->ceremony->id,
            'student_number' => '2027-0101',
        ]);

        try {
            app(StudentAdministration::class)->create(
                $this->payload(),
                $this->photo('abandoned unique conflict.png'),
                null,
                $this->administrator,
                '127.0.0.1',
            );

            $this->fail('The duplicate student number was expected to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'That student number is already used in the selected ceremony.',
                $exception->errors()['student_number'][0],
            );
        }

        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseCount('audit_logs', 0);
        Storage::disk('public')->assertDirectoryEmpty('graduates');
    }

    public function test_audit_values_never_store_raw_qr_tokens_or_media_paths(): void
    {
        $student = Student::factory()->create([
            'ceremony_id' => $this->ceremony->id,
            'qr_token' => 'OLD-SECRET-TOKEN',
        ]);
        $newToken = 'NEW-SUPER-SECRET-TOKEN';

        $this->actingAs($this->administrator)
            ->put("/admin/students/{$student->id}", $this->payload([
                'student_number' => $student->student_number,
                'sequence_number' => $student->sequence_number,
                'qr_token' => $newToken,
                'photo' => $this->photo('private-name.png'),
            ]))
            ->assertRedirect();

        $this->assertSame('OLD-SECRET-TOKEN', $student->refresh()->qr_token);

        $encodedAudit = AuditLog::query()
            ->where('entity_type', 'student')
            ->get()
            ->toJson();

        $this->assertStringNotContainsString('OLD-SECRET-TOKEN', $encodedAudit);
        $this->assertStringNotContainsString($newToken, $encodedAudit);
        $this->assertStringNotContainsString('private-name.jpg', $encodedAudit);
        $this->assertStringNotContainsString('graduates/photos/', $encodedAudit);
    }

    public function test_uploaded_media_paths_are_compatible_with_the_existing_stage_payload(): void
    {
        Event::fake([StudentPresented::class]);
        $this->ceremony->update([
            'status' => CeremonyStatus::Active,
            'activated_at' => now(),
            'presentation_mode' => PresentationMode::Automatic,
        ]);
        StageState::factory()->create(['ceremony_id' => $this->ceremony->id]);
        $scanner = User::factory()->role(UserRole::ScannerOperator)->create();

        $this->actingAs($this->administrator)
            ->post('/admin/students', $this->payload([
                'photo' => $this->photo('stage-photo.png'),
            ]))
            ->assertRedirect();

        $student = Student::query()->sole();

        $this->actingAs($scanner)
            ->postJson('/scans', ['token' => $student->qr_token])
            ->assertCreated()
            ->assertJsonPath(
                'student.display_name',
                'Christopher Emmanuel Villanueva-Santos',
            );

        Event::assertDispatched(
            StudentPresented::class,
            function (StudentPresented $event): bool {
                $payload = $event->broadcastWith();

                $this->assertStringStartsWith(
                    '/storage/graduates/photos/',
                    $payload['photo_url'],
                );

                return true;
            },
        );
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
            'first_name' => 'Christopher Emmanuel',
            'middle_name' => null,
            'last_name' => 'Villanueva-Santos',
            'suffix' => null,
            'display_name' => 'Christopher Emmanuel Villanueva-Santos',
            'announcement_name' => 'Christopher Emmanuel Villanueva-Santos',
            'pronunciation_guide' => null,
            'college' => 'College of Engineering',
            'program' => 'Bachelor of Science in Electronics Engineering',
            'major' => null,
            'honors' => null,
            'sequence_number' => 101,
            'eligibility_status' => EligibilityStatus::Eligible->value,
            ...$overrides,
        ];
    }

    private function storagePath(?string $databasePath): string
    {
        $this->assertNotNull($databasePath);

        return substr($databasePath, strlen('storage/'));
    }

    private function photo(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            (string) base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
                true,
            ),
        );
    }

    private function audio(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            (string) file_get_contents(
                public_path('media/phase3/audio/student-01.wav'),
            ),
        );
    }
}
