<?php

namespace App\Models;

use App\Enums\PronunciationRuleSection;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'ceremony_id',
    'section',
    'match_text',
    'normalized_match',
    'is_enabled',
    'is_builtin',
    'revision',
    'created_by',
    'updated_by',
])]
class PronunciationRule extends Model
{
    protected function casts(): array
    {
        return [
            'section' => PronunciationRuleSection::class,
            'is_enabled' => 'boolean',
            'is_builtin' => 'boolean',
            'revision' => 'integer',
        ];
    }

    public function ceremony(): BelongsTo
    {
        return $this->belongsTo(Ceremony::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(PronunciationRuleVariant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
