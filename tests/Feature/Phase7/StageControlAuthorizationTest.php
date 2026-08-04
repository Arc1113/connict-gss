<?php

namespace Tests\Feature\Phase7;

use App\Enums\CeremonyStatus;
use App\Enums\UserRole;
use App\Models\Ceremony;
use App\Models\StageState;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StageControlAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $broadcasts = app(BroadcastManager::class);
        $broadcasts->setDefaultDriver('reverb');
        $broadcasts->forgetDrivers();
        require base_path('routes/channels.php');
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('broadcasting.default', 'reverb');
        $app['config']->set('broadcasting.connections.reverb.key', 'phase7-key');
        $app['config']->set(
            'broadcasting.connections.reverb.secret',
            'phase7-secret',
        );
        $app['config']->set(
            'broadcasting.connections.reverb.app_id',
            'phase7-app',
        );
    }

    public function test_stage_control_page_queue_commands_and_channel_use_stage_authorization(): void
    {
        $this->withoutVite();
        $ceremony = Ceremony::factory()->create([
            'status' => CeremonyStatus::Active,
            'activated_at' => now(),
        ]);
        StageState::factory()->create(['ceremony_id' => $ceremony->id]);

        $this->get('/stage-control')->assertRedirect('/login');
        $this->getJson('/stage/queue')->assertUnauthorized();
        $this->postJson('/stage/present-next')->assertUnauthorized();

        foreach (UserRole::cases() as $role) {
            $user = User::factory()->role($role)->create();
            $allowed = in_array($role, [
                UserRole::StageOperator,
                UserRole::Administrator,
            ], true);

            $responses = [
                $this->actingAs($user)->get('/stage-control'),
                $this->actingAs($user)->getJson('/stage/queue'),
                $this->actingAs($user)->postJson('/stage/present-next'),
                $this->actingAs($user)->postJson('/stage/replay'),
                $this->actingAs($user)->postJson('/stage/automatic/pause'),
            ];

            foreach ($responses as $index => $response) {
                if ($allowed) {
                    $index < 2
                        ? $response->assertOk()
                        : $response->assertConflict();
                } else {
                    $response->assertForbidden();
                }
            }
        }
    }

    public function test_inactive_stage_operator_is_denied(): void
    {
        $user = User::factory()
            ->role(UserRole::StageOperator)
            ->inactive()
            ->create();

        $this->actingAs($user)->get('/stage-control')->assertRedirect('/login');
        $this->actingAs($user)->postJson('/stage/present-next')->assertRedirect('/login');
    }

    public function test_stage_control_private_channel_excludes_other_roles(): void
    {
        $ceremony = Ceremony::factory()->create([
            'status' => CeremonyStatus::Active,
        ]);

        foreach (UserRole::cases() as $role) {
            $user = User::factory()->role($role)->create();
            $response = $this->actingAs($user)->postJson(
                '/broadcasting/auth',
                [
                    'channel_name' => "private-stage-control.{$ceremony->id}",
                    'socket_id' => '1234.5678',
                ],
            );

            in_array($role, [
                UserRole::StageOperator,
                UserRole::Administrator,
            ], true)
                ? $response->assertOk()
                : $response->assertForbidden();
        }
    }
}
