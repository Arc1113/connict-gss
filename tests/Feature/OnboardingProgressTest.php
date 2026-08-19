<?php

namespace Tests\Feature;

use App\Enums\CeremonyStatus;
use App\Enums\UserRole;
use App\Models\Ceremony;
use App\Models\User;
use App\Models\UserOnboardingProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OnboardingProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_guide_is_shared_and_progress_is_isolated_by_user(): void
    {
        $first = User::factory()->create(['role' => UserRole::DataEncoder]);
        $second = User::factory()->create(['role' => UserRole::DataEncoder]);

        $this->actingAs($first)->get('/admin/students')->assertInertia(
            fn (Assert $page) => $page
                ->where('onboarding.key', 'data-encoder-getting-started')
                ->where('onboarding.version', 1)
                ->where('onboarding.auto_offer', true),
        );
        $this->actingAs($first)->putJson('/onboarding/data-encoder-getting-started', [
            'status' => 'in_progress',
            'current_step' => 2,
        ])->assertOk()->assertJsonPath('progress.current_step', 2);

        $this->assertDatabaseHas('user_onboarding_progress', [
            'user_id' => $first->id,
            'current_step' => 2,
        ]);
        $this->assertDatabaseMissing('user_onboarding_progress', ['user_id' => $second->id]);
    }

    public function test_user_cannot_write_another_roles_tour_and_can_reset_their_own(): void
    {
        $user = User::factory()->create(['role' => UserRole::ScannerOperator]);

        $this->actingAs($user)->putJson('/onboarding/administrator-getting-started', [
            'status' => 'completed',
            'current_step' => 0,
        ])->assertForbidden();
        $this->actingAs($user)->putJson('/onboarding/scanner-operator-rehearsal', [
            'status' => 'skipped',
            'current_step' => 1,
        ])->assertOk();
        $this->actingAs($user)->post('/onboarding/scanner-operator-rehearsal/reset')->assertNoContent();
        $this->assertSame(0, UserOnboardingProgress::query()->count());
    }

    public function test_live_ceremony_suppresses_automatic_operational_tours_without_removing_help_access(): void
    {
        Ceremony::factory()->create(['status' => CeremonyStatus::Active]);
        $user = User::factory()->create(['role' => UserRole::TechnicalOperator]);

        $this->actingAs($user)->get('/dashboard')->assertInertia(
            fn (Assert $page) => $page
                ->where('onboarding.key', 'technical-operator-readiness')
                ->where('onboarding.auto_offer', false)
                ->where('onboarding.suppressed_for_live_ceremony', true),
        );
    }
}
