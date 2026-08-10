<?php

namespace Tests\Feature\Phase8;

use App\Enums\AudioReadinessStatus;
use App\Enums\CeremonyStatus;
use App\Enums\EligibilityStatus;
use App\Enums\StageQueueStatus;
use App\Enums\UserRole;
use App\Events\AudioReplayRequested;
use App\Events\StageAudioStatusChanged;
use App\Models\Ceremony;
use App\Models\StageQueueItem;
use App\Models\StageState;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class StageAudioPlaybackPolicyTest extends TestCase
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

    public function test_only_approved_audio_is_exposed_to_the_audience_payload(): void
    {
        $approved = $this->presentingItem($this->student(
            'APPROVED-AUDIO',
            AudioReadinessStatus::Approved,
        ));

        $this->actingAs($this->operator)
            ->getJson('/stage/state')
            ->assertOk()
            ->assertJsonPath('state.presentation.student_id', $approved->student_id)
            ->assertJsonPath(
                'state.presentation.audio_url',
                '/media/phase3/audio/student-01.wav',
            )
            ->assertJsonMissingPath('state.presentation.audio_status');

        foreach ([
            AudioReadinessStatus::Uploaded,
            AudioReadinessStatus::NeedsReview,
            AudioReadinessStatus::Rejected,
            AudioReadinessStatus::Missing,
        ] as $status) {
            $item = $this->presentingItem($this->student($status->value, $status));

            $this->actingAs($this->operator)
                ->getJson('/stage/state')
                ->assertOk()
                ->assertJsonPath('state.presentation.student_id', $item->student_id)
                ->assertJsonPath('state.presentation.audio_url', null);
        }
    }

    public function test_replay_accepts_only_approved_current_audio_and_keeps_queue_status(): void
    {
        Event::fake();
        $item = $this->presentingItem($this->student(
            'REPLAY-APPROVED',
            AudioReadinessStatus::Approved,
        ));

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
        Event::assertDispatched(AudioReplayRequested::class);

        foreach ([
            AudioReadinessStatus::Uploaded,
            AudioReadinessStatus::NeedsReview,
            AudioReadinessStatus::Rejected,
        ] as $status) {
            Event::fake();
            $this->presentingItem($this->student('REPLAY-'.$status->value, $status));
            $version = $this->state->refresh()->version;

            $this->actingAs($this->operator)
                ->postJson('/stage/replay')
                ->assertConflict()
                ->assertJsonPath('status', 'audio_missing');

            $this->assertSame($version, $this->state->refresh()->version);
            Event::assertNotDispatched(AudioReplayRequested::class);
        }
    }

    public function test_next_waiting_approved_audio_is_available_for_safe_preload(): void
    {
        $this->presentingItem($this->student(
            'CURRENT-NO-AUDIO',
            AudioReadinessStatus::Missing,
        ));
        $next = StageQueueItem::factory()->create([
            'ceremony_id' => $this->ceremony->id,
            'student_id' => $this->student(
                'NEXT-APPROVED',
                AudioReadinessStatus::Approved,
            )->id,
            'status' => StageQueueStatus::Waiting,
            'queue_position' => 2,
            'scanned_at' => now(),
        ]);

        $this->actingAs($this->operator)
            ->getJson('/stage/state')
            ->assertOk()
            ->assertJsonPath('state.preload_audio_url', '/media/phase3/audio/student-01.wav');

        $this->assertDatabaseHas('stage_queue_items', [
            'id' => $next->id,
            'status' => StageQueueStatus::Waiting->value,
        ]);
    }

    public function test_audience_display_reports_audio_status_for_the_current_version(): void
    {
        Event::fake();
        $item = $this->presentingItem($this->student(
            'PLAYBACK-STATUS',
            AudioReadinessStatus::Approved,
        ));
        $version = $this->state->refresh()->version;

        $this->actingAs($this->operator)
            ->postJson('/stage/audio-status', [
                'version' => $version,
                'student_id' => $item->student_id,
                'status' => 'playing',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'recorded')
            ->assertJsonPath('audio_status', 'playing');

        $this->assertDatabaseHas('stage_states', [
            'id' => $this->state->id,
            'version' => $version,
            'audio_status' => 'playing',
        ]);
        Event::assertDispatched(StageAudioStatusChanged::class);
    }

    public function test_stale_audio_status_reports_cannot_change_the_current_presentation(): void
    {
        Event::fake();
        $item = $this->presentingItem($this->student(
            'STALE-PLAYBACK-STATUS',
            AudioReadinessStatus::Approved,
        ));
        $version = $this->state->refresh()->version;

        $this->actingAs($this->operator)
            ->postJson('/stage/audio-status', [
                'version' => $version - 1,
                'student_id' => $item->student_id,
                'status' => 'failed',
            ])
            ->assertConflict()
            ->assertJsonPath('status', 'stale_presentation');

        $this->assertDatabaseHas('stage_states', [
            'id' => $this->state->id,
            'audio_status' => 'stopped',
        ]);
        Event::assertNotDispatched(StageAudioStatusChanged::class);
    }

    private function student(
        string $token,
        AudioReadinessStatus $status,
    ): Student {
        $path = $status === AudioReadinessStatus::Missing
            ? null
            : 'media/phase3/audio/student-01.wav';

        return Student::factory()->create([
            'ceremony_id' => $this->ceremony->id,
            'eligibility_status' => EligibilityStatus::Eligible,
            'qr_token' => $token,
            'audio_path' => $path,
            'audio_readiness_status' => $status,
            'audio_uploaded_at' => $path === null ? null : now(),
            'audio_approved_at' => $status === AudioReadinessStatus::Approved ? now() : null,
            'audio_approved_by' => $status === AudioReadinessStatus::Approved
                ? $this->operator->id
                : null,
        ]);
    }

    private function presentingItem(Student $student): StageQueueItem
    {
        StageQueueItem::query()
            ->where('ceremony_id', $this->ceremony->id)
            ->where('status', StageQueueStatus::Presenting)
            ->update(['status' => StageQueueStatus::Completed]);

        $item = StageQueueItem::factory()->create([
            'ceremony_id' => $this->ceremony->id,
            'student_id' => $student->id,
            'status' => StageQueueStatus::Presenting,
            'queue_position' => null,
            'presented_at' => now(),
        ]);

        $this->state->forceFill([
            'current_queue_item_id' => $item->id,
            'display_mode' => 'graduate',
            'version' => $this->state->version + 1,
        ])->save();

        return $item;
    }
}
