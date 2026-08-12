<?php

namespace Tests\Feature\Phase7;

use App\Enums\AudioReadinessStatus;
use App\Enums\CeremonyStatus;
use App\Enums\EligibilityStatus;
use App\Enums\PresentationMode;
use App\Enums\StageQueueStatus;
use App\Enums\UserRole;
use App\Events\AudioReplayRequested;
use App\Events\QueueUpdated;
use App\Events\StageHoldingChanged;
use App\Events\StagePresentationCleared;
use App\Events\StudentPresented;
use App\Models\Ceremony;
use App\Models\StageQueueItem;
use App\Models\StageState;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class StageQueueWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Ceremony $ceremony;

    private StageState $state;

    private User $scanner;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ceremony = Ceremony::factory()->create([
            'status' => CeremonyStatus::Active,
            'presentation_mode' => PresentationMode::Controlled,
            'activated_at' => now(),
        ]);
        $this->state = StageState::factory()->create([
            'ceremony_id' => $this->ceremony->id,
            'version' => 0,
        ]);
        $this->scanner = User::factory()
            ->role(UserRole::ScannerOperator)
            ->create();
        $this->operator = User::factory()
            ->role(UserRole::StageOperator)
            ->create();
    }

    public function test_controlled_scans_create_stable_waiting_positions_without_preemption(): void
    {
        Event::fake();
        $first = $this->student('QUEUE-FIRST');
        $second = $this->student('QUEUE-SECOND');

        $this->actingAs($this->scanner)
            ->postJson('/scans', ['token' => $first->qr_token])
            ->assertCreated()
            ->assertJsonPath('status', 'queued')
            ->assertJsonPath('queue_position', 1)
            ->assertJsonPath('queue_status', 'waiting')
            ->assertJsonPath('automatically_presented', false);

        $this->actingAs($this->scanner)
            ->postJson('/scans', ['token' => $second->qr_token])
            ->assertCreated()
            ->assertJsonPath('queue_position', 2);

        $this->assertSame(
            [1, 2],
            StageQueueItem::query()
                ->orderBy('queue_position')
                ->pluck('queue_position')
                ->all(),
        );
        $this->assertDatabaseHas('stage_states', [
            'id' => $this->state->id,
            'current_queue_item_id' => null,
            'display_mode' => 'idle',
            'version' => 2,
        ]);
        $this->assertDatabaseCount('stage_events', 2);
        Event::assertDispatchedTimes(QueueUpdated::class, 2);
        Event::assertNotDispatched(StudentPresented::class);
    }

    public function test_rescans_return_precise_queue_presenting_completed_and_skipped_results(): void
    {
        Event::fake();
        $waiting = $this->student('WAITING-DUPLICATE');

        $this->actingAs($this->scanner)
            ->postJson('/scans', ['token' => $waiting->qr_token])
            ->assertCreated();
        $this->actingAs($this->scanner)
            ->postJson('/scans', ['token' => $waiting->qr_token])
            ->assertConflict()
            ->assertJsonPath('status', 'already_queued')
            ->assertJsonPath('queue_position', 1);

        $this->actingAs($this->operator)
            ->postJson('/stage/present-next')
            ->assertOk();
        $this->actingAs($this->scanner)
            ->postJson('/scans', ['token' => $waiting->qr_token])
            ->assertConflict()
            ->assertJsonPath('status', 'already_presenting');

        $this->actingAs($this->operator)
            ->postJson('/stage/complete-current')
            ->assertOk();
        $this->actingAs($this->scanner)
            ->postJson('/scans', ['token' => $waiting->qr_token])
            ->assertConflict()
            ->assertJsonPath('status', 'already_completed');

        $skippedStudent = $this->student('SKIPPED-DUPLICATE');
        $skipped = $this->queue($skippedStudent, 2);
        $this->actingAs($this->operator)
            ->postJson("/stage/skip/{$skipped->id}")
            ->assertOk();
        $this->actingAs($this->scanner)
            ->postJson('/scans', ['token' => $skippedStudent->qr_token])
            ->assertConflict()
            ->assertJsonPath('status', 'skipped_requires_stage_action');

        $this->assertSame(2, StageQueueItem::query()->count());
    }

    public function test_present_next_and_selected_preserve_deterministic_queue_history(): void
    {
        Event::fake();
        $later = $this->queue($this->student('LATER'), 2);
        $first = $this->queue($this->student('FIRST'), 1);

        $this->actingAs($this->operator)
            ->postJson('/stage/present-next')
            ->assertOk()
            ->assertJsonPath('status', 'presented')
            ->assertJsonPath('stage_version', 1);

        $this->assertDatabaseHas('stage_queue_items', [
            'id' => $first->id,
            'status' => StageQueueStatus::Presenting->value,
        ]);
        $this->assertDatabaseHas('stage_queue_items', [
            'id' => $later->id,
            'status' => StageQueueStatus::Waiting->value,
            'queue_position' => 2,
        ]);

        $this->actingAs($this->operator)
            ->postJson('/stage/complete-current')
            ->assertOk();

        $third = $this->queue($this->student('THIRD'), 3);
        $this->actingAs($this->operator)
            ->postJson("/stage/present/{$third->id}")
            ->assertOk()
            ->assertJsonPath('status', 'presented');

        $this->assertDatabaseHas('stage_queue_items', [
            'id' => $later->id,
            'status' => StageQueueStatus::Waiting->value,
            'queue_position' => 2,
        ]);
        $this->assertDatabaseHas('stage_events', [
            'queue_item_id' => $third->id,
            'event_type' => 'present_selected',
            'performed_by' => $this->operator->id,
        ]);
        Event::assertDispatched(StudentPresented::class);
    }

    public function test_present_selected_rejects_other_ceremonies_and_busy_state(): void
    {
        Event::fake();
        $outsideCeremony = Ceremony::factory()->create();
        $outside = StageQueueItem::factory()->create([
            'ceremony_id' => $outsideCeremony->id,
            'student_id' => Student::factory()->create([
                'ceremony_id' => $outsideCeremony->id,
            ])->id,
            'queue_position' => 1,
        ]);

        $this->actingAs($this->operator)
            ->postJson("/stage/present/{$outside->id}")
            ->assertConflict()
            ->assertJsonPath('status', 'queue_item_unavailable');

        $current = $this->queue($this->student('CURRENT'), 1);
        $waiting = $this->queue($this->student('WAITING'), 2);
        $this->actingAs($this->operator)
            ->postJson("/stage/present/{$current->id}")
            ->assertOk();
        $this->actingAs($this->operator)
            ->postJson("/stage/present/{$waiting->id}")
            ->assertConflict()
            ->assertJsonPath('status', 'stage_busy');
    }

    public function test_skip_and_return_preserve_history_and_append_a_new_position(): void
    {
        Event::fake();
        $first = $this->queue($this->student('SKIP-ME'), 1);
        $this->queue($this->student('KEEP-ME'), 2);

        $this->actingAs($this->operator)
            ->postJson("/stage/skip/{$first->id}")
            ->assertOk()
            ->assertJsonPath('status', 'skipped');

        $this->assertDatabaseHas('stage_queue_items', [
            'id' => $first->id,
            'status' => StageQueueStatus::Skipped->value,
            'queue_position' => null,
        ]);

        $this->actingAs($this->operator)
            ->postJson("/stage/return/{$first->id}")
            ->assertOk()
            ->assertJsonPath('status', 'returned')
            ->assertJsonPath('queue_position', 3);

        $this->assertDatabaseHas('stage_queue_items', [
            'id' => $first->id,
            'status' => StageQueueStatus::Waiting->value,
            'queue_position' => 3,
        ]);
        $this->assertDatabaseHas('stage_events', [
            'queue_item_id' => $first->id,
            'event_type' => 'student_skipped',
        ]);
        $this->assertDatabaseHas('stage_events', [
            'queue_item_id' => $first->id,
            'event_type' => 'student_returned_to_queue',
        ]);
    }

    public function test_replay_is_safe_versioned_and_does_not_change_queue_status(): void
    {
        Event::fake();
        $item = $this->queue(
            $this->student('AUDIO', [
                'audio_path' => 'media/phase3/audio/student-01.wav',
                'audio_readiness_status' => AudioReadinessStatus::Approved,
                'audio_approved_at' => now(),
                'audio_approved_by' => $this->operator->id,
            ]),
            1,
        );
        $this->actingAs($this->operator)
            ->postJson("/stage/present/{$item->id}")
            ->assertOk();

        $version = $this->state->refresh()->version;
        $this->actingAs($this->operator)
            ->postJson('/stage/replay')
            ->assertOk()
            ->assertJsonPath('status', 'replayed')
            ->assertJsonPath('stage_version', $version + 1);

        $this->assertDatabaseHas('stage_queue_items', [
            'id' => $item->id,
            'status' => StageQueueStatus::Presenting->value,
        ]);
        $this->assertDatabaseHas('stage_events', [
            'queue_item_id' => $item->id,
            'event_type' => 'announcement_replayed',
        ]);
        Event::assertDispatched(AudioReplayRequested::class, function (
            AudioReplayRequested $event,
        ) use ($item): bool {
            $encoded = json_encode($event->broadcastWith(), JSON_THROW_ON_ERROR);

            return ! str_contains($encoded, $item->student->qr_token)
                && ! str_contains($encoded, $item->student->student_number);
        });
    }

    public function test_replay_rejects_idle_and_missing_audio_without_mutation(): void
    {
        Event::fake();
        $this->actingAs($this->operator)
            ->postJson('/stage/replay')
            ->assertConflict()
            ->assertJsonPath('status', 'stage_idle');

        $item = $this->queue($this->student('NO-AUDIO'), 1);
        $this->actingAs($this->operator)
            ->postJson("/stage/present/{$item->id}")
            ->assertOk();
        $version = $this->state->refresh()->version;

        $this->actingAs($this->operator)
            ->postJson('/stage/replay')
            ->assertConflict()
            ->assertJsonPath('status', 'audio_missing');

        $this->assertSame($version, $this->state->refresh()->version);
        $this->assertDatabaseMissing('stage_events', [
            'event_type' => 'announcement_replayed',
        ]);
    }

    public function test_holding_screen_preserves_and_restores_current_presentation(): void
    {
        Event::fake();
        $item = $this->queue($this->student('HOLDING'), 1);
        $this->actingAs($this->operator)
            ->postJson("/stage/present/{$item->id}")
            ->assertOk();

        $this->actingAs($this->operator)
            ->postJson('/stage/holding/activate')
            ->assertOk()
            ->assertJsonPath('status', 'holding');

        $this->assertDatabaseHas('stage_states', [
            'id' => $this->state->id,
            'current_queue_item_id' => $item->id,
            'display_mode' => 'holding',
        ]);
        $this->actingAs($this->operator)
            ->getJson('/stage/state')
            ->assertOk()
            ->assertJsonPath('state.mode', 'holding')
            ->assertJsonPath('state.presentation', null);

        $this->actingAs($this->operator)
            ->postJson('/stage/holding/deactivate')
            ->assertOk()
            ->assertJsonPath('status', 'live');
        $this->actingAs($this->operator)
            ->getJson('/stage/state')
            ->assertJsonPath('state.mode', 'graduate')
            ->assertJsonPath(
                'state.presentation.display_name',
                $item->student->display_name,
            );

        Event::assertDispatchedTimes(StageHoldingChanged::class, 2);
    }

    public function test_automatic_mode_presents_only_the_first_idle_scan_and_never_preempts(): void
    {
        Event::fake();
        $this->ceremony->update([
            'presentation_mode' => PresentationMode::Automatic,
        ]);
        $first = $this->student('AUTO-FIRST');
        $second = $this->student('AUTO-SECOND');

        $this->actingAs($this->scanner)
            ->postJson('/scans', ['token' => $first->qr_token])
            ->assertCreated()
            ->assertJsonPath('status', 'queued')
            ->assertJsonPath('queue_status', 'presenting')
            ->assertJsonPath('automatically_presented', true);

        $currentId = $this->state->refresh()->current_queue_item_id;

        $this->actingAs($this->scanner)
            ->postJson('/scans', ['token' => $second->qr_token])
            ->assertCreated()
            ->assertJsonPath('queue_status', 'waiting')
            ->assertJsonPath('automatically_presented', false);

        $this->assertSame(
            $currentId,
            $this->state->refresh()->current_queue_item_id,
        );
        $this->actingAs($this->operator)
            ->postJson('/stage/complete-current')
            ->assertOk();
        $this->assertNull($this->state->refresh()->current_queue_item_id);
        $this->assertDatabaseHas('stage_queue_items', [
            'student_id' => $second->id,
            'status' => StageQueueStatus::Waiting->value,
        ]);
    }

    public function test_automatic_pause_persists_and_makes_scans_behave_as_controlled(): void
    {
        Event::fake();
        $this->ceremony->update([
            'presentation_mode' => PresentationMode::Automatic,
        ]);

        $this->actingAs($this->operator)
            ->postJson('/stage/automatic/pause')
            ->assertOk()
            ->assertJsonPath('status', 'automatic_paused');
        $this->assertTrue($this->state->refresh()->automatic_paused);

        $student = $this->student('AUTO-PAUSED');
        $this->actingAs($this->scanner)
            ->postJson('/scans', ['token' => $student->qr_token])
            ->assertCreated()
            ->assertJsonPath('queue_status', 'waiting')
            ->assertJsonPath('automatically_presented', false);

        $this->actingAs($this->operator)
            ->postJson('/stage/automatic/resume')
            ->assertOk()
            ->assertJsonPath('status', 'automatic_resumed');
        $this->assertFalse($this->state->refresh()->automatic_paused);
        $this->assertNull($this->state->current_queue_item_id);
    }

    public function test_queue_recovery_payload_is_authoritative_and_excludes_tokens(): void
    {
        Event::fake();
        $waitingStudent = $this->student('PRIVATE-QUEUE-TOKEN', [
            'student_number' => 'PRIVATE-QUEUE-NUMBER',
            'display_name' => 'Christopher Emmanuel Villanueva-Santos',
        ]);
        $waiting = $this->queue($waitingStudent, 1);
        $skipped = $this->queue($this->student('SKIPPED'), 2);
        $this->actingAs($this->operator)
            ->postJson("/stage/skip/{$skipped->id}")
            ->assertOk();

        $response = $this->actingAs($this->operator)
            ->getJson('/stage/queue')
            ->assertOk()
            ->assertJsonPath('control.waiting.0.id', $waiting->id)
            ->assertJsonPath('control.waiting.0.queue_position', 1)
            ->assertJsonPath('control.skipped.0.id', $skipped->id)
            ->assertJsonPath('ceremony.presentation_mode', 'controlled');

        $encoded = $response->getContent();
        $this->assertStringNotContainsString($waitingStudent->qr_token, $encoded);
        $this->assertStringNotContainsString($waitingStudent->student_number, $encoded);
        $this->assertStringNotContainsString('qr_token', $encoded);
        $this->assertStringNotContainsString('student_number', $encoded);
    }

    public function test_queue_and_presentation_events_dispatch_after_commit(): void
    {
        $queueLevel = null;
        $presentationLevel = null;
        $callingLevel = DB::transactionLevel();
        $student = $this->student('AFTER-COMMIT');

        Event::listen(QueueUpdated::class, function (QueueUpdated $event) use (
            &$queueLevel,
            &$presentationLevel,
        ): void {
            if ($event->action === 'student_queued') {
                $queueLevel = DB::transactionLevel();
            }

            if ($event->action === 'present_next') {
                $presentationLevel = DB::transactionLevel();
            }
        });

        $this->actingAs($this->scanner)
            ->postJson('/scans', ['token' => $student->qr_token])
            ->assertCreated();
        $this->actingAs($this->operator)
            ->postJson('/stage/present-next')
            ->assertOk();

        $this->assertSame($callingLevel, $queueLevel);
        $this->assertSame($callingLevel, $presentationLevel);
    }

    public function test_postgresql_rejects_duplicate_non_null_queue_positions(): void
    {
        $this->queue($this->student('POSITION-ONE'), 1);

        $this->expectException(QueryException::class);

        $this->queue($this->student('POSITION-TWO'), 1);
    }

    public function test_completion_clears_safely_without_exposing_queue_internals(): void
    {
        Event::fake();
        $item = $this->queue($this->student('COMPLETE'), 1);
        $this->actingAs($this->operator)
            ->postJson("/stage/present/{$item->id}")
            ->assertOk();

        $this->actingAs($this->operator)
            ->postJson('/stage/complete-current')
            ->assertOk()
            ->assertJsonPath('status', 'completed');

        Event::assertDispatched(
            StagePresentationCleared::class,
            function (StagePresentationCleared $event): bool {
                return array_keys($event->broadcastWith()) === [
                    'event_id',
                    'version',
                    'presentation',
                    'cleared_at',
                ];
            },
        );
    }

    public function test_fully_automatic_scan_advances_after_audio_completion_and_preserves_fifo(): void
    {
        Event::fake();
        $this->ceremony->update(['presentation_mode' => PresentationMode::FullyAutomatic]);
        $first = $this->student('FULL-AUTO-FIRST');
        $second = $this->student('FULL-AUTO-SECOND');

        $this->actingAs($this->scanner)
            ->postJson('/scans', ['token' => $first->qr_token])
            ->assertCreated()
            ->assertJsonPath('automatically_presented', true);
        $this->actingAs($this->scanner)
            ->postJson('/scans', ['token' => $second->qr_token])
            ->assertCreated()
            ->assertJsonPath('automatically_presented', false);

        $this->actingAs($this->operator)
            ->postJson('/stage/automatic/advance', [
                'expected_version' => 3,
                'student_id' => $first->id,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'completed_and_presented_next');

        $this->assertDatabaseHas('stage_queue_items', [
            'student_id' => $first->id,
            'status' => StageQueueStatus::Completed->value,
        ]);
        $this->assertDatabaseHas('stage_queue_items', [
            'student_id' => $second->id,
            'status' => StageQueueStatus::Presenting->value,
        ]);

        $this->actingAs($this->operator)
            ->postJson('/stage/automatic/advance', [
                'expected_version' => 3,
                'student_id' => $first->id,
            ])
            ->assertConflict()
            ->assertJsonPath('status', 'stale_stage_state');
    }

    public function test_fully_automatic_playback_failure_pauses_before_advancement(): void
    {
        $this->ceremony->update(['presentation_mode' => PresentationMode::FullyAutomatic]);
        $student = $this->student('FULL-AUTO-FAILURE');

        $this->actingAs($this->scanner)
            ->postJson('/scans', ['token' => $student->qr_token])
            ->assertCreated();

        $this->actingAs($this->operator)
            ->postJson('/stage/audio-status', [
                'version' => 2,
                'student_id' => $student->id,
                'status' => 'failed',
            ])
            ->assertOk();

        $this->assertDatabaseHas('stage_states', [
            'ceremony_id' => $this->ceremony->id,
            'automatic_paused' => true,
        ]);
        $this->actingAs($this->operator)
            ->postJson('/stage/automatic/advance', [
                'expected_version' => 2,
                'student_id' => $student->id,
            ])
            ->assertConflict()
            ->assertJsonPath('status', 'automatic_paused');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function student(string $token, array $attributes = []): Student
    {
        return Student::factory()->create([
            'ceremony_id' => $this->ceremony->id,
            'eligibility_status' => EligibilityStatus::Eligible,
            'qr_token' => $token,
            ...$attributes,
        ]);
    }

    private function queue(Student $student, int $position): StageQueueItem
    {
        return StageQueueItem::factory()->create([
            'ceremony_id' => $this->ceremony->id,
            'student_id' => $student->id,
            'status' => StageQueueStatus::Waiting,
            'queue_position' => $position,
            'scanned_at' => now(),
        ]);
    }
}
