<?php

namespace Tests\Feature\EnhancementTts;

use App\Enums\AnnouncementBatchItemStatus;
use App\Enums\AnnouncementBatchStatus;
use App\Enums\AnnouncementGenerationStatus;
use App\Models\AnnouncementGeneration;
use App\Models\AnnouncementGenerationBatch;
use App\Models\AnnouncementGenerationBatchItem;
use App\Models\AnnouncementTemplate;
use App\Models\Ceremony;
use App\Models\Student;
use App\Models\User;
use App\Support\BackupManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AnnouncementBackupReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_backup_fails_safely_during_in_flight_synthesis(): void
    {
        $destination = storage_path('framework/testing/enh-tts-8-active-backup');
        File::deleteDirectory($destination);
        AnnouncementGeneration::factory()->create([
            'status' => AnnouncementGenerationStatus::Generating,
        ]);

        $record = app(BackupManager::class)->create(destination: $destination);

        $this->assertSame('failed', $record->status);
        $this->assertSame('failed', $record->verification_status);
        $this->assertStringContainsString('quiet preparation window', $record->failure_summary);
        $this->assertDirectoryDoesNotExist($destination.'/'.$record->identifier);
    }

    public function test_application_backup_fails_safely_during_batch_item_processing(): void
    {
        $destination = storage_path('framework/testing/enh-tts-8-active-batch-backup');
        File::deleteDirectory($destination);
        $ceremony = Ceremony::factory()->create();
        $student = Student::factory()->for($ceremony)->create();
        $template = AnnouncementTemplate::factory()->for($ceremony)->create();
        $batch = AnnouncementGenerationBatch::query()->create([
            'ceremony_id' => $ceremony->id,
            'announcement_template_id' => $template->id,
            'provider_voice' => $template->default_voice,
            'locale' => $template->default_locale,
            'selection_hash' => hash('sha256', 'backup-processing-fixture'),
            'status' => AnnouncementBatchStatus::Running,
            'selected_count' => 1,
            'estimated_character_count' => 40,
            'requested_by' => User::factory()->create()->id,
        ]);
        AnnouncementGenerationBatchItem::query()->create([
            'announcement_generation_batch_id' => $batch->id,
            'ceremony_id' => $ceremony->id,
            'student_id' => $student->id,
            'position' => 1,
            'status' => AnnouncementBatchItemStatus::Processing,
            'character_count' => 40,
        ]);

        $record = app(BackupManager::class)->create(destination: $destination);

        $this->assertSame('failed', $record->status);
        $this->assertStringContainsString('stop the TTS worker', $record->failure_summary);
        $this->assertDirectoryDoesNotExist($destination.'/'.$record->identifier);
    }

    public function test_operator_scripts_cover_announcement_backup_and_restore_policy(): void
    {
        $backup = File::get(base_path('scripts/gss-backup-docker.sh'));
        $restore = File::get(base_path('scripts/gss-restore-verify-docker.sh'));
        $offlineCompose = File::get(base_path('compose.offline-rehearsal.yaml'));

        $this->assertStringContainsString('--exclude-table-data=announcement_voice_samples', $backup);
        $this->assertStringContainsString('storage/app/private/announcement-candidates', $backup);
        $this->assertStringContainsString('private-announcement-candidates', $backup);
        $this->assertStringContainsString('announcement_summary', $backup);
        $this->assertStringContainsString('status = \'generating\'', $backup);
        $this->assertStringContainsString('announcement_generation_batch_items', $restore);
        $this->assertStringContainsString('invalid_hashes', $restore);
        $this->assertStringContainsString('invalid_active_references', $restore);
        $this->assertStringContainsString('restored_candidate_files', $restore);
        $this->assertStringContainsString('Private candidates verified', $restore);
        $this->assertStringContainsString('Active generated audio verified', $restore);
        $this->assertStringContainsString('internal: true', $offlineCompose);
    }
}
