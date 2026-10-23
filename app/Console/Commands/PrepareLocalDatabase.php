<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PDO;
use Throwable;

class PrepareLocalDatabase extends Command
{
    protected $signature = 'cuentahorro:prepare-local';

    protected $description = 'Crea exclusivamente las bases y usuarios locales de Cuentahorro';

    public function handle(): int
    {
        if (! $this->laravel->environment('local') || $this->laravel->configurationIsCached()) {
            $this->error('Ejecuta este comando únicamente en local y sin configuración cacheada.');

            return self::FAILURE;
        }

        foreach (['.env.migrations', '.env.testing'] as $file) {
            if (file_exists(base_path($file))) {
                $this->error("Ya existe {$file}. No se reemplazarán credenciales; revisa la preparación anterior.");

                return self::FAILURE;
            }
        }

        if (config('database.connections.mysql.password') !== '' && config('database.connections.mysql.password') !== null) {
            $this->error('El entorno ya contiene una contraseña de base de datos. No se reemplazará.');

            return self::FAILURE;
        }

        $stage = 'autenticación';

        try {
            $password = $this->secret('Contraseña local de root (no se muestra ni se guarda)');
            $admin = new PDO('mysql:host=127.0.0.1;port=3307;charset=utf8mb4', 'root', $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 5,
            ]);
            unset($password);

            $stage = 'comprobación de nombres';
            $databases = $admin->query("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME IN ('cuentahorro', 'cuentahorro_testing')")->fetchAll();
            $users = $admin->query("SELECT User FROM mysql.user WHERE User IN ('cuentahorro_app', 'cuentahorro_migrator', 'cuentahorro_test')")->fetchAll();

            if ($databases !== [] || $users !== []) {
                $this->error('Ya existe alguno de los recursos reservados. No se ha modificado ninguno.');

                return self::FAILURE;
            }

            // Credenciales aleatorias sin caracteres que requieran escape en dotenv.
            $credentials = [
                'cuentahorro_app' => bin2hex(random_bytes(32)),
                'cuentahorro_migrator' => bin2hex(random_bytes(32)),
                'cuentahorro_test' => bin2hex(random_bytes(32)),
            ];
            $template = file_get_contents(base_path('.env.example'));
            $ca = config('services.frankfurter.ca_bundle');
            if (is_string($ca)) {
                $template .= "\nHTTP_CA_BUNDLE=".json_encode(str_replace('\\', '/', $ca), JSON_UNESCAPED_SLASHES)."\n";
            }
            $key = config('app.key') ?: 'base64:'.base64_encode(random_bytes(32));
            $environment = function (string $database, string $user, string $env = 'local') use ($template, $credentials, $key): string {
                $content = $template;
                foreach (['APP_ENV' => $env, 'APP_KEY' => $key, 'DB_DATABASE' => $database, 'DB_USERNAME' => $user, 'DB_PASSWORD' => $credentials[$user]] as $name => $value) {
                    $content = preg_replace('/^'.preg_quote($name, '/').'=.*$/m', $name.'='.$value, $content);
                }

                return $content;
            };

            // Se conservan antes del DDL: MySQL no revierte CREATE DATABASE/USER.
            // Si una etapa posterior falla, no se pierden las credenciales generadas.
            $stage = 'escritura de archivos locales';
            $files = [
                '.env.migrations' => $environment('cuentahorro', 'cuentahorro_migrator'),
                '.env.testing' => $environment('cuentahorro_testing', 'cuentahorro_test', 'testing'),
            ];
            foreach ($files as $name => $content) {
                $handle = fopen(base_path($name), 'x');
                if ($handle === false) {
                    throw new \RuntimeException('No se pudo reservar el archivo local.');
                }
                try {
                    if (fwrite($handle, $content) !== strlen($content)) {
                        throw new \RuntimeException('Escritura incompleta.');
                    }
                } finally {
                    fclose($handle);
                }
                @chmod(base_path($name), 0600);
            }
            // .env ya es local y no contiene contraseña según la comprobación inicial.
            if (file_put_contents(base_path('.env'), $environment('cuentahorro', 'cuentahorro_app'), LOCK_EX) === false) {
                throw new \RuntimeException('No se pudo escribir .env.');
            }
            @chmod(base_path('.env'), 0600);

            $stage = 'creación de recursos exclusivos';
            $admin->exec('CREATE DATABASE `cuentahorro` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $admin->exec('CREATE DATABASE `cuentahorro_testing` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

            // Los GRANT de MySQL interpretan _ como comodín si partial_revokes está desactivado.
            $partialRevokes = (bool) $admin->query('SELECT @@partial_revokes')->fetchColumn();
            $testDatabase = $partialRevokes ? 'cuentahorro_testing' : 'cuentahorro\\_testing';
            foreach ($credentials as $user => $secret) {
                $admin->exec("CREATE USER '{$user}'@'localhost' IDENTIFIED BY ".$admin->quote($secret));
            }
            $admin->exec("GRANT SELECT, INSERT, UPDATE, DELETE ON `cuentahorro`.* TO 'cuentahorro_app'@'localhost'");
            $admin->exec("GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES ON `cuentahorro`.* TO 'cuentahorro_migrator'@'localhost'");
            $admin->exec("GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES ON `{$testDatabase}`.* TO 'cuentahorro_test'@'localhost'");

            $stage = 'verificación de las tres credenciales';
            foreach ($credentials as $user => $secret) {
                $database = $user === 'cuentahorro_test' ? 'cuentahorro_testing' : 'cuentahorro';
                $connection = new PDO("mysql:host=127.0.0.1;port=3307;dbname={$database};charset=utf8mb4", $user, $secret);
                $connection->query('SELECT 1')->fetchColumn();
            }
            $this->info('Bases y tres usuarios creados. Credenciales comprobadas y guardadas solo en archivos .env locales.');
            $this->line('Siguiente: php artisan migrate --env=migrations');

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error("No se completó la etapa: {$stage}. No compartas contraseñas ni archivos .env.");
            $this->warn('Si comenzó la creación, puede haber recursos parciales. Se conservan credenciales locales; no se borra ni revierte ninguna base.');

            return self::FAILURE;
        }
    }
}
