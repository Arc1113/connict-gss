<?php

namespace Tests\Feature\Phase10;

use App\Enums\UserRole;
use App\Models\Ceremony;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RunCeremonyWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_run_ceremony_exposes_only_authorized_operational_actions(): void
    {
        $encoder = User::factory()->role(UserRole::DataEncoder)->create();
        $ceremony = Ceremony::factory()->create();

        $this->actingAs($encoder)
            ->get(route('admin.ceremonies.run-ceremony', $ceremony))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/RunCeremony')
                ->where('actions.scanner', null)
                ->where('actions.stage_control', null)
                ->where('actions.stage_display', null));

        $stage = User::factory()->role(UserRole::StageOperator)->create();

        $this->actingAs($stage)
            ->get(route('admin.ceremonies.run-ceremony', $ceremony))
            ->assertForbidden();
    }
}
