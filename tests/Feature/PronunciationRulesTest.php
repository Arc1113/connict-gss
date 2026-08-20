<?php

namespace Tests\Feature;

use App\Enums\AnnouncementGenerationStatus;
use App\Enums\AnnouncementProvider;
use App\Enums\AudioReadinessStatus;
use App\Enums\PronunciationRuleSection;
use App\Enums\UserRole;
use App\Models\AnnouncementGeneration;
use App\Models\AnnouncementTemplate;
use App\Models\Ceremony;
use App\Models\PronunciationRule;
use App\Models\Student;
use App\Models\User;
use App\Services\PreviewGraduationAnnouncement;
use App\Services\TransformPronunciation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PronunciationRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_longest_phrase_and_ceremony_scope_are_deterministic(): void
    {
        $ceremony = Ceremony::factory()->create();
        $common = $this->rule(null, 'Laude', 'lau-deh');
        $phrase = $this->rule(null, 'Academic Latin Distinction', 'academic latin distinction');
        $ceremonyRule = $this->rule($ceremony->id, 'Laude', 'law-deh');

        $result = app(TransformPronunciation::class)->transform(
            'Maria Santos — Academic Latin Distinction. Laude medal.',
            'honors',
            $ceremony->id,
            AnnouncementProvider::Kokoro,
            'en-US',
            'af_heart',
        );

        $this->assertSame(
            'Maria Santos — academic latin distinction. law-deh medal.',
            $result->text,
        );
        $this->assertSame([$phrase->id, $ceremonyRule->id], array_column($result->appliedRules, 'rule_id'));
        $this->assertSame([1, 1], array_column($result->appliedRules, 'variant_revision'));
        $this->assertNotContains($common->id, array_column($result->appliedRules, 'rule_id'));
    }

    public function test_matching_is_unicode_case_insensitive_whole_word_and_non_cascading(): void
    {
        $first = $this->rule(null, 'José', 'ho-say', PronunciationRuleSection::Name);
        $second = $this->rule(null, 'ho-say', 'must-not-run', PronunciationRuleSection::Name);
        $result = app(TransformPronunciation::class)->transform(
            'JOSÉ Josefine',
            'name',
            null,
            AnnouncementProvider::Azure,
            'en-PH',
            'en-PH-RosaNeural',
        );

        $this->assertSame('ho-say Josefine', $result->text);
        $this->assertSame([$first->id], array_column($result->appliedRules, 'rule_id'));
        $this->assertNotContains($second->id, array_column($result->appliedRules, 'rule_id'));
    }

    public function test_philippine_english_catalog_is_available_for_both_providers_and_respects_sections(): void
    {
        $this->assertGreaterThanOrEqual(58, PronunciationRule::query()->where('is_builtin', true)->count());

        $summa = PronunciationRule::query()
            ->where('section', PronunciationRuleSection::Honors)
            ->where('match_text', 'Summa Cum Laude')
            ->firstOrFail();
        $this->assertSame(
            ['azure', 'kokoro'],
            $summa->variants()->orderBy('provider')->get()->map(fn ($variant) => $variant->provider->value)->all(),
        );

        $transformer = app(TransformPronunciation::class);
        $honors = $transformer->transform(
            'Summa Cum Laude',
            'honors',
            null,
            AnnouncementProvider::Azure,
            'en-PH',
            'en-PH-RosaNeural',
        );
        $this->assertSame('soo-mah koom low-day', $honors->text);

        $name = $transformer->transform(
            'Ma Santos',
            'name',
            null,
            AnnouncementProvider::Kokoro,
            'en-US',
            'af_heart',
        );
        $program = $transformer->transform(
            'BS in Information Technology',
            'program',
            null,
            AnnouncementProvider::Kokoro,
            'en-US',
            'af_heart',
        );
        $this->assertSame('Ma Santos', $name->text);
        $this->assertSame('Bachelor of Science in Information Technology', $program->text);
    }

    public function test_administrator_manages_common_rules_and_encoder_is_limited_to_ceremony_rules(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        $encoder = User::factory()->create(['role' => UserRole::DataEncoder]);
        $ceremony = Ceremony::factory()->create();
        $payload = [
            'ceremony_id' => null,
            'section' => 'honors',
            'match_text' => 'Fictional Distinction',
            'is_enabled' => true,
            'variants' => [[
                'provider' => 'kokoro',
                'locale' => 'en-US',
                'voice' => '',
                'replacement_text' => 'fik-shun-al distinction',
                'is_enabled' => true,
            ]],
        ];

        $this->actingAs($encoder)->post('/admin/pronunciation-rules', $payload)->assertForbidden();
        $this->actingAs($administrator)->post('/admin/pronunciation-rules', $payload)->assertRedirect();
        $this->assertDatabaseHas('pronunciation_rules', [
            'match_text' => 'Fictional Distinction',
            'ceremony_id' => null,
        ]);

        $payload['ceremony_id'] = $ceremony->id;
        $payload['match_text'] = 'Ceremony Medal';
        $this->actingAs($encoder)->post('/admin/pronunciation-rules', $payload)->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['action' => 'pronunciation_rule.created']);
    }

    public function test_duplicate_normalized_phrase_and_markup_are_rejected(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        $this->rule(null, 'Academic Medal', 'academic medal');

        $this->actingAs($administrator)->post('/admin/pronunciation-rules', [
            'ceremony_id' => null,
            'section' => 'honors',
            'match_text' => '  ACADEMIC   MEDAL ',
            'is_enabled' => true,
            'variants' => [[
                'provider' => 'azure',
                'locale' => 'en-PH',
                'voice' => '',
                'replacement_text' => '<phoneme>unsafe</phoneme>',
            ]],
        ])->assertSessionHasErrors(['match_text', 'variants.0.replacement_text']);
    }

    public function test_regular_expression_syntax_is_rejected_and_variant_changes_are_revisioned(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        $payload = [
            'ceremony_id' => null,
            'section' => 'name',
            'match_text' => 'Jo.*',
            'is_enabled' => true,
            'variants' => [[
                'provider' => 'kokoro',
                'locale' => '',
                'voice' => '',
                'replacement_text' => 'Joe',
                'is_enabled' => true,
            ], [
                'provider' => 'azure',
                'locale' => 'en-PH',
                'voice' => '',
                'replacement_text' => 'Jose',
                'is_enabled' => true,
            ]],
        ];

        $this->actingAs($administrator)
            ->post('/admin/pronunciation-rules', $payload)
            ->assertSessionHasErrors(['match_text']);

        $payload['match_text'] = 'José';
        $this->actingAs($administrator)->post('/admin/pronunciation-rules', $payload)->assertRedirect();
        $rule = PronunciationRule::query()->where('match_text', 'José')->firstOrFail();
        $this->assertSame([1, 1], $rule->variants()->orderBy('id')->pluck('revision')->all());
        $removedVariantId = $rule->variants()->where('provider', 'azure')->firstOrFail()->id;

        $payload['variants'][0]['replacement_text'] = 'ho-say';
        $payload['variants'] = [$payload['variants'][0]];
        $this->actingAs($administrator)
            ->put("/admin/pronunciation-rules/{$rule->id}", $payload)
            ->assertRedirect();
        $this->assertSame(2, $rule->variants()->where('provider', 'kokoro')->firstOrFail()->revision);
        $removedVariant = $rule->variants()->findOrFail($removedVariantId);
        $this->assertFalse($removedVariant->is_enabled);
        $this->assertSame(2, $removedVariant->revision);
    }

    public function test_rule_change_invalidates_only_matching_generated_audio_and_not_human_recordings(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        $ceremony = Ceremony::factory()->create();
        $template = AnnouncementTemplate::factory()->active()->for($ceremony)->create();
        $matching = Student::factory()->for($ceremony)->create(['program' => 'Quantum Ceremonies']);
        $unaffected = Student::factory()->for($ceremony)->create(['program' => 'Classical Studies']);
        $human = Student::factory()->approvedAudio()->for($ceremony)->create(['program' => 'Quantum Ceremonies']);

        $matchingGeneration = $this->generationFor($matching, $ceremony, $template);
        $unaffectedGeneration = $this->generationFor($unaffected, $ceremony, $template);

        $this->actingAs($administrator)->post('/admin/pronunciation-rules', [
            'ceremony_id' => $ceremony->id,
            'section' => 'program',
            'match_text' => 'Quantum Ceremonies',
            'is_enabled' => true,
            'variants' => [[
                'provider' => 'azure',
                'locale' => '',
                'voice' => '',
                'replacement_text' => 'kwon-tum ceremonies',
                'is_enabled' => true,
            ]],
        ])->assertRedirect();

        $this->assertSame(AnnouncementGenerationStatus::Outdated, $matchingGeneration->fresh()->status);
        $this->assertSame(AnnouncementGenerationStatus::NeedsReview, $unaffectedGeneration->fresh()->status);
        $this->assertSame(AudioReadinessStatus::Approved, $human->fresh()->audio_readiness_status);
    }

    private function generationFor(
        Student $student,
        Ceremony $ceremony,
        AnnouncementTemplate $template,
    ): AnnouncementGeneration {
        $preview = app(PreviewGraduationAnnouncement::class)->preview($student, $template);

        return AnnouncementGeneration::factory()
            ->needsReview()
            ->for($student)
            ->for($ceremony)
            ->for($template, 'template')
            ->create([
                'source_hash' => $preview->composition->sourceHash,
                'provider' => $preview->composition->input->provider,
                'provider_voice' => $preview->composition->voice,
                'locale' => $preview->composition->locale,
                'output_format' => $preview->composition->input->outputFormat,
            ]);
    }

    private function rule(
        ?int $ceremonyId,
        string $match,
        string $replacement,
        PronunciationRuleSection $section = PronunciationRuleSection::Honors,
    ): PronunciationRule {
        $rule = PronunciationRule::query()->create([
            'ceremony_id' => $ceremonyId,
            'section' => $section,
            'match_text' => $match,
            'normalized_match' => mb_strtolower($match),
            'is_enabled' => true,
            'is_builtin' => false,
            'revision' => 1,
        ]);
        $rule->variants()->create([
            'provider' => AnnouncementProvider::Kokoro,
            'locale' => '',
            'voice' => '',
            'replacement_text' => $replacement,
            'is_enabled' => true,
        ]);
        $rule->variants()->create([
            'provider' => AnnouncementProvider::Azure,
            'locale' => '',
            'voice' => '',
            'replacement_text' => $replacement,
            'is_enabled' => true,
        ]);

        return $rule;
    }
}
