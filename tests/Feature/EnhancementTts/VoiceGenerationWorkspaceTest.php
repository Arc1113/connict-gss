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
use App\Support\WorkingCeremonyResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

class VoiceGenerationWorkspaceTest extends AnnouncementTestCase
{
    use RefreshDatabase;

    public function test_generic_voice_generation_route_redirects_to_the_working_ceremony_workspace(): void
    {
        $liveCeremony = Ceremony::factory()->create(['status' => CeremonyStatus::Active]);
        $workingCeremony = Ceremony::factory()->create(['status' => CeremonyStatus::Prepared]);
        $template = AnnouncementTemplate::factory()->active()->for($workingCeremony)->create();
        $missing = Student::factory()->for($workingCeremony)->create([
            'audio_readiness_status' => AudioReadinessStatus::Missing,
        ]);
        $reviewStudent = Student::factory()->for($workingCeremony)->create();
        $candidate = AnnouncementGeneration::factory()
            ->needsReview()
            ->for($reviewStudent)
            ->for($workingCeremony)
            ->for($template, 'template')
            ->create();
        $actor = User::factory()->role(UserRole::Administrator)->create();

        $this->actingAs($actor)
            ->withSession([
                WorkingCeremonyResolver::SESSION_KEY => $workingCeremony->id,
            ])
            ->get(route('admin.voice-generation'))
            ->assertRedirect(
                route('admin.ceremonies.announcements', $workingCeremony),
            );

        $this->actingAs($actor)
            ->withSession([
                WorkingCeremonyResolver::SESSION_KEY => $liveCeremony->id,
            ])
            ->get(route('admin.ceremonies.announcements', $workingCeremony))
            ->assertOk()
            ->assertSessionHas(
                WorkingCeremonyResolver::SESSION_KEY,
                $workingCeremony->id,
            )
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/VoiceGeneration/Index')
                ->where('workspace.ceremony.id', $workingCeremony->id)
                ->where('workspace.ceremony.template.version', $template->version)
                ->where('workspace.counts.missing', 2)
                ->where('workspace.counts.needs_review', 1)
                ->where('workspace.permissions.settings', true)
                ->has('workspace.review_queue', 1)
                ->where('workspace.review_queue.0.student.id', $reviewStudent->id)
                ->where('workspace.review_queue.0.review_url', route('admin.ceremonies.students.show', [$workingCeremony, $reviewStudent]).'?section=announcement#announcement-candidate-'.$candidate->id)
                ->where('workspace.providers.kokoro.configured', true));

        $this->assertSame(AudioReadinessStatus::Missing, $missing->audio_readiness_status);
        $this->assertSame(CeremonyStatus::Active, $liveCeremony->refresh()->status);
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
