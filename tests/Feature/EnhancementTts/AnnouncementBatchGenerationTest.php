<?php

namespace Tests\Feature\EnhancementTts;

use App\Actions\GenerateAnnouncementCandidate;
use App\Enums\AnnouncementBatchItemStatus;
use App\Enums\AnnouncementBatchStatus;
use App\Enums\AnnouncementGenerationStatus;
use App\Enums\AudioReadinessStatus;
use App\Enums\EligibilityStatus;
use App\Enums\UserRole;
use App\Events\AnnouncementBatchProgressUpdated;
use App\Jobs\ProcessAnnouncementGenerationBatch;
use App\Models\AnnouncementGeneration;
use App\Models\AnnouncementGenerationBatch;
use App\Models\AnnouncementTemplate;
use App\Models\AuditLog;
use App\Models\Ceremony;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

class AnnouncementBatchGenerationTest extends AnnouncementTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('local');
        $this->configureAzure();
    }

    public function test_only_administrator_can_open_safe_batch_workspace(): void
    {
        $ceremony = Ceremony::factory()->create();
        AnnouncementTemplate::factory()->active()->for($ceremony)->create();
        Student::factory()->for($ceremony)->create([
            'announcement_name' => 'Prepared Graduate',
            'program' => 'Bachelor of Prepared Studies',
        ]);
        $administrator = User::factory()->role(UserRole::Administrator)->create();
        $encoder = User::factory()->role(UserRole::DataEncoder)->create();
        config()->set('announcements.azure.key', 'never-render-this-key');
        Http::fake();

        $this->actingAs($administrator)
            ->get(route('admin.ceremonies.announcement-batches.index', $ceremony))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Ceremonies/AnnouncementBatches')
                ->where('workspace.provider.configured', true)
                ->where('workspace.limits.maximum_students', 50)
                ->where('workspace.students.0.ready', true)
                ->where('workspace.batches', fn ($batches): bool => $batches->count() === 0));

        $response = $this->actingAs($encoder)
            ->get(route('admin.ceremonies.announcement-batches.index', $ceremony));
        $response->assertForbidden();
        $this->assertStringNotContainsString('never-render-this-key', $response->getContent());
        Http::assertNothingSent();
    }

    public function test_start_is_bounded_deterministic_idempotent_and_safely_audited(): void
    {
        Queue::fake();
        $ceremony = Ceremony::factory()->create();
        AnnouncementTemplate::factory()->active()->for($ceremony)->create();
        $first = $this->student($ceremony, 30, 'Third Graduate');
        $second = $this->student($ceremony, 10, 'First Graduate');
        $third = $this->student($ceremony, 20, 'Second Graduate');
        $administrator = User::factory()->role(UserRole::Administrator)->create();
        $payload = [
            'student_ids' => [$first->id, $second->id, $third->id],
            'budget_acknowledged' => true,
        ];

        $this->actingAs($administrator)->post(
            route('admin.ceremonies.announcement-batches.store', $ceremony),
            $payload,
        )->assertRedirect()->assertSessionHas('success');

        $batch = AnnouncementGenerationBatch::query()->sole();
        $this->assertSame(AnnouncementBatchStatus::Queued, $batch->status);
        $this->assertSame(3, $batch->selected_count);
        $this->assertSame(
            [$second->id, $third->id, $first->id],
            $batch->items()->pluck('student_id')->all(),
        );
        Queue::assertPushedOn('tts-preparation', ProcessAnnouncementGenerationBatch::class);

        $this->actingAs($administrator)->post(
            route('admin.ceremonies.announcement-batches.store', $ceremony),
            $payload,
        )->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseCount('announcement_generation_batches', 1);
        $this->assertDatabaseCount('announcement_generation_batch_items', 3);
        Queue::assertPushed(ProcessAnnouncementGenerationBatch::class, 1);

        $audit = AuditLog::query()->where('action', 'announcement_batch.started')->sole()->toJson();
        $this->assertStringNotContainsString('never-render', $audit);
        $this->assertStringNotContainsString('candidate_audio_path', $audit);
        $this->assertStringNotContainsString('ssml', strtolower($audit));
    }

    public function test_selection_requires_same_ceremony_non_ineligible_ready_records_and_budget_acknowledgement(): void
    {
        Queue::fake();
        $ceremony = Ceremony::factory()->create();
        AnnouncementTemplate::factory()->active()->for($ceremony)->create();
        $administrator = User::factory()->role(UserRole::Administrator)->create();
        $valid = $this->student($ceremony, 1, 'Valid Graduate');
        $ineligible = $this->student($ceremony, 2, 'Ineligible Graduate', EligibilityStatus::Ineligible);
        $other = $this->student(Ceremony::factory()->create(), 1, 'Other Ceremony Graduate');

        foreach ([$ineligible, $other] as $invalid) {
            $this->actingAs($administrator)->post(
                route('admin.ceremonies.announcement-batches.store', $ceremony),
                ['student_ids' => [$invalid->id], 'budget_acknowledged' => true],
            )->assertSessionHasErrors('announcement_batch');
        }

        config()->set('announcements.azure.monthly_character_budget', 1);
        config()->set('announcements.azure.budget_warning_percent', 80);
        $this->actingAs($administrator)->post(
            route('admin.ceremonies.announcement-batches.store', $ceremony),
            ['student_ids' => [$valid->id]],
        )->assertSessionHasErrors('announcement_batch');
        $this->assertDatabaseCount('announcement_generation_batches', 0);

        $this->actingAs($administrator)->post(
            route('admin.ceremonies.announcement-batches.store', $ceremony),
            ['student_ids' => [$valid->id], 'budget_acknowledged' => true],
        )->assertRedirect();
        $this->assertDatabaseCount('announcement_generation_batches', 1);

        $oversized = array_values(range(1, 51));
        $this->actingAs($administrator)->post(
            route('admin.ceremonies.announcement-batches.store', $ceremony),
            ['student_ids' => $oversized, 'budget_acknowledged' => true],
        )->assertSessionHasErrors('student_ids');
    }

    public function test_worker_processes_one_item_at_a_time_and_never_approves_candidates(): void
    {
        Queue::fake();
        Event::fake([AnnouncementBatchProgressUpdated::class]);
        Http::fake(['*' => Http::response($this->mp3(), 200, ['Content-Type' => 'audio/mpeg'])]);
        [$ceremony, $administrator, $students] = $this->batchFixture(2);
        $this->start($ceremony, $administrator, $students);
        $batch = AnnouncementGenerationBatch::query()->sole();

        (new ProcessAnnouncementGenerationBatch($batch->id))
            ->handle(app(GenerateAnnouncementCandidate::class));
        $batch->refresh();
        $this->assertSame(AnnouncementBatchStatus::Queued, $batch->status);
        $this->assertSame(1, $batch->processed_count);
        $this->assertDatabaseCount('announcement_generations', 1);

        (new ProcessAnnouncementGenerationBatch($batch->id))
            ->handle(app(GenerateAnnouncementCandidate::class));
        $batch->refresh();
        $this->assertSame(AnnouncementBatchStatus::Completed, $batch->status);
        $this->assertSame(2, $batch->processed_count);
        $this->assertSame(2, $batch->succeeded_count);
        $this->assertSame(0, $batch->failed_count);
        $this->assertSame(2, AnnouncementGeneration::query()
            ->where('status', AnnouncementGenerationStatus::NeedsReview->value)
            ->count());
        $this->assertSame(0, Student::query()
            ->where('audio_readiness_status', AudioReadinessStatus::Approved->value)
            ->count());
        Http::assertSentCount(2);
        Event::assertDispatched(AnnouncementBatchProgressUpdated::class);
    }

    public function test_disabled_provider_blocks_before_queue_or_http(): void
    {
        Queue::fake();
        Http::fake();
        config()->set('announcements.azure.enabled', false);
        [$ceremony, $administrator, $students] = $this->batchFixture(1);

        $this->actingAs($administrator)->post(
            route('admin.ceremonies.announcement-batches.store', $ceremony),
            ['student_ids' => [$students[0]->id], 'budget_acknowledged' => true],
        )->assertSessionHasErrors('announcement_batch');

        $this->assertDatabaseCount('announcement_generation_batches', 0);
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_transient_failure_pauses_and_resume_completes_without_spinning(): void
    {
        Queue::fake();
        Event::fake([AnnouncementBatchProgressUpdated::class]);
        Http::fakeSequence()
            ->push('rate limited', 429)
            ->push($this->mp3(), 200, ['Content-Type' => 'audio/mpeg']);
        [$ceremony, $administrator, $students] = $this->batchFixture(1);
        $this->start($ceremony, $administrator, $students);
        $batch = AnnouncementGenerationBatch::query()->sole();

        $this->actingAs($administrator)->post(route(
            'admin.ceremonies.announcement-batches.cancel',
            [Ceremony::factory()->create(), $batch],
        ))->assertNotFound();

        (new ProcessAnnouncementGenerationBatch($batch->id))
            ->handle(app(GenerateAnnouncementCandidate::class));
        $batch->refresh();
        $this->assertSame(AnnouncementBatchStatus::Paused, $batch->status);
        $this->assertSame('azure_rate_limited', $batch->last_failure_code);
        $this->assertSame(AnnouncementBatchItemStatus::Queued, $batch->items()->first()->status);
        $this->assertSame(0, $batch->processed_count);
        Http::assertSentCount(1);

        $this->actingAs($administrator)->post(route(
            'admin.ceremonies.announcement-batches.resume',
            [$ceremony, $batch],
        ))->assertRedirect()->assertSessionHas('success');
        $this->assertSame(AnnouncementBatchStatus::Queued, $batch->fresh()->status);

        (new ProcessAnnouncementGenerationBatch($batch->id))
            ->handle(app(GenerateAnnouncementCandidate::class));
        $this->assertNull($batch->fresh()->last_failure_code);
        $this->assertSame(AnnouncementBatchStatus::Completed, $batch->fresh()->status);
        $this->assertDatabaseCount('announcement_generations', 2);
        $this->assertSame(1, AnnouncementGeneration::query()
            ->where('status', AnnouncementGenerationStatus::Failed->value)
            ->count());
    }

    public function test_cancellation_and_settings_changes_stop_queued_work_without_provider_calls(): void
    {
        Queue::fake();
        Event::fake([AnnouncementBatchProgressUpdated::class]);
        Http::fake();
        [$ceremony, $administrator, $students] = $this->batchFixture(2);
        $this->start($ceremony, $administrator, $students);
        $batch = AnnouncementGenerationBatch::query()->sole();

        $this->actingAs($administrator)->post(route(
            'admin.ceremonies.announcement-batches.cancel',
            [$ceremony, $batch],
        ))->assertRedirect()->assertSessionHas('success');
        $this->assertSame(AnnouncementBatchStatus::Cancelled, $batch->fresh()->status);
        $this->assertSame(2, $batch->items()
            ->where('status', AnnouncementBatchItemStatus::Cancelled->value)
            ->count());

        $this->start($ceremony, $administrator, $students);
        $newBatch = AnnouncementGenerationBatch::query()->latest('id')->firstOrFail();
        $this->actingAs($administrator)->post(
            route('admin.ceremonies.announcement-settings.store', $ceremony),
            $this->settingsPayload(['name' => 'New settings version']),
        )->assertRedirect();
        $this->assertSame(AnnouncementBatchStatus::Cancelled, $newBatch->fresh()->status);
        $this->assertSame('settings_changed', $newBatch->fresh()->last_failure_code);

        (new ProcessAnnouncementGenerationBatch($newBatch->id))
            ->handle(app(GenerateAnnouncementCandidate::class));
        Http::assertNothingSent();
        $this->assertDatabaseCount('announcement_generations', 0);
    }

    public function test_worker_interruption_returns_item_to_resumable_paused_state(): void
    {
        Queue::fake();
        Event::fake([AnnouncementBatchProgressUpdated::class]);
        [$ceremony, $administrator, $students] = $this->batchFixture(1);
        $this->start($ceremony, $administrator, $students);
        $batch = AnnouncementGenerationBatch::query()->sole();
        $batch->update(['status' => AnnouncementBatchStatus::Running]);
        $batch->items()->firstOrFail()->update([
            'status' => AnnouncementBatchItemStatus::Processing,
        ]);

        (new ProcessAnnouncementGenerationBatch($batch->id))->failed(null);

        $this->assertSame(AnnouncementBatchStatus::Paused, $batch->fresh()->status);
        $this->assertSame('worker_interrupted', $batch->fresh()->last_failure_code);
        $this->assertSame(
            AnnouncementBatchItemStatus::Queued,
            $batch->items()->first()->status,
        );
        Event::assertDispatched(AnnouncementBatchProgressUpdated::class);
    }

    public function test_worker_service_is_separate_from_ceremony_critical_services(): void
    {
        $compose = file_get_contents(base_path('compose.yaml'));
        $deployment = file_get_contents(base_path('compose.deploy.yaml'));
        $health = file_get_contents(base_path('scripts/gss-health-check.sh'));

        $this->assertStringContainsString('tts-worker:', $compose);
        $this->assertStringContainsString('--queue=tts-preparation', $compose);
        $this->assertStringContainsString('--tries=1', $compose);
        $this->assertStringContainsString('tts-worker:', $deployment);
        $this->assertStringNotContainsString('tts-worker', $health);
    }

    /** @return array{Ceremony, User, list<Student>} */
    private function batchFixture(int $count): array
    {
        $ceremony = Ceremony::factory()->create();
        AnnouncementTemplate::factory()->active()->for($ceremony)->create();
        $administrator = User::factory()->role(UserRole::Administrator)->create();
        $students = [];

        foreach (range(1, $count) as $sequence) {
            $students[] = $this->student($ceremony, $sequence, "Graduate {$sequence}");
        }

        return [$ceremony, $administrator, $students];
    }

    /** @param list<Student> $students */
    private function start(Ceremony $ceremony, User $administrator, array $students): void
    {
        $this->actingAs($administrator)->post(
            route('admin.ceremonies.announcement-batches.store', $ceremony),
            [
                'student_ids' => array_map(fn (Student $student): int => $student->id, $students),
                'budget_acknowledged' => true,
            ],
        )->assertRedirect()->assertSessionHas('success');
    }

    private function student(
        Ceremony $ceremony,
        int $sequence,
        string $name,
        EligibilityStatus $eligibility = EligibilityStatus::Eligible,
    ): Student {
        return Student::factory()->for($ceremony)->create([
            'sequence_number' => $sequence,
            'display_name' => $name,
            'announcement_name' => $name,
            'program' => 'Bachelor of Fictional Studies',
            'eligibility_status' => $eligibility,
        ]);
    }

    /** @return array<string, mixed> */
    private function settingsPayload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Ceremony announcement',
            'introduction_text' => 'We proudly present',
            'include_program' => true,
            'include_major' => true,
            'include_honors' => true,
            'include_awards' => true,
            'include_additional_text' => false,
            'awards_lead_in' => 'recipient of',
            'closing_text' => null,
            'default_voice' => 'en-PH-JamesNeural',
            'default_locale' => 'en-PH',
            'speaking_rate_percent' => -5,
            'pause_after_name_ms' => 250,
            'pause_between_sections_ms' => 160,
            'recommended_character_limit' => 450,
            'maximum_character_limit' => 700,
        ], $overrides);
    }

    private function configureAzure(): void
    {
        foreach ([
            'enabled' => true,
            'key' => 'test-secret-key',
            'region' => 'southeastasia',
            'endpoint' => null,
            'connect_timeout_seconds' => 1,
            'request_timeout_seconds' => 2,
            'maximum_retries' => 0,
            'retry_base_delay_ms' => 0,
            'retry_jitter_ms' => 0,
            'maximum_retry_delay_ms' => 0,
            'maximum_retry_after_seconds' => 0,
            'maximum_response_bytes' => 1024,
            'candidate_disk' => 'local',
            'candidate_directory' => 'announcement-candidates',
            'user_agent' => 'GraduationStageSystem-Test/1.0',
        ] as $key => $value) {
            config()->set("announcements.azure.{$key}", $value);
        }
    }

    private function mp3(): string
    {
        return 'ID3'.str_repeat("\0", 64);
    }
}
