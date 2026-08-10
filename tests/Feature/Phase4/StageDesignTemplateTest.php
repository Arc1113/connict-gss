<?php

namespace Tests\Feature\Phase4;

use App\Enums\UserRole;
use App\Models\Ceremony;
use App\Models\StageDesignTemplate;
use App\Models\User;
use App\Support\StageTheme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StageDesignTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_administrator_can_create_edit_and_delete_reusable_stage_designs(): void
    {
        $administrator = User::factory()->role(UserRole::Administrator)->create();

        $this->actingAs($administrator)
            ->get('/admin/stage-designs')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/StageDesignTemplates/Index')
                ->has('templates', 3));

        $this->post('/admin/stage-designs', $this->payload([
            'name' => 'Satellite campus',
            'description' => 'Reusable satellite-campus palette.',
        ]))->assertRedirect();

        $template = StageDesignTemplate::query()->where('name', 'Satellite campus')->sole();
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'stage_design_template.created',
            'entity_id' => $template->id,
        ]);

        $this->put("/admin/stage-designs/{$template->id}", $this->payload([
            'name' => 'Satellite campus evening',
            'theme' => [...StageTheme::defaults(), 'layout' => 'banner'],
        ]))->assertRedirect();

        $this->assertSame('banner', $template->refresh()->theme['layout']);

        $this->delete("/admin/stage-designs/{$template->id}")->assertRedirect();
        $this->assertModelMissing($template);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'stage_design_template.deleted',
            'entity_id' => $template->id,
        ]);
    }

    public function test_applying_a_template_as_a_ceremony_snapshot_is_not_changed_by_later_template_edits(): void
    {
        $administrator = User::factory()->role(UserRole::Administrator)->create();
        $template = StageDesignTemplate::query()->firstOrFail();
        $snapshot = $template->theme;

        $this->actingAs($administrator)->post('/admin/ceremonies', [
            'name' => 'Snapshot Ceremony',
            'academic_year' => '2026-2027',
            'venue' => null,
            'event_date' => null,
            'status' => 'draft',
            'presentation_mode' => 'controlled',
            'stage_theme' => $snapshot,
        ])->assertRedirect();

        $ceremony = Ceremony::query()->where('name', 'Snapshot Ceremony')->sole();
        $template->update(['theme' => [...$snapshot, 'background_color' => '#112233']]);

        $this->assertSame($snapshot['background_color'], $ceremony->refresh()->stage_theme['background_color']);
        $this->assertSame('#112233', $template->refresh()->theme['background_color']);
    }

    public function test_non_administrators_cannot_manage_stage_designs(): void
    {
        $encoder = User::factory()->role(UserRole::DataEncoder)->create();

        $this->actingAs($encoder)->get('/admin/stage-designs')->assertForbidden();
        $this->post('/admin/stage-designs', $this->payload())->assertForbidden();
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Reusable stage design',
            'description' => null,
            'theme' => StageTheme::defaults(),
            ...$overrides,
        ];
    }
}
