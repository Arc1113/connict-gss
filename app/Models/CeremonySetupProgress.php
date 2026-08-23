<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ceremony_id',
    'user_id',
    'current_step',
    'skipped_steps',
    'started_at',
    'completed_at',
])]
class CeremonySetupProgress extends Model
{
    protected function casts(): array
    {
        return [
            'skipped_steps' => 'array',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function ceremony(): BelongsTo
    {
        return $this->belongsTo(Ceremony::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
