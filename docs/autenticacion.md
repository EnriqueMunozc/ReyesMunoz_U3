# Autenticación web

Cuentahorro utiliza las sesiones y los mecanismos de autenticación de Laravel 13.38.0 sobre PHP 8.3.33 y MySQL 9.7.1. La interfaz está construida con Blade y Bootstrap local.

## Registro y acceso

El registro requiere nombre, correo electrónico único y contraseña confirmada de al menos ocho caracteres con letras y números. El correo se normaliza a minúsculas. La restricción UNIQUE también impide registros simultáneos con el mismo correo.

El modelo User aplica el cast `hashed` de Laravel para almacenar contraseñas con bcrypt. La longitud se limita a 72 bytes para evitar truncamientos. Las contraseñas no se guardan en claro ni se recuperan como valores anteriores del formulario.

Al registrarse o iniciar sesión se regenera la sesión. El cierre de sesión utiliza POST, invalida la sesión y regenera el token CSRF. Los formularios incluyen protección CSRF; no existe cierre de sesión por GET.

| Ruta | Acceso y comportamiento |
|---|---|
| / | Redirige al dashboard o al inicio de sesión |
| /registro | Formulario de registro para visitantes |
| /login | Formulario de acceso para visitantes |
| /dashboard | Requiere sesión autenticada |
| POST /logout | Cierra la sesión autenticada |

Los usuarios autenticados que abren los formularios de acceso se redirigen al dashboard. El mensaje de credenciales incorrectas es el mismo para un correo inexistente y una contraseña incorrecta.

## Protección de sesiones e intentos

- Cinco intentos fallidos por correo/IP o veinte por IP al alternar correos bloquean nuevos intentos dentro de una ventana de 60 segundos.
- El bloqueo devuelve HTTP 429 con `Retry-After`. El inicio correcto limpia el contador por correo/IP; el contador global de IP conserva sus fallos.
- La cookie utiliza HttpOnly y SameSite=Lax. La configuración de producción activa Secure sobre HTTPS.
- Las respuestas privadas utilizan `Cache-Control: no-store, private`.
- Blade escapa el contenido de los usuarios y Eloquent utiliza consultas parametrizadas.
- La aplicación no incluye recuperación ni verificación de correo electrónico.

## Organización del código

| Archivos | Responsabilidad |
|---|---|
| app/Http/Controllers/Auth/* | Registro, acceso y cierre de sesión |
| app/Http/Requests/Auth/* | Normalización, validación y limitación de intentos |
| app/Http/Middleware/PrivateResponse.php | Encabezados de las respuestas privadas |
| bootstrap/app.php y routes/web.php | Middleware y rutas auth/guest |
| lang/es/* | Mensajes en español |
| resources/views/auth/* y layouts/* | Formularios y navegación |
| public/vendor/bootstrap/*, public/css/app.css y public/js/app.js | Estilos locales, accesibilidad y estados de envío |
| tests/Feature/AuthenticationTest.php | Pruebas funcionales de autenticación |

## Verificación

```powershell
php artisan test
php scripts/test-auth-http.php
```

Ejecutar en ese orden, sin otras pruebas simultáneas. La suite verifica el destino aislado antes de ejecutar RefreshDatabase y utiliza exclusivamente cuentahorro_testing/cuentahorro_test.

Las pruebas cubren registro, hash, validaciones, duplicados, contraseñas multibyte, rotación e invalidación de sesión, redirecciones, escape de contenido y límites de intentos. El script HTTP verifica CSRF con solicitudes reales, sin omitir el middleware, y comprueba que una cookie invalidada no permita recuperar la sesión.

El alcance y los resultados completos se documentan en [evidencias](evidencias.md). La autenticación de la API mediante tokens se describe en [finanzas y API](finanzas-api.md). Las condiciones de publicación se detallan en [despliegue](hosting-alwaysdata.md).
