<?php

namespace Tests\Feature\Domain;

use App\Enums\CeremonyStatus;
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
        $this->assertSame(CeremonyStatus::Active, $ceremony->status);
        $this->assertSame(
            [
                'GSS-PHASE3-A7K9M2Q4',
                'GSS-PHASE3-B8L3N5R7',
                'GSS-PHASE3-C4P6S8V2',
                'GSS-PHASE3-D9T5W7Y3',
                'GSS-PHASE3-E2H6J8X4',
            ],
            Student::query()
                ->whereBelongsTo($ceremony)
                ->orderBy('sequence_number')
                ->pluck('qr_token')
                ->all(),
        );
        $this->assertSame(
            4,
            Student::query()
                ->whereBelongsTo($ceremony)
                ->whereNotNull('audio_path')
                ->count(),
        );
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
