<?php

namespace Tests\Feature\Phase2;

use App\Enums\CeremonyStatus;
use App\Enums\UserRole;
use App\Models\Ceremony;
use App\Models\User;
use App\Support\WorkingCeremonyResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WorkingCeremonyContextTest extends TestCase
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

    public function test_authorized_user_can_select_a_working_ceremony_without_changing_status(): void
    {
        $ceremony = Ceremony::factory()->create([
            'status' => CeremonyStatus::Prepared,
        ]);

        $this->actingAs($this->administrator)
            ->from('/dashboard')
            ->post(route('workspace.ceremony'), [
                'ceremony_id' => $ceremony->id,
            ])
            ->assertRedirect('/dashboard')
            ->assertSessionHas(WorkingCeremonyResolver::SESSION_KEY, $ceremony->id);

        $this->assertSame(CeremonyStatus::Prepared, $ceremony->refresh()->status);
    }

    public function test_selecting_a_working_ceremony_does_not_activate_or_deactivate_ceremonies(): void
    {
        $liveCeremony = Ceremony::factory()->create([
            'name' => 'Live Ceremony 2026',
            'status' => CeremonyStatus::Active,
        ]);
        $workingCeremony = Ceremony::factory()->create([
            'name' => 'Working Ceremony 2027',
            'status' => CeremonyStatus::Draft,
        ]);

        $this->actingAs($this->administrator)
            ->post(route('workspace.ceremony'), [
                'ceremony_id' => $workingCeremony->id,
            ])
            ->assertRedirect('/dashboard');

        $this->assertSame(CeremonyStatus::Active, $liveCeremony->refresh()->status);
        $this->assertSame(CeremonyStatus::Draft, $workingCeremony->refresh()->status);
    }

    public function test_unauthorized_role_cannot_select_a_working_ceremony(): void
    {
        $ceremony = Ceremony::factory()->create([
            'status' => CeremonyStatus::Prepared,
        ]);
        $stageOperator = User::factory()->role(UserRole::StageOperator)->create();

        $this->actingAs($stageOperator)
            ->post(route('workspace.ceremony'), [
                'ceremony_id' => $ceremony->id,
            ])
            ->assertForbidden();

        $this->assertSame(CeremonyStatus::Prepared, $ceremony->refresh()->status);
        $this->assertNull(session(WorkingCeremonyResolver::SESSION_KEY));
    }

    public function test_invalid_working_ceremony_selection_is_rejected(): void
    {
        $this->actingAs($this->administrator)
            ->from('/dashboard')
            ->post(route('workspace.ceremony'), [
                'ceremony_id' => 999999,
            ])
            ->assertRedirect('/dashboard')
            ->assertSessionHasErrors('ceremony_id');

        $this->assertNull(session(WorkingCeremonyResolver::SESSION_KEY));
    }

    public function test_working_ceremony_context_is_shared_alongside_live_ceremony_context(): void
    {
        $liveCeremony = Ceremony::factory()->create([
            'name' => 'Live Ceremony 2026',
            'academic_year' => '2025-2026',
            'status' => CeremonyStatus::Active,
        ]);
        $workingCeremony = Ceremony::factory()->create([
            'name' => 'Working Ceremony 2027',
            'academic_year' => '2026-2027',
            'status' => CeremonyStatus::Prepared,
        ]);

        $this->actingAs($this->administrator)
            ->withSession([
                WorkingCeremonyResolver::SESSION_KEY => $workingCeremony->id,
            ])
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('workingCeremonyContext.ceremony.id', $workingCeremony->id)
                ->where('workingCeremonyContext.ceremony.name', 'Working Ceremony 2027')
                ->where('liveCeremonyContext.ceremony.id', $liveCeremony->id)
                ->where('liveCeremonyContext.ceremony.name', 'Live Ceremony 2026')
                ->where('ceremonyContext.active.id', $liveCeremony->id));
    }

    public function test_working_ceremony_can_differ_from_the_live_ceremony(): void
    {
        $liveCeremony = Ceremony::factory()->create([
            'name' => 'UM Commencement 2026',
            'status' => CeremonyStatus::Active,
        ]);
        $workingCeremony = Ceremony::factory()->create([
            'name' => 'UM Commencement 2027',
            'status' => CeremonyStatus::Draft,
        ]);

        $this->actingAs($this->administrator)
            ->withSession([
                WorkingCeremonyResolver::SESSION_KEY => $workingCeremony->id,
            ])
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('workingCeremonyContext.ceremony.id', $workingCeremony->id)
                ->where('workingCeremonyContext.ceremony.status.value', 'draft')
                ->where('liveCeremonyContext.ceremony.id', $liveCeremony->id)
                ->where('liveCeremonyContext.ceremony.status.value', 'active'));
    }

    public function test_logging_out_clears_working_ceremony_session_state_before_another_user_session_begins(): void
    {
        $liveCeremony = Ceremony::factory()->create([
            'name' => 'Live Ceremony 2026',
            'status' => CeremonyStatus::Active,
        ]);
        $workingCeremony = Ceremony::factory()->create([
            'name' => 'Working Ceremony 2027',
            'status' => CeremonyStatus::Draft,
        ]);
        $encoder = User::factory()->role(UserRole::DataEncoder)->create();

        $this->actingAs($this->administrator)
            ->withSession([
                WorkingCeremonyResolver::SESSION_KEY => $workingCeremony->id,
            ])
            ->post('/logout')
            ->assertRedirect('/login');

        $this->actingAs($encoder)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('workingCeremonyContext.ceremony.id', $liveCeremony->id)
                ->where('liveCeremonyContext.ceremony.id', $liveCeremony->id)
                ->where('workingCeremonyContext.ceremony.id', fn (int $id): bool => $id !== $workingCeremony->id));
    }
}
