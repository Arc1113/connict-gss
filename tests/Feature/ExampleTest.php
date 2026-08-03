<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_home_page_sends_operators_to_login(): void
    {
        $this->withoutVite();

        $this->get('/')->assertRedirect('/login');
    }

    public function test_forwarded_https_requests_keep_secure_redirects(): void
    {
        $this->withoutVite();

        $response = $this
            ->withServerVariables([
                'REMOTE_ADDR' => '172.18.0.10',
                'HTTP_HOST' => '192.168.110.50',
                'HTTP_X_FORWARDED_HOST' => '192.168.110.50',
                'HTTP_X_FORWARDED_PORT' => '443',
                'HTTP_X_FORWARDED_PROTO' => 'https',
            ])
            ->get('/');

        $response->assertRedirect('https://192.168.110.50/login');
    }
}
