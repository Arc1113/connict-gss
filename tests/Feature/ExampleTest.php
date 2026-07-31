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
}
