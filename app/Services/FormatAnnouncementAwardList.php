<?php

namespace App\Services;

use App\Data\AnnouncementAwardInput;
use App\Data\AnnouncementAwardListResult;
use App\Data\AnnouncementIssue;

final class FormatAnnouncementAwardList
{
    public function __construct(
        private readonly NormalizeAnnouncementText $normalizer,
    ) {}

    /**
     * @param  list<AnnouncementAwardInput>  $awards
     */
    public function format(
        array $awards,
        ?string $leadIn,
        ?string $displayLeadIn = null,
    ): AnnouncementAwardListResult {
        $warnings = [];
        $errors = [];
        $phrases = [];
        $displayPhrases = [];
        $seen = [];
        $normalizedLeadIn = $this->normalizer->normalize($leadIn);
        $normalizedDisplayLeadIn = $this->normalizer->normalize($displayLeadIn) ?? $normalizedLeadIn;

        $ordered = [];

        foreach ($awards as $index => $award) {
            if (! $award->isAnnouncementEnabled) {
                continue;
            }

            $ordered[] = ['award' => $award, 'source_index' => $index];
        }

        usort($ordered, static function (array $first, array $second): int {
            /** @var AnnouncementAwardInput $firstAward */
            $firstAward = $first['award'];
            /** @var AnnouncementAwardInput $secondAward */
            $secondAward = $second['award'];

            return $firstAward->displayOrder <=> $secondAward->displayOrder
                ?: self::compareIdentifiers($firstAward->identifier, $secondAward->identifier)
                ?: $first['source_index'] <=> $second['source_index'];
        });

        foreach ($ordered as $item) {
            /** @var AnnouncementAwardInput $award */
            $award = $item['award'];
            $phrase = $this->normalizer->normalize($award->spokenText)
                ?? $this->normalizer->normalize($award->awardName);

            if ($phrase === null) {
                $errors[] = new AnnouncementIssue(
                    'empty_enabled_award',
                    'An enabled award needs an award name or spoken wording.',
                    'awards',
                );

                continue;
            }

            if ($this->isRawPlaceholder($phrase)) {
                $errors[] = new AnnouncementIssue(
                    'raw_placeholder_detected',
                    'Enabled awards must not contain placeholder values.',
                    'awards',
                );

                continue;
            }

            if ($this->normalizer->containsKnownSsmlMarkup($phrase)) {
                $errors[] = new AnnouncementIssue(
                    'raw_markup_detected',
                    'Award wording must be plain text without SSML elements.',
                    'awards',
                );

                continue;
            }

            if ($this->containsAwardLeadIn($phrase, $normalizedLeadIn)) {
                $errors[] = new AnnouncementIssue(
                    'award_contains_lead_in',
                    'Award wording must not repeat the ceremony award lead-in.',
                    'awards',
                );

                continue;
            }

            if (preg_match('/^(?:[,;:]|and\b|or\b)/iu', $phrase) === 1) {
                $errors[] = new AnnouncementIssue(
                    'award_not_list_ready',
                    'Award wording must be a list-ready phrase without a leading separator or conjunction.',
                    'awards',
                );

                continue;
            }

            $phrase = $this->normalizer->withoutTrailingClausePunctuation($phrase);
            $comparisonKey = $this->normalizer->comparisonKey($phrase);

            if ($comparisonKey === '') {
                $errors[] = new AnnouncementIssue(
                    'empty_enabled_award',
                    'An enabled award needs an award name or spoken wording.',
                    'awards',
                );

                continue;
            }

            if (isset($seen[$comparisonKey])) {
                $errors[] = new AnnouncementIssue(
                    'duplicate_award_phrase',
                    'Enabled awards must not repeat the same spoken wording.',
                    'awards',
                );

                continue;
            }

            $seen[$comparisonKey] = true;
            $phrases[] = $phrase;
            $displayPhrases[] = $this->normalizer->withoutTrailingClausePunctuation(
                $this->normalizer->normalize($award->awardName) ?? $phrase,
            );
        }

        $list = $this->naturalLanguageList($phrases);
        $displayList = $this->naturalLanguageList($displayPhrases);
        $text = $list;
        $displayText = $displayList;

        if ($list !== '' && $normalizedLeadIn !== null) {
            if ($this->normalizer->containsKnownSsmlMarkup($normalizedLeadIn)) {
                $errors[] = new AnnouncementIssue(
                    'raw_markup_detected',
                    'The award lead-in must be plain text without SSML elements.',
                    'awards_lead_in',
                );
                $text = '';
            } else {
                $text = $this->normalizer->withoutTrailingClausePunctuation(
                    $normalizedLeadIn,
                ).' '.$list;
                $displayText = $this->normalizer->withoutTrailingClausePunctuation(
                    $normalizedDisplayLeadIn,
                ).' '.$displayList;
            }
        }

        return new AnnouncementAwardListResult(
            text: $text,
            displayText: $displayText,
            awardCount: count($phrases),
            warnings: $warnings,
            blockingErrors: $errors,
        );
    }

    private static function compareIdentifiers(int|string|null $first, int|string|null $second): int
    {
        if ($first === $second) {
            return 0;
        }

        if ($first === null) {
            return 1;
        }

        if ($second === null) {
            return -1;
        }

        if (is_int($first) && is_int($second)) {
            return $first <=> $second;
        }

        return strcmp((string) $first, (string) $second);
    }

    private function containsAwardLeadIn(string $phrase, ?string $leadIn): bool
    {
        $phraseKey = $this->normalizer->comparisonKey($phrase);
        $leadIns = array_filter([
            $this->normalizer->comparisonKey($leadIn),
            'recipient of',
        ]);

        foreach ($leadIns as $leadInKey) {
            if ($phraseKey === $leadInKey || str_starts_with($phraseKey, $leadInKey.' ')) {
                return true;
            }
        }

        return false;
    }

    private function isRawPlaceholder(string $value): bool
    {
        if (in_array($this->normalizer->comparisonKey($value), [
            'null',
            'undefined',
            'none',
            'n a',
        ], true)) {
            return true;
        }

        return preg_match('/^(?:\{[^{}]+\}|\{\{[^{}]+\}\}|\$\{[^{}]+\})$/u', $value) === 1;
    }

    /**
     * @param  list<string>  $phrases
     */
    private function naturalLanguageList(array $phrases): string
    {
        return match (count($phrases)) {
            0 => '',
            1 => $phrases[0],
            2 => $phrases[0].' and '.$phrases[1],
            default => implode(', ', array_slice($phrases, 0, -1))
                .', and '.$phrases[array_key_last($phrases)],
        };
    }
}
