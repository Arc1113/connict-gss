<?php

namespace Tests\Feature\Phase3;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_scanner_page_access_is_limited_to_scanner_operators_and_administrators(): void
    {
        $this->withoutVite();

        $this->get('/scanner')->assertRedirect('/login');

        foreach (UserRole::cases() as $role) {
            $user = User::factory()->role($role)->create();
            $response = $this->actingAs($user)->get('/scanner');

            if (in_array($role, [UserRole::ScannerOperator, UserRole::Administrator], true)) {
                $response->assertOk();
            } else {
                $response->assertForbidden();
            }
        }
    }

    public function test_stage_routes_are_limited_to_stage_operators_and_administrators(): void
    {
        $this->withoutVite();

        $this->get('/stage')->assertRedirect('/login');
        $this->getJson('/stage/state')->assertUnauthorized();
        $this->postJson('/stage/complete-current')->assertUnauthorized();

        foreach (UserRole::cases() as $role) {
            $user = User::factory()->role($role)->create();
            $page = $this->actingAs($user)->get('/stage');
            $state = $this->actingAs($user)->getJson('/stage/state');
            $complete = $this->actingAs($user)->postJson('/stage/complete-current');

            if (in_array($role, [UserRole::StageOperator, UserRole::Administrator], true)) {
                $page->assertOk();
                $state->assertConflict();
                $complete->assertConflict();
            } else {
                $page->assertForbidden();
                $state->assertForbidden();
                $complete->assertForbidden();
            }
        }
    }

    public function test_scan_submission_returns_a_structured_unauthorized_result(): void
    {
        $user = User::factory()->role(UserRole::DataEncoder)->create();

        $this->actingAs($user)
            ->postJson('/scans', ['token' => 'NOT-AUTHORIZED'])
            ->assertForbidden()
            ->assertExactJson([
                'status' => 'unauthorized',
                'message' => 'You are not authorized to operate the scanner.',
            ]);
    }
}
