<?php

namespace Tests\Feature\Phase11;

use App\Enums\CeremonyStatus;
use App\Enums\UserRole;
use App\Models\Ceremony;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CompletedCeremonyWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_ceremony_opens_a_review_workspace(): void
    {
        $admin = User::factory()->role(UserRole::Administrator)->create();
        $ceremony = Ceremony::factory()->create([
            'status' => CeremonyStatus::Completed,
            'completed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.ceremonies.show', $ceremony))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Ceremonies/Completed')
                ->where('summary.graduate_count', 0)
                ->where('ceremony.status.value', 'completed'));
    }
}
