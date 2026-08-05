<?php

namespace Tests\Feature\EnhancementTts;

use App\Enums\AnnouncementGenerationStatus;
use App\Enums\AudioReadinessStatus;
use App\Enums\GraduateAudioSourceType;
use App\Enums\UserRole;
use App\Models\AnnouncementGeneration;
use App\Models\AnnouncementTemplate;
use App\Models\AnnouncementVoiceSample;
use App\Models\AuditLog;
use App\Models\Ceremony;
use App\Models\Student;
use App\Models\User;
use App\Services\AnnouncementUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

class CeremonyAnnouncementSettingsTest extends AnnouncementTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('local');
    }

    public function test_only_administrator_can_open_safe_settings_workspace(): void
    {
        $ceremony = Ceremony::factory()->create();
        $template = AnnouncementTemplate::factory()->active()->for($ceremony)->create();
        $administrator = User::factory()->role(UserRole::Administrator)->create();
        $encoder = User::factory()->role(UserRole::DataEncoder)->create();
        config()->set('announcements.azure.key', 'never-render-this-key');

        $this->actingAs($administrator)
            ->get(route('admin.ceremonies.announcement-settings.show', $ceremony))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Ceremonies/AnnouncementSettings')
                ->where('announcement.settings.id', $template->id)
                ->where('announcement.next_version', 2)
                ->where('announcement.fictional_preview.ready', true)
                ->where('announcement.fictional_preview.plain_text', fn (string $text): bool => str_contains($text, 'Maria Santos'))
                ->where('announcement.voices', fn ($voices): bool => $voices->count() === 4)
                ->where('announcement.samples', fn ($samples): bool => $samples->count() === 0));

        $response = $this->actingAs($encoder)
            ->get(route('admin.ceremonies.announcement-settings.show', $ceremony));
        $response->assertForbidden();
        $this->assertStringNotContainsString('never-render-this-key', $response->getContent());
    }

    public function test_publishing_creates_new_active_version_and_invalidates_old_generated_audio(): void
    {
        $ceremony = Ceremony::factory()->create();
        $old = AnnouncementTemplate::factory()->active()->for($ceremony)->create([
            'version' => 1,
            'default_voice' => 'en-PH-RosaNeural',
        ]);
        $student = Student::factory()->for($ceremony)->create();
        $generation = AnnouncementGeneration::factory()
            ->approved()
            ->for($student)
            ->for($ceremony)
            ->for($old, 'template')
            ->create();
        $student->update([
            'audio_path' => $generation->approved_audio_path,
            'audio_source_type' => GraduateAudioSourceType::AzureTts,
            'audio_generation_id' => $generation->id,
            'audio_source_hash' => $generation->source_hash,
            'audio_readiness_status' => AudioReadinessStatus::Approved,
        ]);
        $administrator = User::factory()->role(UserRole::Administrator)->create();

        $this->actingAs($administrator)->post(
            route('admin.ceremonies.announcement-settings.store', $ceremony),
            $this->settingsPayload([
                'name' => 'Formal ceremony announcement',
                'default_voice' => 'fil-PH-BlessicaNeural',
                'default_locale' => 'fil-PH',
                'speaking_rate_percent' => -10,
            ]),
        )->assertRedirect()->assertSessionHas('success');

        $published = AnnouncementTemplate::query()->where('is_active', true)->sole();
        $this->assertSame(2, $published->version);
        $this->assertSame('Formal ceremony announcement', $published->name);
        $this->assertSame('fil-PH-BlessicaNeural', $published->default_voice);
        $this->assertFalse($old->fresh()->is_active);
        $this->assertSame('en-PH-RosaNeural', $old->fresh()->default_voice);
        $this->assertSame(AnnouncementGenerationStatus::Outdated, $generation->fresh()->status);
        $this->assertSame(AudioReadinessStatus::NeedsReview, $student->fresh()->audio_readiness_status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'announcement_template.published',
            'entity_id' => $published->id,
        ]);
    }

    public function test_settings_validation_enforces_allowlist_locale_and_safe_pacing(): void
    {
        $ceremony = Ceremony::factory()->create();
        $administrator = User::factory()->role(UserRole::Administrator)->create();

        $this->actingAs($administrator)->post(
            route('admin.ceremonies.announcement-settings.store', $ceremony),
            $this->settingsPayload([
                'introduction_text' => '<speak>Unsafe</speak>',
                'default_voice' => 'en-PH-JamesNeural',
                'default_locale' => 'fil-PH',
                'speaking_rate_percent' => -21,
                'pause_after_name_ms' => 1501,
                'pause_between_sections_ms' => -1,
            ]),
        )->assertSessionHasErrors([
            'introduction_text',
            'default_locale',
            'speaking_rate_percent',
            'pause_after_name_ms',
            'pause_between_sections_ms',
        ]);

        $this->assertDatabaseCount('announcement_templates', 0);
    }

    public function test_fictional_preview_uses_draft_settings_without_database_or_http_side_effects(): void
    {
        $ceremony = Ceremony::factory()->create();
        $administrator = User::factory()->role(UserRole::Administrator)->create();
        Http::fake();

        $this->actingAs($administrator)->postJson(
            route('admin.ceremonies.announcement-settings.preview', $ceremony),
            $this->settingsPayload([
                'include_awards' => false,
                'closing_text' => 'Congratulations to our fictional graduate',
            ]),
        )->assertOk()
            ->assertJsonPath('preview.ready', true)
            ->assertJsonPath('preview.award_count', 0)
            ->assertJsonPath('preview.voice', 'en-PH-JamesNeural')
            ->assertJsonFragment(['plain_text' => 'We proudly present, Maria Santos, Bachelor of Science in Information Technology, Web and Mobile Applications, magna cum laude, Congratulations to our fictional graduate.']);

        $this->assertDatabaseCount('announcement_templates', 0);
        $this->assertDatabaseCount('announcement_generations', 0);
        Http::assertNothingSent();
    }

    public function test_explicit_voice_comparison_stores_protected_temporary_fictional_samples(): void
    {
        $this->configureAzure();
        Http::fake(['*' => Http::response($this->mp3(), 200, ['Content-Type' => 'audio/mpeg'])]);
        $ceremony = Ceremony::factory()->create();
        AnnouncementTemplate::factory()->active()->for($ceremony)->create();
        $administrator = User::factory()->role(UserRole::Administrator)->create();
        $encoder = User::factory()->role(UserRole::DataEncoder)->create();
        $otherCeremony = Ceremony::factory()->create();

        $this->actingAs($administrator)->post(
            route('admin.ceremonies.announcement-settings.voice-comparison.store', $ceremony),
            ['voices' => ['en-PH-JamesNeural', 'fil-PH-AngeloNeural']],
        )->assertRedirect()->assertSessionHas('success');

        $samples = AnnouncementVoiceSample::query()->orderBy('id')->get();
        $this->assertCount(2, $samples);
        $this->assertDatabaseCount('announcement_generations', 0);
        $this->assertGreaterThan(0, app(AnnouncementUsage::class)->forMonth()->successfulCharacters);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request): bool => str_contains($request->body(), 'Maria Santos'));

        foreach ($samples as $sample) {
            $this->assertStringStartsWith('announcement-voice-samples/', $sample->candidate_audio_path);
            Storage::disk('local')->assertExists($sample->candidate_audio_path);
            $url = route(
                'admin.ceremonies.announcement-settings.voice-comparison.show',
                [$ceremony, $sample],
            );
            $this->actingAs($encoder)->get($url)->assertForbidden();
            $this->actingAs($administrator)->get($url)
                ->assertOk()
                ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
            $this->actingAs($administrator)->get(route(
                'admin.ceremonies.announcement-settings.voice-comparison.show',
                [$otherCeremony, $sample],
            ))->assertNotFound();
        }

        $audit = AuditLog::query()
            ->where('action', 'announcement_voice_comparison.generated')
            ->sole()
            ->toJson();
        $this->assertStringNotContainsString('announcement-voice-samples', $audit);
        $this->assertStringNotContainsString('<speak', $audit);

        $expired = $samples->first();
        DB::table('announcement_voice_samples')
            ->where('id', $expired->id)
            ->update(['expires_at' => DB::raw("CURRENT_TIMESTAMP - INTERVAL '1 hour'")]);
        $this->actingAs($administrator)->get(route(
            'admin.ceremonies.announcement-settings.voice-comparison.show',
            [$ceremony, $expired],
        ))->assertNotFound();
        $this->actingAs($administrator)->post(
            route('admin.ceremonies.announcement-settings.voice-comparison.store', $ceremony),
            ['voices' => ['en-PH-RosaNeural']],
        )->assertRedirect();
        $this->assertDatabaseMissing('announcement_voice_samples', ['id' => $expired->id]);
        Storage::disk('local')->assertMissing($expired->candidate_audio_path);
    }

    public function test_comparison_is_bounded_explicit_and_disabled_without_provider_configuration(): void
    {
        Http::fake();
        config()->set('announcements.azure.enabled', false);
        $ceremony = Ceremony::factory()->create();
        AnnouncementTemplate::factory()->active()->for($ceremony)->create();
        $administrator = User::factory()->role(UserRole::Administrator)->create();

        $this->actingAs($administrator)->post(
            route('admin.ceremonies.announcement-settings.voice-comparison.store', $ceremony),
            ['voices' => ['en-PH-JamesNeural']],
        )->assertSessionHasErrors('voice_comparison');
        $this->actingAs($administrator)->post(
            route('admin.ceremonies.announcement-settings.voice-comparison.store', $ceremony),
            ['voices' => array_fill(0, 5, 'en-PH-JamesNeural')],
        )->assertSessionHasErrors('voices');

        $this->assertDatabaseCount('announcement_voice_samples', 0);
        Http::assertNothingSent();
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
            'user_agent' => 'GraduationStageSystem-Test/1.0',
        ] as $key => $value) {
            config()->set("announcements.azure.{$key}", $value);
        }
    }

    protected function mp3(): string
    {
        return parent::mp3();
    }
}
