<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_guest_can_open_login_and_root_targets_admin(): void
    {
        $this->get('/login')->assertOk();
        $this->get('/')->assertRedirect('/login');
    }
}
