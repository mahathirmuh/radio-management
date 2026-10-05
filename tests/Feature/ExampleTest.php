<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The home URL sends guests to the login page (signed-in users go to their role's home page,
     * see SsoLoginTest).
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertStatus(200);
    }
}
