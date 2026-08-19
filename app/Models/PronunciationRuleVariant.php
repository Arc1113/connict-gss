<?php

namespace App\Models;

use App\Enums\AnnouncementProvider;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'pronunciation_rule_id',
    'provider',
    'locale',
    'voice',
    'replacement_text',
    'is_enabled',
])]
class PronunciationRuleVariant extends Model
{
    protected function casts(): array
    {
        return [
            'provider' => AnnouncementProvider::class,
            'is_enabled' => 'boolean',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(PronunciationRule::class, 'pronunciation_rule_id');
    }
}

