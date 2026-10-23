# Autenticación web e interfaz inicial

Fase local cerrada el 23 de octubre de 2026 (America/Mexico_City). Base: Laravel 13.38.0, PHP 8.3.33, MySQL 9.7.1.

## Objetivo y cambios

Registro, acceso y cierre seguros, con dashboard protegido sin datos financieros ficticios. No incorpora cuentas, movimientos, API, recuperación ni verificación de correo.

| Archivos | Función |
|---|---|
| app/Http/Controllers/Auth/* | Registro e inicio/cierre mediante Auth y sesiones de Laravel |
| app/Http/Requests/Auth/* | Normalización, validación y limitador de intentos |
| app/Http/Middleware/PrivateResponse.php | Respuestas privadas sin caché y encabezados básicos |
| bootstrap/app.php, routes/web.php | Rutas auth/guest y redirecciones |
| lang/es/* | Mensajes de autenticación y validación en español |
| resources/views/auth/*, layouts/*, dashboard.blade.php, errors/* | Formularios, navegación, estados vacíos y mensajes 419/429 |
| public/vendor/bootstrap/*, public/css/app.css, public/js/app.js | Bootstrap con licencia, estilo adaptable, mostrar contraseña y estado de envío |
| tests/TestCase.php, tests/Feature/* | Guarda del entorno y pruebas funcionales MySQL aisladas |
| scripts/test-auth-http.php | Prueba HTTP reproducible con CSRF y cookies reales |

## Seguridad y comportamiento

- Registro: nombre máximo 100, correo válido máximo 254 y único, contraseña confirmada de al menos ocho caracteres con letras y números. Se limita el tamaño de contraseña al límite de bcrypt; se rechazan excesos de bytes, también al entrar, evitando truncamientos de bcrypt.
- Correo normalizado y restricción UNIQUE en SQL. Captura específica del conflicto de unicidad para registros simultáneos. No se aceptan propietarios o roles enviados por cliente.
- Cast `hashed` del modelo User existente; no se almacenan contraseñas en claro. Consultas de Eloquent parametrizadas y salida de Blade escapada.
- Registro inicia sesión y regenera sesión; login usa Auth::attempt y regenera sesión; logout usa POST, Auth::logout, invalidate y regenerateToken.
- Formularios @csrf y middleware web estándar de Laravel 13. Sin excepciones de CSRF. Los tests funcionales normales omiten CSRF por diseño de Laravel; el script HTTP lo comprueba con el servidor real, sin omitirlo.
- Intentos fallidos: cinco por correo/IP y veinte por IP al alternar correos, ventana de 60 segundos mediante RateLimiter y caché persistente. La clave no contiene el correo en claro. Inicio correcto borra el contador correo/IP; el límite global de IP conserva sus fallos. Bloqueo 429 con Retry-After. Correo inexistente y contraseña incorrecta usan el mismo mensaje.
- `/`, `/login`, `/registro`, `/dashboard`, POST `/logout`; visitantes redirigidos al login, autenticados redirigidos desde formularios a dashboard. No hay logout por GET.
- Cookie HttpOnly y SameSite=Lax; Secure activado en la plantilla de producción para HTTPS. Cache-Control no-store/private reduce datos privados recuperados de caché tras salir.
- Interfaz: Bootstrap compilado servido localmente, sin otro framework incorporado. Etiquetas, errores por campo, foco en resumen de errores, enlace para saltar al contenido, estado de envío, mostrar/ocultar contraseña y diseño por breakpoints. Inspección visual en navegador de escritorio; el diseño móvil está implementado por CSS, sin prueba en dispositivo físico.

## Verificación realizada

1. Migraciones de uso ejecutadas con `--env=migrations`; conexión de aplicación confirmada y Frankfurter HTTPS OK. No se repitió el aprovisionamiento ni se sobrescribieron .env.
2. `php artisan test --compact`: 20 pruebas y 161 aserciones satisfactorias. Incluye hash, validaciones/duplicados/multibyte, rotación e invalidación de sesión y CSRF, redirecciones, XSS, dos identidades, límites por correo/IP e IP y recuperación tras ventana. La rotación de CSRF se comprueba en sesión; rechazo HTTP se acredita en el siguiente punto.
3. `php scripts/test-auth-http.php`: 19 comprobaciones satisfactorias contra un servidor temporal y cuentahorro_testing/cuentahorro_test. Registro, validación, duplicate, login, logout, cookie anterior, protección de rutas, CSRF 419, throttling 429 y assets. Contraseñas sintéticas no publicadas; solo se elimina el usuario único del script.
4. Composer validate/check-platform-reqs, Pint y revisión Git de archivos/secretos completados antes del commit.

Para repetir, ejecutar `php artisan test` y después `php scripts/test-auth-http.php`, sin concurrencia sobre la base de pruebas. La guarda de TestCase actúa antes del RefreshDatabase; exige nombre de base, usuario, host y puerto de pruebas y rechaza configuración cacheada. El usuario de pruebas carece de permisos sobre cuentahorro de uso.

## Pendiente

API/Sanctum, autorización de recursos financieros, cuentas/categorías/movimientos, saldos, presupuestos, transferencias, integración Frankfurter para el usuario y despliegue gratuito público real. El aislamiento de identidades en el dashboard no acredita todavía aislamiento de recursos financieros inexistentes.

alwaysdata sigue siendo un requisito final: crear plan Free de 0 €/mes y aportar solo el subdominio público; credenciales se introducen en el sitio/terminal del propietario. Esto no bloquea la fase local.
