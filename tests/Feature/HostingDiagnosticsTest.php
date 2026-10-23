<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HostingDiagnosticsTest extends TestCase
{
    public function test_diagnostic_checks_real_response_shape_without_accessing_external_services(): void
    {
        DB::shouldReceive('selectOne')->once()->with('SELECT VERSION() AS version')
            ->andReturn((object) ['version' => 'test-server']);
        Http::preventStrayRequests();
        Http::fake(['api.frankfurter.dev/*' => Http::response([
            'date' => '2026-10-22', 'base' => 'USD', 'quote' => 'MXN', 'rate' => '18.00',
        ])]);

        $this->artisan('cuentahorro:check-hosting')
            ->expectsOutput('Conexión SQL: OK. Servidor: test-server')
            ->expectsOutput('HTTPS saliente Frankfurter: OK. Fecha del proveedor: 2026-10-22')
            ->assertSuccessful();
        Http::assertSentCount(1);
    }

    public function test_diagnostic_rejects_invalid_provider_data_and_reports_database_failure(): void
    {
        DB::shouldReceive('selectOne')->once()->andThrow(new \RuntimeException('internal-sensitive-detail'));
        Http::preventStrayRequests();
        Http::fake(['api.frankfurter.dev/*' => Http::response(['rate' => -1])]);

        $this->artisan('cuentahorro:check-hosting')
            ->expectsOutput('Conexión SQL: falló. Verifica la configuración de la base de datos.')
            ->expectsOutput('HTTPS saliente Frankfurter: falló o devolvió un formato no válido.')
            ->doesntExpectOutputToContain('internal-sensitive-detail')
            ->assertFailed();
    }
}
