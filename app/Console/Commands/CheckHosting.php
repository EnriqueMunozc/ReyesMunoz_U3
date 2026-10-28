<?php

namespace App\Console\Commands;

use App\Services\FrankfurterClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class CheckHosting extends Command
{
    protected $signature = 'cuentahorro:check-hosting';

    protected $description = 'Comprueba PHP, base de datos y HTTPS saliente sin mostrar secretos';

    public function handle(FrankfurterClient $client): int
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
            $this->error('Conexión SQL: falló. Verifica la configuración de la base de datos.');
        }
        try {
            $data = $client->fetch('USD', 'MXN');
            $this->info('HTTPS saliente Frankfurter: OK. Fecha del proveedor: '.$data['fecha_referencia']);
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
