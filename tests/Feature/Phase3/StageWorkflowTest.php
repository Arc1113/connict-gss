<?php

namespace Tests\Feature\Phase3;

use App\Enums\CeremonyStatus;
use App\Enums\EligibilityStatus;
use App\Enums\StageQueueStatus;
use App\Enums\UserRole;
use App\Events\StagePresentationCleared;
use App\Models\Ceremony;
use App\Models\StageQueueItem;
use App\Models\StageState;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class StageWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Ceremony $ceremony;

    private StageState $stageState;

    private User $stageOperator;

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
        $this->stageOperator = User::factory()
            ->role(UserRole::StageOperator)
            ->create();
    }

    public function test_idle_stage_state_is_safe_and_versioned(): void
    {
        $this->actingAs($this->stageOperator)
            ->getJson('/stage/state')
            ->assertOk()
            ->assertExactJson([
                'status' => 'ready',
                'ceremony' => [
                    'id' => $this->ceremony->id,
                    'name' => $this->ceremony->name,
                ],
                'state' => [
                    'version' => 0,
                    'mode' => 'idle',
                    'presentation' => null,
                ],
            ]);
    }

    public function test_a_refreshed_stage_restores_the_persisted_presentation(): void
    {
        $student = Student::factory()->create([
            'ceremony_id' => $this->ceremony->id,
            'eligibility_status' => EligibilityStatus::Eligible,
            'qr_token' => 'DO-NOT-EXPOSE',
            'student_number' => 'PRIVATE-100',
            'display_name' => 'Clarisse Mendoza',
            'program' => 'Bachelor of Elementary Education',
            'honors' => 'Cum Laude',
            'audio_path' => null,
        ]);
        $queueItem = StageQueueItem::factory()->create([
            'ceremony_id' => $this->ceremony->id,
            'student_id' => $student->id,
            'status' => StageQueueStatus::Presenting,
            'presented_at' => now(),
        ]);
        $event = $this->ceremony->stageEvents()->create([
            'queue_item_id' => $queueItem->id,
            'event_type' => 'student_presented',
            'payload' => ['version' => 7, 'student_id' => $student->id],
            'created_at' => now(),
        ]);
        $this->stageState->update([
            'current_queue_item_id' => $queueItem->id,
            'display_mode' => 'graduate',
            'version' => 7,
        ]);

        $response = $this->actingAs($this->stageOperator)
            ->getJson('/stage/state')
            ->assertOk()
            ->assertJsonPath('state.version', 7)
            ->assertJsonPath('state.mode', 'graduate')
            ->assertJsonPath('state.presentation.event_id', $event->id)
            ->assertJsonPath('state.presentation.display_name', 'Clarisse Mendoza')
            ->assertJsonPath('state.presentation.audio_url', null);

        $encoded = $response->getContent();

        $this->assertStringNotContainsString($student->qr_token, $encoded);
        $this->assertStringNotContainsString($student->student_number, $encoded);
        $this->assertStringNotContainsString($this->stageOperator->email, $encoded);
    }

    public function test_completion_marks_the_item_completed_clears_stage_and_broadcasts(): void
    {
        Event::fake([StagePresentationCleared::class]);
        $student = Student::factory()->create([
            'ceremony_id' => $this->ceremony->id,
            'eligibility_status' => EligibilityStatus::Eligible,
        ]);
        $queueItem = StageQueueItem::factory()->create([
            'ceremony_id' => $this->ceremony->id,
            'student_id' => $student->id,
            'status' => StageQueueStatus::Presenting,
            'presented_at' => now(),
        ]);
        $this->stageState->update([
            'current_queue_item_id' => $queueItem->id,
            'display_mode' => 'graduate',
            'version' => 3,
        ]);

        $this->actingAs($this->stageOperator)
            ->postJson('/stage/complete-current')
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('stage_version', 4)
            ->assertJsonPath('broadcast_delivered', true);

        $this->assertDatabaseHas('stage_queue_items', [
            'id' => $queueItem->id,
            'status' => StageQueueStatus::Completed->value,
            'presented_by' => $this->stageOperator->id,
        ]);
        $this->assertDatabaseHas('stage_states', [
            'id' => $this->stageState->id,
            'current_queue_item_id' => null,
            'display_mode' => 'idle',
            'audio_status' => 'stopped',
            'version' => 4,
        ]);
        $this->assertDatabaseHas('stage_events', [
            'ceremony_id' => $this->ceremony->id,
            'queue_item_id' => $queueItem->id,
            'event_type' => 'student_completed',
            'performed_by' => $this->stageOperator->id,
        ]);

        Event::assertDispatched(
            StagePresentationCleared::class,
            function (StagePresentationCleared $event): bool {
                $this->assertSame($this->ceremony->id, $event->ceremonyId);
                $this->assertSame(
                    ['event_id', 'version', 'presentation', 'cleared_at'],
                    array_keys($event->broadcastWith()),
                );
                $this->assertSame(4, $event->broadcastWith()['version']);
                $this->assertNull($event->broadcastWith()['presentation']);

                return true;
            },
        );
    }

    public function test_completion_is_rejected_when_the_stage_is_idle(): void
    {
        Event::fake([StagePresentationCleared::class]);

        $this->actingAs($this->stageOperator)
            ->postJson('/stage/complete-current')
            ->assertConflict()
            ->assertJsonPath('status', 'stage_idle');

        $this->assertDatabaseCount('stage_events', 0);
        Event::assertNotDispatched(StagePresentationCleared::class);
    }
}
