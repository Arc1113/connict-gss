<?php

namespace Tests\Feature\Phase4;

use App\Enums\UserRole;
use App\Models\Ceremony;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdministrationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guests_are_redirected_from_administration_routes(): void
    {
        $ceremony = Ceremony::factory()->create();
        $student = Student::factory()->create(['ceremony_id' => $ceremony->id]);

        foreach ([
            '/admin',
            '/admin/ceremonies',
            "/admin/ceremonies/{$ceremony->id}",
            '/admin/students',
            "/admin/students/{$student->id}",
            '/admin/audit-logs',
        ] as $uri) {
            $this->get($uri)->assertRedirect('/login');
        }
    }

    public function test_administrators_receive_full_phase_four_access(): void
    {
        $administrator = User::factory()->role(UserRole::Administrator)->create();
        $ceremony = Ceremony::factory()->create();
        $student = Student::factory()->create(['ceremony_id' => $ceremony->id]);

        foreach ([
            '/admin',
            '/admin/ceremonies',
            '/admin/ceremonies/create',
            "/admin/ceremonies/{$ceremony->id}",
            "/admin/ceremonies/{$ceremony->id}/edit",
            '/admin/students',
            '/admin/students/create',
            "/admin/students/{$student->id}",
            "/admin/students/{$student->id}/edit",
            '/admin/audit-logs',
        ] as $uri) {
            $this->actingAs($administrator)->get($uri)->assertOk();
        }
    }

    public function test_data_encoders_can_prepare_graduates_but_not_manage_ceremonies_or_delete(): void
    {
        $encoder = User::factory()->role(UserRole::DataEncoder)->create();
        $ceremony = Ceremony::factory()->create();
        $student = Student::factory()->create(['ceremony_id' => $ceremony->id]);

        foreach ([
            '/admin',
            '/admin/ceremonies',
            "/admin/ceremonies/{$ceremony->id}",
            '/admin/students',
            '/admin/students/create',
            "/admin/students/{$student->id}",
            "/admin/students/{$student->id}/edit",
        ] as $uri) {
            $this->actingAs($encoder)->get($uri)->assertOk();
        }

        foreach ([
            '/admin/ceremonies/create',
            "/admin/ceremonies/{$ceremony->id}/edit",
            '/admin/audit-logs',
        ] as $uri) {
            $this->actingAs($encoder)->get($uri)->assertForbidden();
        }

        $this->actingAs($encoder)
            ->delete("/admin/students/{$student->id}")
            ->assertForbidden();
        $this->actingAs($encoder)
            ->delete("/admin/ceremonies/{$ceremony->id}")
            ->assertForbidden();

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_other_operational_roles_cannot_access_administration(): void
    {
        foreach ([
            UserRole::ScannerOperator,
            UserRole::StageOperator,
            UserRole::TechnicalOperator,
        ] as $role) {
            $user = User::factory()->role($role)->create();

            $this->actingAs($user)->get('/admin')->assertForbidden();
            $this->actingAs($user)->get('/admin/ceremonies')->assertForbidden();
            $this->actingAs($user)->get('/admin/students')->assertForbidden();
        }
    }
}
