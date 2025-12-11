<?php

namespace Tests\Feature\Auth;

use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    public function test_new_users_can_register()
    {
        if (! in_array(Features::registration(), config('fortify.features'))) {
            $this->markTestSkipped('Skip test cause feature is disabled.');
        }

        $response = $this->postJson('/register', [
            'name' => 'Test User',
            'email' => 'test@examples.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();

        $response->assertCreated();
    }
}
