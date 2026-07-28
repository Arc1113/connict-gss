<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/_test/manage-users', fn () => response()->noContent())
            ->middleware(['auth', 'active', 'can:manage-users']);
    }

    public function test_every_approved_role_is_representable_and_cast_to_an_enum(): void
    {
        foreach (UserRole::cases() as $role) {
            $user = User::factory()->role($role)->create();

            $this->assertSame($role, $user->role);
            $this->assertTrue($user->hasRole($role));
        }

        $this->assertSame(
            [
                'administrator',
                'data_encoder',
                'scanner_operator',
                'stage_operator',
                'technical_operator',
            ],
            array_column(UserRole::cases(), 'value'),
        );
    }

    public function test_the_database_rejects_an_invalid_role(): void
    {
        $this->expectException(QueryException::class);

        DB::table('users')->insert([
            'name' => 'Invalid Role',
            'email' => 'invalid-role@example.test',
            'password' => 'not-used',
            'role' => 'super_user',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_each_operational_role_receives_only_its_baseline_ability(): void
    {
        $abilities = [
            UserRole::DataEncoder->value => 'manage-ceremony-data',
            UserRole::ScannerOperator->value => 'operate-scanner',
            UserRole::StageOperator->value => 'operate-stage',
            UserRole::TechnicalOperator->value => 'manage-technical-operations',
        ];

        foreach ($abilities as $roleValue => $allowedAbility) {
            $user = User::factory()->role(UserRole::from($roleValue))->create();

            foreach ($abilities as $ability) {
                $this->assertSame(
                    $ability === $allowedAbility,
                    Gate::forUser($user)->allows($ability),
                );
            }

            $this->assertFalse(Gate::forUser($user)->allows('manage-users'));
        }
    }

    public function test_an_administrator_receives_baseline_access_to_all_abilities(): void
    {
        $administrator = User::factory()
            ->role(UserRole::Administrator)
            ->create();

        foreach ([
            'manage-users',
            'manage-ceremony-data',
            'operate-scanner',
            'operate-stage',
            'manage-technical-operations',
        ] as $ability) {
            $this->assertTrue(Gate::forUser($administrator)->allows($ability));
        }
    }

    public function test_inactive_users_are_denied_all_gate_abilities(): void
    {
        $inactiveAdministrator = User::factory()
            ->role(UserRole::Administrator)
            ->inactive()
            ->create();

        $this->assertFalse(
            Gate::forUser($inactiveAdministrator)->allows('manage-users'),
        );
    }

    public function test_restricted_route_access_is_enforced_on_the_server(): void
    {
        $administrator = User::factory()
            ->role(UserRole::Administrator)
            ->create();
        $dataEncoder = User::factory()
            ->role(UserRole::DataEncoder)
            ->create();

        $this->actingAs($dataEncoder)
            ->get('/_test/manage-users')
            ->assertForbidden();

        $this->actingAs($administrator)
            ->get('/_test/manage-users')
            ->assertNoContent();
    }
}
