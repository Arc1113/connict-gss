<?php

namespace App\Actions;

use App\Models\User;

final readonly class AdvanceFullyAutomaticPresentation
{
    public function __construct(private CompleteAndPresentNext $complete) {}

    /** @return array{data: array<string, mixed>, http_status: int} */
    public function execute(User $operator, int $expectedVersion, int $studentId): array
    {
        return $this->complete->execute(
            $operator,
            $expectedVersion,
            $studentId,
            true,
        );
    }
}
