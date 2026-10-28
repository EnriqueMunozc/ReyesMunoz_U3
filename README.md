# Cuentahorro

Proyecto universitario de finanzas personales: Laravel 13, PHP 8.3, Blade y MySQL local. El despliegue público gratuito es obligatorio; el destino elegido para validar es alwaysdata Free, con MariaDB.

| Entrega | Enlace y estado |
|---|---|
| Código oficial | https://github.com/EnriqueMunozc/ReyesMunoz_U3 |
| Aplicación HTTPS pública | **Pendiente**: aún no existe cuenta ni subdominio asignado. Se añadirá aquí la URL real tras desplegar y probar. |

GitHub aloja el código. La publicación en alwaysdata permitirá utilizar la aplicación sin depender de un servidor local encendido.

## Estado actual

Registro, inicio y cierre de sesión; cuentas y categorías propias; ingresos/gastos con filtros y paginación; transferencias internas inmutables e idempotentes; presupuestos mensuales informativos; dashboard con datos reales; conversor USD/MXN con Frankfurter, caché y respaldo limitado; API REST protegida con Sanctum y tokens revocables de ocho horas. Blade y Bootstrap local. **Pendiente: aplicación pública en alwaysdata y su verificación remota.**

- [Plan y requisitos](docs/plan.md)
- [Compatibilidad, condiciones y despliegue alwaysdata](docs/hosting-alwaysdata.md)
- [Evidencias y pendientes](docs/evidencias.md)
- [Autenticación: comportamiento y pruebas](docs/autenticacion.md)
- [Finanzas, endpoints, reglas y Postman](docs/finanzas-api.md)
- [Conversor, caché y API de tipo de cambio](docs/conversor.md)
- [Transferencias, presupuestos y orden financiero](docs/transferencias-presupuestos.md)

## Requisitos comprobados en Windows

Entorno de referencia: PHP 8.3.33 con pdo_mysql, bcmath, cURL, mbstring y las extensiones de Laravel; Composer 2.8.8; Git 2.49.0; Node 22.15.0 y npm 10.9.2; MySQL Community 9.7.1 en `127.0.0.1:3307`. La aplicación no utiliza SQL Server.

La interfaz utiliza Bootstrap 5.3.8 compilado, versionado en `public/vendor/bootstrap`, con licencia MIT y hashes oficiales comprobados. No necesita CDN, Node, npm ni Vite en ejecución. No instalar Docker, XAMPP, otro PHP ni el instalador global de Laravel.

## Instalación local desde el repositorio

Desde una carpeta padre vacía, en PowerShell:

```powershell
git clone https://github.com/EnriqueMunozc/ReyesMunoz_U3.git cuentahorro
Set-Location cuentahorro
composer install
Copy-Item .env.example .env
php artisan key:generate
```

En instalaciones existentes, conservar el archivo `.env` y las credenciales. La configuración inicial no debe sobrescribirse ni repetirse sobre recursos ya creados.

Si PHP presenta cURL error 60, configurar `HTTP_CA_BUNDLE` en el `.env` local con la ruta de un almacén CA confiable. En Git para Windows, comprobar la existencia del siguiente archivo antes de utilizarlo:

```dotenv
HTTP_CA_BUNDLE="C:/Program Files/Git/mingw64/etc/ssl/cert.pem"
```

No se desactiva la verificación TLS ni se modifica `C:/php/php.ini`. Esta ruta de Windows no debe trasladarse al hosting Linux.

## Crear exclusivamente los recursos locales (solo primera instalación)

Desde la raíz del proyecto, ejecutar en una terminal local:

```powershell
php artisan cuentahorro:prepare-local
```

El comando solicita la contraseña de `root` con entrada oculta, conecta únicamente a `127.0.0.1:3307` y comprueba de nuevo los nombres. Si encuentra alguno existente se detiene; no reemplaza usuarios, bases o credenciales previas. No solicita ni guarda la contraseña de root en Git o archivos.

El comando crea los siguientes recursos en una instalación nueva:

