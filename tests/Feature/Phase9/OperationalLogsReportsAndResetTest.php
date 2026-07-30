<?php

namespace Tests\Feature\Phase9;

use App\Enums\AudioReadinessStatus;
use App\Enums\CeremonyStatus;
use App\Enums\EligibilityStatus;
use App\Enums\ScanResult;
use App\Enums\StageAudioStatus;
use App\Enums\StageDisplayMode;
use App\Enums\StageQueueStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Ceremony;
use App\Models\ScanLog;
use App\Models\StageEvent;
use App\Models\StageQueueItem;
use App\Models\StageState;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class OperationalLogsReportsAndResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_log_access_rules_are_enforced(): void
    {
        $administrator = User::factory()->role(UserRole::Administrator)->create();
        $technical = User::factory()->role(UserRole::TechnicalOperator)->create();
        $stage = User::factory()->role(UserRole::StageOperator)->create();
        $encoder = User::factory()->role(UserRole::DataEncoder)->create();
        $scanner = User::factory()->role(UserRole::ScannerOperator)->create();
        $inactive = User::factory()->inactive()->role(UserRole::Administrator)->create();

        $this->get('/admin/scan-logs')->assertRedirect('/login');

        foreach ([$administrator, $technical, $stage] as $user) {
            $this->actingAs($user)->get('/admin/scan-logs')->assertOk();
            $this->actingAs($user)->get('/admin/stage-events')->assertOk();
        }

        foreach ([$encoder, $scanner] as $user) {
            $this->actingAs($user)->get('/admin/scan-logs')->assertForbidden();
            $this->actingAs($user)->get('/admin/stage-events')->assertForbidden();
        }
        $this->actingAs($inactive)->get('/admin/scan-logs')->assertRedirect('/login');

        $this->actingAs($technical)->get('/admin/backups')->assertOk();
        $this->actingAs($encoder)->get('/admin/backups')->assertForbidden();
        $this->actingAs($scanner)->get('/admin/audit-logs')->assertForbidden();
    }

    public function test_log_filters_and_serializers_exclude_sensitive_values(): void
    {
        [$administrator, $ceremony, $student] = $this->fixture();

        ScanLog::factory()->create([
            'ceremony_id' => $ceremony->id,
            'student_id' => $student->id,
            'raw_token_hash' => hash('sha256', 'GSS-SECRET-TOKEN'),
            'result' => ScanResult::Accepted,
            'message' => '=formula should not matter in HTML',
            'scanned_by' => $administrator->id,
        ]);
        ScanLog::factory()->create([
            'ceremony_id' => $ceremony->id,
            'result' => ScanResult::Invalid,
            'message' => 'Invalid QR rejected safely.',
            'scanned_by' => $administrator->id,
        ]);
        $queueItem = StageQueueItem::factory()->create([
            'ceremony_id' => $ceremony->id,
            'student_id' => $student->id,
            'status' => StageQueueStatus::Waiting,
        ]);
        StageEvent::factory()->create([
            'ceremony_id' => $ceremony->id,
            'queue_item_id' => $queueItem->id,
            'event_type' => 'student_queued',
            'payload' => [
                'version' => 3,
                'qr_token' => 'GSS-SECRET-TOKEN',
                'audio_path' => 'storage/graduates/audio/private.wav',
            ],
            'performed_by' => $administrator->id,
        ]);
        AuditLog::factory()->create([
            'user_id' => $administrator->id,
            'action' => 'student.updated',
            'entity_type' => 'student',
            'entity_id' => $student->id,
            'old_values' => ['qr_token' => 'GSS-SECRET-TOKEN'],
            'new_values' => ['audio_path' => 'storage/graduates/audio/private.wav'],
        ]);

        $this->actingAs($administrator)
            ->get('/admin/scan-logs?result=accepted')
            ->assertOk()
            ->assertSee('Ana Marie dela Cruz')
            ->assertDontSee('GSS-SECRET-TOKEN')
            ->assertDontSee(hash('sha256', 'GSS-SECRET-TOKEN'));

        $this->actingAs($administrator)
            ->get('/admin/stage-events?event_type=student_queued')
            ->assertOk()
            ->assertSee('student_queued')
            ->assertSee('Ana Marie dela Cruz')
            ->assertDontSee('storage/graduates/audio/private.wav')
            ->assertDontSee('GSS-SECRET-TOKEN');

        $this->actingAs($administrator)
            ->get('/admin/audit-logs?action=student.updated')
            ->assertOk()
            ->assertSee('[redacted]')
            ->assertDontSee('storage/graduates/audio/private.wav')
            ->assertDontSee('GSS-SECRET-TOKEN');
    }

    public function test_csv_exports_are_authorized_filtered_and_formula_safe(): void
    {
        [$administrator, $ceremony, $student] = $this->fixture([
            'display_name' => '=Formula Graduate',
            'announcement_name' => '=Formula Graduate',
        ]);
        $encoder = User::factory()->role(UserRole::DataEncoder)->create();

        ScanLog::factory()->create([
            'ceremony_id' => $ceremony->id,
            'student_id' => $student->id,
            'result' => ScanResult::Accepted,
            'message' => '+unsafe spreadsheet value',
            'scanned_by' => $administrator->id,
        ]);

        $this->actingAs($encoder)
            ->get('/admin/scan-logs/export')
            ->assertForbidden();

        $content = $this->actingAs($administrator)
            ->get('/admin/scan-logs/export?result=accepted')
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString("'=Formula Graduate", $content);
        $this->assertStringContainsString("'+unsafe spreadsheet value", $content);
        $this->assertStringNotContainsString($student->qr_token, $content);
        $this->assertStringNotContainsString('raw_token_hash', $content);
    }

    public function test_ceremony_summary_counts_are_accurate_and_cross_ceremony_safe(): void
    {
        [$administrator, $ceremony, $student] = $this->fixture([
            'audio_readiness_status' => AudioReadinessStatus::Approved,
            'audio_path' => 'media/phase3/audio/student-01.wav',
            'photo_path' => 'storage/graduates/photos/photo.jpg',
        ]);
        $waiting = Student::factory()->for($ceremony)->create([
            'eligibility_status' => EligibilityStatus::Eligible,
            'audio_readiness_status' => AudioReadinessStatus::Missing,
            'sequence_number' => 20,
        ]);
        $never = Student::factory()->for($ceremony)->create([
            'eligibility_status' => EligibilityStatus::Eligible,
            'sequence_number' => 21,
        ]);
        $otherCeremony = Ceremony::factory()->create();
        Student::factory()->for($otherCeremony)->create([
            'eligibility_status' => EligibilityStatus::Eligible,
        ]);

        StageQueueItem::factory()->create([
            'ceremony_id' => $ceremony->id,
            'student_id' => $student->id,
            'status' => StageQueueStatus::Completed,
            'completed_at' => now(),
        ]);
        StageQueueItem::factory()->create([
            'ceremony_id' => $ceremony->id,
            'student_id' => $waiting->id,
            'status' => StageQueueStatus::Waiting,
            'queue_position' => 1,
        ]);
        ScanLog::factory()->create([
            'ceremony_id' => $ceremony->id,
            'student_id' => $student->id,
            'result' => ScanResult::Duplicate,
        ]);
        ScanLog::factory()->create([
            'ceremony_id' => $ceremony->id,
            'result' => ScanResult::Invalid,
        ]);

        $this->actingAs($administrator)
            ->get("/admin/reports/ceremony-summary?ceremony_id={$ceremony->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Reports/CeremonySummary')
                ->where('summary.counts.total_graduates', 3)
                ->where('summary.counts.never_scanned', 1)
                ->where('summary.counts.duplicate_scan_attempts', 1)
                ->where('summary.counts.approved_audio', 1));
    }

    public function test_operational_reset_preserves_history_and_blocks_presenting(): void
    {
        [$administrator, $ceremony, $student] = $this->fixture();
        $waiting = Student::factory()->for($ceremony)->create([
            'sequence_number' => 42,
        ]);
        $completed = StageQueueItem::factory()->create([
            'ceremony_id' => $ceremony->id,
            'student_id' => $student->id,
            'status' => StageQueueStatus::Completed,
        ]);
        $waitingItem = StageQueueItem::factory()->create([
            'ceremony_id' => $ceremony->id,
            'student_id' => $waiting->id,
            'status' => StageQueueStatus::Waiting,
            'queue_position' => 1,
        ]);
        StageState::query()->create([
            'ceremony_id' => $ceremony->id,
            'display_mode' => StageDisplayMode::Holding,
            'audio_status' => StageAudioStatus::Stopped,
            'automatic_paused' => true,
            'version' => 4,
        ]);
        ScanLog::factory()->create([
            'ceremony_id' => $ceremony->id,
            'student_id' => $student->id,
            'result' => ScanResult::Accepted,
        ]);

        $this->actingAs($administrator)
            ->post("/admin/ceremonies/{$ceremony->id}/operational-reset", [
                'confirmation' => 'RESET CURRENT OPERATIONS',
            ])
            ->assertRedirect();

        $this->assertSame(StageQueueStatus::Completed, $completed->refresh()->status);
        $this->assertSame(StageQueueStatus::Cancelled, $waitingItem->refresh()->status);
        $this->assertDatabaseCount('scan_logs', 1);
        $this->assertDatabaseHas('stage_events', [
            'ceremony_id' => $ceremony->id,
            'event_type' => 'operational_reset',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ceremony.operational_reset',
            'entity_id' => $ceremony->id,
        ]);

        $presenting = StageQueueItem::factory()->create([
            'ceremony_id' => $ceremony->id,
            'student_id' => $waiting->id,
            'status' => StageQueueStatus::Presenting,
        ]);
        StageState::query()->whereBelongsTo($ceremony)->update([
            'current_queue_item_id' => $presenting->id,
        ]);

        $this->actingAs($administrator)
            ->post("/admin/ceremonies/{$ceremony->id}/operational-reset", [
                'confirmation' => 'RESET CURRENT OPERATIONS',
            ])
            ->assertSessionHas('warning');
    }

    /**
     * @param  array<string, mixed>  $studentOverrides
     * @return array{0: User, 1: Ceremony, 2: Student}
     */
    private function fixture(array $studentOverrides = []): array
    {
        $administrator = User::factory()->role(UserRole::Administrator)->create();
        $ceremony = Ceremony::factory()->create([
            'name' => 'Sample Graduation Ceremony',
            'status' => CeremonyStatus::Active,
            'activated_at' => now(),
        ]);
        $student = Student::factory()->for($ceremony)->create([
            'first_name' => 'Ana Marie',
            'last_name' => 'dela Cruz',
            'display_name' => 'Ana Marie dela Cruz',
            'announcement_name' => 'Ana Marie dela Cruz',
            'eligibility_status' => EligibilityStatus::Eligible,
            'sequence_number' => 10,
            ...$studentOverrides,
        ]);

        return [$administrator, $ceremony, $student];
    }
}
