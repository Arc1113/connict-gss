<?php

namespace Tests\Feature\Phase6;

use App\Actions\Admin\RegenerateStudentQrToken;
use App\Enums\CeremonyStatus;
use App\Enums\EligibilityStatus;
use App\Enums\StageQueueStatus;
use App\Enums\UserRole;
use App\Events\QueueUpdated;
use App\Models\AuditLog;
use App\Models\Ceremony;
use App\Models\ScanLog;
use App\Models\StageQueueItem;
use App\Models\Student;
use App\Models\User;
use App\Services\StudentQrTokenVersion;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

class QrTokenRegenerationTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    private Ceremony $ceremony;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->administrator = User::factory()
            ->role(UserRole::Administrator)
            ->create();
        $this->ceremony = Ceremony::factory()->create([
            'status' => CeremonyStatus::Active,
            'activated_at' => now(),
        ]);
        $this->student = Student::factory()->create([
            'ceremony_id' => $this->ceremony->id,
            'eligibility_status' => EligibilityStatus::Eligible,
        ]);
    }

    public function test_administrator_replacement_invalidates_old_cards_and_is_safely_audited(): void
    {
        Event::fake([QueueUpdated::class]);
        $oldToken = $this->student->qr_token;
        $version = $this->version();
        $history = ScanLog::factory()->create([
            'ceremony_id' => $this->ceremony->id,
            'student_id' => $this->student->id,
        ]);

        $this->actingAs($this->administrator)
            ->post("/admin/students/{$this->student->id}/qr-code/regenerate", [
                'confirmed' => true,
                'expected_version' => $version,
            ])
            ->assertRedirect(
                "/admin/students/{$this->student->id}/qr-code",
            )
            ->assertSessionHas('warning');

        $newToken = $this->student->refresh()->qr_token;
        $this->assertNotSame($oldToken, $newToken);
        $this->assertMatchesRegularExpression('/^GSS-[A-Z0-9]{48}$/', $newToken);

        $audit = AuditLog::query()
            ->where('action', 'student.qr_token_regenerated')
            ->sole();
        $encodedAudit = $audit->toJson();
        $this->assertStringNotContainsString($oldToken, $encodedAudit);
        $this->assertStringNotContainsString($newToken, $encodedAudit);
        $this->assertSame($this->administrator->id, $audit->user_id);
        $this->assertDatabaseHas('scan_logs', ['id' => $history->id]);

        $scanner = User::factory()->role(UserRole::ScannerOperator)->create();

        $this->actingAs($scanner)
            ->postJson('/scans', ['token' => $oldToken])
            ->assertUnprocessable()
            ->assertJsonPath('status', 'invalid_token');
        $this->actingAs($scanner)
            ->postJson('/scans', ['token' => $newToken])
            ->assertCreated()
            ->assertJsonPath('status', 'queued');
        Event::assertDispatched(QueueUpdated::class);
    }

    public function test_replacement_requires_administrator_confirmation_and_current_version(): void
    {
        $encoder = User::factory()->role(UserRole::DataEncoder)->create();
        $endpoint = "/admin/students/{$this->student->id}/qr-code/regenerate";
        $version = $this->version();

        $this->actingAs($encoder)
            ->post($endpoint, [
                'confirmed' => true,
                'expected_version' => $version,
            ])
            ->assertForbidden();

        $this->actingAs($this->administrator)
            ->from("/admin/students/{$this->student->id}/qr-code")
            ->post($endpoint, [
                'confirmed' => false,
                'expected_version' => $version,
            ])
            ->assertSessionHasErrors('confirmed');

        $this->actingAs($this->administrator)
            ->from("/admin/students/{$this->student->id}/qr-code")
            ->post($endpoint, [
                'confirmed' => true,
                'expected_version' => str_repeat('0', 64),
            ])
            ->assertSessionHasErrors('regeneration');

        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'student.qr_token_regenerated',
        ]);
    }

    public function test_presenting_or_completed_graduates_cannot_receive_a_replacement(): void
    {
        $endpoint = "/admin/students/{$this->student->id}/qr-code/regenerate";
        $token = $this->student->qr_token;
        $presenting = StageQueueItem::factory()->create([
            'ceremony_id' => $this->ceremony->id,
            'student_id' => $this->student->id,
            'status' => StageQueueStatus::Presenting,
        ]);
        $version = $this->version();

        $this->actingAs($this->administrator)
            ->from("/admin/students/{$this->student->id}/qr-code")
            ->post($endpoint, [
                'confirmed' => true,
                'expected_version' => $version,
            ])
            ->assertSessionHasErrors('regeneration');

        $presenting->update(['status' => StageQueueStatus::Completed]);

        $this->actingAs($this->administrator)
            ->from("/admin/students/{$this->student->id}/qr-code")
            ->post($endpoint, [
                'confirmed' => true,
                'expected_version' => $this->version(),
            ])
            ->assertSessionHasErrors('regeneration');

        $this->assertSame($token, $this->student->refresh()->qr_token);
    }

    public function test_competing_replacements_leave_one_final_token_and_reject_the_stale_request(): void
    {
        $endpoint = "/admin/students/{$this->student->id}/qr-code/regenerate";
        $version = $this->version();

        $this->actingAs($this->administrator)
            ->post($endpoint, [
                'confirmed' => true,
                'expected_version' => $version,
            ])
            ->assertRedirect();
        $finalToken = $this->student->refresh()->qr_token;

        $this->actingAs($this->administrator)
            ->from("/admin/students/{$this->student->id}/qr-code")
            ->post($endpoint, [
                'confirmed' => true,
                'expected_version' => $version,
            ])
            ->assertSessionHasErrors('regeneration');

        $this->assertSame($finalToken, $this->student->refresh()->qr_token);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_a_failed_replacement_rolls_back_to_the_original_valid_token(): void
    {
        $token = $this->student->qr_token;
        $version = $this->version();
        DB::listen(function (QueryExecuted $query): void {
            if (str_starts_with($query->sql, 'insert into "audit_logs"')) {
                throw new RuntimeException('Injected audit failure.');
            }
        });

        try {
            app(RegenerateStudentQrToken::class)->execute(
                $this->student,
                $version,
                $this->administrator,
                '127.0.0.1',
            );
            $this->fail('The injected audit failure was expected.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Injected audit failure.', $exception->getMessage());
        }

        $this->assertSame($token, $this->student->refresh()->qr_token);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    private function version(): string
    {
        return app(StudentQrTokenVersion::class)->fingerprint(
            $this->student->refresh(),
        );
    }
}
