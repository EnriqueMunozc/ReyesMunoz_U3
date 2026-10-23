<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Cuentahorro');
        $response->assertSee('todavía no permite registrar');
    }

    public function test_database_provisioning_refuses_non_local_environments_without_asking_for_secrets(): void
    {
        $this->artisan('cuentahorro:prepare-local')
            ->expectsOutput('Ejecuta este comando únicamente en local y sin configuración cacheada.')
            ->assertFailed();
    }
}
