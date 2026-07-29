<?php

namespace Tests\Feature\Phase4;

use App\Enums\EligibilityStatus;
use App\Enums\StageQueueStatus;
use App\Enums\UserRole;
use App\Models\Ceremony;
use App\Models\StageQueueItem;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StudentAdministrationTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    private Ceremony $ceremony;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->administrator = User::factory()
            ->role(UserRole::Administrator)
            ->create();
        $this->ceremony = Ceremony::factory()->create();
    }

    public function test_authorized_users_can_create_review_search_filter_and_sort_graduates(): void
    {
        $encoder = User::factory()->role(UserRole::DataEncoder)->create();

        $this->actingAs($encoder)
            ->post('/admin/students', $this->payload())
            ->assertRedirect();

        $student = Student::query()->sole();

        $this->assertSame('Ana Marie dela Cruz', $student->display_name);
        $this->assertSame('GSS-ANA-2027', $student->qr_token);
        $this->assertSame(EligibilityStatus::Eligible, $student->eligibility_status);

        $this->actingAs($encoder)
            ->get(
                '/admin/students?search=Ana+Marie'
                ."&ceremony_id={$this->ceremony->id}"
                .'&college=College+of+Computing'
                .'&program=Bachelor+of+Science+in+Computer+Science'
                .'&eligibility_status=eligible'
                .'&media=missing_any'
                .'&stage_status=not_presented'
                .'&sort=name',
            )
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Students/Index')
                ->has('students.data', 1)
                ->where('students.data.0.id', $student->id)
                ->where('students.data.0.display_name', 'Ana Marie dela Cruz')
                ->where('students.data.0.media.photo', 'Missing')
                ->missing('students.data.0.qr_token')
                ->where('filters.sort', 'name'));

        $this->actingAs($encoder)
            ->get('/admin/students?search=2027-0007')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('students.data', 1)
                ->where('students.data.0.id', $student->id)
                ->where('filters.search', '2027-0007'));
    }

    public function test_update_preserves_the_existing_qr_token_and_reviewed_names_when_blank_or_unchanged(): void
    {
        $student = Student::factory()->create([
            'ceremony_id' => $this->ceremony->id,
            'student_number' => '2027-0001',
            'qr_token' => 'PRESERVE-SECRET',
            'display_name' => 'Ma. Lourdes P. Reyes',
            'announcement_name' => 'Ma. Lourdes P. Reyes',
            'sequence_number' => 4,
        ]);

        $this->actingAs($this->administrator)
            ->put("/admin/students/{$student->id}", $this->payload([
                'student_number' => '2027-0001',
                'qr_token' => '',
                'first_name' => 'Maria Lourdes',
                'display_name' => 'Ma. Lourdes P. Reyes',
                'announcement_name' => 'Ma. Lourdes P. Reyes',
                'sequence_number' => 4,
            ]))
            ->assertRedirect("/admin/students/{$student->id}");

        $student->refresh();

        $this->assertSame('PRESERVE-SECRET', $student->qr_token);
        $this->assertSame('Ma. Lourdes P. Reyes', $student->display_name);
        $this->assertSame('Ma. Lourdes P. Reyes', $student->announcement_name);
        $this->assertSame('Maria Lourdes', $student->first_name);
    }

    public function test_uniqueness_and_required_field_conflicts_are_friendly(): void
    {
        Student::factory()->create([
            'ceremony_id' => $this->ceremony->id,
            'student_number' => '2027-DUPLICATE',
            'qr_token' => 'DUPLICATE-TOKEN',
            'sequence_number' => 22,
        ]);

        $this->actingAs($this->administrator)
            ->from('/admin/students/create')
            ->post('/admin/students', $this->payload([
                'student_number' => '2027-DUPLICATE',
                'qr_token' => 'duplicate-token',
                'sequence_number' => 22,
                'program' => '',
                'eligibility_status' => 'unknown',
            ]))
            ->assertRedirect('/admin/students/create')
            ->assertSessionHasErrors([
                'student_number',
                'qr_token',
                'sequence_number',
                'program',
                'eligibility_status',
            ]);

        $this->assertDatabaseCount('students', 1);
    }

    public function test_filters_and_pagination_preserve_query_parameters(): void
    {
        Student::factory()->count(22)->create([
            'ceremony_id' => $this->ceremony->id,
            'college' => 'College of Engineering',
            'eligibility_status' => EligibilityStatus::Pending,
        ]);

        $this->actingAs($this->administrator)
            ->get(
                "/admin/students?ceremony_id={$this->ceremony->id}"
                .'&college=College+of+Engineering&eligibility_status=pending',
            )
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('students.total', 22)
                ->where('students.per_page', 20)
                ->has('students.data', 20)
                ->where(
                    'students.next_page_url',
                    fn (string $url) => str_contains(
                        $url,
                        'college=College%20of%20Engineering',
                    ) && str_contains($url, 'eligibility_status=pending'),
                ));
    }

    public function test_deletion_is_blocked_after_operational_history_and_allowed_before_it(): void
    {
        $protected = Student::factory()->create([
            'ceremony_id' => $this->ceremony->id,
        ]);
        StageQueueItem::factory()->create([
            'ceremony_id' => $this->ceremony->id,
            'student_id' => $protected->id,
            'status' => StageQueueStatus::Completed,
        ]);

        $this->actingAs($this->administrator)
            ->from("/admin/students/{$protected->id}")
            ->delete("/admin/students/{$protected->id}")
            ->assertSessionHasErrors('student');

        $unused = Student::factory()->create([
            'ceremony_id' => $this->ceremony->id,
        ]);

        $this->actingAs($this->administrator)
            ->delete("/admin/students/{$unused->id}")
            ->assertRedirect('/admin/students');

        $this->assertDatabaseHas('students', ['id' => $protected->id]);
        $this->assertDatabaseMissing('students', ['id' => $unused->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'student.deleted',
            'entity_id' => $unused->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'ceremony_id' => $this->ceremony->id,
            'student_number' => '2027-0007',
            'qr_token' => 'gss-ana-2027',
            'first_name' => 'Ana Marie',
            'middle_name' => null,
            'last_name' => 'dela Cruz',
            'suffix' => null,
            'display_name' => 'Ana Marie dela Cruz',
            'announcement_name' => 'Ana Marie dela Cruz',
            'pronunciation_guide' => null,
            'college' => 'College of Computing',
            'program' => 'Bachelor of Science in Computer Science',
            'major' => 'Software Engineering',
            'honors' => 'Magna Cum Laude',
            'sequence_number' => 7,
            'eligibility_status' => EligibilityStatus::Eligible->value,
            ...$overrides,
        ];
    }
}
