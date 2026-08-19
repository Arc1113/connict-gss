<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'tour_key',
    'tour_version',
    'status',
    'current_step',
    'completed_at',
    'skipped_at',
])]
class UserOnboardingProgress extends Model
{
    protected $table = 'user_onboarding_progress';

    protected function casts(): array
    {
        return [
            'tour_version' => 'integer',
            'current_step' => 'integer',
            'completed_at' => 'immutable_datetime',
            'skipped_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
