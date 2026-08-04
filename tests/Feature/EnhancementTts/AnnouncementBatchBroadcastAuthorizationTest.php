<?php

namespace Tests\Feature\EnhancementTts;

use App\Enums\UserRole;
use App\Models\Ceremony;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementBatchBroadcastAuthorizationTest extends TestCase
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
        $app['config']->set('broadcasting.connections.reverb.key', 'batch-test-key');
        $app['config']->set('broadcasting.connections.reverb.secret', 'batch-test-secret');
        $app['config']->set('broadcasting.connections.reverb.app_id', 'batch-test-app');
    }

    public function test_only_administrator_can_join_existing_ceremony_batch_channel(): void
    {
        $ceremony = Ceremony::factory()->create();
        $administrator = User::factory()->role(UserRole::Administrator)->create();
        $encoder = User::factory()->role(UserRole::DataEncoder)->create();
        $request = [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-announcement-batches.'.$ceremony->id,
        ];

        $this->actingAs($administrator)
            ->postJson('/broadcasting/auth', $request)
            ->assertOk();
        $this->actingAs($encoder)
            ->postJson('/broadcasting/auth', $request)
            ->assertForbidden();
        $this->actingAs($administrator)
            ->postJson('/broadcasting/auth', [
                ...$request,
                'channel_name' => 'private-announcement-batches.999999',
            ])->assertForbidden();
    }
}
