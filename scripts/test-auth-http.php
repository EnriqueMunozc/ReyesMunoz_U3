<?php

// Prueba HTTP real: solo base y usuario de pruebas; nunca lee .env de uso.
require __DIR__.'/../vendor/autoload.php';

use Dotenv\Dotenv;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Symfony\Component\Process\Process;

$root = dirname(__DIR__);
$server = null;
$pdo = null;
$email = null;
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
    if ($pdo && $email) {
        $statement = $pdo->prepare('DELETE FROM users WHERE email = ?');
        $statement->execute([$email]);
    }
}
exit(isset($failed) ? 1 : 0);
