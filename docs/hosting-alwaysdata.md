# Despliegue obligatorio: alwaysdata Free

La entrega requiere **dos enlaces**: [repositorio oficial](https://github.com/EnriqueMunozc/ReyesMunoz_U3) y aplicación HTTPS pública que funcione con el equipo del alumno apagado. La cuenta de hosting aún no existe; **no hay URL pública real ni pruebas remotas realizadas**. No se debe presentar el subdominio de ejemplo como evidencia.

## Verificación documental (22 de octubre de 2026)

| Punto | Evidencia oficial y decisión | Estado |
|---|---|---|
| Plan gratuito | [Oferta](https://www.alwaysdata.com/en/offers/): Free a 0 €/mes, anunciado sin caducidad. Es diferente de la prueba de 30 días de planes de pago. | Documentado |
| Recursos | [Cuotas](https://help.alwaysdata.com/en/docs/admin-billing/billing/public-cloud-prices/): 1 GB total para archivos, correo y bases; 256 MB RAM, 1/4 CPU. Medir la versión de producción y dejar margen para SQL, logs y caché. | Documentado; cuota real pendiente |
| PHP | [Configuración](https://help.alwaysdata.com/en/docs/web-hosting/languages/php/configuration/): PHP 8.3 disponible, seleccionable para CLI y sitio. [Laravel 13](https://laravel.com/framework/docs/13.x/deployment) exige PHP >=8.3. | Compatible según documentación |
| Extensiones | La configuración de ejemplo del proveedor incluye cURL, mbstring, OpenSSL, PDO/MySQL, XML, fileinfo y otras. Es un ejemplo PHP 8.2, no prueba del futuro entorno 8.3. [Extensiones](https://help.alwaysdata.com/en/docs/web-hosting/languages/php/extensions/) permite habilitar las incluidas, como bcmath. Comprobar todas con `php -m` y Composer en la cuenta. | Pendiente de cuenta |
| SQL incluido | [MariaDB](https://help.alwaysdata.com/en/docs/web-hosting/databases/mariadb/): versiones 10.11, 11.4 y 11.8, host propio y puerto 3306. Laravel tiene soporte MariaDB. Usaremos el protocolo MySQL, InnoDB, utf8mb4_unicode_ci y Schema Builder; sin SQL exclusivo de MySQL 9.7. | Documentado; migraciones remotas pendientes |
| HTTPS y dirección | [Certificados](https://help.alwaysdata.com/en/docs/web-hosting/sites/ssl-tls/lets-encrypt/): `*.alwaysdata.net` cubierto por certificado del proveedor. Dirección gratuita de la cuenta. | Documentado |
| Raíz web | [Alta de sitios](https://help.alwaysdata.com/en/docs/web-hosting/sites/add-a-site/): sitio PHP con raíz configurable. Usar `/home/USUARIO/www/cuentahorro/public`, dejando `.env`, vendor y código fuera. No mover index.php. | Documentado; configuración pendiente |
| Despliegue | [SSH en todos los planes](https://help.alwaysdata.com/en/docs/web-hosting/remote-access/ssh/) y [Composer](https://help.alwaysdata.com/en/languages/php/packages/). Artisan permite migraciones por SSH. Sin root del sistema. | Documentado |
| Frankfurter saliente | [Red](https://help.alwaysdata.com/en/docs/technical-specifications/network/): firewall saliente sin filtrado, con bloqueo temporal por abuso. HTTPS/443 permitido según esta política. | Petición desde hosting pendiente |
| API y Authorization | PHP se sirve mediante Apache/FastCGI. El `.htaccess` estándar conservado de Laravel transmite Authorization a PHP. El proveedor documenta ese encabezado en [HTTP cache](https://help.alwaysdata.com/en/sites/http-cache/). Esto no acredita aún Sanctum ni Postman. | Prueba HTTP remota pendiente |
| Permanencia e inactividad | [Suspensión](https://help.alwaysdata.com/en/docs/admin-billing/profile/suspension/): entrar al panel cada 120 días durante el primer año; cada 6 meses entre 1 y 4 años; después, anualmente. El tráfico web no sustituye este acceso. | Documentado |
| Restricciones | Uso sin fines de lucro, sitios solo en la dirección alwaysdata.net asignada, sin servicios persistentes en Free. Sin dominio propio contratado. Usaremos PHP/Apache, colas sync y caché/ sesiones de archivo, sin workers permanentes. | Compatible con alcance académico |

**Conclusión:** candidato gratuito adecuado según las capacidades documentadas. La compatibilidad efectiva y aceptación del despliegue quedan sujetas a las pruebas de la cuenta. El plan anunciado sin caducidad no garantiza que las condiciones del proveedor nunca cambien.

## Preparación de la cuenta por el propietario

1. Crear una cuenta desde la oferta **Free**, confirmando 0 €/mes. No seleccionar un plan de pago con prueba de 30 días. No se requiere contratar dominio.
2. Elegir PHP 8.3 en Environment > PHP y usar la misma versión para el sitio. Mantener las credenciales en el panel y en el gestor local; no enviarlas al chat.
3. Crear la base y usuario exclusivos desde Databases > MySQL. Los nombres estarán prefijados por la cuenta; no usar root ni la base MySQL de Windows. El panel documenta ALL/READONLY/ninguno: limitar ALL a la base exclusiva si no permite permisos CRUD más finos, sin afirmar que existe separación DDL en hosting hasta comprobarla.
4. Habilitar SSH, registrar una clave pública y verificar la huella contra el panel. Compartir únicamente el nombre de cuenta/subdominio público para continuar.

## Despliegue inicial por SSH (cuando exista la cuenta)

Los siguientes comandos son para el shell Linux del hosting. Sustituir USUARIO y no introducir contraseñas en argumentos. El repositorio debe contener primero el código publicado.

```sh
mkdir -p ~/www
cd ~/www
git clone https://github.com/EnriqueMunozc/ReyesMunoz_U3.git cuentahorro
cd cuentahorro
php -v
php -m
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
composer check-platform-reqs --no-dev
du -sh . vendor
cp .env.production.example .env
chmod 600 .env
```

Editar `.env` **en el hosting** con el host SQL, base, usuario y contraseña reales y la URL asignada. Generar `APP_KEY` solo en la primera instalación; conservarla en posteriores despliegues.

```sh
php artisan key:generate --force
php artisan cuentahorro:check-hosting
php artisan migrate --force
php artisan optimize
```

No ejecutar migraciones si el diagnóstico SQL falla. Medir la cuota total en el panel: `du` no incluye la base ni correo. Si Composer excede memoria, preparar vendor en un entorno compatible con PHP 8.3/Linux y subir el artefacto sin dependencias dev, caché de Composer ni node_modules. No eludir comprobaciones de plataforma. Por ahora la base no necesita compilación frontend; cuando se incorpore Bootstrap/Vite, compilar localmente y subir `public/build`.

Crear/editar sitio tipo PHP: raíz `www/cuentahorro/public`, dirección de la cuenta, HTTPS forzado. No activar caché HTTP compartida para páginas privadas/API. Mantener escribibles storage y bootstrap/cache sin usar permisos 777.

## Aceptación remota obligatoria

### Requisitos añadidos por autenticación

La fase financiera añade Sanctum 4.3.3 y una migración de cuentas/categorías/movimientos/tokens. Desplegar composer.lock y ejecutar `composer install --no-dev` y `php artisan migrate --force` con la credencial de migraciones del hosting. No subir .env.migrations/.env.testing locales ni usar helpers de pruebas en producción. Probar claves foráneas compuestas y bloqueos con MariaDB real: la verificación local fue MySQL 9.7.1. La API utiliza Bearer; comprobar la transmisión Authorization del .htaccess mediante Postman una vez publicada.

Bootstrap 5.3.8 y el CSS/JS propio se sirven desde `public/`; incluirlos en el despliegue. No se requiere CDN ni proceso Vite/Node permanente. El limitador utiliza el cache store de Laravel: mantener `CACHE_STORE=file` persistente y escribible y no usar `array` en producción. Las sesiones usan `SESSION_DRIVER=file`; conservar storage escribible y APP_KEY entre despliegues. Mantener `SESSION_SECURE_COOKIE=true` con HTTPS. No activar caché HTTP compartida sobre formularios, dashboard ni logout; las respuestas web se marcan no-store/private. No se ha incorporado envío de correo.

La autenticación y CSRF están verificados localmente. **Continúan pendientes todas las comprobaciones de la cuenta real**, incluida persistencia de sesión/limitador y la interpretación de HTTPS detrás del proxy del proveedor. No configurar confianza en proxies arbitrarios; comprobar los encabezados de la infraestructura antes de ajustarla.

### Checklist de publicación

- Abrir `/` y `/up` con HTTPS desde otra red/equipo. Comprobar PHP web y CLI con la misma versión; no publicar phpinfo.
- Registrar dos usuarios, iniciar/cerrar sesión y comprobar persistencia de datos y aislamiento.
- Aplicar todas las migraciones en MariaDB y probar saldos, edición/eliminación, concurrencia, transferencias y presupuestos.
- Ejecutar `cuentahorro:check-hosting` y comprobar el servicio Frankfurter desde el backend del sitio, incluyendo caché y falla simulada. El comando es diagnóstico, no la integración de usuario.
- Postman: token real obtenido localmente, GET `/api/cuentas` con Bearer válido -> 200; ausente/revocado -> 401; cuenta ajena -> 404. No guardar tokens en la colección exportada. Verificar POST/PUT/DELETE y JSON de errores.
- Verificar que `/.env`, `/composer.json` y `/vendor/autoload.php` no sean accesibles. `APP_DEBUG=false` y cookies Secure/HttpOnly.
- Registrar uso total inferior a 1 GB, margen de memoria/CPU y logs acotados. El tamaño de la base inicial no acredita el tamaño final.
- Apagar el equipo del alumno y repetir navegación y API públicas.
- Sustituir en README y evidencias el estado pendiente por la URL HTTPS real y la fecha de cada prueba. Ningún requisito se considera cumplido solo por tener configuración o código.
