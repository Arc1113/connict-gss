<?php

namespace App\Actions;

use App\Contracts\TextToSpeechProvider;
use App\Data\AnnouncementSynthesisRequest;
use App\Enums\AnnouncementProvider;
use App\Exceptions\CandidateAudioStorageException;
use App\Exceptions\SpeechSynthesisException;
use App\Exceptions\VoiceComparisonException;
use App\Models\AnnouncementTemplate;
use App\Models\AnnouncementVoiceSample;
use App\Models\Ceremony;
use App\Models\User;
use App\Services\PreviewFictionalAnnouncement;
use App\Services\PruneVoiceComparisonSamples;
use App\Services\TemporaryVoiceSampleStore;
use App\Support\AdministrativeAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Throwable;

final class GenerateVoiceComparison
{
    public function __construct(
        private readonly PreviewFictionalAnnouncement $previewer,
        private readonly TextToSpeechProvider $provider,
        private readonly TemporaryVoiceSampleStore $store,
        private readonly PruneVoiceComparisonSamples $pruner,
        private readonly AdministrativeAuditLogger $auditLogger,
    ) {}

    /**
     * @param  list<string>  $voices
     * @return list<AnnouncementVoiceSample>
     */
    public function generate(
        Ceremony $ceremony,
        array $voices,
        User $actor,
        ?string $ipAddress,
    ): array {
        Gate::forUser($actor)->authorize('manage-announcement-settings');
        $this->pruner->expired();

        $activeSamples = AnnouncementVoiceSample::query()
            ->whereBelongsTo($ceremony)
            ->where('generated_by', $actor->id)
            ->where('expires_at', '>', now())
            ->count();

        if ($activeSamples + count($voices) > (int) config(
            'announcements.voice_comparison.maximum_active_samples_per_user',
            12,
        )) {
            throw new VoiceComparisonException(
                'sample_limit_reached',
                'Too many temporary samples are still active. Wait for expiry before generating more.',
            );
        }

        if (! $this->provider->health()->isConfigured()) {
            throw new VoiceComparisonException(
                'azure_not_configured',
                'Azure Speech is disabled or not configured safely for voice comparison.',
            );
        }

        $template = AnnouncementTemplate::query()
            ->whereBelongsTo($ceremony)
            ->where('is_active', true)
            ->first();

        if ($template === null) {
            throw new VoiceComparisonException(
                'missing_active_template',
                'Publish ceremony announcement settings before comparing voices.',
            );
        }

        $prepared = [];

        try {
            foreach ($voices as $voice) {
                $preview = $this->previewer->preview($template, $voice);

                if (! $preview->isReady() || $preview->composition === null
                    || $preview->ssml?->ssml === null) {
                    throw new VoiceComparisonException(
                        'fictional_sample_not_ready',
                        'The fictional comparison sample did not pass announcement validation.',
                    );
                }

                $composition = $preview->composition;
                $speech = $this->provider->synthesize(new AnnouncementSynthesisRequest(
                    ssml: $preview->ssml->ssml,
                    plainText: $composition->plainText,
                    characterCount: $composition->characterCount,
                    voice: $composition->voice,
                    locale: $composition->locale,
                    outputFormat: $composition->input->outputFormat,
                    sourceHash: $composition->sourceHash,
                ));
                $stored = $this->store->store($speech);
                $prepared[] = compact('composition', 'stored');
            }
        } catch (SpeechSynthesisException $exception) {
            $this->cleanup($prepared);

            throw new VoiceComparisonException($exception->safeCode, $exception->getMessage());
        } catch (CandidateAudioStorageException) {
            $this->cleanup($prepared);

            throw new VoiceComparisonException(
                'sample_storage_failed',
                'The temporary voice comparison sample could not be stored safely.',
            );
        } catch (Throwable $exception) {
            $this->cleanup($prepared);

            throw $exception;
        }

        try {
            return DB::transaction(function () use (
                $ceremony,
                $template,
                $actor,
                $ipAddress,
                $prepared,
            ): array {
                Ceremony::query()->lockForUpdate()->findOrFail($ceremony->id);
                $activeTemplateId = AnnouncementTemplate::query()
                    ->whereBelongsTo($ceremony)
                    ->where('is_active', true)
                    ->value('id');

                if ((int) $activeTemplateId !== (int) $template->id) {
                    throw new VoiceComparisonException(
                        'settings_changed',
                        'Ceremony settings changed during comparison. Generate fresh samples.',
                    );
                }

                $expiresAt = now()->addMinutes(max(5, (int) config(
                    'announcements.voice_comparison.retention_minutes',
                    60,
                )));
                $samples = [];

                foreach ($prepared as $item) {
                    $composition = $item['composition'];
                    $stored = $item['stored'];
                    $samples[] = AnnouncementVoiceSample::query()->create([
                        'ceremony_id' => $ceremony->id,
                        'announcement_template_id' => $template->id,
                        'provider' => AnnouncementProvider::Azure->value,
                        'provider_voice' => $composition->voice,
                        'locale' => $composition->locale,
                        'character_count' => $composition->characterCount,
                        'source_hash' => $composition->sourceHash,
                        'candidate_audio_path' => $stored->path,
                        'generated_by' => $actor->id,
                        'expires_at' => $expiresAt,
                    ]);
                }

                $this->auditLogger->record(
                    $actor,
                    'announcement_voice_comparison.generated',
                    'ceremony',
                    $ceremony->id,
                    null,
                    [
                        'template_id' => $template->id,
                        'template_version' => $template->version,
                        'voices' => array_map(
                            static fn (AnnouncementVoiceSample $sample): string => $sample->provider_voice,
                            $samples,
                        ),
                        'sample_count' => count($samples),
                        'characters_per_sample' => $samples[0]?->character_count,
                        'temporary' => true,
                    ],
                    $ipAddress,
                );

                return $samples;
            }, 3);
        } catch (Throwable $exception) {
            $this->cleanup($prepared);

            throw $exception;
        }
    }

    /** @param array<int, array<string, mixed>> $prepared */
    private function cleanup(array $prepared): void
    {
        foreach ($prepared as $item) {
            $this->store->delete($item['stored']->path ?? null);
        }
    }
}
