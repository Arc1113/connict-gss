<?php

namespace Tests\Feature\Phase12;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_administrators_can_access_user_administration(): void
    {
        $administrator = User::factory()
            ->role(UserRole::Administrator)
            ->create();

        $this->actingAs($administrator)
            ->get('/admin/users')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Users/Index')
                ->has('users.data')
                ->has('roleOptions', 5));

        foreach ([
            UserRole::DataEncoder,
            UserRole::ScannerOperator,
            UserRole::StageOperator,
            UserRole::TechnicalOperator,
        ] as $role) {
            $user = User::factory()->role($role)->create();

            $this->actingAs($user)->get('/admin/users')->assertForbidden();
            $this->actingAs($user)->get('/admin/users/create')->assertForbidden();
            $this->actingAs($user)
                ->post('/admin/users', $this->validAttributes())
                ->assertForbidden();
        }
    }

    public function test_administrator_creates_a_role_account_with_a_safe_audit(): void
    {
        $administrator = User::factory()
            ->role(UserRole::Administrator)
            ->create();

        $this->actingAs($administrator)
            ->post('/admin/users', $this->validAttributes([
                'name' => 'Maria Lourdes Stage Operator',
                'email' => 'stage.operator@example.test',
                'role' => UserRole::StageOperator->value,
            ]))
            ->assertRedirect();

        $created = User::query()
            ->where('email', 'stage.operator@example.test')
            ->sole();
        $this->assertSame(UserRole::StageOperator, $created->role);
        $this->assertTrue($created->is_active);
        $this->assertTrue(Hash::check('LocalCeremony!2026', $created->password));

        $audit = AuditLog::query()
            ->where('action', 'user.created')
            ->where('entity_type', 'user')
            ->where('entity_id', $created->id)
            ->sole();
        $encoded = json_encode($audit->new_values, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('LocalCeremony!2026', $encoded);
        $this->assertStringNotContainsString($created->password, $encoded);
        $this->assertTrue($audit->new_values['password_set']);
    }

    public function test_account_validation_requires_unique_email_and_strong_confirmation(): void
    {
        $administrator = User::factory()
            ->role(UserRole::Administrator)
            ->create();
        User::factory()->create(['email' => 'used@example.test']);

        $this->actingAs($administrator)
            ->post('/admin/users', $this->validAttributes([
                'email' => 'used@example.test',
                'password' => 'short',
                'password_confirmation' => 'different',
            ]))
            ->assertSessionHasErrors(['email', 'password']);
    }

    public function test_administrator_updates_role_status_and_password_without_exposing_it(): void
    {
        $administrator = User::factory()
            ->role(UserRole::Administrator)
            ->create();
        $operator = User::factory()
            ->role(UserRole::ScannerOperator)
            ->create();

        $this->actingAs($administrator)
            ->put("/admin/users/{$operator->id}", [
                'name' => 'Updated Technical Operator',
                'email' => 'technical.updated@example.test',
                'role' => UserRole::TechnicalOperator->value,
                'is_active' => false,
                'password' => 'Replacement!2026',
                'password_confirmation' => 'Replacement!2026',
            ])
            ->assertRedirect();

        $operator->refresh();
        $this->assertSame(UserRole::TechnicalOperator, $operator->role);
        $this->assertFalse($operator->is_active);
        $this->assertTrue(Hash::check('Replacement!2026', $operator->password));

        $audit = AuditLog::query()
            ->where('action', 'user.updated')
            ->where('entity_id', $operator->id)
            ->sole();
        $this->assertTrue($audit->new_values['password_changed']);
        $this->assertArrayNotHasKey('password', $audit->new_values);
        $this->assertArrayNotHasKey('password', $audit->old_values ?? []);
    }

    public function test_blank_edit_password_preserves_the_existing_hash(): void
    {
        $administrator = User::factory()
            ->role(UserRole::Administrator)
            ->create();
        $operator = User::factory()
            ->role(UserRole::ScannerOperator)
            ->create();
        $before = $operator->password;

        $this->actingAs($administrator)
            ->put("/admin/users/{$operator->id}", [
                'name' => 'Renamed Scanner Operator',
                'email' => $operator->email,
                'role' => $operator->role->value,
                'is_active' => true,
                'password' => null,
                'password_confirmation' => null,
            ])
            ->assertRedirect();

        $this->assertSame($before, $operator->refresh()->password);
    }

    public function test_current_administrator_cannot_deactivate_or_demote_their_account(): void
    {
        $administrator = User::factory()
            ->role(UserRole::Administrator)
            ->create();

        $this->actingAs($administrator)
            ->put("/admin/users/{$administrator->id}", [
                'name' => $administrator->name,
                'email' => $administrator->email,
                'role' => UserRole::Administrator->value,
                'is_active' => false,
                'password' => null,
                'password_confirmation' => null,
            ])
            ->assertSessionHasErrors('is_active');

        $this->actingAs($administrator)
            ->put("/admin/users/{$administrator->id}", [
                'name' => $administrator->name,
                'email' => $administrator->email,
                'role' => UserRole::DataEncoder->value,
                'is_active' => true,
                'password' => null,
                'password_confirmation' => null,
            ])
            ->assertSessionHasErrors('role');

        $administrator->refresh();
        $this->assertTrue($administrator->is_active);
        $this->assertSame(UserRole::Administrator, $administrator->role);
    }

    public function test_account_list_filters_and_never_returns_password_data(): void
    {
        $administrator = User::factory()
            ->role(UserRole::Administrator)
            ->create();
        User::factory()->create([
            'name' => 'Technical Operations Lead',
            'email' => 'tech@example.test',
            'role' => UserRole::TechnicalOperator,
            'is_active' => false,
        ]);

        $response = $this->actingAs($administrator)
            ->get('/admin/users?search=technical&role=technical_operator&status=inactive')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('users.data', 1)
                ->where('users.data.0.email', 'tech@example.test')
                ->where('users.data.0.is_active', false));

        $this->assertStringNotContainsString('password', $response->getContent());
        $this->assertStringNotContainsString('remember_token', $response->getContent());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validAttributes(array $overrides = []): array
    {
        return [
            'name' => 'Local Scanner Operator',
            'email' => 'local.scanner@example.test',
            'role' => UserRole::ScannerOperator->value,
            'is_active' => true,
            'password' => 'LocalCeremony!2026',
            'password_confirmation' => 'LocalCeremony!2026',
            ...$overrides,
        ];
    }
}
