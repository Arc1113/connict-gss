<?php

namespace App\Support;

final class StudentImportLimits
{
    public const MAX_FILE_BYTES = 2 * 1024 * 1024;

    public const MAX_FILE_KILOBYTES = 2 * 1024;

    public const MAX_MEDIA_PACKAGE_KILOBYTES = 256 * 1024;

    public const MAX_ROWS = 2000;

    public const MAX_COLUMNS = 40;

    public const MAX_CELL_CHARACTERS = 2000;

    public const PREVIEW_PER_PAGE = 50;

    public const EXPIRATION_MINUTES = 30;

    /**
     * @return array<string, int|string>
     */
    public static function forBrowser(): array
    {
        return [
            'max_file_megabytes' => 2,
            'max_media_package_megabytes' => 256,
            'max_rows' => self::MAX_ROWS,
            'max_columns' => self::MAX_COLUMNS,
            'max_cell_characters' => self::MAX_CELL_CHARACTERS,
            'preview_per_page' => self::PREVIEW_PER_PAGE,
            'expiration_minutes' => self::EXPIRATION_MINUTES,
            'encoding' => 'UTF-8',
            'delimiters' => 'Comma, semicolon, or tab',
        ];
    }
}
