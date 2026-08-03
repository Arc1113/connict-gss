<?php

namespace Tests\Feature\EnhancementTts;

use App\Data\AnnouncementSynthesisRequest;
use App\Exceptions\SpeechSynthesisException;
use App\Services\AzureSpeechConfiguration;
use App\Services\AzureSpeechProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class AzureSpeechProviderTest extends AnnouncementTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->configureAzure();
    }

    public function test_disabled_and_invalid_configuration_never_send_a_request(): void
    {
        Http::fake();
        config()->set('announcements.azure.enabled', false);
        $provider = app(AzureSpeechProvider::class);

        $this->assertSame('disabled', $provider->health()->status);

        try {
            $provider->synthesize($this->request());
            $this->fail('Disabled Azure configuration did not block synthesis.');
        } catch (SpeechSynthesisException $exception) {
            $this->assertSame('azure_not_configured', $exception->safeCode);
        }

        config()->set('announcements.azure.enabled', true);
        config()->set('announcements.azure.endpoint', 'https://evil.example/cognitiveservices/v1');
        $this->assertSame('misconfigured', $provider->health()->status);
        Http::assertNothingSent();
    }

    public function test_custom_resource_endpoint_is_normalized_and_unapproved_urls_are_rejected(): void
    {
        config()->set(
            'announcements.azure.endpoint',
            'https://fictional-resource.cognitiveservices.azure.com',
        );
        $settings = app(AzureSpeechConfiguration::class)->resolve();

        $this->assertSame(
            'https://fictional-resource.cognitiveservices.azure.com/cognitiveservices/v1',
            $settings->synthesisEndpoint,
        );
        $this->assertSame(
            'https://fictional-resource.cognitiveservices.azure.com/tts/cognitiveservices/voices/list',
            $settings->healthEndpoint,
        );

        foreach ([
            'http://southeastasia.tts.speech.microsoft.com/cognitiveservices/v1',
            'https://southeastasia.tts.speech.microsoft.com:444/cognitiveservices/v1',
            'https://southeastasia.tts.speech.microsoft.com/cognitiveservices/v1?secret=value',
            'https://evil.example/cognitiveservices/v1',
        ] as $endpoint) {
            config()->set('announcements.azure.endpoint', $endpoint);
            $this->assertSame('misconfigured', app(AzureSpeechConfiguration::class)->health()->status);
        }
    }

    public function test_malformed_key_is_rejected_before_http_header_construction(): void
    {
        Http::fake();
        config()->set('announcements.azure.key', "unsafe\nkey");

        $this->assertSame(
            'misconfigured',
            app(AzureSpeechConfiguration::class)->health()->status,
        );
        Http::assertNothingSent();
    }

    public function test_success_uses_the_approved_contract_and_returns_valid_mp3(): void
    {
        Http::fake([
            '*' => Http::response($this->mp3(), 200, [
                'Content-Type' => 'audio/mpeg',
                'X-Microsoft-RequestId' => 'safe-request-123',
            ]),
        ]);

        $result = app(AzureSpeechProvider::class)->synthesize($this->request());

        $this->assertSame($this->mp3(), $result->audioBytes);
        $this->assertSame('audio/mpeg', $result->contentType);
        $this->assertSame('safe-request-123', $result->providerRequestId);
        $this->assertSame(1, $result->attemptCount);
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://southeastasia.tts.speech.microsoft.com/cognitiveservices/v1'
                && $request->method() === 'POST'
                && $request->hasHeader('Ocp-Apim-Subscription-Key', 'test-secret-key')
                && $request->hasHeader('Content-Type', 'application/ssml+xml')
                && $request->hasHeader(
                    'X-Microsoft-OutputFormat',
                    'audio-24khz-48kbitrate-mono-mp3',
                )
                && str_contains($request->body(), '<speak');
        });
    }

    public function test_authentication_failure_does_not_retry_or_leak_the_body(): void
    {
        Http::fake(['*' => Http::response('upstream-secret-body', 401)]);

        try {
            app(AzureSpeechProvider::class)->synthesize($this->request());
            $this->fail('Authentication failure did not fail synthesis.');
        } catch (SpeechSynthesisException $exception) {
            $this->assertSame('azure_authentication_failed', $exception->safeCode);
            $this->assertSame(1, $exception->attemptCount);
            $this->assertStringNotContainsString('upstream-secret-body', $exception->getMessage());
            $this->assertStringNotContainsString('test-secret-key', $exception->getMessage());
        }

        Http::assertSentCount(1);
    }

    public function test_invalid_ssml_does_not_retry_or_leak_the_body(): void
    {
        Http::fake(['*' => Http::response('upstream-secret-body', 400)]);

        try {
            app(AzureSpeechProvider::class)->synthesize($this->request());
            $this->fail('Invalid SSML did not fail synthesis.');
        } catch (SpeechSynthesisException $exception) {
            $this->assertSame('azure_invalid_ssml', $exception->safeCode);
            $this->assertSame(1, $exception->attemptCount);
            $this->assertStringNotContainsString('upstream-secret-body', $exception->getMessage());
        }

        Http::assertSentCount(1);
    }

    public function test_timeout_is_bounded_and_redacted(): void
    {
        Http::fake([
            '*' => Http::sequence()
                ->pushFailedConnection('cURL error 28: Operation timed out')
                ->pushFailedConnection('cURL error 28: Operation timed out')
                ->pushFailedConnection('cURL error 28: Operation timed out')
                ->pushFailedConnection('cURL error 28: Operation timed out'),
        ]);

        try {
            app(AzureSpeechProvider::class)->synthesize($this->request());
            $this->fail('Timeout did not fail synthesis.');
        } catch (SpeechSynthesisException $exception) {
            $this->assertSame('azure_timeout', $exception->safeCode);
            $this->assertSame(4, $exception->attemptCount);
            $this->assertStringNotContainsString('test-secret-key', $exception->getMessage());
        }

        Http::assertSentCount(4);
    }

    public function test_rate_limit_and_server_errors_retry_then_succeed(): void
    {
        Http::fake([
            '*' => Http::sequence()
                ->push('', 429, ['Retry-After' => '0'])
                ->push('', 502)
                ->push($this->mp3(), 200, ['Content-Type' => 'audio/mpeg']),
        ]);

        $result = app(AzureSpeechProvider::class)->synthesize($this->request());

        $this->assertSame(3, $result->attemptCount);
        Http::assertSentCount(3);
    }

    public function test_exhausted_rate_limit_has_a_stable_code(): void
    {
        Http::fake([
            '*' => Http::sequence()
                ->pushStatus(429)
                ->pushStatus(429)
                ->pushStatus(429)
                ->pushStatus(429),
        ]);

        try {
            app(AzureSpeechProvider::class)->synthesize($this->request());
            $this->fail('Rate limiting did not exhaust retries.');
        } catch (SpeechSynthesisException $exception) {
            $this->assertSame('azure_rate_limited', $exception->safeCode);
            $this->assertSame(4, $exception->attemptCount);
        }

        Http::assertSentCount(4);
    }

    public function test_exhausted_service_error_has_a_stable_code(): void
    {
        Http::fake([
            '*' => Http::sequence()
                ->pushStatus(503)
                ->pushStatus(503)
                ->pushStatus(503)
                ->pushStatus(503),
        ]);

        try {
            app(AzureSpeechProvider::class)->synthesize($this->request());
            $this->fail('Service errors did not exhaust retries.');
        } catch (SpeechSynthesisException $exception) {
            $this->assertSame('azure_service_unavailable', $exception->safeCode);
            $this->assertSame(4, $exception->attemptCount);
        }

        Http::assertSentCount(4);
    }

    public function test_wrong_content_type_is_rejected(): void
    {
        $this->assertInvalidAudio($this->mp3(), 'application/json');
    }

    public function test_empty_audio_is_rejected(): void
    {
        $this->assertInvalidAudio('', 'audio/mpeg');
    }

    public function test_malformed_audio_is_rejected(): void
    {
        $this->assertInvalidAudio('not-an-mp3', 'audio/mpeg');
    }

    public function test_oversized_audio_is_rejected(): void
    {
        config()->set('announcements.azure.maximum_response_bytes', 4);
        $this->assertInvalidAudio($this->mp3(), 'audio/mpeg');
    }

    public function test_request_id_is_allowlisted(): void
    {
        Http::fake([
            '*' => Http::response($this->mp3(), 200, [
                'Content-Type' => 'audio/mpeg',
                'X-Microsoft-RequestId' => 'unsafe request id',
            ]),
        ]);
        $this->assertNull(app(AzureSpeechProvider::class)->synthesize($this->request())->providerRequestId);
    }

    public function test_redirects_are_not_followed(): void
    {
        Http::fake(['*' => Http::response('', 302, ['Location' => 'https://evil.example'])]);

        try {
            app(AzureSpeechProvider::class)->synthesize($this->request());
            $this->fail('A redirect was accepted.');
        } catch (SpeechSynthesisException $exception) {
            $this->assertSame('azure_response_unexpected', $exception->safeCode);
        }

        Http::assertSentCount(1);
    }

    public function test_configuration_and_explicit_network_health_are_separate(): void
    {
        $provider = app(AzureSpeechProvider::class);
        Http::fake(['*' => Http::response([], 200)]);

        $configuration = $provider->health();
        $network = $provider->health(true);

        $this->assertSame('configured', $configuration->status);
        $this->assertFalse($configuration->networkChecked);
        $this->assertSame('available', $network->status);
        $this->assertTrue($network->networkChecked);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && $request->url() === 'https://southeastasia.tts.speech.microsoft.com/cognitiveservices/voices/list');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function configureAzure(array $overrides = []): void
    {
        foreach (array_replace([
            'enabled' => true,
            'key' => 'test-secret-key',
            'region' => 'southeastasia',
            'endpoint' => null,
            'connect_timeout_seconds' => 1,
            'request_timeout_seconds' => 2,
            'maximum_retries' => 3,
            'retry_base_delay_ms' => 0,
            'retry_jitter_ms' => 0,
            'maximum_retry_delay_ms' => 0,
            'maximum_retry_after_seconds' => 0,
            'maximum_response_bytes' => 1024,
            'user_agent' => 'GraduationStageSystem-Test/1.0',
        ], $overrides) as $key => $value) {
            config()->set("announcements.azure.{$key}", $value);
        }
    }

    private function request(): AnnouncementSynthesisRequest
    {
        $plainText = 'We proudly present Fictional Graduate.';

        return new AnnouncementSynthesisRequest(
            ssml: '<speak version="1.0" xmlns="http://www.w3.org/2001/10/synthesis" xml:lang="en-PH"><voice name="en-PH-JamesNeural"><prosody rate="-5%">'.$plainText.'</prosody></voice></speak>',
            plainText: $plainText,
            characterCount: mb_strlen($plainText),
            voice: 'en-PH-JamesNeural',
            locale: 'en-PH',
            outputFormat: 'audio-24khz-48kbitrate-mono-mp3',
            sourceHash: hash('sha256', 'fictional-source'),
        );
    }

    private function mp3(): string
    {
        return 'ID3'.str_repeat("\0", 64);
    }

    private function assertInvalidAudio(string $body, string $contentType): void
    {
        Http::fake([
            '*' => Http::response($body, 200, ['Content-Type' => $contentType]),
        ]);

        try {
            app(AzureSpeechProvider::class)->synthesize($this->request());
            $this->fail('An invalid audio response was accepted.');
        } catch (SpeechSynthesisException $exception) {
            $this->assertSame('candidate_validation_failed', $exception->safeCode);
        }
    }
}
