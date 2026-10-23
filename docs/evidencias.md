# Evidencias de Cuentahorro

Registro de verificaciones locales: 23 de octubre de 2026, America/Mexico_City.

| Comprobación | Resultado | Alcance |
|---|---|---|
| Entorno | Laravel 13.38.0, PHP 8.3.33 y MySQL Community 9.7.1 | Conexión local en 127.0.0.1:3307 |
| Esquema SQL | Migraciones iniciales y financiera aplicadas con cuentahorro_migrator | Tablas de autenticación, cuentas, categorías, movimientos y tokens |
| Suite aislada | 36 pruebas y 328 aserciones satisfactorias | Autenticación, propiedad web/API, decimales, fechas, archivo y saldos históricos |
| HTTP e integración | 43 comprobaciones satisfactorias | Sesiones, CSRF, operaciones financieras, Bearer, errores y revocación |
| Concurrencia | De dos gastos de 60.00 sobre una cuenta de 100.00, uno se acepta y otro se rechaza; saldo final 40.00 | Procesos independientes sobre la base de pruebas |
| Tokens | Válidos, vencidos, revocados y sin habilidad verificados | Sanctum 4.3.3, vencimiento de ocho horas y almacenamiento con hash |
| Interfaz | Cuenta, categoría y gasto registrados desde navegador; saldo 1124.50 calculado desde 1250.00 menos 125.50 | Datos desechables de cuentahorro_testing |
| Postman | Colección de 34 solicitudes y entorno sin credenciales | Operaciones equivalentes verificadas por script HTTP; sin ejecución registrada desde la aplicación Postman |
| Rutas | route:cache y route:clear satisfactorios | Configuración local |
| Dependencias y formato | Composer validate, check-platform-reqs y Pint satisfactorios | Entorno local |
| Protección de archivos | Archivos .env privados excluidos de Git y plantillas sin credenciales reales | Revisión de archivos versionados |
| Frankfurter | Diagnóstico HTTPS desde Laravel satisfactorio con verificación TLS | Falta integrar el conversor y su endpoint para el usuario |
| Publicación | URL pública y pruebas remotas pendientes | Despliegue gratuito previsto en alwaysdata |

## Reproducción

Desde la raíz del proyecto, con los recursos locales configurados:

```powershell
php artisan migrate:status --env=migrations
php artisan cuentahorro:check-hosting
php artisan test
php scripts/test-auth-http.php
composer validate --strict
composer check-platform-reqs
php vendor/bin/pint --test
```

Las pruebas destructivas utilizan exclusivamente cuentahorro_testing/cuentahorro_test. El script HTTP verifica base y usuario, utiliza identidades sintéticas y elimina únicamente los registros de su ejecución. Las respuestas simuladas de las pruebas son datos de ensayo, no respuestas reales del proveedor.

## Evidencia visual

[Dashboard con datos de prueba](evidencias/dashboard-pruebas.png). La captura corresponde a registros desechables de la base aislada; no contiene datos financieros personales ni cifras precargadas para usuarios reales. La revisión visual se realizó en navegador de escritorio. No se ha registrado una prueba en dispositivo físico móvil.

## Verificaciones pendientes

Conversor y endpoint tipo-cambio con caché y manejo de fallas, transferencias, presupuestos, instalación completa desde una copia limpia, validación en MariaDB y publicación gratuita. La publicación debe comprobarse desde otra red con el servidor local detenido.

El diagnóstico CLI de Frankfurter no acredita por sí solo la integración externa completa. El enlace del repositorio contiene el código; la URL de la aplicación pública se registrará tras su despliegue y verificación.
