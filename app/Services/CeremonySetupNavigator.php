<?php

namespace App\Services;

final class CeremonySetupNavigator
{
    /**
     * Return the next unresolved step, preferring work that has not been
     * explicitly skipped during the current guided setup run.
     *
     * @param  array{steps?: list<array<string, mixed>>}  $preparation
     * @param  array{skipped_steps?: list<string>}  $setup
     * @return array<string, mixed>|null
     */
    public function nextStep(array $preparation, array $setup = []): ?array
    {
        $steps = array_values(array_filter(
            $preparation['steps'] ?? [],
            static fn (mixed $step): bool => is_array($step) && ($step['key'] ?? null) !== 'readiness',
        ));
        $skipped = array_flip(array_values(array_filter($setup['skipped_steps'] ?? [], 'is_string')));

        foreach ([false, true] as $includeSkipped) {
            foreach ($steps as $step) {
                if (($step['status'] ?? 'ready') === 'ready') {
                    continue;
                }

                $isSkipped = isset($skipped[$step['key'] ?? '']);
                if ($includeSkipped || ! $isSkipped) {
                    return $step;
                }
            }
        }

        return null;
    }

    /** @param array<string, mixed> $preparation @param array<string, mixed> $setup */
    public function recommendedNextStep(array $preparation, array $setup = []): ?array
    {
        return $this->nextStep($preparation, $setup);
    }
}
