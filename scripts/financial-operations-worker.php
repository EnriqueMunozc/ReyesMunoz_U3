<?php

// Operaciones concurrentes sobre identidades sintéticas y la base de pruebas.
require __DIR__.'/../vendor/autoload.php';

use App\Models\User;
use App\Services\FinanceService;
use App\Services\TransferService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

try {
    if (is_file(__DIR__.'/../bootstrap/cache/config.php') || count($argv) !== 4 || ! ctype_digit($argv[1])) {
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
    $user = User::findOrFail($argv[1]);
    $input = json_decode(base64_decode($argv[3], true), true, 32, JSON_THROW_ON_ERROR);
    if ($argv[2] === 'transferencia') {
        $result = app(TransferService::class)->create($user, $input);
        echo json_encode(['estado' => $result['creada'] ? 'creada' : 'reutilizada', 'id' => $result['transferencia']->id]);
    } elseif ($argv[2] === 'gasto') {
        $result = app(FinanceService::class)->save($user, 'movimientos', $input);
        echo json_encode(['estado' => 'creada', 'id' => $result->id]);
    } else {
        throw new RuntimeException;
    }
} catch (ValidationException) {
    echo json_encode(['estado' => 'rechazada']);
} catch (Throwable) {
    echo json_encode(['estado' => 'error']);
    exit(1);
}
