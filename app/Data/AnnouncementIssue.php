<?php

namespace App\Data;

final readonly class AnnouncementIssue
{
    public function __construct(
        public string $code,
        public string $message,
        public ?string $field = null,
    ) {}
}
