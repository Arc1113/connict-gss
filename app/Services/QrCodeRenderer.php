<?php

namespace App\Services;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use InvalidArgumentException;

final class QrCodeRenderer
{
    public const QUIET_ZONE_MODULES = 4;

    public const RENDER_SIZE = 512;

    private readonly Writer $writer;

    public function __construct()
    {
        $this->writer = new Writer(new ImageRenderer(
            new RendererStyle(
                self::RENDER_SIZE,
                self::QUIET_ZONE_MODULES,
            ),
            new SvgImageBackEnd,
        ));
    }

    public function svg(string $token): string
    {
        if ($token === '') {
            throw new InvalidArgumentException('A QR token is required.');
        }

        $encoding = mb_check_encoding($token, 'ASCII')
            ? 'ISO-8859-1'
            : 'UTF-8';

        return $this->writer->writeString(
            $token,
            $encoding,
            ErrorCorrectionLevel::Q(),
        );
    }

    public function dataUri(string $token): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode($this->svg($token));
    }
}
