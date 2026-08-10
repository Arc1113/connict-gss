<?php

namespace Tests\Feature\EnhancementTts;

use App\Enums\AudioReadinessStatus;
use App\Enums\CeremonyStatus;
use App\Enums\UserRole;
use App\Models\AnnouncementGeneration;
use App\Models\AnnouncementTemplate;
use App\Models\Ceremony;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

class VoiceGenerationWorkspaceTest extends AnnouncementTestCase
{
    use RefreshDatabase;

    public function test_authorized_preparer_can_open_the_active_ceremony_voice_workspace(): void
    {
        $ceremony = Ceremony::factory()->create(['status' => CeremonyStatus::Active]);
        $template = AnnouncementTemplate::factory()->active()->for($ceremony)->create();
        $missing = Student::factory()->for($ceremony)->create([
            'audio_readiness_status' => AudioReadinessStatus::Missing,
        ]);
        $reviewStudent = Student::factory()->for($ceremony)->create();
        $candidate = AnnouncementGeneration::factory()
            ->needsReview()
            ->for($reviewStudent)
            ->for($ceremony)
            ->for($template, 'template')
            ->create();
        $actor = User::factory()->role(UserRole::Administrator)->create();

        $this->actingAs($actor)
            ->get(route('admin.voice-generation'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/VoiceGeneration/Index')
                ->where('workspace.ceremony.id', $ceremony->id)
                ->where('workspace.ceremony.template.version', $template->version)
                ->where('workspace.counts.missing', 2)
                ->where('workspace.counts.needs_review', 1)
                ->where('workspace.permissions.settings', true)
                ->has('workspace.review_queue', 1)
                ->where('workspace.review_queue.0.student.id', $reviewStudent->id)
                ->where('workspace.review_queue.0.review_url', route('admin.students.show', $reviewStudent).'#announcement-candidate-'.$candidate->id)
                ->where('workspace.providers.kokoro.configured', true));

        $this->assertSame(AudioReadinessStatus::Missing, $missing->audio_readiness_status);
    }

    public function test_unapproved_roles_cannot_discover_voice_generation_data(): void
    {
        Ceremony::factory()->create(['status' => CeremonyStatus::Active]);
        $stageOperator = User::factory()->role(UserRole::StageOperator)->create();

        $this->actingAs($stageOperator)
            ->get(route('admin.voice-generation'))
            ->assertForbidden();
    }
}
