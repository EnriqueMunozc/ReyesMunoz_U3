<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class CheckHosting extends Command
{
    protected $signature = 'cuentahorro:check-hosting';

    protected $description = 'Comprueba PHP, base de datos y HTTPS saliente sin mostrar secretos';

    public function handle(): int
    {
        $failed = PHP_VERSION_ID < 80300;
        $this->line('PHP: '.PHP_VERSION);
        foreach (['ctype', 'curl', 'dom', 'fileinfo', 'filter', 'hash', 'mbstring', 'openssl', 'pcre', 'PDO', 'session', 'tokenizer', 'xml', 'pdo_mysql', 'bcmath'] as $extension) {
            $loaded = extension_loaded($extension);
            $failed = $failed || ! $loaded;
            $this->line($extension.': '.($loaded ? 'OK' : 'FALTA'));
        }
        foreach ([storage_path(), base_path('bootstrap/cache')] as $directory) {
            $writable = is_writable($directory);
            $failed = $failed || ! $writable;
            $this->line(basename($directory).' escribible: '.($writable ? 'OK' : 'NO'));
        }
        try {
            $version = DB::selectOne('SELECT VERSION() AS version')->version;
            $this->info('Conexión SQL: OK. Servidor: '.$version);
        } catch (Throwable) {
            $failed = true;
            $this->error('Conexión SQL: falló. Revisa la configuración localmente, sin compartir .env.');
        }
        try {
            $response = Http::withOptions(['verify' => config('services.frankfurter.ca_bundle')])
                ->connectTimeout(3)->timeout(8)->acceptJson()
                ->get(rtrim(config('services.frankfurter.url'), '/').'/v2/rate/USD/MXN')->throw();
            $data = $response->json();
            if (! is_array($data) || ($data['base'] ?? null) !== 'USD' || ($data['quote'] ?? null) !== 'MXN'
                || ! is_numeric($data['rate'] ?? null) || $data['rate'] <= 0
                || ! is_string($data['date'] ?? null)
                || ! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $data['date'])) {
                throw new \RuntimeException('Respuesta no válida');
            }
            $this->info('HTTPS saliente Frankfurter: OK. Fecha del proveedor: '.$data['date']);
        } catch (Throwable) {
            $failed = true;
            $this->error('HTTPS saliente Frankfurter: falló o devolvió un formato no válido.');
        }
        if ($this->laravel->environment('production') && (config('app.debug') || ! str_starts_with(config('app.url'), 'https://'))) {
            $failed = true;
            $this->error('Producción exige APP_DEBUG=false y APP_URL con HTTPS.');
        }
        $this->comment('Este diagnóstico CLI no verifica la configuración PHP web, la cuota, Authorization ni el funcionamiento completo de la aplicación.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
