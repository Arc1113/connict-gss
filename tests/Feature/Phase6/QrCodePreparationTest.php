<?php

namespace Tests\Feature\Phase6;

use App\Enums\EligibilityStatus;
use App\Enums\StageQueueStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Ceremony;
use App\Models\StageQueueItem;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QrCodePreparationTest extends TestCase
{
    use RefreshDatabase;

    private Ceremony $ceremony;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->ceremony = Ceremony::factory()->create([
            'name' => 'University Commencement Exercises',
            'academic_year' => '2026–2027',
        ]);
        $this->student = Student::factory()->create([
            'ceremony_id' => $this->ceremony->id,
            'student_number' => '2027-0007',
            'display_name' => 'Christopher Emmanuel Villanueva-Santos',
            'college' => 'College of Engineering',
            'program' => 'Bachelor of Science in Electronics Engineering',
            'sequence_number' => 7,
            'eligibility_status' => EligibilityStatus::Eligible,
        ]);
    }

    public function test_qr_preparation_routes_enforce_the_approved_roles(): void
    {
        $this->get('/admin/students/qr-codes')->assertRedirect('/login');
        $this->get(
            "/admin/students/{$this->student->id}/qr-code/image",
        )->assertRedirect('/login');

        foreach (UserRole::cases() as $role) {
            $user = User::factory()->role($role)->create();
            $index = $this->actingAs($user)->get('/admin/students/qr-codes');
            $card = $this->actingAs($user)->get(
                "/admin/students/{$this->student->id}/qr-code",
            );
            $image = $this->actingAs($user)->get(
                "/admin/students/{$this->student->id}/qr-code/image",
            );

            if (in_array(
                $role,
                [UserRole::Administrator, UserRole::DataEncoder],
                true,
            )) {
                $index->assertOk();
                $card->assertOk();
                $image->assertOk();
            } else {
                $index->assertForbidden();
                $card->assertForbidden();
                $image->assertForbidden();
            }
        }

        $this->actingAs(
            User::factory()->role(UserRole::DataEncoder)->create(),
        )
            ->get('/admin/students/999999/qr-code/image')
            ->assertNotFound();
    }

    public function test_workspace_exposes_readiness_without_exposing_raw_tokens(): void
    {
        $encoder = User::factory()->role(UserRole::DataEncoder)->create();

        $response = $this->actingAs($encoder)->get(
            "/admin/students/qr-codes?ceremony_id={$this->ceremony->id}"
            .'&college=College+of+Engineering',
        );

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Students/QrCodes/Index')
                ->has('students.data', 1)
                ->where('students.data.0.id', $this->student->id)
                ->where('students.data.0.qr.status', 'ready')
                ->where('students.data.0.qr.printable', true)
                ->missing('students.data.0.qr_token')
                ->where('maximumBatchSize', 100));

        $this->assertStringNotContainsString(
            $this->student->qr_token,
            $response->getContent(),
        );
    }

    public function test_protected_svg_and_individual_print_are_local_and_hide_the_token(): void
    {
        $encoder = User::factory()->role(UserRole::DataEncoder)->create();
        $image = $this->actingAs($encoder)->get(
            "/admin/students/{$this->student->id}/qr-code/image",
        );

        $image
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml; charset=UTF-8')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Robots-Tag', 'noindex, noarchive');
        $this->assertStringContainsString(
            'no-store',
            (string) $image->headers->get('Cache-Control'),
        );

        $svg = $image->getContent();
        $this->assertStringContainsString('fill="#ffffff"', $svg);
        $this->assertStringContainsString('translate(4,4)', $svg);
        $this->assertStringNotContainsString($this->student->qr_token, $svg);
        $this->assertStringNotContainsString('<script', mb_strtolower($svg));

        $print = $this->actingAs($encoder)->get(
            "/admin/students/{$this->student->id}/qr-code/print",
        );

        $print
            ->assertOk()
            ->assertSee($this->student->display_name)
            ->assertSee($this->student->program)
            ->assertSee($this->student->student_number)
            ->assertSee('@page', false)
            ->assertSee('A4 portrait')
            ->assertDontSee($this->student->qr_token);
        $this->assertStringContainsString(
            'no-store',
            (string) $print->headers->get('Cache-Control'),
        );
        $this->assertDoesNotMatchRegularExpression(
            '/https?:\/\//i',
            $print->getContent(),
        );
    }

    public function test_ineligible_or_completed_graduates_cannot_be_printed(): void
    {
        $encoder = User::factory()->role(UserRole::DataEncoder)->create();
        $this->student->update([
            'eligibility_status' => EligibilityStatus::Ineligible,
        ]);

        $this->actingAs($encoder)
            ->get("/admin/students/{$this->student->id}/qr-code/image")
            ->assertStatus(409);

        $this->student->update([
            'eligibility_status' => EligibilityStatus::Eligible,
        ]);
        StageQueueItem::factory()->create([
            'ceremony_id' => $this->ceremony->id,
            'student_id' => $this->student->id,
            'status' => StageQueueStatus::Completed,
        ]);

        $this->actingAs($encoder)
            ->get("/admin/students/{$this->student->id}/qr-code/print")
            ->assertStatus(409);
    }

    public function test_batch_print_is_deterministic_bounded_and_safely_audited(): void
    {
        $encoder = User::factory()->role(UserRole::DataEncoder)->create();
        $second = Student::factory()->create([
            'ceremony_id' => $this->ceremony->id,
            'student_number' => '2027-0002',
            'display_name' => 'Ana Marie dela Cruz',
            'program' => 'Bachelor of Science in Computer Science',
            'sequence_number' => 2,
            'eligibility_status' => EligibilityStatus::Eligible,
        ]);
        $third = Student::factory()->create([
            'ceremony_id' => $this->ceremony->id,
            'student_number' => '2027-0011',
            'display_name' => 'Ma. Lourdes P. Reyes',
            'program' => 'Bachelor of Science in Business Administration',
            'sequence_number' => 11,
            'eligibility_status' => EligibilityStatus::Eligible,
        ]);

        $response = $this->actingAs($encoder)->post(
            '/admin/students/qr-codes/batch',
            [
                'ceremony_id' => $this->ceremony->id,
                'student_ids' => [
                    $third->id,
                    $this->student->id,
                    $second->id,
                    $second->id,
                ],
                'college' => 'College of Engineering',
                'sequence_from' => 1,
                'sequence_to' => 20,
            ],
        );

        $response
            ->assertOk()
            ->assertSee('Graduate QR batch')
            ->assertSee('3 graduates')
            ->assertDontSee($this->student->qr_token)
            ->assertDontSee($second->qr_token)
            ->assertDontSee($third->qr_token);
        $content = $response->getContent();
        $this->assertLessThan(
            strpos($content, $this->student->display_name),
            strpos($content, $second->display_name),
        );
        $this->assertLessThan(
            strpos($content, $third->display_name),
            strpos($content, $this->student->display_name),
        );
        $this->assertSame(3, substr_count(
            $content,
            'data:image/svg+xml;base64,',
        ));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'student.qr_batch_generated',
            'entity_type' => 'ceremony',
            'entity_id' => $this->ceremony->id,
            'user_id' => $encoder->id,
        ]);
        $audit = AuditLog::query()
            ->where('action', 'student.qr_batch_generated')
            ->sole();
        $this->assertStringNotContainsString(
            $this->student->qr_token,
            $audit->toJson(),
        );
    }

    public function test_batch_rejects_cross_ceremony_and_oversized_selection(): void
    {
        $encoder = User::factory()->role(UserRole::DataEncoder)->create();
        $other = Student::factory()->create([
            'ceremony_id' => Ceremony::factory(),
            'eligibility_status' => EligibilityStatus::Eligible,
        ]);

        $this->actingAs($encoder)
            ->from('/admin/students/qr-codes')
            ->post('/admin/students/qr-codes/batch', [
                'ceremony_id' => $this->ceremony->id,
                'student_ids' => [],
            ])
            ->assertRedirect('/admin/students/qr-codes')
            ->assertSessionHasErrors('student_ids');

        $this->actingAs($encoder)
            ->from('/admin/students/qr-codes')
            ->post('/admin/students/qr-codes/batch', [
                'ceremony_id' => $this->ceremony->id,
                'student_ids' => [$this->student->id, $other->id],
            ])
            ->assertRedirect('/admin/students/qr-codes')
            ->assertSessionHasErrors('student_ids');

        $this->actingAs($encoder)
            ->from('/admin/students/qr-codes')
            ->post('/admin/students/qr-codes/batch', [
                'ceremony_id' => $this->ceremony->id,
                'student_ids' => range(1, 101),
            ])
            ->assertRedirect('/admin/students/qr-codes')
            ->assertSessionHasErrors('student_ids');
    }
}
