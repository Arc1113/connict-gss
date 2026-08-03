<?php

namespace Tests\Feature\EnhancementTts;

use App\Actions\GenerateAnnouncementCandidate;
use App\Contracts\CandidateAudioStore;
use App\Data\GeneratedSpeechResult;
use App\Data\StoredCandidateAudio;
use App\Enums\AnnouncementGenerationStatus;
use App\Enums\AudioReadinessStatus;
use App\Enums\UserRole;
use App\Exceptions\AnnouncementCandidateGenerationException;
use App\Exceptions\CandidateAudioStorageException;
use App\Models\AnnouncementGeneration;
use App\Models\AnnouncementTemplate;
use App\Models\Ceremony;
use App\Models\Student;
use App\Models\User;
use App\Services\AnnouncementUsage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class AnnouncementCandidateGenerationTest extends AnnouncementTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->configureAzure();
    }

    public function test_one_candidate_is_generated_privately_without_changing_active_audio(): void
    {
        Http::fake(['*' => Http::response($this->mp3(), 200, [
            'Content-Type' => 'audio/mpeg',
            'X-Microsoft-RequestId' => 'request-123',
        ])]);
        [$student, $actor] = $this->fixture();
        Event::fake([BroadcastEvent::class]);
        $audioBefore = $student->only([
            'audio_path',
            'audio_source_type',
            'audio_generation_id',
            'audio_source_hash',
            'audio_readiness_status',
        ]);

        $result = app(GenerateAnnouncementCandidate::class)->generate($student, $actor);
        $generation = $result->generation;

        $this->assertFalse($result->reused);
        $this->assertSame(AnnouncementGenerationStatus::NeedsReview, $generation->status);
        $this->assertSame(1, $generation->attempt_count);
        $this->assertSame('request-123', $generation->provider_request_id);
        $this->assertNotNull($generation->candidate_audio_path);
        Storage::disk('local')->assertExists($generation->candidate_audio_path);
        $this->assertSame($audioBefore, $student->fresh()->only(array_keys($audioBefore)));
        $this->assertSame(AudioReadinessStatus::Missing, $student->fresh()->audio_readiness_status);
        $this->assertArrayNotHasKey('candidate_audio_path', $generation->toArray());
        Event::assertNotDispatched(BroadcastEvent::class);
        Http::assertSent(fn (Request $request): bool => ! str_contains($request->body(), 'qr_token'));
    }

    public function test_identical_readable_candidate_is_reused_without_another_request(): void
    {
        Http::fake(['*' => Http::response($this->mp3(), 200, ['Content-Type' => 'audio/mpeg'])]);
        [$student, $actor] = $this->fixture();

        $first = app(GenerateAnnouncementCandidate::class)->generate($student, $actor);
        $second = app(GenerateAnnouncementCandidate::class)->generate($student, $actor);

        $this->assertFalse($first->reused);
        $this->assertTrue($second->reused);
        $this->assertSame($first->generation->id, $second->generation->id);
        $this->assertSame(1, AnnouncementGeneration::query()->count());
        Http::assertSentCount(1);
    }

    public function test_force_generation_creates_a_distinct_candidate(): void
    {
        Http::fake(['*' => Http::response($this->mp3(), 200, ['Content-Type' => 'audio/mpeg'])]);
        [$student, $actor] = $this->fixture();
        $administrator = User::factory()->role(UserRole::Administrator)->create();

        $first = app(GenerateAnnouncementCandidate::class)->generate($student, $actor);
        $second = app(GenerateAnnouncementCandidate::class)->generate($student, $administrator, true);

        $this->assertNotSame($first->generation->id, $second->generation->id);
        $this->assertSame(2, AnnouncementGeneration::query()->count());
        Http::assertSentCount(2);
    }

    public function test_disabled_configuration_blocks_before_generation_or_http(): void
    {
        Http::fake();
        config()->set('announcements.azure.enabled', false);
        [$student, $actor] = $this->fixture();

        try {
            app(GenerateAnnouncementCandidate::class)->generate($student, $actor);
            $this->fail('Disabled provider configuration did not block generation.');
        } catch (AnnouncementCandidateGenerationException $exception) {
            $this->assertSame('azure_not_configured', $exception->safeCode);
            $this->assertNull($exception->generation);
        }

        $this->assertSame(0, AnnouncementGeneration::query()->count());
        Http::assertNothingSent();
    }

    public function test_provider_failure_is_persisted_with_only_safe_metadata(): void
    {
        Http::fake(['*' => Http::response('secret upstream details', 401)]);
        [$student, $actor] = $this->fixture();

        try {
            app(GenerateAnnouncementCandidate::class)->generate($student, $actor);
            $this->fail('Authentication failure did not fail generation.');
        } catch (AnnouncementCandidateGenerationException $exception) {
            $this->assertSame('azure_authentication_failed', $exception->safeCode);
        }

        $generation = AnnouncementGeneration::query()->sole();
        $this->assertSame(AnnouncementGenerationStatus::Failed, $generation->status);
        $this->assertSame('azure_authentication_failed', $generation->failure_code);
        $this->assertStringNotContainsString('secret upstream details', $generation->failure_message);
        $this->assertStringNotContainsString('test-secret-key', $generation->failure_message);
        $this->assertNull($generation->candidate_audio_path);
        Storage::disk('local')->assertDirectoryEmpty('/');
    }

    public function test_storage_failure_marks_generation_failed_and_leaves_no_candidate(): void
    {
        Http::fake(['*' => Http::response($this->mp3(), 200, ['Content-Type' => 'audio/mpeg'])]);
        $this->app->bind(CandidateAudioStore::class, fn () => new class implements CandidateAudioStore
        {
            public function store(GeneratedSpeechResult $speech): StoredCandidateAudio
            {
                throw new CandidateAudioStorageException;
            }

            public function exists(string $path): bool
            {
                return false;
            }

            public function delete(string $path): void {}
        });
        [$student, $actor] = $this->fixture();

        try {
            app(GenerateAnnouncementCandidate::class)->generate($student, $actor);
            $this->fail('Storage failure did not fail generation.');
        } catch (AnnouncementCandidateGenerationException $exception) {
            $this->assertSame('candidate_storage_failed', $exception->safeCode);
        }

        $generation = AnnouncementGeneration::query()->sole();
        $this->assertSame(AnnouncementGenerationStatus::Failed, $generation->status);
        $this->assertSame('candidate_storage_failed', $generation->failure_code);
        $this->assertNull($generation->candidate_audio_path);
        Storage::disk('local')->assertDirectoryEmpty('/');
    }

    public function test_unauthorized_user_cannot_probe_configuration_or_generate(): void
    {
        Http::fake();
        [$student] = $this->fixture();
        $actor = User::factory()->role(UserRole::StageOperator)->create();

        $this->expectException(AuthorizationException::class);

        try {
            app(GenerateAnnouncementCandidate::class)->generate($student, $actor);
        } finally {
            Http::assertNothingSent();
            $this->assertSame(0, AnnouncementGeneration::query()->count());
        }
    }

    public function test_data_encoder_cannot_force_regeneration(): void
    {
        Http::fake();
        [$student, $actor] = $this->fixture();

        $this->expectException(AuthorizationException::class);

        try {
            app(GenerateAnnouncementCandidate::class)->generate($student, $actor, true);
        } finally {
            Http::assertNothingSent();
            $this->assertSame(0, AnnouncementGeneration::query()->count());
        }
    }

    public function test_usage_counts_successful_synthesis_once_and_is_clearly_estimated(): void
    {
        Http::fake(['*' => Http::response($this->mp3(), 200, ['Content-Type' => 'audio/mpeg'])]);
        config()->set('announcements.azure.monthly_character_budget', 100);
        config()->set('announcements.azure.budget_warning_percent', 50);
        [$student, $actor] = $this->fixture();
        $generation = app(GenerateAnnouncementCandidate::class)->generate($student, $actor)->generation;
        app(GenerateAnnouncementCandidate::class)->generate($student, $actor);

        $usage = app(AnnouncementUsage::class)->forMonth();

        $this->assertSame($generation->character_count, $usage->successfulCharacters);
        $this->assertSame(100, $usage->monthlyBudget);
        $this->assertSame(50, $usage->warningThreshold);
        $this->assertSame(max(0, 100 - $generation->character_count), $usage->estimatedRemaining);
        $this->assertStringContainsString('estimate', strtolower($usage->label));
    }

    /**
     * @return array{Student, User}
     */
    private function fixture(): array
    {
        $ceremony = Ceremony::factory()->create();
        $student = Student::factory()->for($ceremony)->create([
            'announcement_name' => 'Fictional Graduate',
            'program' => 'Bachelor of Fictional Studies',
        ]);
        AnnouncementTemplate::factory()->active()->for($ceremony)->create();
        $actor = User::factory()->role(UserRole::DataEncoder)->create();

        return [$student, $actor];
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
