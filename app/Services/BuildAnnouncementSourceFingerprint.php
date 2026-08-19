<?php

namespace App\Services;

use App\Data\AnnouncementSourceFingerprintData;
use JsonException;

final class BuildAnnouncementSourceFingerprint
{
    /**
     * @throws JsonException
     */
    public function handle(AnnouncementSourceFingerprintData $source, array $pronunciation = []): string
    {
        $canonical = $source->toCanonicalArray();
        $canonical['pronunciation'] = $pronunciation;

        return hash('sha256', json_encode(
            $this->sortAssociativeKeys($canonical),
            JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_PRESERVE_ZERO_FRACTION,
        ));
    }

    /**
     * @throws JsonException
     */
    public function canonicalJson(AnnouncementSourceFingerprintData $source): string
    {
        return json_encode(
            $this->sortAssociativeKeys($source->toCanonicalArray()),
            JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_PRESERVE_ZERO_FRACTION,
        );
    }

    private function sortAssociativeKeys(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(
                fn (mixed $item): mixed => $this->sortAssociativeKeys($item),
                $value,
            );
        }

        ksort($value, SORT_STRING);

        foreach ($value as $key => $item) {
            $value[$key] = $this->sortAssociativeKeys($item);
        }

        return $value;
    }
}
