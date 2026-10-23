# Alcance de Cuentahorro

## Entrega

Aplicación Laravel 13/PHP 8.3; MySQL 9.7.1 local en 127.0.0.1:3307; MariaDB en alwaysdata Free. Despliegue público obligatorio y persistente sin equipo local encendido, sin hosting ni dominio de pago. SQL Server y las bases de otros proyectos quedan fuera de todas las operaciones.

Enlaces: repositorio https://github.com/EnriqueMunozc/ReyesMunoz_U3; **aplicación pública pendiente de crear cuenta y desplegar**.

## Datos y pantallas

users -> cuentas/categorias/movimientos/transferencias/presupuestos. Movimientos pertenecen a cuenta y categoría del mismo usuario. Transferencias relacionan dos cuentas propias distintas; presupuestos tienen categoría de gasto y mes, con unicidad usuario/categoría/mes. Tablas de sesiones/caché/tokens según mecanismos de Laravel. No hay conexión bancaria ni pagos.

Pantallas: registro, login, dashboard, cuentas, movimientos con filtros y paginación, transferencias, presupuestos y tipo de cambio. Blade, Bootstrap y JavaScript; sin datos falsos que aparenten ser operaciones reales.

DECIMAL(15,2) y BCMath; importes positivos. Saldo inicial separado de ingresos. Saldo derivado, transferencias fuera de ingresos/gastos. Transacciones y bloqueos de cuentas ordenados por ID, idempotencia por usuario/clave. No permitir saldos negativos, incluidos los saldos posteriores a cambios históricos. Archivo de cuentas con historial; sin cascadas destructivas. UTC para timestamps; America/Mexico_City para periodos y fechas de negocio.

## Orden

1. Base Laravel, recursos SQL aislados y diagnóstico de hosting (etapa actual).
2. Autenticación web, CSRF, hash, regeneración/invalidez de sesión, throttling y políticas; dos usuarios.
3. Cuentas, movimientos, dashboard y API mediante los mismos servicios de negocio.
4. Frankfurter HTTP, timeout, validación JSON, caché reciente/último válido, conversión USD/MXN y fallas.
5. Transferencias y presupuestos; pruebas financieras y concurrencia.
6. Publicación gratuita, Postman, instalación limpia, evidencias y guion.

Sanctum se agregará para tokens revocables y con vencimiento cuando se implemente la API. Web mediante sesiones de Laravel. Propiedad siempre tomada de autenticación, nunca del user_id recibido. API JSON: 401 no autenticado, 404 inexistente o ajeno, 403 acción prohibida sobre recurso visible, 422 validación/regla financiera. No exponer trazas.

## Requisitos y evidencias previstas

| Requisito | Implementación | Prueba realizada | Evidencia | Estado |
|---|---|---|---|---|
| Seguridad | Sesión web, CSRF, validación, políticas, hash y Sanctum | Solo base y exclusión de secretos; falta prueba funcional de seguridad | docs/evidencias.md | Pendiente |
| Web Service externo | Frankfurter desde backend con caché y conversión | Hay diagnóstico CLI; falta integración de usuario | Respuesta real, falla simulada y caché | Pendiente |
| Web Service propio | CRUD JSON de cuentas/movimientos, dashboard y tipo de cambio | No implementado | Colección Postman y HTTP remoto | Pendiente |
| Repositorio y aplicación publicados | GitHub oficial + URL HTTPS gratuita | Repositorio accesible inicialmente vacío; app sin publicar | Instalación limpia y navegación con PC apagada | Pendiente |

Cada módulo tendrá cambios concretos, verificación y un commit; no se avanzará sobre fallas de módulos dependientes.