| Recurso | Acceso |
|---|---|
| `cuentahorro` | Base de aplicación |
| `cuentahorro_testing` | Base exclusiva de pruebas |
| `cuentahorro_app@localhost` | SELECT, INSERT, UPDATE, DELETE solo sobre cuentahorro |
| `cuentahorro_migrator@localhost` | CRUD, CREATE, ALTER, DROP, INDEX, REFERENCES solo sobre cuentahorro |
| `cuentahorro_test@localhost` | CRUD y DDL anteriores solo sobre cuentahorro_testing |

Las contraseñas aleatorias quedan en `.env`, `.env.migrations` y `.env.testing`, ignorados por Git. Solo se versionan `.env.example` y `.env.production.example`, sin secretos. El tercer usuario impide que las pruebas tengan acceso a la base de uso. No hay GRANT global, GRANT OPTION ni privilegios sobre otras bases.

MySQL no revierte el DDL dentro de transacciones. Ante un fallo parcial, conservar los archivos de credenciales y revisar la operación indicada antes de continuar. No eliminar bases ni omitir las comprobaciones del comando. Los archivos `.env` y sus credenciales deben permanecer privados y fuera del control de versiones.

Tras un resultado satisfactorio:

```powershell
php artisan migrate --env=migrations
php artisan cuentahorro:check-hosting
php artisan migrate:status --env=migrations
php artisan test
php artisan serve --host=127.0.0.1 --port=8000
```

El resultado esperado del diagnóstico es SQL y HTTPS saliente en OK. Abrir http://127.0.0.1:8000 lleva a iniciar sesión; `/up` es la comprobación de salud de Laravel. La URL local no es el enlace público de entrega.

La configuración de pruebas fuerza base y usuario `cuentahorro_testing`/`cuentahorro_test`; una guarda verifica el destino antes de iniciar las pruebas. La suite usa `RefreshDatabase`: recrea **exclusivamente la base de pruebas** y revierte los usuarios de cada prueba. No ejecutar contra una base con datos que quieras conservar ni con configuración cacheada.

## Abrir y probar la autenticación

Con la base ya preparada, desde la raíz del proyecto:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

1. Abrir http://127.0.0.1:8000/registro y registrar nombre, correo y contraseña confirmada de al menos ocho caracteres con letras y números. El correo se normaliza a minúsculas y no se permiten duplicados.
2. Se inicia sesión automáticamente y se abre `/dashboard`. Crear una cuenta con saldo/fecha inicial, categorías de ingreso/gasto y movimientos. Los indicadores usan datos reales; sin cuentas se muestra el estado vacío.
3. Usar **Cerrar sesión**. Volver a `/dashboard` debe redirigir a `/login`.
4. Iniciar sesión con el correo y contraseña creados. Si se introduce un correo inexistente o contraseña incorrecta se muestra el mismo mensaje genérico.
5. Cinco fallos por correo/IP bloquean nuevos intentos durante una ventana de 60 segundos, aunque el siguiente sea correcto. También hay un límite de 20 fallos por IP al alternar correos. La respuesta de bloqueo es HTTP 429 y contiene `Retry-After`; no es un bloqueo permanente de la cuenta.

Las contraseñas se guardan mediante el cast `hashed` de Laravel (bcrypt); los formularios llevan CSRF, las sesiones se regeneran al registrar/iniciar y se invalidan al salir. Las páginas privadas usan `Cache-Control: no-store, private`. La aplicación no incluye recuperación ni verificación de correo.

## Pruebas aisladas y HTTP real

```powershell
php artisan test
php scripts/test-auth-http.php
```

Ejecutar los comandos en ese orden, sin otras pruebas simultáneas. La suite prepara `cuentahorro_testing`. El script HTTP comprueba base/usuario, usa un servidor temporal en loopback y crea identidades sintéticas; solo elimina sus propios registros al terminar. No utiliza `.env` de uso ni imprime tokens, cookies o contraseñas. Incluye dos procesos concurrentes usando el servicio financiero y cierra sus servidores automáticamente.

