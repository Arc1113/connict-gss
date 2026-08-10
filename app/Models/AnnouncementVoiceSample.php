<?php

namespace App\Models;

use App\Enums\AnnouncementProvider;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ceremony_id',
    'announcement_template_id',
    'provider',
    'provider_voice',
    'locale',
    'character_count',
    'source_hash',
    'candidate_audio_path',
    'generated_by',
    'expires_at',
])]
#[Hidden(['candidate_audio_path', 'source_hash'])]
class AnnouncementVoiceSample extends Model
{
    protected function casts(): array
    {
        return [
            'provider' => AnnouncementProvider::class,
            'character_count' => 'integer',
            'expires_at' => 'immutable_datetime',
        ];
    }

    public function ceremony(): BelongsTo
    {
        return $this->belongsTo(Ceremony::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(AnnouncementTemplate::class, 'announcement_template_id');
    }

    public function generatingUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
