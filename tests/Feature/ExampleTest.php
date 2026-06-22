<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_application_redirects_to_status_page(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/status');
    }
}
