<?php

namespace Tests\Feature\Domain;

use App\Enums\CeremonyStatus;
use App\Enums\EligibilityStatus;
use App\Enums\ScanResult;
use App\Enums\StageAudioStatus;
use App\Enums\StageDisplayMode;
use App\Enums\StageQueueStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Ceremony;
use App\Models\Device;
use App\Models\ScanLog;
use App\Models\StageEvent;
use App\Models\StageQueueItem;
use App\Models\StageState;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_ceremony_student_and_stage_state_relationships_work(): void
    {
        $ceremony = Ceremony::factory()->create([
            'status' => CeremonyStatus::Prepared,
        ]);
        $student = Student::factory()->for($ceremony)->create([
            'sequence_number' => 1,
            'eligibility_status' => EligibilityStatus::Eligible,
        ]);
        $stageState = StageState::factory()->for($ceremony)->create();

        $this->assertTrue($ceremony->students->contains($student));
        $this->assertTrue($student->ceremony->is($ceremony));
        $this->assertTrue($ceremony->stageState->is($stageState));
        $this->assertTrue($stageState->ceremony->is($ceremony));
        $this->assertSame(CeremonyStatus::Prepared, $ceremony->status);
        $this->assertSame(EligibilityStatus::Eligible, $student->eligibility_status);
        $this->assertSame(StageDisplayMode::Idle, $stageState->display_mode);
        $this->assertSame(StageAudioStatus::Stopped, $stageState->audio_status);
    }

    public function test_queue_item_actor_and_event_relationships_work(): void
    {
        $ceremony = Ceremony::factory()->create();
        $student = Student::factory()->for($ceremony)->create([
            'sequence_number' => 1,
        ]);
        $scanner = User::factory()
            ->role(UserRole::ScannerOperator)
            ->create();
        $presenter = User::factory()
            ->role(UserRole::StageOperator)
            ->create();
        $queueItem = StageQueueItem::factory()->create([
            'ceremony_id' => $ceremony->id,
            'student_id' => $student->id,
            'status' => StageQueueStatus::Presenting,
            'scanned_by' => $scanner->id,
            'presented_by' => $presenter->id,
        ]);
        $stageState = StageState::factory()->for($ceremony)->create([
            'current_queue_item_id' => $queueItem->id,
            'display_mode' => StageDisplayMode::Graduate,
        ]);
        $stageEvent = StageEvent::factory()->for($ceremony)->create([
            'queue_item_id' => $queueItem->id,
            'event_type' => 'student_presented',
            'payload' => ['version' => 1],
            'performed_by' => $presenter->id,
        ]);

        $this->assertTrue($queueItem->ceremony->is($ceremony));
        $this->assertTrue($queueItem->student->is($student));
        $this->assertTrue($queueItem->scannedBy->is($scanner));
        $this->assertTrue($queueItem->presentedBy->is($presenter));
        $this->assertTrue($queueItem->stageState->is($stageState));
        $this->assertTrue($queueItem->stageEvents->contains($stageEvent));
        $this->assertTrue($stageEvent->performedBy->is($presenter));
        $this->assertSame(['version' => 1], $stageEvent->payload);
    }

    public function test_scan_logs_can_record_an_invalid_scan_without_a_student(): void
    {
        $device = Device::factory()->create();
        $operator = User::factory()
            ->role(UserRole::ScannerOperator)
            ->create();
        $tokenHash = hash('sha256', 'invalid-token');

        $scanLog = ScanLog::factory()->create([
            'device_id' => $device->id,
            'scanned_by' => $operator->id,
            'raw_token_hash' => $tokenHash,
            'result' => ScanResult::Invalid,
        ]);

        $this->assertNull($scanLog->ceremony);
        $this->assertNull($scanLog->student);
        $this->assertTrue($scanLog->device->is($device));
        $this->assertTrue($scanLog->operator->is($operator));
        $this->assertSame(ScanResult::Invalid, $scanLog->result);
        $this->assertSame($tokenHash, $scanLog->raw_token_hash);
    }

    public function test_audit_log_actor_relationship_and_json_casts_work(): void
    {
        $user = User::factory()
            ->role(UserRole::Administrator)
            ->create();
        $auditLog = AuditLog::factory()->for($user)->create([
            'action' => 'user_deactivated',
            'entity_type' => 'user',
            'entity_id' => 42,
            'old_values' => ['is_active' => true],
            'new_values' => ['is_active' => false],
        ]);

        $this->assertTrue($auditLog->user->is($user));
        $this->assertSame(['is_active' => true], $auditLog->old_values);
        $this->assertSame(['is_active' => false], $auditLog->new_values);
        $this->assertTrue($user->auditLogs->contains($auditLog));
    }

    public function test_deleting_an_actor_preserves_operational_records(): void
    {
        $ceremony = Ceremony::factory()->create();
        $student = Student::factory()->for($ceremony)->create([
            'sequence_number' => 1,
        ]);
        $operator = User::factory()->create();
        $queueItem = StageQueueItem::factory()->create([
            'ceremony_id' => $ceremony->id,
            'student_id' => $student->id,
            'scanned_by' => $operator->id,
        ]);

        $operator->delete();

        $this->assertNull($queueItem->refresh()->scanned_by);
    }

    public function test_deleting_a_student_preserves_its_scan_log_and_ceremony_context(): void
    {
        $ceremony = Ceremony::factory()->create();
        $student = Student::factory()->for($ceremony)->create([
            'sequence_number' => 1,
        ]);
        $scanLog = ScanLog::factory()->create([
            'ceremony_id' => $ceremony->id,
            'student_id' => $student->id,
            'result' => ScanResult::Accepted,
        ]);

        $student->delete();

        $scanLog->refresh();

        $this->assertSame($ceremony->id, $scanLog->ceremony_id);
        $this->assertNull($scanLog->student_id);
    }

    public function test_deleting_a_student_with_queue_history_is_restricted(): void
    {
        $ceremony = Ceremony::factory()->create();
        $student = Student::factory()->for($ceremony)->create([
            'sequence_number' => 1,
        ]);
        StageQueueItem::factory()->create([
            'ceremony_id' => $ceremony->id,
            'student_id' => $student->id,
        ]);

        $this->expectException(QueryException::class);

        $student->delete();
    }
}
