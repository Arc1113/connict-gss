<?php

namespace App\Actions;

use App\Enums\AnnouncementProvider;
use App\Enums\PronunciationRuleSection;
use App\Models\Ceremony;
use App\Models\PronunciationRule;
use App\Models\User;
use App\Services\InvalidateAnnouncementCandidates;
use App\Services\NormalizePronunciationRule;
use App\Support\AdministrativeAuditLogger;
use Illuminate\Support\Facades\DB;

final class ManagePronunciationRule
{
    public function __construct(
        private readonly NormalizePronunciationRule $normalizer,
        private readonly AdministrativeAuditLogger $audit,
        private readonly InvalidateAnnouncementCandidates $invalidator,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor, ?string $ipAddress): PronunciationRule
    {
        return DB::transaction(function () use ($data, $actor, $ipAddress): PronunciationRule {
            $rule = PronunciationRule::query()->create([
                'ceremony_id' => $data['ceremony_id'] ?? null,
                'section' => PronunciationRuleSection::from($data['section']),
                'match_text' => $this->normalizer->sourceText($data['match_text']),
                'normalized_match' => $this->normalizer->matchText($data['match_text']),
                'is_enabled' => $data['is_enabled'],
                'is_builtin' => false,
                'revision' => 1,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $this->syncVariants($rule, $data['variants']);
            $this->audit->record(
                $actor,
                'pronunciation_rule.created',
                'pronunciation_rule',
                $rule->id,
                null,
                $this->snapshot($rule->fresh('variants')),
                $ipAddress,
            );

            if ($rule->is_enabled) {
                $this->invalidate($rule->ceremony_id);
            }

            return $rule->fresh('variants');
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function update(PronunciationRule $rule, array $data, User $actor, ?string $ipAddress): PronunciationRule
    {
        return DB::transaction(function () use ($rule, $data, $actor, $ipAddress): PronunciationRule {
            $locked = PronunciationRule::query()->with('variants')->lockForUpdate()->findOrFail($rule->id);
            $oldCeremonyId = $locked->ceremony_id;
            $old = $this->snapshot($locked);
            $locked->update([
                'ceremony_id' => $data['ceremony_id'] ?? null,
                'section' => PronunciationRuleSection::from($data['section']),
                'match_text' => $this->normalizer->sourceText($data['match_text']),
                'normalized_match' => $this->normalizer->matchText($data['match_text']),
                'is_enabled' => $data['is_enabled'],
                'revision' => $locked->revision + 1,
                'updated_by' => $actor->id,
            ]);
            $this->syncVariants($locked, $data['variants']);
            $this->audit->record(
                $actor,
                $old['is_enabled'] === $data['is_enabled']
                    ? 'pronunciation_rule.updated'
                    : ($data['is_enabled'] ? 'pronunciation_rule.enabled' : 'pronunciation_rule.disabled'),
                'pronunciation_rule',
                $locked->id,
                $old,
                $this->snapshot($locked->fresh('variants')),
                $ipAddress,
            );
            $this->invalidate($oldCeremonyId);
            if ($oldCeremonyId !== ($data['ceremony_id'] ?? null)) {
                $this->invalidate($data['ceremony_id'] ?? null);
            }

            return $locked->fresh('variants');
        }, 3);
    }

    /** @param list<array<string, mixed>> $variants */
    private function syncVariants(PronunciationRule $rule, array $variants): void
    {
        $kept = [];

        foreach ($variants as $variant) {
            $model = $rule->variants()->updateOrCreate([
                'provider' => AnnouncementProvider::from($variant['provider'])->value,
                'locale' => trim((string) ($variant['locale'] ?? '')),
                'voice' => trim((string) ($variant['voice'] ?? '')),
            ], [
                'replacement_text' => trim($variant['replacement_text']),
                'is_enabled' => (bool) ($variant['is_enabled'] ?? true),
            ]);
            $kept[] = $model->id;
        }

        $rule->variants()->whereNotIn('id', $kept)->update(['is_enabled' => false]);
    }

    private function invalidate(?int $ceremonyId): void
    {
        $ceremonyIds = $ceremonyId === null
            ? Ceremony::query()->pluck('id')
            : collect([$ceremonyId]);

        foreach ($ceremonyIds as $id) {
            $this->invalidator->forCeremony((int) $id);
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(PronunciationRule $rule): array
    {
        return [
            'ceremony_id' => $rule->ceremony_id,
            'section' => $rule->section->value,
            'match_text' => $rule->match_text,
            'is_enabled' => $rule->is_enabled,
            'is_builtin' => $rule->is_builtin,
            'revision' => $rule->revision,
            'variants' => $rule->variants->map(fn ($variant): array => [
                'provider' => $variant->provider->value,
                'locale' => $variant->locale,
                'voice' => $variant->voice,
                'replacement_text' => $variant->replacement_text,
                'is_enabled' => $variant->is_enabled,
            ])->values()->all(),
        ];
    }
}
