<?php

namespace Tests\Feature\Phase8;

use App\Enums\UserRole;
use App\Models\Ceremony;
use App\Models\Student;
use App\Models\User;
use App\Support\WorkingCeremonyResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\InteractsWithStudentImports;
use Tests\TestCase;

class SemanticParentNavigationTest extends TestCase
{
    use InteractsWithStudentImports;
    use RefreshDatabase;

    private Ceremony $ceremony;

    private Student $student;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->administrator = User::factory()->role(UserRole::Administrator)->create();
        $this->ceremony = Ceremony::factory()->create();
        $this->student = Student::factory()->create([
            'ceremony_id' => $this->ceremony->id,
        ]);
    }

    public function test_direct_ceremony_scoped_pages_supply_the_urls_needed_by_semantic_parents(): void
    {
        $session = [WorkingCeremonyResolver::SESSION_KEY => $this->ceremony->id];

        $this->actingAs($this->administrator)
            ->withSession($session)
            ->get("/admin/ceremonies/{$this->ceremony->id}/graduates/create")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Students/Create')
                ->where('workspace.locked', true)
                ->where('workspace.urls.graduates', url("/admin/ceremonies/{$this->ceremony->id}/graduates")));

        $this->actingAs($this->administrator)
            ->withSession($session)
            ->get("/admin/ceremonies/{$this->ceremony->id}/graduates/{$this->student->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Students/Edit')
                ->where('workspace.locked', true)
                ->where('workspace.urls.graduates', url("/admin/ceremonies/{$this->ceremony->id}/graduates")));

        $this->actingAs($this->administrator)
            ->withSession($session)
            ->get("/admin/ceremonies/{$this->ceremony->id}/qr/{$this->student->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Students/QrCodes/Show')
                ->where('workspace.urls.graduates', url("/admin/ceremonies/{$this->ceremony->id}/graduates")));
    }

    public function test_direct_import_mapping_keeps_its_ceremony_scoped_import_parent(): void
    {
        $studentImport = $this->inspectCsv(
            $this->administrator,
            $this->ceremony,
            $this->graduateCsv([
                ['2028-0001', 'Semantic', '', 'Parent', 'BSCS', 1, 'eligible'],
            ]),
        );

        $this->actingAs($this->administrator)
            ->withSession([WorkingCeremonyResolver::SESSION_KEY => $this->ceremony->id])
            ->get("/admin/ceremonies/{$this->ceremony->id}/import/{$studentImport->token}/mapping")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Students/Import/Mapping')
                ->where('workspace.urls.import', url("/admin/ceremonies/{$this->ceremony->id}/import")));
    }
}
