<?php

namespace Tests\Feature\Domain;

use App\Enums\UserRole;
use App\Models\Ceremony;
use App\Models\StageState;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_development_seed_data_contains_every_role_and_sample_domain_data(): void
    {
        config()->set('app.development_seed_password', 'local-test-password');

        $this->seed();

        foreach (UserRole::cases() as $role) {
            $this->assertDatabaseHas('users', [
                'role' => $role->value,
                'is_active' => true,
            ]);
        }

        $administrator = User::query()
            ->where('role', UserRole::Administrator)
            ->firstOrFail();
        $ceremony = Ceremony::query()
            ->where('name', 'Sample Graduation Ceremony')
            ->firstOrFail();

        $this->assertTrue(Hash::check('local-test-password', $administrator->password));
        $this->assertSame(5, User::query()->count());
        $this->assertSame(5, Student::query()->whereBelongsTo($ceremony)->count());
        $this->assertTrue(
            StageState::query()
                ->whereBelongsTo($ceremony)
                ->firstOrFail()
                ->ceremony
                ->is($ceremony),
        );
        $this->assertSame(0, $ceremony->queueItems()->count());
    }
}
