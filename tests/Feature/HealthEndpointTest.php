<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    public function test_the_health_endpoint_is_local_json_without_external_assets(): void
    {
        $response = $this->get('/up');

        $response
            ->assertOk()
            ->assertHeader('content-type', 'application/json')
            ->assertExactJson(['status' => 'up']);

        $content = $response->getContent();

        $this->assertStringNotContainsString('https://', $content);
        $this->assertStringNotContainsString('http://', $content);
    }
}
