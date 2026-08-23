<?php

namespace Tests\Feature\Phase1;

use App\Enums\EligibilityStatus;
use App\Models\Ceremony;
use App\Models\Student;
use App\Services\CeremonyPreparationService;
use App\Services\CeremonySetupNavigator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CeremonyPreparationContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_ceremony_is_not_ready_and_points_to_import(): void
    {
        $ceremony = Ceremony::factory()->create();
        $preparation = app(CeremonyPreparationService::class)->forCeremony($ceremony);

        $graduates = collect($preparation['steps'])->firstWhere('key', 'graduates');

        $this->assertSame('Preparing', $preparation['display_state']);
        $this->assertFalse($preparation['ready']);
        $this->assertSame('not_started', $graduates['status']);
        $this->assertStringContainsString('/import', $graduates['href']);
    }

    public function test_real_data_changes_the_contract_without_mutation(): void
    {
        $ceremony = Ceremony::factory()->create();
        $student = Student::factory()->for($ceremony)->create([
            'eligibility_status' => EligibilityStatus::Eligible,
        ]);
        $before = $student->fresh()->toArray();

        $preparation = app(CeremonyPreparationService::class)->forCeremony($ceremony);

        $after = $student->fresh()->toArray();
        $graduates = collect($preparation['steps'])->firstWhere('key', 'graduates');

        $this->assertSame('ready', $graduates['status']);
        $this->assertSame($before, $after);
    }

    public function test_navigator_prioritizes_unskipped_unresolved_work_then_revisits_skipped_work(): void
    {
        $navigator = app(CeremonySetupNavigator::class);
        $preparation = [
            'steps' => [
                ['key' => 'photos', 'status' => 'needs_attention'],
                ['key' => 'announcements', 'status' => 'needs_attention'],
                ['key' => 'qr', 'status' => 'ready'],
            ],
        ];

        $this->assertSame('announcements', $navigator->nextStep($preparation, [
            'skipped_steps' => ['photos'],
        ])['key']);
        $this->assertSame('photos', $navigator->nextStep($preparation, [
            'skipped_steps' => ['photos', 'announcements'],
        ])['key']);
    }
}
