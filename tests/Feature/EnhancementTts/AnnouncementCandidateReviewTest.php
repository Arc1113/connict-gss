<?php

namespace Tests\Feature\EnhancementTts;

use App\Actions\Admin\StudentAdministration;
use App\Actions\ReviewAnnouncementCandidate;
use App\Enums\AnnouncementGenerationStatus;
use App\Enums\AnnouncementProvider;
use App\Enums\AudioReadinessStatus;
use App\Enums\GraduateAudioSourceType;
use App\Enums\UserRole;
use App\Models\AnnouncementGeneration;
use App\Models\AnnouncementTemplate;
use App\Models\AuditLog;
use App\Models\Ceremony;
use App\Models\Student;
use App\Models\StudentAward;
use App\Models\User;
use App\Services\PreviewGraduationAnnouncement;
use App\Support\AnnouncementAudio;
use App\Support\BackupManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AnnouncementCandidateReviewTest extends AnnouncementTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('local');
        Storage::fake('public');
        config()->set('announcements.azure.maximum_response_bytes', 1024);
    }

    public function test_administrator_approves_candidate_and_manual_upload_is_replaced_safely(): void
    {
        [$student, $template] = $this->fixture();
        $oldPath = 'storage/graduates/audio/manual.mp3';
        Storage::disk('public')->put('graduates/audio/manual.mp3', $this->mp3());
        $student->update([
            'audio_path' => $oldPath,
            'audio_source_type' => GraduateAudioSourceType::ManualUpload,
            'audio_readiness_status' => AudioReadinessStatus::Approved,
        ]);
        $generation = $this->candidate($student, $template);
        $administrator = User::factory()->role(UserRole::Administrator)->create();

        $this->actingAs($administrator)->post(route(
            'admin.students.announcement-candidates.approve',
            [$student, $generation],
        ), ['review_notes' => 'Pronunciation and pacing verified.'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $student->refresh();
        $generation->refresh();
        $this->assertSame(AnnouncementGenerationStatus::Approved, $generation->status);
        $this->assertSame(GraduateAudioSourceType::AzureTts, $student->audio_source_type);
        $this->assertSame($generation->id, $student->audio_generation_id);
        $this->assertSame($generation->source_hash, $student->audio_source_hash);
        $this->assertSame(AudioReadinessStatus::Approved, $student->audio_readiness_status);
        $this->assertSame($generation->approved_audio_path, $student->audio_path);
        Storage::disk('public')->assertExists(substr($student->audio_path, strlen('storage/')));
        Storage::disk('public')->assertMissing('graduates/audio/manual.mp3');
        $this->assertNotNull(app(AnnouncementAudio::class)->approvedUrl($student));
    }

    public function test_rejection_and_authorization_preserve_active_audio(): void
    {
        [$student, $template] = $this->fixture();
        $student->update([
            'audio_path' => 'storage/graduates/audio/existing.mp3',
            'audio_source_type' => GraduateAudioSourceType::ManualUpload,
            'audio_readiness_status' => AudioReadinessStatus::Approved,
        ]);
        Storage::disk('public')->put('graduates/audio/existing.mp3', $this->mp3());
        $generation = $this->candidate($student, $template);
        $encoder = User::factory()->role(UserRole::DataEncoder)->create();
        $administrator = User::factory()->role(UserRole::Administrator)->create();

        $this->actingAs($encoder)->post(route(
            'admin.students.announcement-candidates.approve',
            [$student, $generation],
        ))->assertForbidden();

        $this->actingAs($administrator)->post(route(
            'admin.students.announcement-candidates.reject',
            [$student, $generation],
        ), ['review_notes' => 'Name emphasis needs another pass.'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(AnnouncementGenerationStatus::Rejected, $generation->refresh()->status);
        $this->assertSame('storage/graduates/audio/existing.mp3', $student->refresh()->audio_path);
        Storage::disk('public')->assertExists('graduates/audio/existing.mp3');
    }

    public function test_stale_candidate_cannot_be_approved_and_audit_is_sanitized(): void
    {
        [$student, $template] = $this->fixture();
        $generation = $this->candidate($student, $template);
        $administrator = User::factory()->role(UserRole::Administrator)->create();
        Student::query()->whereKey($student->id)->update(['honors' => 'summa cum laude']);

        $this->actingAs($administrator)->post(route(
            'admin.students.announcement-candidates.approve',
            [$student, $generation],
        ))->assertSessionHasErrors('announcement_review');

        $this->assertSame(AnnouncementGenerationStatus::Outdated, $generation->refresh()->status);
        $this->assertNull($student->refresh()->audio_path);
        $audit = AuditLog::query()->where('action', 'announcement_generation.outdated')->sole()->toJson();
        $this->assertStringNotContainsString('announcement-candidates', $audit);
        $this->assertStringNotContainsString('<speak', $audit);
        $this->assertStringNotContainsString($generation->source_hash, $audit);
    }

    public function test_candidate_without_playable_audio_frames_cannot_be_approved(): void
    {
        [$student, $template] = $this->fixture();
        $generation = $this->candidate($student, $template);
        Storage::disk('local')->put(
            $generation->candidate_audio_path,
            'ID3'.str_repeat("\0", 64),
        );
        $administrator = User::factory()->role(UserRole::Administrator)->create();

        $this->actingAs($administrator)->post(route(
            'admin.students.announcement-candidates.approve',
            [$student, $generation],
        ))->assertSessionHasErrors('announcement_review');

        $this->assertSame(AnnouncementGenerationStatus::NeedsReview, $generation->refresh()->status);
        $this->assertNull($student->refresh()->audio_path);
        Storage::disk('public')->assertDirectoryEmpty('graduates/audio');
    }

    public function test_source_and_template_changes_invalidate_candidates_and_stage_excludes_stale_audio(): void
    {
        [$student, $template] = $this->fixture();
        $studentGeneration = $this->approvedCandidate($student, $template);
        $student->update(['honors' => 'magna cum laude']);
        $this->assertSame(AnnouncementGenerationStatus::Outdated, $studentGeneration->refresh()->status);
        $this->assertSame(AudioReadinessStatus::NeedsReview, $student->refresh()->audio_readiness_status);
        $this->assertNull(app(AnnouncementAudio::class)->approvedUrl($student));

        [$awardStudent, $awardTemplate] = $this->fixture();
        $awardGeneration = $this->candidate($awardStudent, $awardTemplate);
        StudentAward::factory()->for($awardStudent)->create();
        $this->assertSame(AnnouncementGenerationStatus::Outdated, $awardGeneration->refresh()->status);

        [$templateStudent, $changedTemplate] = $this->fixture();
        $templateGeneration = $this->candidate($templateStudent, $changedTemplate);
        $changedTemplate->update(['default_voice' => 'en-PH-RosaNeural']);
        $this->assertSame(AnnouncementGenerationStatus::Outdated, $templateGeneration->refresh()->status);
    }

    public function test_database_failure_after_promotion_preserves_active_audio_and_removes_new_file(): void
    {
        [$student, $template] = $this->fixture();
        $oldPath = 'storage/graduates/audio/keep.mp3';
        Storage::disk('public')->put('graduates/audio/keep.mp3', $this->mp3());
        $student->update([
            'audio_path' => $oldPath,
            'audio_source_type' => GraduateAudioSourceType::ManualUpload,
            'audio_readiness_status' => AudioReadinessStatus::Approved,
        ]);
        $generation = $this->candidate($student, $template);
        $administrator = User::factory()->role(UserRole::Administrator)->create();

        DB::unprepared(<<<'SQL'
            CREATE FUNCTION fail_generated_audio_promotion() RETURNS trigger AS $$
            BEGIN
                IF NEW.audio_source_type = 'azure_tts' THEN
                    RAISE EXCEPTION 'simulated promotion database failure';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER fail_generated_audio_promotion
            BEFORE UPDATE ON students
            FOR EACH ROW EXECUTE FUNCTION fail_generated_audio_promotion();
            SQL);

        try {
            app(ReviewAnnouncementCandidate::class)->approve(
                $student,
                $generation,
                null,
                $administrator,
                '127.0.0.1',
            );
            $this->fail('Expected the simulated database failure.');
        } catch (Throwable $exception) {
            $this->assertStringContainsString('simulated promotion database failure', $exception->getMessage());
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS fail_generated_audio_promotion ON students; DROP FUNCTION IF EXISTS fail_generated_audio_promotion();');
        }

        $this->assertSame($oldPath, $student->refresh()->audio_path);
        $this->assertSame(AnnouncementGenerationStatus::NeedsReview, $generation->refresh()->status);
        $this->assertSame(['graduates/audio/keep.mp3'], Storage::disk('public')->allFiles('graduates/audio'));
    }

    public function test_manual_upload_supersedes_approved_generated_audio(): void
    {
        [$student, $template] = $this->fixture();
        $generation = $this->approvedCandidate($student, $template);
        $encoder = User::factory()->role(UserRole::DataEncoder)->create();

        app(StudentAdministration::class)->update(
            $student,
            [],
            null,
            UploadedFile::fake()->createWithContent('manual.mp3', $this->mp3()),
            $encoder,
            '127.0.0.1',
        );

        $student->refresh();
        $this->assertSame(GraduateAudioSourceType::ManualUpload, $student->audio_source_type);
        $this->assertNull($student->audio_generation_id);
        $this->assertNull($student->audio_source_hash);
        $this->assertSame(AudioReadinessStatus::Uploaded, $student->audio_readiness_status);
        $this->assertSame(AnnouncementGenerationStatus::Approved, $generation->refresh()->status);
    }

    public function test_backup_media_copy_includes_private_candidates_without_public_exposure(): void
    {
        $candidate = 'announcement-candidates/private-fixture/candidate.mp3';
        Storage::disk('local')->put($candidate, $this->mp3());
        $target = storage_path('framework/testing/enh-tts-5-backup-media');
        File::deleteDirectory($target);

        $method = new \ReflectionMethod(BackupManager::class, 'copyMedia');
        $method->invoke(app(BackupManager::class), $target);

        $this->assertFileExists($target.'/private-announcement-candidates/private-fixture/candidate.mp3');
        $this->assertFileDoesNotExist(public_path($candidate));
        File::deleteDirectory($target);
    }

    private function fixture(): array
    {
        $ceremony = Ceremony::factory()->create();
        $student = Student::factory()->for($ceremony)->create([
            'announcement_name' => 'Fictional Graduate',
            'program' => 'Bachelor of Fictional Studies',
        ]);
        $template = AnnouncementTemplate::factory()->active()->for($ceremony)->create();

        return [$student, $template];
    }

    private function candidate(Student $student, AnnouncementTemplate $template): AnnouncementGeneration
    {
        $preview = app(PreviewGraduationAnnouncement::class)->preview($student, $template);
        $path = 'announcement-candidates/'.fake()->uuid().'/'.fake()->uuid().'.mp3';
        Storage::disk('local')->put($path, $this->mp3());

        return AnnouncementGeneration::query()->create([
            'student_id' => $student->id,
            'ceremony_id' => $student->ceremony_id,
            'announcement_template_id' => $template->id,
            'provider' => AnnouncementProvider::Azure,
            'provider_voice' => $preview->composition->voice,
            'locale' => $preview->composition->locale,
            'plain_text' => $preview->composition->plainText,
            'ssml' => $preview->ssml->ssml,
            'source_hash' => $preview->composition->sourceHash,
            'status' => AnnouncementGenerationStatus::NeedsReview,
            'character_count' => $preview->composition->characterCount,
            'output_format' => $preview->composition->input->outputFormat,
            'candidate_audio_path' => $path,
            'attempt_count' => 1,
            'generated_by' => User::factory()->role(UserRole::DataEncoder)->create()->id,
            'generated_at' => now(),
        ]);
    }

    private function approvedCandidate(Student $student, AnnouncementTemplate $template): AnnouncementGeneration
    {
        $generation = $this->candidate($student, $template);
        $path = 'storage/graduates/audio/generated.mp3';
        Storage::disk('public')->put('graduates/audio/generated.mp3', $this->mp3());
        $generation->update([
            'status' => AnnouncementGenerationStatus::Approved,
            'approved_audio_path' => $path,
        ]);
        $student->update([
            'audio_path' => $path,
            'audio_source_type' => GraduateAudioSourceType::AzureTts,
            'audio_generation_id' => $generation->id,
            'audio_source_hash' => $generation->source_hash,
            'audio_readiness_status' => AudioReadinessStatus::Approved,
        ]);

        return $generation;
    }

    protected function mp3(): string
    {
        return parent::mp3();
    }
}
