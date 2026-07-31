<?php

namespace Tests\Feature\Phase4;

use App\Enums\CeremonyStatus;
use App\Enums\StageQueueStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Ceremony;
use App\Models\StageQueueItem;
use App\Models\StageState;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CeremonyAdministrationTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->administrator = User::factory()
            ->role(UserRole::Administrator)
            ->create();
    }

    public function test_an_administrator_can_create_review_update_and_filter_ceremonies(): void
    {
        $response = $this->actingAs($this->administrator)->post(
            '/admin/ceremonies',
            $this->payload([
                'name' => 'College of Engineering Commencement',
                'academic_year' => '2026-2027',
                'status' => CeremonyStatus::Prepared->value,
            ]),
        );

        $ceremony = Ceremony::query()->sole();

        $response->assertRedirect("/admin/ceremonies/{$ceremony->id}");
        $this->assertSame(CeremonyStatus::Prepared, $ceremony->status);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->administrator->id,
            'action' => 'ceremony.created',
            'entity_type' => 'ceremony',
            'entity_id' => $ceremony->id,
        ]);

        $this->actingAs($this->administrator)
            ->get('/admin/ceremonies?search=Engineering&status=prepared&academic_year=2026-2027')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Ceremonies/Index')
                ->has('ceremonies.data', 1)
                ->where('ceremonies.data.0.id', $ceremony->id)
                ->where('filters.search', 'Engineering')
                ->where('filters.status', 'prepared'));

        $this->actingAs($this->administrator)
            ->put(
                "/admin/ceremonies/{$ceremony->id}",
                $this->payload([
                    'name' => 'Engineering and Technology Commencement',
                    'academic_year' => '2026-2027',
                    'status' => CeremonyStatus::Active->value,
                    'presentation_mode' => 'automatic',
                ]),
            )
            ->assertRedirect("/admin/ceremonies/{$ceremony->id}");

        $ceremony->refresh();

        $this->assertSame('Engineering and Technology Commencement', $ceremony->name);
        $this->assertSame(CeremonyStatus::Active, $ceremony->status);
        $this->assertNotNull($ceremony->activated_at);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ceremony.status_changed',
            'entity_id' => $ceremony->id,
        ]);
    }

    public function test_ceremony_validation_is_specific_and_preserves_input(): void
    {
        $this->actingAs($this->administrator)
            ->from('/admin/ceremonies/create')
            ->post('/admin/ceremonies', $this->payload([
                'name' => '',
                'academic_year' => '2026-2030',
                'event_date' => 'not-a-date',
                'status' => 'unknown',
                'presentation_mode' => 'unknown',
            ]))
            ->assertRedirect('/admin/ceremonies/create')
            ->assertSessionHasErrors([
                'name',
                'academic_year',
                'event_date',
                'status',
                'presentation_mode',
            ])
            ->assertSessionHasInput('academic_year', '2026-2030');

        $this->assertDatabaseCount('ceremonies', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_stage_theme_and_local_brand_assets_are_saved_and_exposed_to_the_audience_display(): void
    {
        Storage::fake('public');
        $theme = [
            'preset' => 'classic',
            'layout' => 'split',
            'background_color' => '#112233',
            'surface_color' => '#f5f1e8',
            'text_color' => '#ffffff',
            'accent_color' => '#d7b56d',
            'show_photo' => true,
        ];
        $png = (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );

        $this->actingAs($this->administrator)
            ->post('/admin/ceremonies', $this->payload([
                'status' => CeremonyStatus::Active->value,
                'stage_theme' => $theme,
                'stage_logo' => UploadedFile::fake()->createWithContent('logo.png', $png),
                'stage_background' => UploadedFile::fake()->createWithContent('background.png', $png),
            ]))
            ->assertRedirect();

        $ceremony = Ceremony::query()->sole();
        $this->assertEquals($theme, $ceremony->stage_theme);
        $this->assertStringStartsWith('storage/ceremonies/theme/logos/', $ceremony->stage_logo_path);
        $this->assertStringStartsWith('storage/ceremonies/theme/backgrounds/', $ceremony->stage_background_path);

        Storage::disk('public')->assertExists(
            substr($ceremony->stage_logo_path, strlen('storage/')),
        );
        Storage::disk('public')->assertExists(
            substr($ceremony->stage_background_path, strlen('storage/')),
        );

        $this->get('/stage')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Stage')
                ->where('ceremony.theme.preset', 'classic')
                ->where('ceremony.theme.layout', 'split')
                ->where('ceremony.theme.background_color', '#112233')
                ->where(
                    'ceremony.theme.logo_url',
                    fn (string $url): bool => str_starts_with(
                        $url,
                        '/storage/ceremonies/theme/logos/',
                    ),
                ));

        $this->getJson('/stage/state')
            ->assertOk()
            ->assertJsonPath('ceremony.theme.preset', 'classic')
            ->assertJsonPath('ceremony.theme.layout', 'split');
    }

    public function test_a_second_active_ceremony_is_rejected_without_changing_either_record(): void
    {
        $active = Ceremony::factory()->create([
            'status' => CeremonyStatus::Active,
            'activated_at' => now(),
        ]);
        $prepared = Ceremony::factory()->create([
            'status' => CeremonyStatus::Prepared,
        ]);

        $this->actingAs($this->administrator)
            ->from("/admin/ceremonies/{$prepared->id}/edit")
            ->put(
                "/admin/ceremonies/{$prepared->id}",
                $this->payload([
                    'name' => $prepared->name,
                    'status' => CeremonyStatus::Active->value,
                ]),
            )
            ->assertRedirect("/admin/ceremonies/{$prepared->id}/edit")
            ->assertSessionHasErrors('status');

        $this->assertSame(CeremonyStatus::Active, $active->refresh()->status);
        $this->assertSame(CeremonyStatus::Prepared, $prepared->refresh()->status);
        $this->assertSame(1, Ceremony::query()
            ->where('status', CeremonyStatus::Active)
            ->count());
    }

    public function test_a_presenting_ceremony_cannot_be_changed_or_deactivated(): void
    {
        $ceremony = Ceremony::factory()->create([
            'status' => CeremonyStatus::Active,
            'activated_at' => now(),
        ]);
        $student = Student::factory()->create(['ceremony_id' => $ceremony->id]);
        $item = StageQueueItem::factory()->create([
            'ceremony_id' => $ceremony->id,
            'student_id' => $student->id,
            'status' => StageQueueStatus::Presenting,
        ]);
        StageState::factory()->create([
            'ceremony_id' => $ceremony->id,
            'current_queue_item_id' => $item->id,
            'display_mode' => 'graduate',
        ]);

        $this->actingAs($this->administrator)
            ->from("/admin/ceremonies/{$ceremony->id}/edit")
            ->put(
                "/admin/ceremonies/{$ceremony->id}",
                $this->payload([
                    'name' => 'Unsafe rename',
                    'status' => CeremonyStatus::Paused->value,
                ]),
            )
            ->assertSessionHasErrors('status');

        $ceremony->refresh();

        $this->assertNotSame('Unsafe rename', $ceremony->name);
        $this->assertSame(CeremonyStatus::Active, $ceremony->status);
        $this->assertSame($item->id, $ceremony->stageState->current_queue_item_id);
    }

    public function test_completing_a_ceremony_sets_its_timestamp_and_prevents_reactivation(): void
    {
        $ceremony = Ceremony::factory()->create([
            'status' => CeremonyStatus::Active,
            'activated_at' => now(),
        ]);

        $this->actingAs($this->administrator)
            ->put(
                "/admin/ceremonies/{$ceremony->id}",
                $this->payload([
                    'name' => $ceremony->name,
                    'status' => CeremonyStatus::Completed->value,
                ]),
            )
            ->assertRedirect("/admin/ceremonies/{$ceremony->id}");

        $ceremony->refresh();

        $this->assertSame(CeremonyStatus::Completed, $ceremony->status);
        $this->assertNotNull($ceremony->completed_at);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ceremony.status_changed',
            'entity_id' => $ceremony->id,
        ]);

        $this->actingAs($this->administrator)
            ->from("/admin/ceremonies/{$ceremony->id}/edit")
            ->put(
                "/admin/ceremonies/{$ceremony->id}",
                $this->payload([
                    'name' => $ceremony->name,
                    'status' => CeremonyStatus::Active->value,
                ]),
            )
            ->assertRedirect("/admin/ceremonies/{$ceremony->id}/edit")
            ->assertSessionHasErrors('status');

        $this->assertSame(
            CeremonyStatus::Completed,
            $ceremony->refresh()->status,
        );
    }

    public function test_only_an_unused_non_operational_ceremony_can_be_deleted(): void
    {
        $protected = Ceremony::factory()->create([
            'status' => CeremonyStatus::Prepared,
        ]);
        Student::factory()->create(['ceremony_id' => $protected->id]);

        $this->actingAs($this->administrator)
            ->from("/admin/ceremonies/{$protected->id}")
            ->delete("/admin/ceremonies/{$protected->id}")
            ->assertSessionHasErrors('ceremony');

        $this->assertDatabaseHas('ceremonies', ['id' => $protected->id]);

        $unused = Ceremony::factory()->create([
            'status' => CeremonyStatus::Draft,
        ]);

        $this->actingAs($this->administrator)
            ->delete("/admin/ceremonies/{$unused->id}")
            ->assertRedirect('/admin/ceremonies');

        $this->assertDatabaseMissing('ceremonies', ['id' => $unused->id]);
        $this->assertTrue(AuditLog::query()
            ->where('action', 'ceremony.deleted')
            ->where('entity_id', $unused->id)
            ->exists());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Sample Graduation Ceremony',
            'academic_year' => '2026-2027',
            'venue' => 'University Auditorium',
            'event_date' => '2027-04-10',
            'status' => CeremonyStatus::Draft->value,
            'presentation_mode' => 'controlled',
            ...$overrides,
        ];
    }
}
