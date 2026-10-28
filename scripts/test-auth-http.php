<?php

// Prueba HTTP real: solo base y usuario de pruebas; nunca lee .env de uso.
require __DIR__.'/../vendor/autoload.php';

use App\Models\User;
use Dotenv\Dotenv;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

$root = dirname(__DIR__);
$server = null;
$pdo = null;
$email = null;
$cachePrefix = null;
$stage = 'comprobación del entorno aislado';
$passed = 0;
$check = function (bool $valid, string $label) use (&$stage, &$passed): void {
    $stage = $label;
    if (! $valid) {
        throw new RuntimeException('Comprobación fallida');
    }
    $passed++;
    echo "OK: {$label}\n";
};

try {
    if (is_file($root.'/bootstrap/cache/config.php') || ! is_file($root.'/.env.testing')) {
        throw new RuntimeException('Se requiere .env.testing y configuración sin cachear');
    }
    $env = Dotenv::parse(file_get_contents($root.'/.env.testing'));
    foreach (['DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3307',
        'DB_DATABASE' => 'cuentahorro_testing', 'DB_USERNAME' => 'cuentahorro_test'] as $key => $expected) {
        if (($env[$key] ?? null) !== $expected) {
            throw new RuntimeException('Conexión no aislada');
        }
    }
    $pdo = new PDO('mysql:host=127.0.0.1;port=3307;dbname=cuentahorro_testing;charset=utf8mb4',
        'cuentahorro_test', $env['DB_PASSWORD'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $identity = $pdo->query('SELECT DATABASE() AS db, CURRENT_USER() AS account')->fetch(PDO::FETCH_ASSOC);
    $check($identity['db'] === 'cuentahorro_testing' && str_starts_with($identity['account'], 'cuentahorro_test@'), 'base y usuario reales aislados');
    $pdo->query('SELECT 1 FROM users LIMIT 1'); // Requiere migraciones previas de la suite.

    $run = bin2hex(random_bytes(8));
    $email = 'http-'.$run.'@example.test';
    $password = 'Prueba'.bin2hex(random_bytes(12));
    $listener = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if (! $listener) {
        throw new RuntimeException('No se pudo reservar puerto local');
    }
    $address = stream_socket_get_name($listener, false);
    fclose($listener);
    $base = 'http://'.$address;
    $env = array_merge($env, [
        'APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'APP_URL' => $base, 'DB_URL' => '',
        'SESSION_DRIVER' => 'database', 'SESSION_COOKIE' => 'http-auth-'.$run,
        'SESSION_SECURE_COOKIE' => 'false', 'CACHE_STORE' => 'database', 'CACHE_PREFIX' => 'http-auth-'.$run.'-',
        'BCRYPT_ROUNDS' => '4', 'MAIL_MAILER' => 'array',
    ]);
    $cachePrefix = $env['CACHE_PREFIX'];
    $server = new Process([PHP_BINARY, '-S', $address, '-t', $root.'/public',
        $root.'/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'], $root.'/public', $env);
    $server->setTimeout(120);
    $server->start();
    $jar = new CookieJar;
    $client = new Client(['base_uri' => $base, 'cookies' => $jar, 'http_errors' => false,
        'allow_redirects' => false, 'timeout' => 10]);
    $ready = false;
    for ($attempt = 0; $attempt < 50; $attempt++) {
        try {
            $ready = $client->get('/up')->getStatusCode() === 200;
            if ($ready) {
                break;
            }
        } catch (Throwable) {
            // Solo espera por el proceso local iniciado por esta prueba.
        }
        usleep(100000);
    }
    $check($ready, 'servidor HTTP temporal listo');
    $token = function (string $path) use ($client): string {
        $response = $client->get($path);
        if ($response->getStatusCode() !== 200 || ! preg_match('/name="_token" value="([^"]+)"/', (string) $response->getBody(), $match)) {
            throw new RuntimeException('Formulario sin token');
        }

        return html_entity_decode($match[1], ENT_QUOTES);
    };
    $post = fn (string $path, array $fields) => $client->post($path, ['form_params' => $fields, 'headers' => ['Referer' => $base.$path]]);
    $status = fn (string $path) => $client->get($path)->getStatusCode();

    $check($status('/dashboard') === 302, 'dashboard rechaza visitantes');
    $check($post('/registro', ['name' => 'Sin CSRF'])->getStatusCode() === 419, 'CSRF rechaza registro sin token');
    $csrf = $token('/registro');
    $check($post('/registro', ['_token' => $csrf, 'name' => '', 'email' => 'invalido',
        'password' => 'corta', 'password_confirmation' => 'otra'])->getStatusCode() === 302, 'datos inválidos rechazados');
    $check(str_contains((string) $client->get('/registro')->getBody(), 'Introduce un correo electrónico válido.'), 'validación HTTP en español');
    $csrf = $token('/registro');
    $check($post('/registro', ['_token' => $csrf, 'name' => 'Persona HTTP', 'email' => $email,
        'password' => $password, 'password_confirmation' => $password])->getStatusCode() === 302, 'registro correcto');
    $dashboard = $client->get('/dashboard');
    $check($dashboard->getStatusCode() === 200 && str_contains((string) $dashboard->getBody(), 'Tu espacio está listo'), 'sesión autenticada y dashboard vacío');
    $check(str_contains($dashboard->getHeaderLine('Cache-Control'), 'no-store'), 'respuesta privada sin caché');
    $check(str_contains(strtolower($dashboard->getHeaderLine('Set-Cookie')), 'httponly')
        && str_contains(strtolower($dashboard->getHeaderLine('Set-Cookie')), 'samesite=lax'), 'cookie HttpOnly y SameSite');
    $check($status('/login') === 302 && $status('/registro') === 302, 'usuarios autenticados salen de formularios de acceso');
    $check($post('/logout', [])->getStatusCode() === 419 && $status('/dashboard') === 200, 'CSRF impide cerrar sesión sin token');
    $staleJar = new CookieJar(false, $jar->toArray());
    $check($post('/logout', ['_token' => $token('/dashboard')])->getStatusCode() === 302 && $status('/dashboard') === 302, 'logout bloquea acceso posterior');
    $staleClient = new Client(['base_uri' => $base, 'cookies' => $staleJar, 'http_errors' => false, 'allow_redirects' => false]);
    $check($staleClient->get('/dashboard')->getStatusCode() === 302, 'sesión anterior invalidada también al reproducir su cookie');

    $csrf = $token('/registro');
    $check($post('/registro', ['_token' => $csrf, 'name' => 'Duplicada', 'email' => strtoupper($email),
        'password' => $password, 'password_confirmation' => $password])->getStatusCode() === 302
        && str_contains((string) $client->get('/registro')->getBody(), 'ya está registrado'), 'correo duplicado rechazado');
    $csrf = $token('/login');
    $check($post('/login', ['_token' => $csrf, 'email' => $email, 'password' => 'incorrecta'])->getStatusCode() === 302
        && str_contains((string) $client->get('/login')->getBody(), 'El correo o la contraseña no son correctos.'), 'credenciales incorrectas rechazadas');
    $check($post('/login', ['_token' => $token('/login'), 'email' => $email, 'password' => $password])->getStatusCode() === 302
        && $status('/dashboard') === 200, 'inicio de sesión correcto');

    // Las rutas web y API operan sobre los mismos datos de prueba aislados.
    $today = (new DateTimeImmutable('now', new DateTimeZone('America/Mexico_City')))->format('Y-m-d');
    $accountData = ['nombre' => 'Efectivo HTTP', 'tipo' => 'efectivo', 'saldo_inicial' => '100.00', 'fecha_inicial' => $today];
    $created = $post('/cuentas', $accountData + ['_token' => $token('/cuentas/create')]);
    $check($created->getStatusCode() === 302 && preg_match('~/cuentas/(\d+)$~', $created->getHeaderLine('Location'), $matches) === 1, 'cuenta creada por formulario web');
    $accountId = (int) $matches[1];
    $created = $post('/categorias', ['nombre' => 'Comida HTTP', 'tipo' => 'gasto', '_token' => $token('/categorias/create')]);
    $check($created->getStatusCode() === 302 && preg_match('~/categorias/(\d+)$~', $created->getHeaderLine('Location'), $matches) === 1, 'categoría creada por formulario web');
    $categoryId = (int) $matches[1];
    $movementData = ['cuenta_id' => $accountId, 'categoria_id' => $categoryId, 'tipo' => 'gasto', 'importe' => '10.00', 'fecha' => $today, 'descripcion' => 'Gasto HTTP'];
    $created = $post('/movimientos', $movementData + ['_token' => $token('/movimientos/create')]);
    $check($created->getStatusCode() === 302 && preg_match('~/movimientos/(\d+)$~', $created->getHeaderLine('Location'), $matches) === 1, 'gasto creado por formulario web');
    $movementId = (int) $matches[1];
    $check(str_contains((string) $client->get('/dashboard')->getBody(), '$90.00 MXN'), 'dashboard web refleja datos financieros reales');
    $plain = new Client(['base_uri' => $base, 'http_errors' => false, 'allow_redirects' => false, 'timeout' => 10]);
    $check($plain->get('/api/cuentas')->getStatusCode() === 401, 'API exige autenticación sin depender de Accept');
    $issued = $plain->post('/api/tokens', ['json' => ['email' => $email, 'password' => $password, 'nombre_dispositivo' => 'HTTP aislado']]);
    $issuedData = json_decode((string) $issued->getBody(), true);
    $check($issued->getStatusCode() === 201 && ! empty($issuedData['data']['expires_at']), 'token Sanctum emitido con vencimiento');
    $apiToken = $issuedData['data']['token'];
    $api = new Client(['base_uri' => $base, 'http_errors' => false, 'timeout' => 10, 'headers' => ['Authorization' => 'Bearer '.$apiToken, 'Accept' => 'application/json']]);
    $json = fn ($response) => json_decode((string) $response->getBody(), true);
    // Tasas sintéticas en la caché aislada: la prueba HTTP no llama al proveedor.
    $storeRate = function (string $pair, array|bool $value, string $suffix = 'valida') use ($pdo, $cachePrefix): void {
        $statement = $pdo->prepare('REPLACE INTO cache (`key`, value, expiration) VALUES (?, ?, ?)');
        $statement->execute([$cachePrefix.'frankfurter:v2:'.$pair.':'.$suffix, serialize($value), time() + 3600]);
    };
    $snapshot = ['base' => 'USD', 'destino' => 'MXN', 'tasa' => '18.500000000000000000', 'fecha_referencia' => $today,
        'consultado_en' => gmdate('c'), 'proveedor' => 'Frankfurter'];
    $storeRate('USD:MXN', $snapshot);
    $check($plain->get('/conversor')->getStatusCode() === 302 && $plain->get('/api/tipo-cambio?base=USD&destino=MXN')->getStatusCode() === 401, 'conversor web y API exigen autenticación');
    $converted = $api->get('/api/tipo-cambio?base=USD&destino=MXN&importe=2.00');
    $check($converted->getStatusCode() === 200 && $json($converted)['data']['resultado'] === '37.00'
        && $json($converted)['data']['estado'] === 'cache', 'conversión HTTP USD/MXN con caché simulada');
    $storeRate('MXN:USD', array_replace($snapshot, ['base' => 'MXN', 'destino' => 'USD', 'tasa' => '0.055000000000000000']));
    $check($json($api->get('/api/tipo-cambio?base=MXN&destino=USD&importe=1.00'))['data']['resultado'] === '0.06', 'conversión HTTP MXN/USD con redondeo');
    $conversionPage = $client->get('/conversor?base=USD&destino=MXN&importe=2.00');
    $check($conversionPage->getStatusCode() === 200 && str_contains((string) $conversionPage->getBody(), '37.00 MXN'), 'pantalla del conversor con sesión real');
    $check($api->get('/api/tipo-cambio?base=USD&destino=USD')->getStatusCode() === 422
        && $api->get('/api/tipo-cambio?base=USD&destino=MXN&importe=-1')->getStatusCode() === 422, 'parámetros inválidos del conversor rechazados');
    $storeRate('USD:MXN', array_replace($snapshot, ['consultado_en' => gmdate('c', time() - 7200)]));
    $storeRate('USD:MXN', true, 'reintento');
    $check($json($api->get('/api/tipo-cambio?base=USD&destino=MXN'))['data']['estado'] === 'respaldo', 'respaldo HTTP permitido durante falla simulada');
    $storeRate('USD:MXN', array_replace($snapshot, ['consultado_en' => gmdate('c', time() - 8 * 86400)]));
    $check($api->get('/api/tipo-cambio?base=USD&destino=MXN')->getStatusCode() === 503
        && $client->get('/conversor')->getStatusCode() === 503, 'respaldo demasiado antiguo rechazado por web y API');
    $check($status('/dashboard') === 200 && $json($api->get('/api/cuentas/'.$accountId))['data']['saldo'] === '90.00'
        && $json($api->get('/api/movimientos'))['meta']['total'] === 1, 'convertir o fallar conserva movimientos y saldos');
    $account = $api->get('/api/cuentas/'.$accountId);
    $check($account->getStatusCode() === 200 && $json($account)['data']['saldo'] === '90.00', 'API devuelve saldo como cadena decimal');
    $check($api->put('/api/cuentas/'.$accountId, ['json' => array_replace($accountData, ['saldo_inicial' => '150.00'])])->getStatusCode() === 422, 'saldo inicial bloqueado tras operaciones');
    $check($api->post('/api/movimientos', ['json' => array_replace($movementData, ['importe' => '999.00'])])->getStatusCode() === 422, 'gasto sin saldo rechazado íntegramente');
    $check($api->delete('/api/categorias/'.$categoryId)->getStatusCode() === 422, 'categoría utilizada no eliminable');
    $incomeCategory = $api->post('/api/categorias', ['json' => ['nombre' => 'Ingreso HTTP', 'tipo' => 'ingreso']]);
    $check($incomeCategory->getStatusCode() === 201, 'categoría de ingreso creada por API');
    $incomeCategoryId = $json($incomeCategory)['data']['id'];
    $income = $api->post('/api/movimientos', ['json' => array_replace($movementData, ['tipo' => 'ingreso', 'categoria_id' => $incomeCategoryId, 'importe' => '25.00'])]);
    $check($income->getStatusCode() === 201 && $json($income)['data']['importe'] === '25.00', 'ingreso creado por API');
    $incomeId = $json($income)['data']['id'];
    $check($api->put('/api/movimientos/'.$movementId, ['json' => array_replace($movementData, ['importe' => '15.00'])])->getStatusCode() === 200, 'edición de movimiento por API');
    $filtered = $api->get('/api/movimientos?tipo=gasto&per_page=1');
    $check($filtered->getStatusCode() === 200 && $json($filtered)['meta']['total'] === 1, 'filtros y paginación reales');
    $check($api->delete('/api/cuentas/'.$accountId)->getStatusCode() === 200, 'cuenta con historial archivada');
    $summary = $json($api->get('/api/dashboard'))['data'];
    $check($summary['saldo_total'] === '0.00' && $summary['ingresos_mes'] === '25.00' && $summary['gastos_mes'] === '15.00', 'archivo excluye saldo pero conserva resúmenes mensuales');
    $check($api->post('/api/movimientos', ['json' => $movementData])->getStatusCode() === 422, 'cuenta archivada rechaza movimientos');
    $check($api->put('/api/cuentas/'.$accountId, ['json' => $accountData + ['archivada' => false]])->getStatusCode() === 200, 'cuenta reactivada sin alterar saldo inicial');

    $secondEmail = 'http-second-'.$run.'@example.test';
    $insert = $pdo->prepare('INSERT INTO users (name,email,password,created_at,updated_at) VALUES (?,?,?,NOW(),NOW())');
    $insert->execute(['Segunda persona HTTP', $secondEmail, password_hash($password, PASSWORD_BCRYPT, ['cost' => 4])]);
    $second = $json($plain->post('/api/tokens', ['json' => ['email' => $secondEmail, 'password' => $password, 'nombre_dispositivo' => 'HTTP segunda persona']]));
    $otherApi = new Client(['base_uri' => $base, 'http_errors' => false, 'headers' => ['Authorization' => 'Bearer '.$second['data']['token']]]);
    $check($otherApi->get('/api/cuentas/'.$accountId)->getStatusCode() === 404 && $otherApi->get('/api/movimientos/'.$movementId)->getStatusCode() === 404, 'segundo usuario no puede consultar recursos ajenos');
    $check($otherApi->post('/api/movimientos', ['json' => $movementData])->getStatusCode() === 422, 'segundo usuario no puede usar cuenta ni categoría ajenas');
    $check($api->delete('/api/movimientos/'.$incomeId)->getStatusCode() === 200 && $api->delete('/api/movimientos/'.$movementId)->getStatusCode() === 200, 'eliminación de movimientos recalcula saldos');
    $check($api->delete('/api/cuentas/'.$accountId)->getStatusCode() === 200 && $api->get('/api/cuentas/'.$accountId)->getStatusCode() === 404, 'cuenta sin operaciones eliminable');
    $concurrentAccount = $json($api->post('/api/cuentas', ['json' => array_replace($accountData, ['nombre' => 'Concurrencia HTTP'])]))['data']['id'];
    $ownerLookup = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $ownerLookup->execute([$email]);
    $ownerId = $ownerLookup->fetchColumn();
    $pdo->beginTransaction();
    $pdo->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE')->execute([$ownerId]);
    $workers = [];
    for ($i = 0; $i < 2; $i++) {
        $worker = new Process([PHP_BINARY, $root.'/scripts/financial-test-worker.php', (string) $ownerId, (string) $concurrentAccount, (string) $categoryId], $root);
        $worker->setTimeout(20);
        $worker->start();
        $workers[] = $worker;
    }
    usleep(250000);
    $pdo->commit();
    $outcomes = [];
    foreach ($workers as $worker) {
        $worker->wait();
        $outcomes[] = trim($worker->getOutput());
    }
    sort($outcomes);
    $check($outcomes === ['rejected', 'saved'] && $json($api->get('/api/cuentas/'.$concurrentAccount))['data']['saldo'] === '40.00', 'dos gastos concurrentes: uno aceptado, otro rechazado sin sobregiro');
    $source = $json($api->post('/api/cuentas', ['json' => array_replace($accountData, ['nombre' => 'Origen planeación'])]))['data']['id'];
    $target = $json($api->post('/api/cuentas', ['json' => array_replace($accountData, ['nombre' => 'Destino planeación', 'saldo_inicial' => '0.00'])]))['data']['id'];
    $planningCategory = $json($api->post('/api/categorias', ['json' => ['nombre' => 'Planeación HTTP', 'tipo' => 'gasto']]))['data']['id'];
    $budgetData = ['categoria_id' => $planningCategory, 'mes' => substr($today, 0, 7), 'limite' => '10.00'];
    $budgetResponse = $api->post('/api/presupuestos', ['json' => $budgetData]);
    $budgetId = $json($budgetResponse)['data']['id'];
    $check($budgetResponse->getStatusCode() === 201 && $json($budgetResponse)['data']['indicadores']['consumo'] === '0.00', 'presupuesto API creado sin consumo ficticio');
    $check($api->post('/api/presupuestos', ['json' => $budgetData])->getStatusCode() === 422, 'presupuesto duplicado rechazado por HTTP');
    $uuid = fn () => (string) Str::uuid();
    $transferData = ['origen_id' => $source, 'destino_id' => $target, 'importe' => '25.25', 'fecha' => $today, 'clave_idempotencia' => $uuid()];
    $transferResponse = $api->post('/api/transferencias', ['json' => $transferData]);
    $transferId = $json($transferResponse)['data']['id'];
    $check($transferResponse->getStatusCode() === 201 && $json($api->get('/api/cuentas/'.$source))['data']['saldo'] === '74.75'
        && $json($api->get('/api/cuentas/'.$target))['data']['saldo'] === '25.25', 'transferencia API conserva saldo total entre cuentas');
    $replay = $api->post('/api/transferencias', ['json' => $transferData]);
    $check($replay->getStatusCode() === 200 && $json($replay)['data']['id'] === $transferId, 'reenvío HTTP no duplica transferencia');
    $check($api->post('/api/transferencias', ['json' => array_replace($transferData, ['importe' => '26.00'])])->getStatusCode() === 422, 'misma clave con otro importe rechazada');
    $check($api->put('/api/transferencias/'.$transferId, ['json' => $transferData])->getStatusCode() === 405
        && $api->delete('/api/transferencias/'.$transferId)->getStatusCode() === 405, 'transferencias no admiten edición ni eliminación');
    $form = (string) $client->get('/transferencias/create')->getBody();
    preg_match('/name="clave_idempotencia" value="([^"]+)"/', $form, $keyMatch);
    $webData = array_replace($transferData, ['importe' => '1.00', 'clave_idempotencia' => $keyMatch[1], '_token' => $token('/transferencias/create')]);
    $check(str_contains($form, 'no se pueden editar ni eliminar') && $post('/transferencias', $webData)->getStatusCode() === 302
        && $post('/transferencias', $webData)->getStatusCode() === 302, 'formulario web informa inmutabilidad y reutiliza su clave');
    $webBudget = $post('/presupuestos', ['_token' => $token('/presupuestos/create'), 'categoria_id' => $planningCategory,
        'mes' => (new DateTimeImmutable($today))->modify('first day of next month')->format('Y-m'), 'limite' => '15.00']);
    $check($webBudget->getStatusCode() === 302 && $status('/presupuestos') === 200, 'presupuesto creado y listado mediante formulario web');
    $check($otherApi->get('/api/transferencias/'.$transferId)->getStatusCode() === 404
        && $otherApi->get('/api/presupuestos/'.$budgetId)->getStatusCode() === 404, 'transferencias y presupuestos ajenos responden 404');
    $check($otherApi->post('/api/transferencias', ['json' => $transferData])->getStatusCode() === 422
        && $otherApi->post('/api/presupuestos', ['json' => $budgetData])->getStatusCode() === 422, 'referencias ajenas rechazadas en transferencias y presupuestos');
    $parallel = function (array $operations) use ($pdo, $ownerId, $root): array {
        $pdo->beginTransaction();
        $pdo->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE')->execute([$ownerId]);
        $processes = [];
        foreach ($operations as [$type, $payload]) {
            $process = new Process([PHP_BINARY, $root.'/scripts/financial-operations-worker.php', (string) $ownerId, $type, base64_encode(json_encode($payload))], $root);
            $process->setTimeout(30);
            $process->start();
            $processes[] = $process;
        }
        usleep(250000);
        $pdo->commit();
        $results = [];
        foreach ($processes as $process) {
            $process->wait();
            $results[] = json_decode($process->getOutput(), true, 32, JSON_THROW_ON_ERROR);
        }

        return $results;
    };
    $simultaneous = array_replace($transferData, ['importe' => '2.00', 'clave_idempotencia' => $uuid()]);
    $results = $parallel([['transferencia', $simultaneous], ['transferencia', $simultaneous]]);
    $states = array_column($results, 'estado');
    sort($states);
    $check($states === ['creada', 'reutilizada'] && $results[0]['id'] === $results[1]['id'], 'idempotencia simultánea: un registro y un reenvío al mismo ID');
    $raceAccount = $json($api->post('/api/cuentas', ['json' => array_replace($accountData, ['nombre' => 'Carrera gasto y transferencia'])]))['data']['id'];
    $results = $parallel([
        ['transferencia', array_replace($transferData, ['origen_id' => $raceAccount, 'importe' => '60.00', 'clave_idempotencia' => $uuid()])],
        ['gasto', ['cuenta_id' => $raceAccount, 'categoria_id' => $planningCategory, 'tipo' => 'gasto', 'importe' => '60.00', 'fecha' => $today]],
    ]);
    $states = array_column($results, 'estado');
    sort($states);
    $check($states === ['creada', 'rechazada'] && $json($api->get('/api/cuentas/'.$raceAccount))['data']['saldo'] === '40.00', 'gasto y transferencia concurrentes: una aceptación sin sobregiro');
    $spentResponse = $api->post('/api/movimientos', ['json' => ['cuenta_id' => $target, 'categoria_id' => $planningCategory, 'tipo' => 'gasto', 'importe' => '20.00', 'fecha' => $today]]);
    $indicators = $json($api->get('/api/presupuestos/'.$budgetId))['data']['indicadores'];
    $check($spentResponse->getStatusCode() === 201 && bccomp($indicators['consumo'], '20.00', 2) >= 0
        && bccomp($indicators['porcentaje_utilizado'], '100.00', 2) > 0 && $indicators['disponible'] === '0.00', 'presupuesto informativo permite gasto superior al límite');
    $check($api->delete('/api/cuentas/'.$source)->getStatusCode() === 200
        && $json($api->get('/api/cuentas/'.$source))['data']['archivada'] === true
        && $api->get('/api/transferencias/'.$transferId)->getStatusCode() === 200, 'archivo conserva cuenta con transferencias y su historial');
    $check($api->delete('/api/tokens/actual')->getStatusCode() === 200 && $api->get('/api/categorias')->getStatusCode() === 401, 'token revocado no vuelve a autenticar');
    $post('/logout', ['_token' => $token('/dashboard')]);
    $csrf = $token('/login');
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $response = $post('/login', ['_token' => $csrf, 'email' => $email, 'password' => 'incorrecta']);
        if ($response->getStatusCode() !== 302) {
            throw new RuntimeException('Intento fallido inesperado');
        }
    }
    $locked = $post('/login', ['_token' => $csrf, 'email' => $email, 'password' => $password]);
    $check($locked->getStatusCode() === 429 && (int) $locked->getHeaderLine('Retry-After') > 0, 'limitación real: sexto intento HTTP 429 con Retry-After');
    $check($status('/vendor/bootstrap/bootstrap.min.css') === 200 && $status('/js/app.js') === 200, 'assets locales disponibles');
    echo "HTTP: {$passed} comprobaciones satisfactorias. Sin datos de uso modificados.\n";
} catch (Throwable) {
    fwrite(STDERR, "HTTP: fallo en {$stage}. No se muestran credenciales ni respuestas privadas.\n");
    $failed = true;
} finally {
    $server?->stop(2);
    if ($pdo?->inTransaction()) {
        $pdo->rollBack();
    }
    if ($pdo && $email) {
        // Solo registros de las identidades sintéticas de esta ejecución.
        $lookup = $pdo->prepare('SELECT id FROM users WHERE email IN (?,?)');
        $lookup->execute([$email, $secondEmail ?? $email]);
        foreach ($lookup->fetchAll(PDO::FETCH_COLUMN) as $userId) {
            $orders = $pdo->prepare('SELECT operacion_id FROM movimientos WHERE user_id = ? UNION ALL SELECT operacion_id FROM transferencias WHERE user_id = ?');
            $orders->execute([$userId, $userId]);
            $orderIds = $orders->fetchAll(PDO::FETCH_COLUMN);
            foreach (['presupuestos', 'transferencias', 'movimientos', 'cuentas', 'categorias', 'sessions'] as $table) {
                $statement = $pdo->prepare("DELETE FROM {$table} WHERE user_id = ?");
                $statement->execute([$userId]);
            }
            foreach ($orderIds as $orderId) {
                $pdo->prepare('DELETE FROM operaciones_financieras WHERE id = ?')->execute([$orderId]);
            }
            $statement = $pdo->prepare('DELETE FROM personal_access_tokens WHERE tokenable_id = ? AND tokenable_type = ?');
            $statement->execute([$userId, User::class]);
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
        }
    }
    if ($pdo && $cachePrefix) {
        $pdo->prepare('DELETE FROM cache WHERE `key` LIKE ?')->execute([$cachePrefix.'%']);
    }
}
exit(isset($failed) ? 1 : 0);
