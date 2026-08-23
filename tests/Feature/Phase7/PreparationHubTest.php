<?php

namespace Tests\Feature\Phase7;

use App\Enums\UserRole;
use App\Models\Ceremony;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PreparationHubTest extends TestCase
{
    use RefreshDatabase;

    public function test_preparation_hub_is_scoped_to_the_working_ceremony_and_exposes_task_rows(): void
    {
        $user = User::factory()->role(UserRole::Administrator)->create();
        $ceremony = Ceremony::factory()->create(['name' => 'Preparation Hub Ceremony']);

        $this->actingAs($user)
            ->get(route('admin.ceremonies.preparation', $ceremony))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Preparation/Index')
                ->where('ceremony.name', 'Preparation Hub Ceremony')
                ->where('preparation.steps.0.key', 'graduates')
                ->where('preparation.steps.1.key', 'photos')
                ->where('preparation.steps.2.key', 'announcements')
                ->where('preparation.steps.3.key', 'qr')
                ->where('preparation.steps.4.key', 'stage_design')
                ->where('preparation.steps.5.key', 'readiness'));
    }
}
