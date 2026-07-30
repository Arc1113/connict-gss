<?php

namespace Tests\Unit;

use App\Services\QrCodeRenderer;
use PHPUnit\Framework\TestCase;

class QrCodeRendererTest extends TestCase
{
    public function test_svg_output_is_deterministic_local_and_has_the_required_quiet_zone(): void
    {
        $token = 'GSS-'.str_repeat('A1', 24);
        $renderer = new QrCodeRenderer;
        $first = $renderer->svg($token);
        $second = $renderer->svg($token);

        $this->assertSame($first, $second);
        $this->assertStringContainsString('width="512"', $first);
        $this->assertStringContainsString('height="512"', $first);
        $this->assertStringContainsString('fill="#ffffff"', $first);
        $this->assertStringContainsString('translate(4,4)', $first);
        $this->assertStringNotContainsString($token, $first);
        $this->assertStringNotContainsString('<script', mb_strtolower($first));
        $this->assertStringStartsWith(
            'data:image/svg+xml;base64,',
            $renderer->dataUri($token),
        );
    }
}
