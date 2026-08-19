<?php

namespace App\Services;

use App\Data\AnnouncementSynthesisRequest;
use App\Enums\AnnouncementProvider;
use App\Exceptions\SpeechSynthesisException;
use XMLWriter;

final class GeneratePronunciationPreviewAudio
{
    public function __construct(
        private readonly TextToSpeechProviderRegistry $providers,
    ) {}

    /** @return array{data_uri: string|null, warning: string|null} */
    public function generate(
        string $text,
        AnnouncementProvider $provider,
        string $locale,
        string $voice,
    ): array {
        $health = $this->providers->healthFor($provider, true);

        if ($health->status !== 'available') {
            return ['data_uri' => null, 'warning' => 'The selected speech provider is not available for an audio preview.'];
        }

        $hash = hash('sha256', implode('|', [$provider->value, $locale, $voice, $text]));
        $writer = new XMLWriter;
        $writer->openMemory();
        $writer->startElementNS(null, 'speak', 'http://www.w3.org/2001/10/synthesis');
        $writer->writeAttribute('version', '1.0');
        $writer->writeAttributeNS('xml', 'lang', 'http://www.w3.org/XML/1998/namespace', $locale);
        $writer->startElement('voice');
        $writer->writeAttribute('name', $voice);
        $writer->text($text);
        $writer->endElement();
        $writer->endElement();

        try {
            $speech = $this->providers->synthesize(new AnnouncementSynthesisRequest(
                ssml: $writer->outputMemory(),
                plainText: $text,
                characterCount: mb_strlen($text, 'UTF-8'),
                voice: $voice,
                locale: $locale,
                outputFormat: (string) config('announcements.default_output_format'),
                sourceHash: $hash,
                provider: $provider,
                sections: [['code' => 'preview', 'text' => $text]],
            ));
        } catch (SpeechSynthesisException $exception) {
            return ['data_uri' => null, 'warning' => $exception->getMessage()];
        }

        return [
            'data_uri' => 'data:'.$speech->contentType.';base64,'.base64_encode($speech->audioBytes),
            'warning' => null,
        ];
    }
}
