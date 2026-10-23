<?php

use App\Http\Middleware\PrivateResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
        $middleware->web(append: [PrivateResponse::class]);
        $middleware->api(append: [PrivateResponse::class]);
        $middleware->alias(['abilities' => CheckAbilities::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn ($request, $e) => $request->is('api/*') || $request->expectsJson());
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }
            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
            if ($e instanceof AuthenticationException) {
                $status = 401;
            }
            if ($e instanceof ValidationException) {
                $status = 422;
            }
            $messages = [401 => 'No autenticado o credenciales incorrectas.', 403 => 'Acción no autorizada.', 404 => 'Recurso no encontrado.', 405 => 'Método no permitido.', 422 => 'Revisa los datos enviados.', 429 => 'Demasiados intentos. Espera antes de reintentar.'];
            $body = ['message' => $messages[$status] ?? 'No fue posible procesar la solicitud.'];
            if ($e instanceof ValidationException) {
                $body['errors'] = $e->errors();
            }
            $headers = $e instanceof HttpExceptionInterface ? $e->getHeaders() : [];

            return response()->json($body, $status, $headers)->header('Cache-Control', 'no-store, private');
        });
    })->create();
