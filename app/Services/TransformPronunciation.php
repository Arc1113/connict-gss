<?php

namespace App\Services;

use App\Data\PronunciationTransformResult;
use App\Enums\AnnouncementProvider;
use App\Enums\PronunciationRuleSection;
use App\Models\PronunciationRule;
use App\Models\PronunciationRuleVariant;
use Illuminate\Support\Collection;

final class TransformPronunciation
{
    public function transform(
        string $text,
        string $section,
        ?int $ceremonyId,
        AnnouncementProvider $provider,
        string $locale,
        string $voice,
        bool $protected = false,
    ): PronunciationTransformResult {
        if ($protected || trim($text) === '') {
            return new PronunciationTransformResult($text, []);
        }

        $section = $this->section($section);
        $rules = PronunciationRule::query()
            ->with(['variants' => fn ($query) => $query
                ->where('provider', $provider->value)
                ->where('is_enabled', true)])
            ->where('is_enabled', true)
            ->whereIn('section', [PronunciationRuleSection::Any->value, $section])
            ->where(function ($query) use ($ceremonyId): void {
                $query->whereNull('ceremony_id');

                if ($ceremonyId !== null) {
                    $query->orWhere('ceremony_id', $ceremonyId);
                }
            })
            ->get()
            ->map(function (PronunciationRule $rule) use ($locale, $voice): ?array {
                $variant = $this->variant($rule->variants, $locale, $voice);

                return $variant === null ? null : [
                    'rule' => $rule,
                    'variant' => $variant,
                    'length' => mb_strlen($rule->match_text, 'UTF-8'),
                    'section_specific' => $rule->section->value !== PronunciationRuleSection::Any->value,
                ];
            })
            ->filter()
            ->sort(function (array $first, array $second) use ($section): int {
                return $second['length'] <=> $first['length']
                    ?: ((int) $second['rule']->ceremony_id !== 0) <=> ((int) $first['rule']->ceremony_id !== 0)
                    ?: ($second['rule']->section->value === $section) <=> ($first['rule']->section->value === $section)
                    ?: $first['rule']->id <=> $second['rule']->id;
            })
            ->values();

        if ($rules->isEmpty()) {
            return new PronunciationTransformResult($text, []);
        }

        $matches = [];

        foreach ($rules as $candidate) {
            /** @var PronunciationRule $rule */
            $rule = $candidate['rule'];
            $pattern = '/(?<![\p{L}\p{N}\p{M}])'.preg_quote($rule->match_text, '/').'(?![\p{L}\p{N}\p{M}])/iu';
            preg_match_all($pattern, $text, $found, PREG_OFFSET_CAPTURE);

            foreach ($found[0] as [$matched, $byteOffset]) {
                $matches[] = [
                    ...$candidate,
                    'matched' => $matched,
                    'offset' => $byteOffset,
                    'bytes' => strlen($matched),
                ];
            }
        }

        usort($matches, static fn (array $first, array $second): int => $first['offset'] <=> $second['offset']
            ?: $second['length'] <=> $first['length']
            ?: ((int) $second['rule']->ceremony_id !== 0) <=> ((int) $first['rule']->ceremony_id !== 0)
            ?: $second['section_specific'] <=> $first['section_specific']
            ?: $first['rule']->id <=> $second['rule']->id);

        $cursor = 0;
        $speech = '';
        $applied = [];

        foreach ($matches as $match) {
            if ($match['offset'] < $cursor) {
                continue;
            }

            /** @var PronunciationRule $rule */
            $rule = $match['rule'];
            /** @var PronunciationRuleVariant $variant */
            $variant = $match['variant'];
            $speech .= substr($text, $cursor, $match['offset'] - $cursor);
            $speech .= $variant->replacement_text;
            $cursor = $match['offset'] + $match['bytes'];
            $applied[] = [
                'rule_id' => $rule->id,
                'revision' => $rule->revision,
                'variant_id' => $variant->id,
                'variant_revision' => $variant->revision,
                'scope' => $rule->ceremony_id === null ? 'common' : 'ceremony',
                'section' => $rule->section->value,
                'match_text' => $rule->match_text,
                'replacement_text' => $variant->replacement_text,
                'provider' => $variant->provider->value,
                'locale' => $variant->locale,
                'voice' => $variant->voice,
            ];
        }

        $speech .= substr($text, $cursor);

        return new PronunciationTransformResult($speech, $applied);
    }

    /**
     * @param  Collection<int, PronunciationRuleVariant>  $variants
     */
    private function variant(Collection $variants, string $locale, string $voice): ?PronunciationRuleVariant
    {
        return $variants
            ->filter(static fn (PronunciationRuleVariant $variant): bool => ($variant->locale === '' || strcasecmp($variant->locale, $locale) === 0)
                && ($variant->voice === '' || strcasecmp($variant->voice, $voice) === 0))
            ->sortByDesc(static fn (PronunciationRuleVariant $variant): int => ($variant->voice !== '' ? 2 : 0)
                + ($variant->locale !== '' ? 1 : 0))
            ->first();
    }

    private function section(string $section): string
    {
        return match ($section) {
            'name' => PronunciationRuleSection::Name->value,
            'program' => PronunciationRuleSection::Program->value,
            'major' => PronunciationRuleSection::Major->value,
            'honors' => PronunciationRuleSection::Honors->value,
            'awards' => PronunciationRuleSection::Award->value,
            'additional_text' => PronunciationRuleSection::Additional->value,
            default => PronunciationRuleSection::Template->value,
        };
    }
}