Última verificación: **65 pruebas, 661 aserciones y 65 comprobaciones HTTP/integración satisfactorias**. Incluyen autenticación, saldos actuales/históricos, aislamiento de propietarios, tokens, CSRF, sesión invalidada y concurrencia sin sobregiro.

## Actualizar una instalación existente

```powershell
composer install
php artisan migrate --env=migrations
php artisan serve --host=127.0.0.1 --port=8000
```

Conservar los `.env` existentes y utilizar la credencial de migraciones para actualizar el esquema. `prepare-local` se utiliza únicamente en la instalación inicial. Para Postman, importar los dos archivos de `docs/postman`, completar las variables sensibles solo localmente y seguir [la guía](docs/finanzas-api.md). La colección incluye 57 ejemplos sin tokens reales.

## Hosting gratuito

[alwaysdata Free](https://www.alwaysdata.com/en/offers/) anuncia 0 €/mes sin vencimiento, con 1 GB total, 256 MB RAM y subdominio HTTPS. No confundir con la prueba temporal de planes de pago. La cuenta debe mantenerse activa entrando al panel según sus condiciones. La compatibilidad efectiva queda pendiente de medir y probar en la cuenta real.

Seguir [la guía de despliegue](docs/hosting-alwaysdata.md): PHP 8.3, raíz `public/`, MariaDB exclusiva, `APP_DEBUG=false`, `.env` creado en el servidor, dependencias sin dev y migraciones por SSH. No necesita servidor Laravel local, workers permanentes ni dominio contratado.

La API financiera y el conversor están implementados y probados localmente. Frankfurter se consulta desde el backend con TLS, timeouts, caché y respaldo con antigüedad limitada. La guía mantiene las pruebas remotas con Authorization, dos usuarios y fallas del proveedor; todavía no hay hosting real acreditado.

## Probar el conversor

Con sesión iniciada, abrir **Conversor** en la navegación o `/conversor`. Seleccionar USD/MXN o MXN/USD e introducir un importe positivo de hasta `999999999.99`. Dejar el importe vacío consulta solo la tasa. Se muestran el resultado, tasa, proveedor, fecha de referencia, momento de consulta UTC y estado de caché. No se registran movimientos ni se modifican saldos.

La API ofrece `GET /api/tipo-cambio?base=USD&destino=MXN&importe=100.00` con Bearer Sanctum. La carpeta 6 de Postman contiene ejemplos; la carpeta 8 cierra la demostración y revoca el token. Configuración, redondeo, respuestas 503 y límites de antigüedad: [guía del conversor](docs/conversor.md). El conversor no requiere migraciones ni dependencias adicionales. Los valores predeterminados funcionan con los `.env` existentes; las opciones configurables aparecen en las plantillas sin credenciales.

## Probar transferencias y presupuestos

Aplicar `php artisan migrate --env=migrations` con la aplicación sin escrituras concurrentes; no repetir la preparación de bases. La migración conserva movimientos y añade su orden financiero compartido con las transferencias. No se requieren dependencias nuevas.

Desde la navegación, abrir `/transferencias`, crear dos cuentas activas y registrar un importe con saldo suficiente. El formulario informa antes de confirmar que el registro no se podrá editar ni eliminar; reenviar la misma clave y datos devuelve el mismo registro. `/presupuestos` permite fijar un límite por categoría de gasto y mes. Su consumo incluye gastos de cuentas archivadas y puede superar el 100 % sin bloquear gastos con fondos.

La API ofrece GET/POST `/api/transferencias`, GET `/api/transferencias/{id}` y CRUD `/api/presupuestos`, con Bearer Sanctum. La carpeta 7 de Postman incluye ejemplos. Reglas, idempotencia, parámetros y estados HTTP: [guía de transferencias y presupuestos](docs/transferencias-presupuestos.md).

## Verificación

```powershell
composer validate --strict
composer check-platform-reqs
php artisan test
php vendor/bin/pint --test
```

Conservar `composer.lock` para reproducir las dependencias. Excluir del control de versiones `vendor`, `node_modules`, bases de datos, archivos `.env` con credenciales y tokens de Postman. El estado de los requisitos y la URL pública se documentan con pruebas verificables.
