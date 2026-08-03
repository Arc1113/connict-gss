<?php

namespace App\Data;

final readonly class AnnouncementSection
{
    public function __construct(
        public string $code,
        public string $content,
        public string $spokenText,
    ) {}
}
