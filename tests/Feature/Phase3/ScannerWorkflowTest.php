<?php

namespace Tests\Feature\Phase3;

use App\Actions\ScanStudentForPresentation;
use App\Enums\CeremonyStatus;
use App\Enums\EligibilityStatus;
use App\Enums\ScanResult;
use App\Enums\StageQueueStatus;
use App\Enums\UserRole;
use App\Events\QueueUpdated;
use App\Events\StudentPresented;
use App\Models\Ceremony;
use App\Models\ScanLog;
use App\Models\StageEvent;
use App\Models\StageQueueItem;
use App\Models\StageState;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class ScannerWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Ceremony $ceremony;

    private StageState $stageState;

    private User $scanner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ceremony = Ceremony::factory()->create([
            'status' => CeremonyStatus::Active,
            'activated_at' => now(),
        ]);
        $this->stageState = StageState::factory()->create([
            'ceremony_id' => $this->ceremony->id,
            'version' => 0,
        ]);
        $this->scanner = User::factory()->role(UserRole::ScannerOperator)->create();
    }

    public function test_a_valid_scan_commits_a_waiting_queue_item_and_broadcasts_safe_control_data(): void
    {
        Event::fake([QueueUpdated::class, StudentPresented::class]);
        $student = $this->eligibleStudent([
            'qr_token' => 'GSS-VALID-ALPHA',
            'display_name' => 'Amara Santos',
            'program' => 'Bachelor of Science in Computer Science',
            'major' => 'Software Engineering',
            'honors' => 'Magna Cum Laude',
            'photo_path' => 'media/graduate-photo.jpg',
            'audio_path' => 'media/phase3/audio/student-01.wav',
        ]);

        $response = $this->actingAs($this->scanner)
            ->postJson('/scans', ['token' => '  gss-valid-alpha  ']);

        $response
            ->assertCreated()
            ->assertJsonPath('status', 'queued')
            ->assertJsonPath('student.id', $student->id)
            ->assertJsonPath('student.display_name', 'Amara Santos')
            ->assertJsonPath('queue_position', 1)
            ->assertJsonPath('queue_status', 'waiting')
            ->assertJsonPath('automatically_presented', false)
            ->assertJsonPath('broadcast_delivered', true);

        $queueItem = StageQueueItem::query()->sole();

        $this->assertSame(StageQueueStatus::Waiting, $queueItem->status);
        $this->assertSame(1, $queueItem->queue_position);
        $this->assertSame($this->scanner->id, $queueItem->scanned_by);
        $this->assertDatabaseHas('stage_states', [
            'ceremony_id' => $this->ceremony->id,
            'current_queue_item_id' => null,
            'display_mode' => 'idle',
            'version' => 1,
        ]);
        $this->assertDatabaseHas('scan_logs', [
            'ceremony_id' => $this->ceremony->id,
            'student_id' => $student->id,
            'raw_token_hash' => hash('sha256', 'GSS-VALID-ALPHA'),
            'result' => ScanResult::Accepted->value,
            'message' => 'queued',
            'scanned_by' => $this->scanner->id,
        ]);
        $this->assertDatabaseHas('stage_events', [
            'ceremony_id' => $this->ceremony->id,
            'queue_item_id' => $queueItem->id,
            'event_type' => 'student_queued',
        ]);

        Event::assertDispatched(
            QueueUpdated::class,
            function (QueueUpdated $event) use ($student): bool {
                $payload = $event->broadcastWith();

                $this->assertSame(
                    ['version', 'action', 'queue_item_id'],
                    array_keys($payload),
                );
                $this->assertSame('student_queued', $payload['action']);

                $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
                $this->assertStringNotContainsString($student->qr_token, $encoded);
                $this->assertStringNotContainsString($student->student_number, $encoded);
                $this->assertStringNotContainsString($this->scanner->email, $encoded);
                $this->assertStringNotContainsString('operator', $encoded);

                return true;
            },
        );
        Event::assertNotDispatched(StudentPresented::class);
    }

    public function test_the_success_event_is_dispatched_only_after_the_transaction_commits(): void
    {
        $student = $this->eligibleStudent(['qr_token' => 'AFTER-COMMIT']);
        $transactionLevel = null;
        $callingTransactionLevel = DB::transactionLevel();

        Event::listen(
            QueueUpdated::class,
            function () use (&$transactionLevel): void {
                $transactionLevel = DB::transactionLevel();
            },
        );

        $this->actingAs($this->scanner)
            ->postJson('/scans', ['token' => $student->qr_token])
            ->assertCreated();

        $this->assertSame($callingTransactionLevel, $transactionLevel);
    }

    public function test_an_unknown_token_is_logged_without_storing_the_raw_value(): void
    {
        Event::fake([StudentPresented::class]);
        $token = 'UNKNOWN-SECRET-TOKEN';

        $this->actingAs($this->scanner)
            ->postJson('/scans', ['token' => $token])
            ->assertUnprocessable()
            ->assertJsonPath('status', 'invalid_token');

        $log = ScanLog::query()->sole();

        $this->assertSame(hash('sha256', $token), $log->raw_token_hash);
        $this->assertSame(ScanResult::Invalid, $log->result);
        $this->assertStringNotContainsString($token, (string) $log->message);
        $this->assertSame(0, StageQueueItem::query()->count());
        $this->assertSame(0, StageEvent::query()->count());
        Event::assertNotDispatched(StudentPresented::class);
    }

    public function test_zero_or_multiple_active_ceremonies_are_rejected_and_logged(): void
    {
        Event::fake([StudentPresented::class]);
        $this->ceremony->update(['status' => CeremonyStatus::Prepared]);

        $this->actingAs($this->scanner)
            ->postJson('/scans', ['token' => 'ANY-TOKEN'])
            ->assertConflict()
            ->assertJsonPath('status', 'ceremony_inactive')
            ->assertJsonPath('message', 'No active ceremony is configured.');

        $this->assertDatabaseHas('scan_logs', [
            'ceremony_id' => null,
            'result' => ScanResult::Rejected->value,
            'message' => 'no_active_ceremony',
        ]);

        $this->ceremony->update(['status' => CeremonyStatus::Active]);
        Ceremony::factory()->create(['status' => CeremonyStatus::Active]);

        $this->actingAs($this->scanner)
            ->postJson('/scans', ['token' => 'SECOND-ATTEMPT'])
            ->assertConflict()
            ->assertJsonPath('status', 'ceremony_inactive')
            ->assertJsonPath(
                'message',
                'Multiple active ceremonies require administrator correction.',
            );

        $this->assertDatabaseHas('scan_logs', [
            'ceremony_id' => null,
            'result' => ScanResult::Rejected->value,
            'message' => 'multiple_active_ceremonies',
        ]);
        Event::assertNotDispatched(StudentPresented::class);
    }

    public function test_an_ineligible_student_is_rejected_without_partial_stage_state(): void
    {
        Event::fake([StudentPresented::class]);
        $student = Student::factory()->create([
            'ceremony_id' => $this->ceremony->id,
            'eligibility_status' => EligibilityStatus::Ineligible,
            'qr_token' => 'INELIGIBLE',
        ]);

        $this->actingAs($this->scanner)
            ->postJson('/scans', ['token' => $student->qr_token])
            ->assertUnprocessable()
            ->assertJsonPath('status', 'student_ineligible');

        $this->assertDatabaseHas('scan_logs', [
            'student_id' => $student->id,
            'result' => ScanResult::Ineligible->value,
            'message' => 'student_ineligible',
        ]);
        $this->assertStageRemainsIdle();
        Event::assertNotDispatched(StudentPresented::class);
    }

    public function test_a_duplicate_active_scan_is_rejected_before_the_stage_busy_rule(): void
    {
        Event::fake([StudentPresented::class]);
        $student = $this->eligibleStudent(['qr_token' => 'DUPLICATE']);

        $this->actingAs($this->scanner)
            ->postJson('/scans', ['token' => $student->qr_token])
            ->assertCreated();

        Event::fake([StudentPresented::class]);

        $this->actingAs($this->scanner)
            ->postJson('/scans', ['token' => $student->qr_token])
            ->assertConflict()
            ->assertJsonPath('status', 'already_queued');

        $this->assertSame(1, StageQueueItem::query()->count());
        $this->assertSame(2, ScanLog::query()->count());
        Event::assertNotDispatched(StudentPresented::class);
    }

    public function test_a_completed_student_cannot_be_presented_again(): void
    {
        Event::fake([StudentPresented::class]);
        $student = $this->eligibleStudent(['qr_token' => 'COMPLETED']);
        StageQueueItem::factory()->create([
            'ceremony_id' => $this->ceremony->id,
            'student_id' => $student->id,
            'status' => StageQueueStatus::Completed,
            'completed_at' => now(),
        ]);

        $this->actingAs($this->scanner)
            ->postJson('/scans', ['token' => $student->qr_token])
            ->assertConflict()
            ->assertJsonPath('status', 'already_completed');

        $this->assertStageRemainsIdle();
        Event::assertNotDispatched(StudentPresented::class);
    }

    public function test_a_busy_stage_queues_another_student_without_preemption(): void
    {
        Event::fake([StudentPresented::class]);
        $current = $this->eligibleStudent(['qr_token' => 'CURRENT']);
        $next = $this->eligibleStudent(['qr_token' => 'NEXT']);
        $currentItem = StageQueueItem::factory()->create([
            'ceremony_id' => $this->ceremony->id,
            'student_id' => $current->id,
            'status' => StageQueueStatus::Presenting,
            'presented_at' => now(),
        ]);
        $this->stageState->update([
            'current_queue_item_id' => $currentItem->id,
            'display_mode' => 'graduate',
            'version' => 4,
        ]);

        $this->actingAs($this->scanner)
            ->postJson('/scans', ['token' => $next->qr_token])
            ->assertCreated()
            ->assertJsonPath('status', 'queued')
            ->assertJsonPath('queue_status', 'waiting');

        $this->assertDatabaseHas('stage_states', [
            'id' => $this->stageState->id,
            'current_queue_item_id' => $currentItem->id,
            'version' => 5,
        ]);
        $this->assertSame(2, StageQueueItem::query()->count());
        $this->assertDatabaseHas('stage_queue_items', [
            'student_id' => $next->id,
            'status' => StageQueueStatus::Waiting->value,
            'queue_position' => 1,
        ]);
        Event::assertNotDispatched(StudentPresented::class);
    }

    public function test_a_transaction_failure_rolls_back_queue_state_log_and_event(): void
    {
        Event::fake([StudentPresented::class]);
        $student = $this->eligibleStudent(['qr_token' => 'ROLLBACK']);

        StageEvent::creating(function (): never {
            throw new RuntimeException('Forced transaction failure.');
        });

        try {
            $this->app
                ->make(ScanStudentForPresentation::class)
                ->execute($student->qr_token, $this->scanner);

            $this->fail('The forced transaction exception was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Forced transaction failure.', $exception->getMessage());
        } finally {
            StageEvent::flushEventListeners();
        }

        $this->assertStageRemainsIdle();
        $this->assertSame(0, StageQueueItem::query()->count());
        $this->assertSame(0, StageEvent::query()->count());
        $this->assertSame(0, ScanLog::query()->count());
        Event::assertNotDispatched(StudentPresented::class);
    }

    public function test_an_unexpected_http_failure_returns_safe_json_without_logging_the_token(): void
    {
        $student = $this->eligibleStudent([
            'qr_token' => 'GSS-SENSITIVE-HTTP-FAILURE-TOKEN',
        ]);
        StageEvent::creating(function (): never {
            throw new RuntimeException('Forced HTTP transaction failure.');
        });
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function (string $message, array $context) use ($student): bool {
                return $message === 'Scan command failed before confirmation.'
                    && $context['operator_id'] === $this->scanner->id
                    && $context['command'] === 'token_scan'
                    && $context['exception'] === RuntimeException::class
                    && ! str_contains(
                        json_encode($context, JSON_THROW_ON_ERROR),
                        $student->qr_token,
                    );
            });

        try {
            $this->actingAs($this->scanner)
                ->postJson('/scans', ['token' => $student->qr_token])
                ->assertInternalServerError()
                ->assertExactJson([
                    'status' => 'request_failed',
                    'message' => 'The scan was not confirmed. Check the local server, then try the same card again.',
                ]);
        } finally {
            StageEvent::flushEventListeners();
        }

        $this->assertStageRemainsIdle();
        $this->assertDatabaseCount('stage_queue_items', 0);
        $this->assertDatabaseCount('stage_events', 0);
        $this->assertDatabaseCount('scan_logs', 0);
    }

    private function eligibleStudent(array $attributes = []): Student
    {
        return Student::factory()->create([
            'ceremony_id' => $this->ceremony->id,
            'eligibility_status' => EligibilityStatus::Eligible,
            ...$attributes,
        ]);
    }

    private function assertStageRemainsIdle(): void
    {
        $this->assertDatabaseHas('stage_states', [
            'id' => $this->stageState->id,
            'current_queue_item_id' => null,
            'display_mode' => 'idle',
            'version' => 0,
        ]);
        $this->assertSame(0, StageQueueItem::query()
            ->where('status', StageQueueStatus::Presenting)
            ->count());
    }
}
