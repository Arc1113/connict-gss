<?php

namespace Tests\Feature\Phase6;

use App\Enums\CeremonyStatus;
use App\Enums\EligibilityStatus;
use App\Enums\UserRole;
use App\Events\StudentPresented;
use App\Models\Ceremony;
use App\Models\ScanLog;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ScannerManualFallbackTest extends TestCase
{
    use RefreshDatabase;

    private User $scanner;

    private Ceremony $ceremony;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scanner = User::factory()
            ->role(UserRole::ScannerOperator)
            ->create();
        $this->ceremony = Ceremony::factory()->create([
            'status' => CeremonyStatus::Active,
            'activated_at' => now(),
        ]);
    }

    public function test_lookup_is_active_ceremony_scoped_bounded_and_never_returns_tokens(): void
    {
        $students = Student::factory()->count(12)->sequence(
            fn ($sequence): array => [
                'ceremony_id' => $this->ceremony->id,
                'student_number' => '2027-'.str_pad(
                    (string) ($sequence->index + 1),
                    4,
                    '0',
                    STR_PAD_LEFT,
                ),
                'display_name' => "Villanueva Graduate {$sequence->index}",
                'sequence_number' => $sequence->index + 1,
            ],
        )->create();
        $outside = Student::factory()->create([
            'ceremony_id' => Ceremony::factory(),
            'display_name' => 'Villanueva Outside Ceremony',
        ]);

        $response = $this->actingAs($this->scanner)
            ->postJson('/scanner/lookup', ['query' => 'Villanueva'])
            ->assertOk()
            ->assertJsonPath('status', 'ready')
            ->assertJsonCount(10, 'students')
            ->assertJsonMissing(['id' => $outside->id]);

        $encoded = $response->getContent();

        foreach ($students as $student) {
            $this->assertStringNotContainsString($student->qr_token, $encoded);
        }

        $this->assertStringNotContainsString('qr_token', $encoded);
        $this->assertStringNotContainsString('student_number', $encoded);
    }

    public function test_manual_selection_uses_the_same_transaction_and_duplicate_rules(): void
    {
        Event::fake([StudentPresented::class]);
        $student = Student::factory()->create([
            'ceremony_id' => $this->ceremony->id,
            'display_name' => 'Ana Marie dela Cruz',
            'program' => 'Bachelor of Science in Computer Science',
            'eligibility_status' => EligibilityStatus::Eligible,
        ]);

        $this->actingAs($this->scanner)
            ->postJson('/scans/manual', ['student_id' => $student->id])
            ->assertCreated()
            ->assertJsonPath('status', 'accepted')
            ->assertJsonPath('student.display_name', 'Ana Marie dela Cruz');

        $log = ScanLog::query()->sole();
        $this->assertNull($log->raw_token_hash);
        $this->assertSame($student->id, $log->student_id);
        Event::assertDispatched(StudentPresented::class);

        $this->actingAs($this->scanner)
            ->postJson('/scans/manual', ['student_id' => $student->id])
            ->assertConflict()
            ->assertJsonPath('status', 'duplicate_active');
    }

    public function test_manual_selection_rejects_other_ceremonies_and_unauthorized_roles(): void
    {
        $outside = Student::factory()->create([
            'ceremony_id' => Ceremony::factory(),
            'eligibility_status' => EligibilityStatus::Eligible,
        ]);

        $this->actingAs($this->scanner)
            ->postJson('/scans/manual', ['student_id' => $outside->id])
            ->assertUnprocessable()
            ->assertJsonPath('status', 'invalid_token');

        $encoder = User::factory()->role(UserRole::DataEncoder)->create();

        $this->actingAs($encoder)
            ->postJson('/scans/manual', ['student_id' => $outside->id])
            ->assertForbidden()
            ->assertExactJson([
                'status' => 'unauthorized',
                'message' => 'You are not authorized to operate the scanner.',
            ]);
    }

    public function test_lookup_stops_when_exactly_one_active_ceremony_is_not_available(): void
    {
        $this->ceremony->update([
            'status' => CeremonyStatus::Paused,
            'activated_at' => null,
        ]);

        $this->actingAs($this->scanner)
            ->postJson('/scanner/lookup', ['query' => 'Ana'])
            ->assertConflict()
            ->assertJsonPath('status', 'ceremony_inactive')
            ->assertJsonCount(0, 'students');
    }
}
