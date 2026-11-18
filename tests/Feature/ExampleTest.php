<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Halaman utama wajib login.
     */
    public function test_the_application_requires_authentication(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }
}
