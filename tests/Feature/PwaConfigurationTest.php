<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PwaConfigurationTest extends TestCase
{
    public function test_manifest_contains_installable_local_application_metadata(): void
    {
        $manifest = json_decode(
            File::get(public_path('manifest.webmanifest')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame('Graduation Stage System', $manifest['name']);
        $this->assertSame('/', $manifest['start_url']);
        $this->assertSame('/', $manifest['scope']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertFalse($manifest['prefer_related_applications']);
        $this->assertFileExists(public_path('icons/gss-192.png'));
        $this->assertFileExists(public_path('icons/gss-512.png'));
        $this->assertFileExists(public_path('icons/gss-maskable-512.png'));
    }

    public function test_service_worker_never_queues_or_caches_mutation_requests(): void
    {
        $serviceWorker = File::get(public_path('sw.js'));

        $this->assertStringContainsString("request.method !== 'GET'", $serviceWorker);
        $this->assertStringContainsString("request.mode === 'navigate'", $serviceWorker);
        $this->assertStringContainsString("'/offline.html'", $serviceWorker);
        $this->assertStringNotContainsString('BackgroundSync', $serviceWorker);
        $this->assertStringNotContainsString('indexedDB', $serviceWorker);
    }

    public function test_application_template_registers_the_manifest_and_workstation_icon(): void
    {
        $template = File::get(resource_path('views/app.blade.php'));

        $this->assertStringContainsString('/manifest.webmanifest', $template);
        $this->assertStringContainsString('/icons/gss-192.png', $template);
        $this->assertStringContainsString('apple-mobile-web-app-capable', $template);
    }
}
