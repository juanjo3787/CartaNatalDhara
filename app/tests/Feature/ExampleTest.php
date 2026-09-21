<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_redirects_to_saved_charts(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/charts');
    }
}
