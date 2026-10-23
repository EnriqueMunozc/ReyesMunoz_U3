# Cuentahorro

Proyecto universitario de finanzas personales: Laravel 13, PHP 8.3, Blade y MySQL local. El despliegue público gratuito es obligatorio; el destino elegido para validar es alwaysdata Free, con MariaDB.

| Entrega | Enlace y estado |
|---|---|
| Código oficial | https://github.com/EnriqueMunozc/ReyesMunoz_U3 |
| Aplicación HTTPS pública | **Pendiente**: aún no existe cuenta ni subdominio asignado. Se añadirá aquí la URL real tras desplegar y probar. |

GitHub aloja el código. La aplicación deberá funcionar desde alwaysdata con el equipo del alumno apagado.

## Estado actual

Base de Laravel y dependencias instaladas, inicio provisional sin operaciones financieras, configuración MySQL, diagnóstico de hosting y preparación local de bases/usuarios. **Todavía no hay autenticación funcional, CRUD financiero, API ni aplicación publicada.** Los comandos de diagnóstico no sustituyen esos módulos.

- [Plan y requisitos](docs/plan.md)
- [Compatibilidad, condiciones y despliegue alwaysdata](docs/hosting-alwaysdata.md)
- [Evidencias y pendientes](docs/evidencias.md)

## Requisitos comprobados en Windows

PHP 8.3.33 con pdo_mysql, bcmath, cURL, mbstring y las extensiones de Laravel; Composer 2.8.8; Git 2.49.0; Node 22.15.0 y npm 10.9.2. MySQL Community 9.7.1 autenticado por el propietario en `127.0.0.1:3307`. SQL Server no se utiliza ni se modifica.

La página inicial no necesita Node, npm ni Vite en ejecución. Se incorporará Bootstrap al implementar la interfaz. No instalar Docker, XAMPP, otro PHP ni el instalador global de Laravel.

## Instalación local desde el repositorio

Desde una carpeta padre vacía, en PowerShell:

```powershell
git clone https://github.com/EnriqueMunozc/ReyesMunoz_U3.git cuentahorro
Set-Location cuentahorro
composer install
Copy-Item .env.example .env
php artisan key:generate
```

En la carpeta de trabajo actual esos pasos ya están realizados; **no copies otra vez .env.example sobre tu .env**.

Si PHP presenta cURL error 60, configura en el `.env` local `HTTP_CA_BUNDLE` con la ruta de un almacén CA confiable existente. En este equipo se comprobó el incluido con Git:

```dotenv
HTTP_CA_BUNDLE="C:/Program Files/Git/mingw64/etc/ssl/cert.pem"
```

No se desactiva la verificación TLS ni se modifica `C:/php/php.ini`. Esta ruta de Windows no debe trasladarse al hosting Linux.

## Crear exclusivamente los recursos locales

En la raíz del proyecto, ejecutar **en tu propia terminal**, no dentro del chat:

```powershell
php artisan cuentahorro:prepare-local
```

El comando solicita la contraseña de `root` con entrada oculta, conecta únicamente a `127.0.0.1:3307` y comprueba de nuevo los nombres. Si encuentra alguno existente se detiene; no reemplaza usuarios, bases o credenciales previas. No solicita ni guarda la contraseña de root en Git o archivos.

Creará:

| Recurso | Acceso |
|---|---|
| `cuentahorro` | Base de aplicación |
| `cuentahorro_testing` | Base exclusiva de pruebas |
| `cuentahorro_app@localhost` | SELECT, INSERT, UPDATE, DELETE solo sobre cuentahorro |
| `cuentahorro_migrator@localhost` | CRUD, CREATE, ALTER, DROP, INDEX, REFERENCES solo sobre cuentahorro |
| `cuentahorro_test@localhost` | CRUD y DDL anteriores solo sobre cuentahorro_testing |

Las contraseñas aleatorias quedan en `.env`, `.env.migrations` y `.env.testing`, ignorados por Git. Solo se versionan `.env.example` y `.env.production.example`, sin secretos. El tercer usuario impide que las pruebas tengan acceso a la base de uso. No hay GRANT global, GRANT OPTION ni privilegios sobre otras bases.

MySQL no revierte el DDL dentro de transacciones: si el comando informa un fallo parcial, conserva los archivos de credenciales y detente para revisar la etapa indicada. No borres bases ni repitas borrando las comprobaciones. No compartas archivos .env ni contraseñas.

Tras un resultado satisfactorio:

```powershell
php artisan migrate --env=migrations
php artisan cuentahorro:check-hosting
php artisan migrate:status --env=migrations
php artisan test
php artisan serve --host=127.0.0.1 --port=8000
```

El resultado esperado del diagnóstico es SQL y HTTPS saliente en OK. Abrir http://127.0.0.1:8000 muestra el inicio provisional; `/up` es la comprobación de salud de Laravel. La URL local no es el enlace público de entrega.

La configuración de pruebas fuerza base y usuario `cuentahorro_testing`/`cuentahorro_test`. No ejecutar pruebas con configuración de producción cacheada. Las pruebas actuales no migran ni escriben datos financieros.

## Hosting gratuito

[alwaysdata Free](https://www.alwaysdata.com/en/offers/) anuncia 0 €/mes sin vencimiento, con 1 GB total, 256 MB RAM y subdominio HTTPS. No confundir con la prueba temporal de planes de pago. La cuenta debe mantenerse activa entrando al panel según sus condiciones. La compatibilidad efectiva queda pendiente de medir y probar en la cuenta real.

Seguir [la guía de despliegue](docs/hosting-alwaysdata.md): PHP 8.3, raíz `public/`, MariaDB exclusiva, `APP_DEBUG=false`, `.env` creado en el servidor, dependencias sin dev y migraciones por SSH. No necesita servidor Laravel local, workers permanentes ni dominio contratado.

La API protegida y la integración visible de Frankfurter se implementarán en módulos posteriores. La guía define las pruebas remotas pendientes con Authorization, dos usuarios y falla del proveedor. No se consideran cumplidas por este scaffolding.

## Verificación y commits

```powershell
composer validate --strict
composer check-platform-reqs
php artisan test
php vendor/bin/pint --test
```

Conservar composer.lock. No versionar vendor, node_modules, bases, archivos .env reales ni tokens de Postman. Los commits documentarán avances comprobables; la tabla de requisitos y la URL pública se actualizarán con evidencia real.
