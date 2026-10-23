<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        if (is_file(__DIR__.'/../bootstrap/cache/config.php')) {
            throw new \RuntimeException('Retira la configuración cacheada antes de probar; no se tocará ninguna base.');
        }

        $app = parent::createApplication();
        $connection = $app['config']->get('database.connections.mysql');
        if (! $app->environment('testing') || $app['config']->get('database.default') !== 'mysql'
            || $connection['database'] !== 'cuentahorro_testing' || $connection['username'] !== 'cuentahorro_test'
            || $connection['host'] !== '127.0.0.1' || (string) $connection['port'] !== '3307'
            || ! empty($connection['url'])) {
            throw new \RuntimeException('Pruebas canceladas: se exige la conexión local aislada cuentahorro_testing/cuentahorro_test.');
        }

        return $app;
    }
}
