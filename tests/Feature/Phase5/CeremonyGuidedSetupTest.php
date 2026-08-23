<?php

namespace Tests\Feature\Phase5;

use App\Enums\UserRole;
use App\Models\Ceremony;
use App\Models\CeremonySetupProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CeremonyGuidedSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_setup_is_optional_and_skip_does_not_change_domain_readiness(): void
    {
        $user = User::factory()->role(UserRole::Administrator)->create();
        $ceremony = Ceremony::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.ceremonies.guided-setup', $ceremony))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Ceremonies/GuidedSetup')
                ->where('preparation.ready', false)
                ->where('setup.started', false));

        $this->actingAs($user)
            ->post(route('admin.ceremonies.guided-setup.skip-setup', $ceremony))
            ->assertRedirect(route('admin.ceremonies.show', $ceremony));

        $this->assertSame('draft', $ceremony->refresh()->status->value);
        $this->assertDatabaseMissing('ceremony_setup_progress', [
            'ceremony_id' => $ceremony->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_skipping_a_step_is_persisted_and_resume_revisits_unresolved_work(): void
    {
        $user = User::factory()->role(UserRole::Administrator)->create();
        $ceremony = Ceremony::factory()->create();

        $this->actingAs($user)
            ->post(route('admin.ceremonies.guided-setup.start', $ceremony))
            ->assertRedirect(route('admin.ceremonies.guided-setup', $ceremony));

        $progress = CeremonySetupProgress::query()->where([
            'ceremony_id' => $ceremony->id,
            'user_id' => $user->id,
        ])->firstOrFail();

        $this->assertSame('graduates', $progress->current_step);

        $this->actingAs($user)
            ->post(route('admin.ceremonies.guided-setup.skip', $ceremony), [
                'step' => 'graduates',
            ])
            ->assertRedirect(route('admin.ceremonies.guided-setup', $ceremony));

        $this->assertContains('graduates', $progress->refresh()->skipped_steps);
        $this->assertSame('photos', $progress->current_step);

        $this->actingAs($user)
            ->get(route('admin.ceremonies.guided-setup', $ceremony))
            ->assertInertia(fn (Assert $page) => $page
                ->where('setup.skipped_steps.0', 'graduates')
                ->where('currentStep.key', 'photos'));
    }

    public function test_preparation_role_can_resume_setup_but_live_state_is_untouched(): void
    {
        $user = User::factory()->role(UserRole::DataEncoder)->create();
        $ceremony = Ceremony::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.ceremonies.guided-setup', $ceremony))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Ceremonies/GuidedSetup'));

        $this->assertSame('draft', $ceremony->refresh()->status->value);
    }
}
