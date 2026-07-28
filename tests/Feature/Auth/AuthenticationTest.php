<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_an_active_user_can_log_in(): void
    {
        $user = User::factory()->create([
            'email' => 'operator@example.test',
            'password' => 'correct-password',
        ]);

        $response = $this->post('/login', [
            'email' => ' OPERATOR@example.test ',
            'password' => 'correct-password',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'operator@example.test',
            'password' => 'correct-password',
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'operator@example.test',
            'password' => 'incorrect-password',
        ]);

        $response
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_an_inactive_user_cannot_log_in(): void
    {
        User::factory()->inactive()->create([
            'email' => 'inactive@example.test',
            'password' => 'correct-password',
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'inactive@example.test',
            'password' => 'correct-password',
        ]);

        $response
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_logout_invalidates_the_authenticated_session(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_protected_routes_require_authentication(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_public_registration_is_unavailable(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register')->assertNotFound();
    }

    public function test_a_deactivated_user_cannot_remain_authorized(): void
    {
        $user = User::factory()->create();
        $user->update(['is_active' => false]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_authenticated_user_data_is_shared_safely_with_inertia(): void
    {
        $user = User::factory()
            ->role(UserRole::StageOperator)
            ->create([
                'name' => 'Stage Operator',
                'email' => 'stage@example.test',
            ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('auth.user.id', $user->id)
                ->where('auth.user.name', 'Stage Operator')
                ->where('auth.user.email', 'stage@example.test')
                ->where('auth.user.role.value', 'stage_operator')
                ->where('auth.user.role.label', 'Stage operator')
                ->where('auth.user.is_active', true)
                ->missing('auth.user.password')
                ->missing('auth.user.remember_token'));
    }
}
