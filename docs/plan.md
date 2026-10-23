# Alcance de Cuentahorro

## Entrega

Aplicación Laravel 13/PHP 8.3; MySQL 9.7.1 local en 127.0.0.1:3307; MariaDB en alwaysdata Free. Despliegue público obligatorio y persistente sin equipo local encendido, sin hosting ni dominio de pago. SQL Server y las bases de otros proyectos quedan fuera de todas las operaciones.

Enlaces: repositorio https://github.com/EnriqueMunozc/ReyesMunoz_U3; **aplicación pública pendiente de crear cuenta y desplegar**.

## Datos y pantallas

users -> cuentas/categorias/movimientos/transferencias/presupuestos. Movimientos pertenecen a cuenta y categoría del mismo usuario. Transferencias relacionan dos cuentas propias distintas; presupuestos tienen categoría de gasto y mes, con unicidad usuario/categoría/mes. Tablas de sesiones/caché/tokens según mecanismos de Laravel. No hay conexión bancaria ni pagos.

Pantallas: registro, login, dashboard, cuentas, movimientos con filtros y paginación, transferencias, presupuestos y tipo de cambio. Blade, Bootstrap y JavaScript; sin datos falsos que aparenten ser operaciones reales.

DECIMAL(15,2) y BCMath; importes positivos. Saldo inicial separado de ingresos y bloqueado junto con fecha inicial cuando hay movimientos. Saldo derivado. Transacciones y bloqueos ordenados por ID, sin negativos actuales/históricos. Cuentas con historial se archivan: fuera del saldo activo, conservando resúmenes mensuales. UTC para timestamps; America/Mexico_City para periodos. Transferencias e idempotencia se implementarán en su fase posterior.

## Orden

1. Base Laravel, recursos SQL aislados y diagnóstico de hosting (completados localmente; hosting efectivo pendiente).
2. Autenticación web, CSRF, hash, regeneración/invalidez de sesión, throttling e interfaz inicial (completados y probados localmente; las políticas de recursos corresponden a los siguientes módulos).
3. Cuentas, categorías, movimientos, dashboard y API con servicios compartidos (completados y probados localmente).
4. Frankfurter HTTP, timeout, validación JSON, caché reciente/último válido, conversión USD/MXN y fallas.
5. Transferencias y presupuestos; pruebas financieras y concurrencia.
6. Publicación gratuita, Postman, instalación limpia, evidencias y guion.

Sanctum 4.3.3 protege la API con tokens revocables de ocho horas. Web mediante sesiones de Laravel. Propiedad tomada de autenticación, nunca de user_id recibido. API JSON: 401 no autenticado, 404 inexistente/ajeno, 403 sin habilidad, 422 validación/regla financiera. Sin trazas.

## Requisitos y evidencias previstas

| Requisito | Implementación | Prueba realizada | Evidencia | Estado |
|---|---|---|---|---|
| Seguridad | Sesiones, CSRF, validación, hash, scopes por propietario y Sanctum | 36 tests y 43 comprobaciones HTTP/integración; dos propietarios y saldos históricos | docs/evidencias.md y docs/finanzas-api.md | Verificado localmente para esta fase |
| Web Service externo | Frankfurter desde backend con caché y conversión | Hay diagnóstico CLI; falta integración de usuario | Respuesta real, falla simulada y caché | Pendiente |
| Web Service propio | CRUD JSON de cuentas/categorías/movimientos y dashboard protegidos | HTTP local real y errores/tokens verificados | Postman y docs/finanzas-api.md | Finanzas verificadas; tipo-cambio y hosting pendientes |
| Repositorio y aplicación publicados | GitHub oficial + URL HTTPS gratuita | Código en repositorio oficial; app sin publicar | Instalación limpia y navegación con PC apagada pendientes | Parcial: despliegue pendiente |

Cada módulo tendrá cambios concretos, verificación y un commit; no se avanzará sobre fallas de módulos dependientes.
