<?php

// Worker CLI exclusivamente para comprobar concurrencia en la base aislada.
require __DIR__.'/../vendor/autoload.php';

use App\Models\User;
use App\Services\FinanceService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

try {
    if (is_file(__DIR__.'/../bootstrap/cache/config.php') || count($argv) !== 4) {
        throw new RuntimeException;
    }
    putenv('APP_ENV=testing');
    $_ENV['APP_ENV'] = 'testing';
    $app = require __DIR__.'/../bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $config = config('database.connections.mysql');
    if (! app()->environment('testing') || config('database.default') !== 'mysql'
        || $config['database'] !== 'cuentahorro_testing' || $config['username'] !== 'cuentahorro_test'
        || $config['host'] !== '127.0.0.1' || (string) $config['port'] !== '3307' || ! empty($config['url'])) {
        throw new RuntimeException;
    }
    foreach (array_slice($argv, 1) as $argument) {
        if (! ctype_digit($argument)) {
            throw new RuntimeException;
        }
    }
    $service = app(FinanceService::class);
    $service->save(User::findOrFail($argv[1]), 'movimientos', [
        'cuenta_id' => $argv[2], 'categoria_id' => $argv[3], 'tipo' => 'gasto',
        'importe' => '60.00', 'fecha' => $service->today(), 'descripcion' => 'Prueba concurrente aislada',
    ]);
    echo 'saved';
} catch (ValidationException) {
    echo 'rejected';
} catch (Throwable) {
    echo 'error';
    exit(1);
}
