<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ManagePronunciationRule;
use App\Enums\AnnouncementProvider;
use App\Enums\PronunciationRuleSection;
use App\Http\Controllers\Controller;
use App\Http\Requests\PreviewPronunciationRequest;
use App\Http\Requests\SavePronunciationRuleRequest;
use App\Models\Ceremony;
use App\Models\PronunciationRule;
use App\Services\ApprovedAnnouncementVoices;
use App\Services\GeneratePronunciationPreviewAudio;
use App\Services\TextToSpeechProviderHealth;
use App\Services\TransformPronunciation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PronunciationRuleController extends Controller
{
    public function index(Request $request, ApprovedAnnouncementVoices $voices): Response
    {
        Gate::authorize('view-pronunciation-rules');
        $query = PronunciationRule::query()
            ->select('pronunciation_rules.*')
            ->selectSub(
                fn ($usage) => $usage
                    ->from('announcement_generations')
                    ->selectRaw('count(*)')
                    ->whereRaw(
                        "pronunciation_manifest @> jsonb_build_array(jsonb_build_object('rule_id', pronunciation_rules.id))",
                    ),
                'usage_count',
            )
            ->with(['ceremony:id,name', 'variants']);

        if ($request->filled('ceremony')) {
            $request->string('ceremony')->toString() === 'common'
                ? $query->whereNull('ceremony_id')
                : $query->where('ceremony_id', $request->integer('ceremony'));
        }
        if ($request->filled('section')) {
            $query->where('section', $request->string('section'));
        }
        if ($request->filled('status')) {
            $query->where('is_enabled', $request->string('status')->toString() === 'enabled');
        }
        if ($request->filled('provider') || $request->filled('locale') || $request->filled('voice')) {
            $query->whereHas('variants', function ($variants) use ($request): void {
                if ($request->filled('provider')) {
                    $variants->where('provider', $request->string('provider'));
                }
                if ($request->filled('locale')) {
                    $variants->where('locale', $request->string('locale'));
                }
                if ($request->filled('voice')) {
                    $variants->where('voice', $request->string('voice'));
                }
            });
        }
        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->toString().'%';
            $query->where(fn ($builder) => $builder
                ->whereRaw('match_text ILIKE ?', [$search])
                ->orWhereHas('variants', fn ($variants) => $variants
                    ->whereRaw('replacement_text ILIKE ?', [$search])));
        }

        return Inertia::render('Admin/PronunciationRules/Index', [
            'rules' => $query->orderByRaw('ceremony_id NULLS FIRST')->orderBy('match_text')->get()->map(
                fn (PronunciationRule $rule): array => $this->serialize($rule),
            ),
            'ceremonies' => Ceremony::query()->orderByDesc('event_date')->get(['id', 'name']),
            'sections' => collect(PronunciationRuleSection::cases())->map(fn ($section): array => [
                'value' => $section->value,
                'label' => $section->label(),
            ]),
            'providers' => collect(AnnouncementProvider::cases())->map(fn ($provider): array => [
                'value' => $provider->value,
                'label' => $provider === AnnouncementProvider::Kokoro ? 'Local Kokoro' : 'Azure Speech',
                'voices' => collect($voices->all($provider))->map(fn ($locale, $voice): array => [
                    'value' => $voice,
                    'locale' => $locale,
                ])->values(),
            ]),
            'permissions' => [
                'manage_common' => $request->user()->can('manage-common-pronunciation'),
                'manage_ceremony' => $request->user()->can('manage-ceremony-pronunciation'),
            ],
            'filters' => $request->only(['search', 'ceremony', 'section', 'status', 'provider', 'locale', 'voice']),
        ]);
    }

    public function store(SavePronunciationRuleRequest $request, ManagePronunciationRule $manager): RedirectResponse
    {
        $manager->create($request->validated(), $request->user(), $request->ip());

        return back()->with('success', 'Pronunciation rule saved. Affected generated audio now requires review.');
    }

    public function update(SavePronunciationRuleRequest $request, PronunciationRule $pronunciationRule, ManagePronunciationRule $manager): RedirectResponse
    {
        Gate::authorize(
            $pronunciationRule->ceremony_id === null
                ? 'manage-common-pronunciation'
                : 'manage-ceremony-pronunciation',
        );
        $manager->update($pronunciationRule, $request->validated(), $request->user(), $request->ip());

        return back()->with('success', 'Pronunciation rule updated. Affected generated audio now requires review.');
    }

    public function preview(
        PreviewPronunciationRequest $request,
        TransformPronunciation $transformer,
        TextToSpeechProviderHealth $health,
        GeneratePronunciationPreviewAudio $audioPreview,
    ): JsonResponse {
        $data = $request->validated();
        $provider = AnnouncementProvider::from($data['provider']);
        $result = $transformer->transform(
            $data['text'],
            $data['section'],
            $data['ceremony_id'] ?? null,
            $provider,
            $data['locale'],
            $data['voice'],
        );
        $speechText = $result->text;
        $appliedRules = $result->appliedRules;

        if (filled($data['candidate_match_text'] ?? null)
            && filled($data['candidate_replacement_text'] ?? null)) {
            $pattern = '/(?<![\p{L}\p{N}\p{M}])'.preg_quote($data['candidate_match_text'], '/').'(?![\p{L}\p{N}\p{M}])/iu';
            $speechText = preg_replace_callback(
                $pattern,
                static fn (): string => $data['candidate_replacement_text'],
                $data['text'],
            ) ?? $data['text'];
            $appliedRules = [[
                'rule_id' => null,
                'revision' => null,
                'variant_id' => null,
                'variant_revision' => null,
                'scope' => ($data['ceremony_id'] ?? null) === null ? 'common' : 'ceremony',
                'section' => $data['section'],
                'match_text' => $data['candidate_match_text'],
                'replacement_text' => $data['candidate_replacement_text'],
                'provider' => $data['provider'],
                'locale' => $data['locale'],
                'voice' => $data['voice'],
            ]];
        }
        $audio = $audioPreview->generate(
            $speechText,
            $provider,
            $data['locale'],
            $data['voice'],
        );

        return response()->json([
            'preview' => [
                'display_text' => $data['text'],
                'speech_text' => $speechText,
                'applied_rules' => $appliedRules,
                'source_hash' => hash('sha256', json_encode([$data, $appliedRules], JSON_UNESCAPED_UNICODE)),
                'audio_url' => $audio['data_uri'],
                'provider' => $health->offline($provider)['status'],
                'warnings' => array_values(array_filter([
                    $appliedRules === []
                        ? 'No enabled pronunciation rule matched this phrase and voice.'
                        : 'This preview is temporary. Generate and approve each graduate candidate before ceremony use.',
                    $audio['warning'],
                ])),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function serialize(PronunciationRule $rule): array
    {
        return [
            'id' => $rule->id,
            'ceremony_id' => $rule->ceremony_id,
            'scope' => $rule->ceremony_id === null ? 'common' : 'ceremony',
            'ceremony' => $rule->ceremony?->name,
            'section' => $rule->section->value,
            'match_text' => $rule->match_text,
            'is_enabled' => $rule->is_enabled,
            'is_builtin' => $rule->is_builtin,
            'revision' => $rule->revision,
            'usage_count' => (int) $rule->getAttribute('usage_count'),
            'variants' => $rule->variants->map(fn ($variant): array => [
                'provider' => $variant->provider->value,
                'locale' => $variant->locale,
                'voice' => $variant->voice,
                'replacement_text' => $variant->replacement_text,
                'is_enabled' => $variant->is_enabled,
                'revision' => $variant->revision,
            ])->values(),
        ];
    }
}
