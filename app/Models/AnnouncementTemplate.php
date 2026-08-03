<?php

namespace App\Models;

use Database\Factories\AnnouncementTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'ceremony_id',
    'name',
    'introduction_text',
    'include_program',
    'include_major',
    'include_honors',
    'include_awards',
    'include_additional_text',
    'awards_lead_in',
    'closing_text',
    'default_voice',
    'default_locale',
    'speaking_rate_percent',
    'pause_after_name_ms',
    'pause_between_sections_ms',
    'recommended_character_limit',
    'maximum_character_limit',
    'version',
    'is_active',
    'created_by',
    'updated_by',
])]
class AnnouncementTemplate extends Model
{
    /** @use HasFactory<AnnouncementTemplateFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'include_program' => 'boolean',
            'include_major' => 'boolean',
            'include_honors' => 'boolean',
            'include_awards' => 'boolean',
            'include_additional_text' => 'boolean',
            'speaking_rate_percent' => 'integer',
            'pause_after_name_ms' => 'integer',
            'pause_between_sections_ms' => 'integer',
            'recommended_character_limit' => 'integer',
            'maximum_character_limit' => 'integer',
            'version' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function ceremony(): BelongsTo
    {
        return $this->belongsTo(Ceremony::class);
    }

    public function generations(): HasMany
    {
        return $this->hasMany(AnnouncementGeneration::class);
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
