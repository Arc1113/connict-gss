<?php

namespace Tests\Feature\Phase4;

use App\Enums\AudioReadinessStatus;
use App\Enums\CeremonyStatus;
use App\Enums\EligibilityStatus;
use App\Enums\UserRole;
use App\Models\Ceremony;
use App\Models\Student;
use App\Models\User;
use App\Support\WorkingCeremonyResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CeremonyWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->administrator = User::factory()
            ->role(UserRole::Administrator)
            ->create();
    }

    public function test_authorized_workspace_access_returns_the_workspace_overview_component(): void
    {
        $ceremony = Ceremony::factory()->create([
            'name' => 'UM Commencement 2027',
            'academic_year' => '2026-2027',
            'status' => CeremonyStatus::Draft,
        ]);

        $this->actingAs($this->administrator)
            ->get("/admin/ceremonies/{$ceremony->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Ceremonies/Show')
                ->where('ceremony.id', $ceremony->id)
                ->where('ceremony.name', 'UM Commencement 2027')
                ->where('ceremony.status.value', 'draft'));
    }

    public function test_unauthorized_workspace_access_is_forbidden(): void
    {
        $ceremony = Ceremony::factory()->create();
        $stageOperator = User::factory()->role(UserRole::StageOperator)->create();

        $this->actingAs($stageOperator)
            ->get("/admin/ceremonies/{$ceremony->id}")
            ->assertForbidden();
    }

    public function test_entering_workspace_updates_working_ceremony_without_changing_live_ceremony(): void
    {
        $liveCeremony = Ceremony::factory()->create([
            'name' => 'Live Ceremony 2026',
            'status' => CeremonyStatus::Active,
        ]);
        $workspaceCeremony = Ceremony::factory()->create([
            'name' => 'Working Ceremony 2027',
            'status' => CeremonyStatus::Draft,
        ]);

        $this->actingAs($this->administrator)
            ->withSession([
                WorkingCeremonyResolver::SESSION_KEY => $liveCeremony->id,
            ])
            ->get("/admin/ceremonies/{$workspaceCeremony->id}")
            ->assertOk()
            ->assertSessionHas(
                WorkingCeremonyResolver::SESSION_KEY,
                $workspaceCeremony->id,
            )
            ->assertInertia(fn (Assert $page) => $page
                ->where('workingCeremonyContext.ceremony.id', $workspaceCeremony->id)
                ->where('liveCeremonyContext.ceremony.id', $liveCeremony->id));

        $this->assertSame(CeremonyStatus::Active, $liveCeremony->refresh()->status);
        $this->assertSame(CeremonyStatus::Draft, $workspaceCeremony->refresh()->status);
    }

    public function test_workspace_overview_props_include_lightweight_identity_and_summary_data(): void
    {
        $ceremony = Ceremony::factory()->create([
            'name' => 'UM Commencement 2027',
            'academic_year' => '2026-2027',
            'venue' => 'UM Gymnasium',
            'status' => CeremonyStatus::Prepared,
        ]);

        Student::factory()->create([
            'ceremony_id' => $ceremony->id,
            'eligibility_status' => EligibilityStatus::Eligible,
            'photo_path' => 'photos/one.jpg',
            'qr_token' => 'qr-ready-1',
            'audio_readiness_status' => AudioReadinessStatus::Approved,
        ]);
        Student::factory()->create([
            'ceremony_id' => $ceremony->id,
            'eligibility_status' => EligibilityStatus::Pending,
            'photo_path' => null,
            'qr_token' => '',
            'audio_readiness_status' => AudioReadinessStatus::NeedsReview,
        ]);

        $this->actingAs($this->administrator)
            ->get("/admin/ceremonies/{$ceremony->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Ceremonies/Show')
                ->where('ceremony.name', 'UM Commencement 2027')
                ->where('ceremony.academic_year', '2026-2027')
                ->where('ceremony.venue', 'UM Gymnasium')
                ->where('overview.summary.graduates_total', 2)
                ->where('overview.summary.eligibility_ready', 1)
                ->where('overview.summary.eligibility_issues', 1)
                ->where('overview.summary.photos_ready', 1)
                ->where('overview.summary.photos_missing', 1)
                ->where('overview.summary.qr_ready', 1)
                ->where('overview.summary.qr_missing', 1)
                ->where('overview.summary.announcements_ready', 1)
                ->where('overview.summary.announcements_needs_review', 1)
                ->where('overview.summary.announcements_missing', 0)
                ->where('overview.checklist.0.key', 'graduate_data')
                ->where('overview.checklist.0.status', 'warning')
                ->where('overview.checklist.1.issue_count', 1)
                ->has('overview.checklist', 6));
    }

    public function test_setup_route_renders_the_ceremony_setup_experience_with_authorization(): void
    {
        $ceremony = Ceremony::factory()->create([
            'status' => CeremonyStatus::Prepared,
        ]);

        $this->actingAs($this->administrator)
            ->get("/admin/ceremonies/{$ceremony->id}/setup")
            ->assertOk()
            ->assertSessionHas(
                WorkingCeremonyResolver::SESSION_KEY,
                $ceremony->id,
            )
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Ceremonies/Setup')
                ->where('ceremony.id', $ceremony->id));

        $encoder = User::factory()->role(UserRole::DataEncoder)->create();

        $this->actingAs($encoder)
            ->get("/admin/ceremonies/{$ceremony->id}/setup")
            ->assertForbidden();
    }

    public function test_legacy_edit_route_redirects_to_setup(): void
    {
        $ceremony = Ceremony::factory()->create();

        $this->actingAs($this->administrator)
            ->get("/admin/ceremonies/{$ceremony->id}/edit")
            ->assertRedirect("/admin/ceremonies/{$ceremony->id}/setup");
    }

    public function test_workspace_section_routes_render_phase_five_scoped_preparation_views(): void
    {
        $ceremony = Ceremony::factory()->create([
            'status' => CeremonyStatus::Prepared,
        ]);

        $this->actingAs($this->administrator)
            ->get("/admin/ceremonies/{$ceremony->id}/graduates")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Students/Index')
                ->where('workspace.locked', true)
                ->where('workspace.ceremony.id', $ceremony->id)
                ->where('filters.ceremony_id', (string) $ceremony->id));

        $this->actingAs($this->administrator)
            ->get("/admin/ceremonies/{$ceremony->id}/import")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Students/Import/Index')
                ->where('workspace.locked', true)
                ->where('workspace.ceremony.id', $ceremony->id));

        $this->actingAs($this->administrator)
            ->get("/admin/ceremonies/{$ceremony->id}/qr")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Students/QrCodes/Index')
                ->where('workspace.locked', true)
                ->where('workspace.ceremony.id', $ceremony->id)
                ->where('filters.ceremony_id', $ceremony->id));

        $this->actingAs($this->administrator)
            ->get("/admin/ceremonies/{$ceremony->id}/announcements")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/VoiceGeneration/Index')
                ->where('workspace.ceremony.id', $ceremony->id));

        $this->actingAs($this->administrator)
            ->get("/admin/ceremonies/{$ceremony->id}/stage-design")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Ceremonies/StageDesign')
                ->where('ceremony.id', $ceremony->id));

        $this->actingAs($this->administrator)
            ->get("/admin/ceremonies/{$ceremony->id}/readiness")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Ceremonies/Readiness')
                ->where('ceremony.id', $ceremony->id)
                ->has('readiness.sections', 6));
    }
}
