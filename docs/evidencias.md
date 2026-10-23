# Evidencias de Cuentahorro

## Estado actual: finanzas y API local, 23 de octubre de 2026

| Comprobación | Resultado | Alcance |
|---|---|---|
| Migración financiera | Aplicada con cuentahorro_migrator, lote 2 | Cuentas/categorías/movimientos/tokens; bases y usuarios conservados |
| Suite MySQL aislada | 36 pruebas y 328 aserciones satisfactorias | Regresión de autenticación, propiedad web/API, decimales, fechas, archivo y saldos históricos |
| HTTP/integración | 43 comprobaciones satisfactorias | Web real, Bearer, 401/404/422/429, revocación, sesión/CSRF y concurrencia |
| Concurrencia | Dos procesos intentan gastar 60.00 sobre una cuenta de 100.00: uno guarda, otro rechaza; saldo 40.00 | Servicio compartido y conexiones SQL independientes en base de pruebas |
| Tokens | Válidos, vencidos, revocados y sin habilidad verificados | Sanctum 4.3.3, ocho horas y hash SQL; sin tokens reales en archivos compartidos |
| Interfaz | Cuenta/categoría/gasto creados en navegador; dashboard muestra 1124.50 desde 1250.00 − 125.50 | Datos sintéticos aislados; captura evidencias/dashboard-pruebas.png |
| Postman | 34 solicitudes y entorno vacío preparados | Operaciones equivalentes probadas por script HTTP, no por aplicación Postman |
| Rutas | route:cache y route:clear satisfactorios | No acredita servidor remoto |
| Frankfurter | Diagnóstico técnico HTTPS satisfactorio | Interfaz y endpoint del conversor pendientes |
| Hosting | Sin cuenta o URL pública comprobada | alwaysdata sigue como requisito final |

Reglas y reproducción en [finanzas-api.md](finanzas-api.md). La captura no representa datos personales ni cifras precargadas de usuarios reales.

## Histórico: autenticación local, 23 de octubre de 2026

| Comprobación | Resultado | Alcance |
|---|---|---|
| Recursos SQL | El propietario completó prepare-local y la conexión de aplicación se verificó contra MySQL 9.7.1 | No se repitió la preparación ni se sobrescribieron credenciales |
| Migraciones | Tres migraciones iniciales aplicadas con cuentahorro_migrator; tabla de migraciones creada | Solo cuentahorro; SQL Server y otras bases sin cambios |
| Registro/acceso/salida | Implementados y probados con hash, sesiones, CSRF, validación y limitación | Web local; sin API financiera |
| Suite aislada | 20 pruebas, 161 aserciones satisfactorias | cuentahorro_testing/cuentahorro_test; guarda previa a RefreshDatabase |
| HTTP real | 19 comprobaciones satisfactorias, incluidos CSRF 419, bloqueo 429 y reproducción de cookie invalidada | Servidor temporal local y base de pruebas, sin middleware desactivado |
| Interfaz | Formularios Bootstrap locales y dashboard vacío con nombre escapado | Revisados en navegador de escritorio; breakpoints móviles implementados, sin dispositivo físico |
| Frankfurter | Diagnóstico HTTPS desde Laravel satisfactorio con TLS validado | Falta herramienta visible con caché/fallas |
| Hosting público | Pendiente: sin cuenta ni subdominio asignado | No hay pruebas remotas ni URL real de aplicación |

Detalles y reproducción: [autenticación](autenticacion.md), [hosting](hosting-alwaysdata.md) y [README](../README.md).

## Histórico: base inicial cd0d2f7

Fecha de preparación: 22 de octubre de 2026, America/Mexico_City.

| Comprobación | Resultado | Alcance |
|---|---|---|
| MySQL autenticado | El propietario confirmó MySQL Community 9.7.1, 127.0.0.1:3307, root@localhost y ausencia de nombres reservados | Confirmación aportada por el propietario; esta sesión no dispone de su contraseña |
| Laravel | Instalado Laravel Framework 13.38.0 con PHP 8.3.33 | Ejecución local |
| Dependencias | Composer instaló las versiones de composer.lock; check-platform-reqs satisfactorio | Entorno local, no hosting |
| Secretos | Git ignora .env, .env.migrations, .env.testing y .env.production; plantillas sin claves | Comprobación git check-ignore |
| Tamaño inicial | 45.04 MiB de archivos locales, incluyendo vendor de desarrollo, antes de completar documentación | Menor a 1 GB; no prueba consumo final ni cuota remota, SQL/correo/logs |
| Diagnóstico HTTPS | Laravel/Guzzle obtuvo respuesta real USD/MXN con verificación TLS y CA ya incluido en Git | Fecha devuelta por el proveedor: 2026-10-23; conservar esa fecha aunque difiera de la fecha local. No son datos en tiempo real |
| Diagnóstico SQL | Falla esperada: cuentahorro_app todavía no ha sido creado | Necesita ejecución local del comando con entrada secreta del propietario |
| Pruebas automatizadas | 5 pruebas, 16 aserciones satisfactorias tras la configuración CA final | Inicio provisional, rechazo de preparación fuera de local, diagnóstico con proveedor simulado correcto/incorrecto y fallo SQL sin traza |
| HTTP local real | GET / y GET /up respondieron 200; inicio incluye Cuentahorro | Servidor Laravel local; no demuestra hosting |
| Formato y manifiesto | Pint satisfactorio y composer validate --strict válido | Verificación final de esta base |
| GitHub | Repositorio oficial accesible, originalmente vacío | https://github.com/EnriqueMunozc/ReyesMunoz_U3 |
| alwaysdata | Compatibilidad documental y límites registrados en hosting-alwaysdata.md | No existe cuenta; no se han probado PHP web, migraciones MariaDB, cuota, Frankfurter saliente ni Authorization remotos |
| Aplicación pública | Pendiente: todavía no hay URL asignada | No sustituir con una dirección de ejemplo |

## Pendiente de acreditar

Conversor y endpoint tipo-cambio con caché/falla, transferencias, presupuestos, instalación limpia completa con MySQL/MariaDB y publicación gratuita con el PC apagado. Cuentas/categorías/movimientos, sus propietarios y API ya se comprobaron en la fase actual.

Las respuestas HTTP simuladas de tests son fixtures de prueba. No representan consultas reales al proveedor. El diagnóstico CLI no acredita por sí solo el requisito de Web Services de terceros.
