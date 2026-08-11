<?php

namespace Tests\Feature\Phase6;

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

class DashboardOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_administrator_dashboard_renders_the_unified_overview(): void
    {
        $administrator = $this->user(UserRole::Administrator);
        $ceremony = Ceremony::factory()->create(['name' => 'Working Ceremony']);

        $this->actingAs($administrator)
            ->withSession([WorkingCeremonyResolver::SESSION_KEY => $ceremony->id])
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('overview.working_ceremony.name', 'Working Ceremony')
                ->where('overview.primary_action.href', url("/admin/ceremonies/{$ceremony->id}/import")));
    }

    public function test_dashboard_keeps_working_and_live_ceremonies_separate(): void
    {
        $administrator = $this->user(UserRole::Administrator);
        $live = Ceremony::factory()->create([
            'name' => 'Live Ceremony A',
            'status' => CeremonyStatus::Active,
        ]);
        $working = Ceremony::factory()->create([
            'name' => 'Working Ceremony B',
            'status' => CeremonyStatus::Draft,
        ]);

        $this->actingAs($administrator)
            ->withSession([WorkingCeremonyResolver::SESSION_KEY => $working->id])
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('overview.working_ceremony.id', $working->id)
                ->where('overview.working_ceremony.name', 'Working Ceremony B')
                ->where('overview.live_ceremony.state', 'ready')
                ->where('overview.live_ceremony.ceremony.id', $live->id)
                ->where('overview.live_ceremony.ceremony.name', 'Live Ceremony A'));
    }

    public function test_dashboard_blockers_only_use_the_working_ceremony(): void
    {
        $administrator = $this->user(UserRole::Administrator);
        $live = Ceremony::factory()->create(['status' => CeremonyStatus::Active]);
        $working = Ceremony::factory()->create(['status' => CeremonyStatus::Draft]);

        Student::factory()->create([
            'ceremony_id' => $live->id,
            'eligibility_status' => EligibilityStatus::Pending,
            'photo_path' => null,
            'audio_readiness_status' => AudioReadinessStatus::NeedsReview,
        ]);
        Student::factory()->create([
            'ceremony_id' => $working->id,
            'eligibility_status' => EligibilityStatus::Pending,
            'photo_path' => null,
            'audio_readiness_status' => AudioReadinessStatus::NeedsReview,
        ]);

        $this->actingAs($administrator)
            ->withSession([WorkingCeremonyResolver::SESSION_KEY => $working->id])
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->has('overview.attention', 3)
                ->where('overview.attention.0.count', 1)
                ->where('overview.attention.0.href', url("/admin/ceremonies/{$working->id}/graduates?eligibility_status=pending"))
                ->where('overview.attention.1.href', url("/admin/ceremonies/{$working->id}/graduates?media=missing_photo"))
                ->where('overview.attention.2.href', url("/admin/ceremonies/{$working->id}/announcements")));
    }

    public function test_admin_compatibility_route_redirects_authorized_preparation_users(): void
    {
        foreach ([UserRole::Administrator, UserRole::DataEncoder] as $role) {
            $this->actingAs($this->user($role))
                ->get('/admin')
                ->assertRedirect('/dashboard');
        }
    }

    public function test_data_encoder_dashboard_only_receives_preparation_actions(): void
    {
        $encoder = $this->user(UserRole::DataEncoder);
        $ceremony = Ceremony::factory()->create();
        Student::factory()->create([
            'ceremony_id' => $ceremony->id,
            'eligibility_status' => EligibilityStatus::Eligible,
            'photo_path' => 'photos/ready.jpg',
            'audio_readiness_status' => AudioReadinessStatus::Approved,
        ]);

        $this->actingAs($encoder)
            ->withSession([WorkingCeremonyResolver::SESSION_KEY => $ceremony->id])
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('overview.working_ceremony.id', $ceremony->id)
                ->where('overview.primary_action.label', 'Continue preparation')
                ->has('overview.relevant_actions', 0)
                ->missing('overview.system'));
    }

    public function test_scanner_operator_dashboard_prioritizes_scanning_without_preparation_data(): void
    {
        $this->actingAs($this->user(UserRole::ScannerOperator))
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('overview.primary_action.label', 'Open Scanner')
                ->where('overview.primary_action.href', '/scanner')
                ->missing('overview.working_ceremony')
                ->missing('overview.attention'));
    }

    public function test_stage_operator_dashboard_prioritizes_stage_control_without_preparation_data(): void
    {
        $this->actingAs($this->user(UserRole::StageOperator))
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('overview.primary_action.label', 'Open Stage Control')
                ->where('overview.primary_action.href', '/stage-control')
                ->missing('overview.working_ceremony')
                ->missing('overview.attention'));
    }

    public function test_preparation_users_receive_a_no_working_ceremony_state(): void
    {
        $this->actingAs($this->user(UserRole::DataEncoder))
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('overview.working_ceremony', null)
                ->where('overview.primary_action.label', 'Choose ceremony')
                ->where('overview.primary_action.href', '/admin/ceremonies'));
    }

    public function test_dashboard_handles_the_no_live_ceremony_state(): void
    {
        $this->actingAs($this->user(UserRole::ScannerOperator))
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('overview.live_ceremony.state', 'none')
                ->where('overview.live_ceremony.ceremony', null));
    }

    public function test_technical_operator_only_receives_authorized_system_actions(): void
    {
        $this->actingAs($this->user(UserRole::TechnicalOperator))
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('overview.primary_action.label', 'Connect devices')
                ->has('overview.relevant_actions', 2)
                ->where('overview.relevant_actions.0.href', '/admin/scan-logs')
                ->where('overview.relevant_actions.1.href', '/admin/backups')
                ->missing('overview.working_ceremony')
                ->missing('overview.attention'));
    }

    private function user(UserRole $role): User
    {
        return User::factory()->role($role)->create();
    }
}
