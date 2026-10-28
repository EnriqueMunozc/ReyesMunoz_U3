# Evidencias de Cuentahorro

Registro de verificaciones locales: 23 de octubre de 2026, America/Mexico_City.

| Comprobación | Resultado | Alcance |
|---|---|---|
| Entorno | Laravel 13.38.0, PHP 8.3.33 y MySQL Community 9.7.1 | Conexión local en 127.0.0.1:3307 |
| Esquema SQL | Migraciones iniciales y financiera aplicadas con cuentahorro_migrator | Tablas de autenticación, cuentas, categorías, movimientos y tokens |
| Suite aislada | 50 pruebas y 497 aserciones satisfactorias | Autenticación, propiedad web/API, decimales, fechas, archivo y saldos históricos |
| HTTP e integración | 51 comprobaciones satisfactorias | Sesiones, CSRF, finanzas, Bearer, conversor con caché sintética, respaldo, errores y revocación |
| Concurrencia | De dos gastos de 60.00 sobre una cuenta de 100.00, uno se acepta y otro se rechaza; saldo final 40.00 | Procesos independientes sobre la base de pruebas |
| Tokens | Válidos, vencidos, revocados y sin habilidad verificados | Sanctum 4.3.3, vencimiento de ocho horas y almacenamiento con hash |
| Interfaz | Cuenta, categoría y gasto registrados desde navegador; saldo 1124.50 calculado desde 1250.00 menos 125.50 | Datos desechables de cuentahorro_testing |
| Postman | Colección de 41 solicitudes y entorno sin credenciales | Operaciones equivalentes verificadas por script HTTP; sin ejecución registrada desde la aplicación Postman |
| Rutas | route:cache y route:clear satisfactorios | Configuración local |
| Dependencias y formato | Composer validate, check-platform-reqs y Pint satisfactorios | Entorno local |
| Protección de archivos | Archivos .env privados excluidos de Git y plantillas sin credenciales reales | Revisión de archivos versionados |
| Conversor simulado | 14 pruebas específicas, incluidas en la suite completa | Ambas direcciones, redondeo, caché, fallas, respaldo y conservación de datos existentes |
| Frankfurter real | USD/MXN 18.743100000000000000; MXN/USD 0.053350000000000000; fecha del proveedor 2026-10-23 | Consultas directas con TLS, registradas a las 02:57:47 y 02:57:48 UTC del 2026-10-24; separadas de las pruebas simuladas |
| Conversor en navegador | 100.00 USD = 1874.31 MXN, estado de caché vigente y metadatos visibles | Sesión desechable de cuentahorro_testing; tasa obtenida por HTTPS real; vista estrecha de escritorio |
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

[Conversor con tasa de referencia real](evidencias/conversor-pruebas.png). La identidad de revisión pertenece a la base aislada y se eliminó al terminar. El importe 100.00 es un ejemplo informativo; no generó movimientos. La fecha de consulta UTC difiere del día local y de la fecha de referencia, y se muestra por separado.

## Verificaciones pendientes

Transferencias, presupuestos, instalación completa desde una copia limpia, validación en MariaDB y publicación gratuita. La publicación debe comprobarse desde otra red con el servidor local detenido.

La integración de Frankfurter está comprobada localmente mediante web/API, pruebas simuladas y consultas HTTPS reales; todavía falta verificarla desde el hosting. El enlace del repositorio contiene el código; la URL de la aplicación pública se registrará tras su despliegue y verificación.
