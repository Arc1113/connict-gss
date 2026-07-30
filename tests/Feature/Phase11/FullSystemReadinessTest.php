<?php

namespace Tests\Feature\Phase11;

use App\Enums\AudioReadinessStatus;
use App\Enums\CeremonyStatus;
use App\Enums\EligibilityStatus;
use App\Enums\PresentationMode;
use App\Enums\StageDisplayMode;
use App\Enums\StageQueueStatus;
use App\Enums\UserRole;
use App\Models\Ceremony;
use App\Models\ScanLog;
use App\Models\StageEvent;
use App\Models\StageQueueItem;
use App\Models\StageState;
use App\Models\Student;
use App\Models\User;
use App\Support\StageControlPayload;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Tests\TestCase;

class FullSystemReadinessTest extends TestCase
{
    use DatabaseMigrations;

    public function test_high_volume_queue_remains_ordered_compact_and_private(): void
    {
        [$ceremony, $scanner, $stageOperator] = $this->activeCeremony();

        $tokens = [];

        for ($index = 1; $index <= 120; $index++) {
            $student = Student::factory()
                ->for($ceremony)
                ->create([
                    'student_number' => sprintf('RC1-%04d', $index),
                    'qr_token' => sprintf('GSS-RC1-%04d-%s', $index, Str::upper(Str::random(8))),
                    'display_name' => $index % 10 === 0
                        ? 'Christopher Emmanuel Villanueva-Santos Rehearsal '.$index
                        : 'Rehearsal Graduate '.$index,
                    'announcement_name' => 'Rehearsal Graduate '.$index,
                    'program' => $index % 7 === 0
                        ? 'Bachelor of Science in Electronics Engineering with Extended Rehearsal Program Name'
                        : 'Bachelor of Science in Computer Science',
                    'sequence_number' => $index,
                    'eligibility_status' => EligibilityStatus::Eligible,
                    'audio_readiness_status' => $index % 5 === 0
                        ? AudioReadinessStatus::Missing
                        : AudioReadinessStatus::Approved,
                    'audio_path' => $index % 5 === 0
                        ? null
                        : 'media/phase3/audio/student-01.wav',
                    'audio_approved_at' => $index % 5 === 0 ? null : now(),
                    'audio_approved_by' => $index % 5 === 0 ? null : $stageOperator->id,
                ]);

            $tokens[] = $student->qr_token;
        }

        $startedAt = hrtime(true);

        foreach ($tokens as $token) {
            $this->actingAs($scanner)
                ->postJson('/scans', ['token' => "  {$token}\n"])
                ->assertCreated()
                ->assertJsonPath('status', 'queued');
        }

        $durationMs = (int) ((hrtime(true) - $startedAt) / 1_000_000);

        $this->assertSame(120, StageQueueItem::query()
            ->whereBelongsTo($ceremony)
            ->where('status', StageQueueStatus::Waiting)
            ->count());

        $positions = StageQueueItem::query()
            ->whereBelongsTo($ceremony)
            ->orderBy('queue_position')
            ->pluck('queue_position')
            ->all();

        $this->assertSame(range(1, 120), $positions);
        $this->assertSame(120, count(array_unique($positions)));

        $payload = app(StageControlPayload::class)->forCeremony($ceremony);

        $this->assertCount(120, $payload['control']['waiting']);
        $this->assertSame(1, $payload['control']['waiting'][0]['queue_position']);
        $this->assertSame(120, $payload['control']['waiting'][119]['queue_position']);

        $encodedPayload = json_encode($payload, JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('qr_token', $encodedPayload);
        $this->assertStringNotContainsString('GSS-RC1-', $encodedPayload);
        $this->assertLessThan(250_000, strlen($encodedPayload), 'Stage-control payload should remain compact enough for local rehearsal use.');
        $this->assertLessThan(12_000, $durationMs, '120 sequential rehearsal scans should remain responsive on the local test server.');

        $this->actingAs($stageOperator)
            ->postJson('/stage/present-next')
            ->assertOk()
            ->assertJsonPath('status', 'presented');

        $this->assertSame(1, StageQueueItem::query()
            ->whereBelongsTo($ceremony)
            ->where('status', StageQueueStatus::Presenting)
            ->count());
        $this->assertSame(119, StageQueueItem::query()
            ->whereBelongsTo($ceremony)
            ->where('status', StageQueueStatus::Waiting)
            ->count());
    }

    public function test_scanner_failure_matrix_preserves_authoritative_state(): void
    {
        [$ceremony, $scanner, $stageOperator] = $this->activeCeremony();

        $eligible = $this->student($ceremony, 'MATRIX-VALID', EligibilityStatus::Eligible);
        $ineligible = $this->student($ceremony, 'MATRIX-INELIGIBLE', EligibilityStatus::Ineligible);

        $this->actingAs($scanner)
            ->postJson('/scans', ['token' => 'not-a-real-token'])
            ->assertUnprocessable()
            ->assertJsonPath('status', 'invalid_token');

        $this->actingAs($scanner)
            ->postJson('/scans', ['token' => $ineligible->qr_token])
            ->assertUnprocessable()
            ->assertJsonPath('status', 'student_ineligible');

        $this->actingAs($scanner)
            ->postJson('/scans', ['token' => $eligible->qr_token])
            ->assertCreated()
            ->assertJsonPath('status', 'queued');

        $this->actingAs($scanner)
            ->postJson('/scans', ['token' => $eligible->qr_token])
            ->assertConflict()
            ->assertJsonPath('status', 'already_queued');

        $this->actingAs($stageOperator)
            ->postJson('/stage/present-next')
            ->assertOk()
            ->assertJsonPath('status', 'presented');

        $this->actingAs($scanner)
            ->postJson('/scans', ['token' => $eligible->qr_token])
            ->assertConflict()
            ->assertJsonPath('status', 'already_presenting');

        $this->actingAs($stageOperator)
            ->postJson('/stage/complete-current')
            ->assertOk()
            ->assertJsonPath('status', 'completed');

        $this->actingAs($scanner)
            ->postJson('/scans', ['token' => $eligible->qr_token])
            ->assertConflict()
            ->assertJsonPath('status', 'already_completed');

        $this->assertSame(6, ScanLog::query()->whereBelongsTo($ceremony)->count());
        $this->assertSame(1, StageQueueItem::query()->whereBelongsTo($ceremony)->count());
        $this->assertSame(StageQueueStatus::Completed, StageQueueItem::query()->whereBelongsTo($ceremony)->sole()->status);
        $this->assertNull(StageState::query()->whereBelongsTo($ceremony)->sole()->current_queue_item_id);
    }

    public function test_postgresql_interruption_returns_safe_failures_without_partial_queue_records(): void
    {
        [$ceremony, $scanner, $stageOperator] = $this->activeCeremony();
        $student = $this->student($ceremony, 'DATABASE-FAILURE', EligibilityStatus::Eligible);

        config(['database.connections.pgsql.password' => 'intentionally-wrong-password']);
        \DB::purge('pgsql');

        $this->actingAs($scanner)
            ->postJson('/scans', ['token' => $student->qr_token])
            ->assertStatus(500)
            ->assertJsonPath('status', 'request_failed');

        $this->actingAs($stageOperator)
            ->postJson('/stage/present-next')
            ->assertStatus(500)
            ->assertJsonPath('status', 'request_failed');

        config(['database.connections.pgsql.password' => env('DB_PASSWORD')]);
        \DB::purge('pgsql');
        \DB::connection()->getPdo();

        $this->assertSame(0, StageQueueItem::query()->whereBelongsTo($ceremony)->count());
        $this->assertSame(0, ScanLog::query()->whereBelongsTo($ceremony)->count());

        $this->actingAs($scanner)
            ->postJson('/scans', ['token' => $student->qr_token])
            ->assertCreated()
            ->assertJsonPath('status', 'queued');
    }

    public function test_operational_reset_rehearsal_blocks_presenting_then_preserves_history(): void
    {
        [$ceremony, $scanner, $stageOperator, $admin] = $this->activeCeremony();
        $first = $this->student($ceremony, 'RESET-FIRST', EligibilityStatus::Eligible);
        $second = $this->student($ceremony, 'RESET-SECOND', EligibilityStatus::Eligible);

        $this->actingAs($scanner)->postJson('/scans', ['token' => $first->qr_token])->assertCreated();
        $this->actingAs($scanner)->postJson('/scans', ['token' => $second->qr_token])->assertCreated();
        $this->actingAs($stageOperator)->postJson('/stage/present-next')->assertOk();

        $this->actingAs($admin)
            ->post("/admin/ceremonies/{$ceremony->id}/operational-reset", [
                'confirmation' => 'RESET CURRENT OPERATIONS',
            ])
            ->assertRedirect()
            ->assertSessionHas('warning');

        $this->actingAs($stageOperator)->postJson('/stage/complete-current')->assertOk();

        $this->actingAs($admin)
            ->post("/admin/ceremonies/{$ceremony->id}/operational-reset", [
                'confirmation' => 'reset current operations',
            ])
            ->assertSessionHasErrors('confirmation');

        $this->actingAs($admin)
            ->post("/admin/ceremonies/{$ceremony->id}/operational-reset", [
                'confirmation' => 'RESET CURRENT OPERATIONS',
            ])
            ->assertRedirect();

        $this->assertSame(2, StageQueueItem::query()->whereBelongsTo($ceremony)->count());
        $this->assertSame(1, StageQueueItem::query()->whereBelongsTo($ceremony)->where('status', StageQueueStatus::Completed)->count());
        $this->assertSame(1, StageQueueItem::query()->whereBelongsTo($ceremony)->where('status', StageQueueStatus::Cancelled)->count());
        $this->assertSame(2, ScanLog::query()->whereBelongsTo($ceremony)->count());
        $this->assertTrue(StageEvent::query()->whereBelongsTo($ceremony)->where('event_type', 'operational_reset')->exists());
        $this->assertNull(StageState::query()->whereBelongsTo($ceremony)->sole()->current_queue_item_id);
    }

    /**
     * @return array{Ceremony, User, User, User}
     */
    private function activeCeremony(): array
    {
        $admin = User::factory()->role(UserRole::Administrator)->create();
        $scanner = User::factory()->role(UserRole::ScannerOperator)->create();
        $stageOperator = User::factory()->role(UserRole::StageOperator)->create();

        $ceremony = Ceremony::factory()->create([
            'name' => 'GSS-RC1 Rehearsal Ceremony',
            'status' => CeremonyStatus::Active,
            'presentation_mode' => PresentationMode::Controlled,
            'activated_at' => now(),
        ]);

        StageState::query()->create([
            'ceremony_id' => $ceremony->id,
            'current_queue_item_id' => null,
            'display_mode' => StageDisplayMode::Idle,
            'audio_status' => 'stopped',
            'is_muted' => false,
            'automatic_paused' => false,
            'version' => 0,
        ]);

        return [$ceremony, $scanner, $stageOperator, $admin];
    }

    private function student(
        Ceremony $ceremony,
        string $number,
        EligibilityStatus $eligibility,
    ): Student {
        return Student::factory()
            ->for($ceremony)
            ->create([
                'student_number' => $number,
                'qr_token' => 'GSS-RC1-'.$number,
                'display_name' => 'Phase 11 '.$number,
                'announcement_name' => 'Phase 11 '.$number,
                'program' => 'Bachelor of Science in Computer Science',
                'sequence_number' => fake()->unique()->numberBetween(1000, 9999),
                'eligibility_status' => $eligibility,
                'audio_readiness_status' => AudioReadinessStatus::Approved,
                'audio_path' => 'media/phase3/audio/student-01.wav',
                'audio_approved_at' => now(),
            ]);
    }
}
