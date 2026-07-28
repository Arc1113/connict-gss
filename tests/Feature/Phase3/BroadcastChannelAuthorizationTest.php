<?php

namespace Tests\Feature\Phase3;

use App\Enums\CeremonyStatus;
use App\Enums\UserRole;
use App\Models\Ceremony;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BroadcastChannelAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Ceremony $ceremony;

    protected function setUp(): void
    {
        parent::setUp();

        $broadcasts = app(BroadcastManager::class);
        $broadcasts->setDefaultDriver('reverb');
        $broadcasts->forgetDrivers();
        require base_path('routes/channels.php');

        $this->ceremony = Ceremony::factory()->create([
            'status' => CeremonyStatus::Active,
            'activated_at' => now(),
        ]);
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('broadcasting.default', 'reverb');
        $app['config']->set(
            'broadcasting.connections.reverb.key',
            'phase3-test-key',
        );
        $app['config']->set(
            'broadcasting.connections.reverb.secret',
            'phase3-test-secret',
        );
        $app['config']->set(
            'broadcasting.connections.reverb.app_id',
            'phase3-test-app',
        );
    }

    public function test_stage_operator_and_administrator_can_join_the_active_private_channel(): void
    {
        foreach ([UserRole::StageOperator, UserRole::Administrator] as $role) {
            $user = User::factory()->role($role)->create();

            $response = $this->actingAs($user)
                ->postJson('/broadcasting/auth', $this->channelRequest())
                ->assertOk();

            $this->assertNotSame('', $response->getContent());
        }
    }

    public function test_guests_inactive_accounts_and_other_roles_cannot_join(): void
    {
        $this->postJson('/broadcasting/auth', $this->channelRequest())
            ->assertForbidden();

        $inactive = User::factory()
            ->role(UserRole::StageOperator)
            ->inactive()
            ->create();
        $scanner = User::factory()->role(UserRole::ScannerOperator)->create();

        $this->actingAs($inactive)
            ->postJson('/broadcasting/auth', $this->channelRequest())
            ->assertForbidden();
        $this->actingAs($scanner)
            ->postJson('/broadcasting/auth', $this->channelRequest())
            ->assertForbidden();
    }

    public function test_an_inactive_or_ambiguous_ceremony_channel_is_denied(): void
    {
        $operator = User::factory()->role(UserRole::StageOperator)->create();
        $this->ceremony->update(['status' => CeremonyStatus::Prepared]);

        $this->actingAs($operator)
            ->postJson('/broadcasting/auth', $this->channelRequest())
            ->assertForbidden();

        $this->ceremony->update(['status' => CeremonyStatus::Active]);
        Ceremony::factory()->create(['status' => CeremonyStatus::Active]);

        $this->actingAs($operator)
            ->postJson('/broadcasting/auth', $this->channelRequest())
            ->assertForbidden();
    }

    /**
     * @return array<string, string>
     */
    private function channelRequest(): array
    {
        return [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-stage.'.$this->ceremony->id,
        ];
    }
}
