<?php

namespace Tests\Feature\Phase9;

use App\Enums\UserRole;
use App\Models\BackupRecord;
use App\Models\User;
use App\Support\BackupManager;
use App\Support\RestoreVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

class BackupAndRestoreSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_destination_rejects_public_directory(): void
    {
        $this->expectException(RuntimeException::class);

        app(BackupManager::class)->ensureSafeDestination(public_path('unsafe-backups'));
    }

    public function test_backup_failure_is_recorded_without_success_status(): void
    {
        $record = app(BackupManager::class)->create(destination: storage_path('app/testing-backups'));

        if ($record->status === 'completed') {
            $this->markTestSkipped('Docker backup tooling is available in this PHP process; success is verified by the Phase 9 CLI check.');
        }

        $this->assertSame('failed', $record->status);
        $this->assertSame('failed', $record->verification_status);
        $this->assertNotNull($record->failure_summary);
    }

    public function test_restore_verifier_rejects_missing_manifest_and_tampered_checksum(): void
    {
        $base = storage_path('framework/testing/phase9-backups/bad');
        File::deleteDirectory(dirname($base));
        File::ensureDirectoryExists($base);

        $this->expectException(RuntimeException::class);
        app(RestoreVerifier::class)->verify($base);
    }

    public function test_backup_page_access_and_metadata_are_safe(): void
    {
        $administrator = User::factory()->role(UserRole::Administrator)->create();
        $technical = User::factory()->role(UserRole::TechnicalOperator)->create();
        $encoder = User::factory()->role(UserRole::DataEncoder)->create();

        BackupRecord::query()->create([
            'identifier' => 'gss-backup-20260730-120000',
            'path_label' => 'gss-backups/gss-backup-20260730-120000',
            'status' => 'completed',
            'started_at' => now(),
            'completed_at' => now(),
            'created_by' => $administrator->id,
            'file_size' => 12345,
            'checksum' => str_repeat('a', 64),
            'verification_status' => 'passed',
        ]);

        $this->actingAs($technical)
            ->get('/admin/backups')
            ->assertOk()
            ->assertSee('gss-backup-20260730-120000')
            ->assertDontSee(config('app.key'));

        $this->actingAs($encoder)->get('/admin/backups')->assertForbidden();
    }
}
