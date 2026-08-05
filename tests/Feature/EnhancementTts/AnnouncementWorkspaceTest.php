<?php

namespace Tests\Feature\EnhancementTts;

use App\Enums\AnnouncementGenerationStatus;
use App\Enums\UserRole;
use App\Models\AnnouncementGeneration;
use App\Models\AnnouncementTemplate;
use App\Models\AuditLog;
use App\Models\Ceremony;
use App\Models\Student;
use App\Models\StudentAward;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

class AnnouncementWorkspaceTest extends AnnouncementTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_graduate_detail_exposes_safe_workspace_without_secrets_or_paths(): void
    {
        [$student, $template] = $this->fixture();
        $actor = User::factory()->role(UserRole::DataEncoder)->create();
        StudentAward::factory()->for($student)->create([
            'award_name' => 'Best in Fictional Research',
            'spoken_text' => 'the Best in Fictional Research Award',
        ]);
        config()->set('announcements.azure.key', 'never-expose-this-key');
        $generation = AnnouncementGeneration::factory()
            ->for($student)
            ->for($student->ceremony)
            ->for($template, 'template')
            ->create([
                'status' => AnnouncementGenerationStatus::NeedsReview,
                'candidate_audio_path' => 'announcement-candidates/private/candidate.mp3',
            ]);

        $response = $this->actingAs($actor)->get(route('admin.students.show', $student));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Students/Show')
            ->where('announcement.permissions.manage_awards', true)
            ->where('announcement.permissions.generate', true)
            ->where('announcement.permissions.force_generate', false)
            ->where('announcement.awards.0.award_name', 'Best in Fictional Research')
            ->where('announcement.selected.template_id', $template->id)
            ->where('announcement.preview.ready', true)
            ->where('announcement.generations.0.id', $generation->id)
            ->where('announcement.generations.0.candidate_preview_url', null)
            ->where('announcement.usage', null));
        $this->assertStringNotContainsString('never-expose-this-key', $response->getContent());
        $this->assertStringNotContainsString('announcement-candidates/private', $response->getContent());
    }

    public function test_data_encoder_can_create_edit_reorder_and_remove_awards_with_safe_audit(): void
    {
        [$student] = $this->fixture();
        $actor = User::factory()->role(UserRole::DataEncoder)->create();
        $first = StudentAward::factory()->for($student)->create([
            'award_name' => 'First Award',
            'display_order' => 0,
        ]);

        $this->actingAs($actor)->post(route('admin.students.awards.store', $student), [
            'award_name' => '  Leadership   Excellence  ',
            'spoken_text' => 'the Leadership Excellence Award',
            'is_announcement_enabled' => true,
        ])->assertRedirect();

        $second = StudentAward::query()->where('award_name', 'Leadership Excellence')->sole();
        $this->assertSame(1, $second->display_order);

        $this->actingAs($actor)->put(route('admin.students.awards.update', [$student, $second]), [
            'award_name' => 'Leadership Excellence',
            'spoken_text' => 'the Leadership Excellence Award',
            'is_announcement_enabled' => false,
        ])->assertRedirect();
        $this->assertFalse($second->fresh()->is_announcement_enabled);

        $this->actingAs($actor)->post(route('admin.students.awards.move', [$student, $second]), [
            'direction' => 'up',
        ])->assertRedirect();
        $this->assertSame([$second->id, $first->id], $student->awards()->pluck('id')->all());

        $this->actingAs($actor)->delete(route('admin.students.awards.destroy', [$student, $first]))
            ->assertRedirect();
        $this->assertDatabaseMissing('student_awards', ['id' => $first->id]);
        $this->assertSame(0, $second->fresh()->display_order);
        $this->assertSame(4, AuditLog::query()->where('entity_type', 'student_award')->count());
        $this->assertStringNotContainsString(
            'announcement-candidates',
            AuditLog::query()->get()->toJson(),
        );
    }

    public function test_duplicate_and_list_breaking_awards_are_rejected(): void
    {
        [$student] = $this->fixture();
        $actor = User::factory()->role(UserRole::DataEncoder)->create();
        StudentAward::factory()->for($student)->create([
            'award_name' => 'Research Award',
            'spoken_text' => 'the Research Award',
        ]);

        $this->actingAs($actor)->from(route('admin.students.show', $student))->post(
            route('admin.students.awards.store', $student),
            [
                'award_name' => ' research award ',
                'spoken_text' => ' THE RESEARCH AWARD ',
                'is_announcement_enabled' => true,
            ],
        )->assertSessionHasErrors('award_name');

        $this->actingAs($actor)->post(route('admin.students.awards.store', $student), [
            'award_name' => 'Unsafe Award',
            'spoken_text' => ',the Unsafe Award.',
            'is_announcement_enabled' => true,
        ])->assertSessionHasErrors('spoken_text');
        $this->assertSame(1, $student->awards()->count());
    }

    public function test_preview_accepts_only_same_ceremony_template_and_allowlisted_voice_without_http(): void
    {
        [$student] = $this->fixture();
        $actor = User::factory()->role(UserRole::DataEncoder)->create();
        $selected = AnnouncementTemplate::factory()->inactive()->for($student->ceremony)->create([
            'version' => 2,
        ]);
        $other = AnnouncementTemplate::factory()->active()->create();
        Http::fake();

        $this->actingAs($actor)->getJson(route('admin.students.announcement-preview', [
            $student,
            'announcement_template_id' => $selected->id,
            'voice' => 'fil-PH-BlessicaNeural',
        ]))->assertOk()
            ->assertJsonPath('selection.template_id', $selected->id)
            ->assertJsonPath('selection.voice', 'fil-PH-BlessicaNeural')
            ->assertJsonPath('preview.locale', 'fil-PH')
            ->assertJsonPath('preview.ready', true);

        $wrongTemplate = $this->actingAs($actor)->getJson(route('admin.students.announcement-preview', [
            $student,
            'announcement_template_id' => $other->id,
        ]));
        $wrongTemplate->assertStatus(422);
        $this->assertArrayHasKey(
            'announcement_template_id',
            $wrongTemplate->json('errors'),
        );

        $wrongVoice = $this->actingAs($actor)->getJson(route('admin.students.announcement-preview', [
            $student,
            'voice' => 'unapproved-voice',
        ]));
        $wrongVoice->assertStatus(422);
        $this->assertArrayHasKey('voice', $wrongVoice->json('errors'));
        Http::assertNothingSent();
    }

    public function test_generation_route_creates_private_candidate_and_preserves_manual_audio(): void
    {
        $this->configureAzure();
        Http::fake(['*' => Http::response($this->mp3(), 200, ['Content-Type' => 'audio/mpeg'])]);
        [$student, $template] = $this->fixture(['audio' => true]);
        $actor = User::factory()->role(UserRole::DataEncoder)->create();
        $before = $student->only([
            'audio_path',
            'audio_source_type',
            'audio_readiness_status',
            'audio_generation_id',
            'audio_source_hash',
        ]);

        $this->actingAs($actor)->post(route('admin.students.announcement-candidates.store', $student), [
            'announcement_template_id' => $template->id,
            'voice' => 'en-PH-RosaNeural',
        ])->assertRedirect()->assertSessionHas('success');

        $generation = AnnouncementGeneration::query()->sole();
        $this->assertSame(AnnouncementGenerationStatus::NeedsReview, $generation->status);
        $this->assertSame('en-PH-RosaNeural', $generation->provider_voice);
        $this->assertSame($before, $student->fresh()->only(array_keys($before)));
        $this->assertNotNull($generation->candidate_audio_path);
        Storage::disk('local')->assertExists($generation->candidate_audio_path);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'announcement_generation.requested',
            'entity_id' => $generation->id,
        ]);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => str_contains(
            $request->body(),
            'en-PH-RosaNeural',
        ));
    }

    public function test_identical_generation_submit_reuses_candidate_and_force_remains_admin_only(): void
    {
        $this->configureAzure();
        Http::fake(['*' => Http::response($this->mp3(), 200, ['Content-Type' => 'audio/mpeg'])]);
        [$student, $template] = $this->fixture();
        $encoder = User::factory()->role(UserRole::DataEncoder)->create();
        $payload = [
            'announcement_template_id' => $template->id,
            'voice' => $template->default_voice,
        ];

        $this->actingAs($encoder)->post(
            route('admin.students.announcement-candidates.store', $student),
            $payload,
        )->assertRedirect();
        $this->actingAs($encoder)->post(
            route('admin.students.announcement-candidates.store', $student),
            $payload,
        )->assertRedirect()->assertSessionHas('success', fn (string $message): bool => str_contains($message, 'reused'));
        $this->assertSame(1, AnnouncementGeneration::query()->count());
        Http::assertSentCount(1);

        $this->actingAs($encoder)->post(
            route('admin.students.announcement-candidates.store', $student),
            [...$payload, 'force' => true],
        )->assertForbidden();
        $this->assertSame(1, AnnouncementGeneration::query()->count());
    }

    public function test_private_candidate_requires_authorized_nested_access_and_never_reveals_path(): void
    {
        [$student, $template] = $this->fixture();
        $otherStudent = Student::factory()->for($student->ceremony)->create();
        $encoder = User::factory()->role(UserRole::DataEncoder)->create();
        $stageOperator = User::factory()->role(UserRole::StageOperator)->create();
        $path = 'announcement-candidates/11111111-1111-4111-8111-111111111111/22222222-2222-4222-8222-222222222222.mp3';
        Storage::disk('local')->put($path, $this->mp3());
        $generation = AnnouncementGeneration::factory()
            ->for($student)
            ->for($student->ceremony)
            ->for($template, 'template')
            ->create([
                'status' => AnnouncementGenerationStatus::NeedsReview,
                'candidate_audio_path' => $path,
            ]);
        $url = route('admin.students.announcement-candidates.show', [$student, $generation]);

        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($stageOperator)->get($url)->assertForbidden();
        $this->actingAs($encoder)->get(route(
            'admin.students.announcement-candidates.show',
            [$otherStudent, $generation],
        ))->assertNotFound();

        $response = $this->actingAs($encoder)->get($url);
        $response->assertOk()
            ->assertHeader('Content-Type', 'audio/mpeg');
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame($this->mp3(), $response->getContent());
        $this->assertStringNotContainsString($path, $response->headers->get('Content-Disposition'));
    }

    public function test_unrelated_roles_cannot_manage_preview_or_generate(): void
    {
        [$student] = $this->fixture();
        $operator = User::factory()->role(UserRole::StageOperator)->create();

        $this->actingAs($operator)->post(route('admin.students.awards.store', $student), [
            'award_name' => 'Blocked Award',
            'is_announcement_enabled' => true,
        ])->assertForbidden();
        $this->actingAs($operator)
            ->getJson(route('admin.students.announcement-preview', $student))
            ->assertForbidden();
        $this->actingAs($operator)
            ->post(route('admin.students.announcement-candidates.store', $student))
            ->assertForbidden();
        $this->assertSame(0, StudentAward::query()->count());
        $this->assertSame(0, AnnouncementGeneration::query()->count());
    }

    /**
     * @param  array{audio?: bool}  $options
     * @return array{Student, AnnouncementTemplate}
     */
    private function fixture(array $options = []): array
    {
        $ceremony = Ceremony::factory()->create();
        $studentFactory = Student::factory()->for($ceremony);

        if ($options['audio'] ?? false) {
            $studentFactory = $studentFactory->approvedAudio();
        }

        $student = $studentFactory->create([
            'announcement_name' => 'Fictional Graduate',
            'program' => 'Bachelor of Fictional Studies',
        ]);
        $template = AnnouncementTemplate::factory()->active()->for($ceremony)->create();

        return [$student, $template];
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

    protected function mp3(): string
    {
        return parent::mp3();
    }
}
