<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_home_page_is_rendered_through_inertia(): void
    {
        $this->withoutVite();

        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Welcome')
                ->where('appName', 'Graduation Stage System')
                ->where('phase', 'Phase 5')
                ->where('auth.user', null)
                ->where('stack', [
                    'Laravel',
                    'React',
                    'Inertia.js',
                    'Vite',
                    'PostgreSQL',
                ]));
    }
}
