<?php

namespace Tests\Feature\Phase12;

use App\Enums\CeremonyStatus;
use App\Enums\EligibilityStatus;
use App\Enums\PresentationMode;
use App\Enums\StageQueueStatus;
use App\Enums\UserRole;
use App\Events\StageHoldingChanged;
use App\Events\StagePresentationCleared;
use App\Events\StudentPresented;
use App\Models\Ceremony;
use App\Models\StageQueueItem;
use App\Models\StageState;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class StageOperationsCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private Ceremony $ceremony;

    private StageState $state;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ceremony = Ceremony::factory()->create([
            'status' => CeremonyStatus::Active,
            'presentation_mode' => PresentationMode::Automatic,
            'activated_at' => now(),
        ]);
        $this->state = StageState::factory()->create([
            'ceremony_id' => $this->ceremony->id,
            'version' => 0,
        ]);
        $this->operator = User::factory()
            ->role(UserRole::StageOperator)
            ->create();
    }

    public function test_completion_and_next_presentation_commit_as_one_audience_change(): void
    {
        Event::fake();
        $current = $this->queue('CURRENT', 1);
        $next = $this->queue('NEXT', 2);

        $this->actingAs($this->operator)
            ->postJson("/stage/present/{$current->id}")
            ->assertOk();
        $expectedVersion = $this->state->refresh()->version;
        Event::fake();

        $this->actingAs($this->operator)
            ->postJson('/stage/complete-and-present-next', [
                'expected_version' => $expectedVersion,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'completed_and_presented_next')
            ->assertJsonPath('stage_version', $expectedVersion + 1);

        $this->assertDatabaseHas('stage_queue_items', [
            'id' => $current->id,
            'status' => StageQueueStatus::Completed->value,
        ]);
        $this->assertDatabaseHas('stage_queue_items', [
            'id' => $next->id,
            'status' => StageQueueStatus::Presenting->value,
        ]);
        $this->assertDatabaseHas('stage_states', [
            'id' => $this->state->id,
            'current_queue_item_id' => $next->id,
            'display_mode' => 'graduate',
            'version' => $expectedVersion + 1,
        ]);
        $this->assertDatabaseHas('stage_events', [
            'queue_item_id' => $current->id,
            'event_type' => 'student_completed',
            'performed_by' => $this->operator->id,
        ]);
        $this->assertDatabaseHas('stage_events', [
            'queue_item_id' => $next->id,
            'event_type' => 'present_next_after_completion',
            'performed_by' => $this->operator->id,
        ]);

        Event::assertDispatched(StudentPresented::class);
        Event::assertNotDispatched(StagePresentationCleared::class);
    }

    public function test_completion_and_next_clears_when_the_queue_is_empty(): void
    {
        Event::fake();
        $current = $this->queue('ONLY-CURRENT', 1);

        $this->actingAs($this->operator)
            ->postJson("/stage/present/{$current->id}")
            ->assertOk();
        $expectedVersion = $this->state->refresh()->version;
        Event::fake();

        $this->actingAs($this->operator)
            ->postJson('/stage/complete-and-present-next', [
                'expected_version' => $expectedVersion,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'completed_queue_empty');

        $this->assertSame(StageQueueStatus::Completed, $current->refresh()->status);
        $this->assertNull($this->state->refresh()->current_queue_item_id);
        $this->assertSame('idle', $this->state->display_mode->value);
        Event::assertDispatched(StagePresentationCleared::class);
        Event::assertNotDispatched(StudentPresented::class);
    }

    public function test_wrong_current_graduate_returns_to_the_original_position_and_holds(): void
    {
        Event::fake();
        $wrong = $this->queue('WRONG-CURRENT', 1);
        $correct = $this->queue('CORRECT-NEXT', 2);

        $this->actingAs($this->operator)
            ->postJson("/stage/present/{$wrong->id}")
            ->assertOk();
        $expectedVersion = $this->state->refresh()->version;

        $this->actingAs($this->operator)
            ->postJson('/stage/return-current', [
                'expected_version' => $expectedVersion,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'current_returned')
            ->assertJsonPath('queue_position', 1);

        $wrong->refresh();
        $this->assertSame(StageQueueStatus::Waiting, $wrong->status);
        $this->assertSame(1, $wrong->queue_position);
        $this->assertNull($wrong->presented_at);
        $this->assertNull($wrong->presented_by);
        $this->assertNull($wrong->completed_at);
        $this->assertDatabaseHas('stage_states', [
            'id' => $this->state->id,
            'current_queue_item_id' => null,
            'display_mode' => 'holding',
            'audio_status' => 'stopped',
            'version' => $expectedVersion + 1,
        ]);
        $this->assertDatabaseHas('stage_events', [
            'queue_item_id' => $wrong->id,
            'event_type' => 'current_presentation_returned',
            'performed_by' => $this->operator->id,
        ]);
        Event::assertDispatched(StageHoldingChanged::class);

        $this->actingAs($this->operator)
            ->postJson("/stage/present/{$correct->id}")
            ->assertOk()
            ->assertJsonPath('status', 'presented');

        $this->assertSame(
            $correct->id,
            $this->state->refresh()->current_queue_item_id,
        );
        $this->assertSame('graduate', $this->state->display_mode->value);
        $this->assertSame(StageQueueStatus::Waiting, $wrong->refresh()->status);
    }

    public function test_stale_combined_command_cannot_advance_a_second_graduate(): void
    {
        Event::fake();
        $current = $this->queue('STALE-CURRENT', 1);
        $next = $this->queue('STALE-NEXT', 2);
        $third = $this->queue('STALE-THIRD', 3);

        $this->actingAs($this->operator)
            ->postJson("/stage/present/{$current->id}")
            ->assertOk();
        $expectedVersion = $this->state->refresh()->version;

        $this->actingAs($this->operator)
            ->postJson('/stage/complete-and-present-next', [
                'expected_version' => $expectedVersion,
            ])
            ->assertOk();
        $this->actingAs($this->operator)
            ->postJson('/stage/complete-and-present-next', [
                'expected_version' => $expectedVersion,
            ])
            ->assertConflict()
            ->assertJsonPath('status', 'stale_stage_state');

        $this->assertSame(StageQueueStatus::Presenting, $next->refresh()->status);
        $this->assertSame(StageQueueStatus::Waiting, $third->refresh()->status);
    }

    public function test_new_stage_commands_require_stage_role_and_expected_version(): void
    {
        $current = $this->queue('AUTH-CURRENT', 1);
        $this->actingAs($this->operator)
            ->postJson("/stage/present/{$current->id}")
            ->assertOk();

        $this->actingAs($this->operator)
            ->postJson('/stage/return-current')
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.expected_version.0',
                'The expected version field is required.',
            );

        foreach ([
            UserRole::DataEncoder,
            UserRole::ScannerOperator,
            UserRole::TechnicalOperator,
        ] as $role) {
            $user = User::factory()->role($role)->create();

            $this->actingAs($user)
                ->postJson('/stage/complete-and-present-next', [
                    'expected_version' => $this->state->refresh()->version,
                ])
                ->assertForbidden();
            $this->actingAs($user)
                ->postJson('/stage/return-current', [
                    'expected_version' => $this->state->version,
                ])
                ->assertForbidden();
        }
    }

    private function queue(string $token, int $position): StageQueueItem
    {
        $student = Student::factory()->create([
            'ceremony_id' => $this->ceremony->id,
            'eligibility_status' => EligibilityStatus::Eligible,
            'qr_token' => $token,
        ]);

        return StageQueueItem::factory()->create([
            'ceremony_id' => $this->ceremony->id,
            'student_id' => $student->id,
            'status' => StageQueueStatus::Waiting,
            'queue_position' => $position,
            'scanned_at' => now(),
        ]);
    }
}
