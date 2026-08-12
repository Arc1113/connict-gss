<?php

namespace Tests\Feature\EnhancementTts;

use App\Data\AnnouncementSynthesisRequest;
use App\Enums\AnnouncementProvider;
use App\Exceptions\SpeechSynthesisException;
use App\Services\KokoroSpeechProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class KokoroSpeechProviderTest extends AnnouncementTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('announcements.kokoro.enabled', true);
        config()->set('announcements.kokoro.base_url', 'http://local-tts:8880');
        config()->set('announcements.kokoro.maximum_response_bytes', 4096);
    }

    public function test_local_synthesis_uses_only_the_internal_service_and_returns_valid_mp3(): void
    {
        Http::fake([
            'http://local-tts:8880/v1/synthesize' => Http::response(
                $this->mp3(),
                200,
                ['Content-Type' => 'audio/mpeg'],
            ),
        ]);

        $result = app(KokoroSpeechProvider::class)->synthesize($this->request());

        $this->assertSame('audio/mpeg', $result->contentType);
        $this->assertSame(1, $result->attemptCount);
        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'http://local-tts:8880/v1/synthesize'
                && $request['voice'] === 'af_heart'
                && $request['sections'][1] === ['code' => 'name', 'text' => 'Fictional Graduate.']
                && $request['pause_after_name_ms'] === 250
                && $request['delivery_profile'] === 'professional_mc';
        });
    }

    public function test_approved_male_voice_is_sent_to_the_local_service(): void
    {
        Http::fake([
            'http://local-tts:8880/v1/synthesize' => Http::response(
                $this->mp3(),
                200,
                ['Content-Type' => 'audio/mpeg'],
            ),
        ]);
        $request = $this->request();
        $maleRequest = new AnnouncementSynthesisRequest(
            ssml: $request->ssml,
            plainText: $request->plainText,
            characterCount: $request->characterCount,
            voice: 'am_michael',
            locale: 'en-US',
            outputFormat: $request->outputFormat,
            sourceHash: $request->sourceHash,
            provider: $request->provider,
            speakingRatePercent: $request->speakingRatePercent,
            pauseAfterNameMs: $request->pauseAfterNameMs,
            pauseBetweenSectionsMs: $request->pauseBetweenSectionsMs,
            sections: $request->sections,
        );

        app(KokoroSpeechProvider::class)->synthesize($maleRequest);

        Http::assertSent(fn (Request $sent): bool => $sent['voice'] === 'am_michael');
    }

    public function test_local_failure_is_explicit_and_never_calls_azure(): void
    {
        Http::fake([
            'http://local-tts:8880/*' => Http::response(['detail' => 'failed'], 500),
            '*' => Http::response($this->mp3()),
        ]);

        try {
            app(KokoroSpeechProvider::class)->synthesize($this->request());
            $this->fail('Local provider failure did not stop synthesis.');
        } catch (SpeechSynthesisException $exception) {
            $this->assertSame('local_tts_failed', $exception->safeCode);
        }

        Http::assertSentCount(1);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'azure'));
    }

    public function test_health_check_is_explicit_and_local_only(): void
    {
        Http::fake([
            'http://local-tts:8880/health' => Http::response(['status' => 'ready']),
        ]);

        $provider = app(KokoroSpeechProvider::class);

        $this->assertSame('configured', $provider->health()->status);
        $this->assertSame('available', $provider->health(true)->status);
    }

    private function request(): AnnouncementSynthesisRequest
    {
        $plainText = 'We proudly present. Fictional Graduate.';

        return new AnnouncementSynthesisRequest(
            ssml: '<speak>Fictional Graduate</speak>',
            plainText: $plainText,
            characterCount: mb_strlen($plainText),
            voice: 'af_heart',
            locale: 'en-US',
            outputFormat: 'audio-24khz-48kbitrate-mono-mp3',
            sourceHash: hash('sha256', 'local-fictional-source'),
            provider: AnnouncementProvider::Kokoro,
            speakingRatePercent: -5,
            pauseAfterNameMs: 250,
            pauseBetweenSectionsMs: 160,
            sections: [
                ['code' => 'introduction', 'text' => 'We proudly present.'],
                ['code' => 'name', 'text' => 'Fictional Graduate.'],
            ],
        );
    }
}
