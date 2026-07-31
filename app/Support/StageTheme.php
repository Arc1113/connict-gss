<?php

namespace App\Support;

use App\Models\Ceremony;

final class StageTheme
{
    /** @return array<string, string|bool> */
    public static function defaults(): array
    {
        return [
            'preset' => 'institutional',
            'layout' => 'centered',
            'background_color' => '#082f2b',
            'surface_color' => '#f6f1e7',
            'text_color' => '#ffffff',
            'accent_color' => '#d6b474',
            'show_photo' => true,
        ];
    }

    /** @return array<string, string|bool|null> */
    public static function forCeremony(Ceremony $ceremony): array
    {
        $theme = [...self::defaults(), ...($ceremony->stage_theme ?? [])];

        return [
            ...$theme,
            'logo_url' => self::localUrl($ceremony->stage_logo_path),
            'background_url' => self::localUrl($ceremony->stage_background_path),
        ];
    }

    private static function localUrl(?string $path): ?string
    {
        if ($path === null || str_contains($path, '..')) {
            return null;
        }

        return '/'.ltrim($path, '/');
    }
}
