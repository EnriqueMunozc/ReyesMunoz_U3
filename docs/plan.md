# Alcance y requisitos de Cuentahorro

Cuentahorro es una aplicación de finanzas personales para registrar manualmente cuentas, ingresos y gastos en pesos mexicanos. No se conecta con bancos, realiza transferencias bancarias ni procesa pagos.

## Tecnología y publicación

Laravel 13, PHP 8.3, Blade, Bootstrap local y JavaScript. MySQL 9.7.1 local en 127.0.0.1:3307; MariaDB como destino previsto de publicación en alwaysdata Free. El despliegue público gratuito es un requisito de entrega y debe funcionar sin depender del servidor local.

- Código: https://github.com/EnriqueMunozc/ReyesMunoz_U3
- Aplicación pública: URL y verificación de despliegue pendientes.

## Funciones disponibles

Registro, inicio y cierre de sesión; dashboard; cuentas propias con saldo y fecha inicial; categorías de ingreso/gasto; movimientos con filtros y paginación; conversor informativo USD/MXN; API REST protegida con Sanctum. Todas las operaciones financieras y el conversor comparten su lógica entre web y API.

Los importes utilizan DECIMAL(15,2) y BCMath. El saldo inicial no es ingreso mensual y queda bloqueado junto con su fecha cuando existen movimientos. Las transacciones y bloqueos ordenados impiden saldos negativos actuales o históricos. El archivo conserva el historial, excluye la cuenta del saldo activo y mantiene sus movimientos en los resúmenes mensuales.

Los timestamps utilizan UTC; las fechas y los periodos de negocio utilizan America/Mexico_City. La propiedad se obtiene de la autenticación, nunca de un user_id enviado por el cliente. Los tokens de la API vencen a las ocho horas y pueden revocarse.

## Funciones pendientes

- Transferencias internas entre cuentas propias, registradas en transacción y con protección frente a duplicados.
- Presupuestos mensuales únicos por usuario, categoría de gasto y mes.
- Publicación gratuita, instalación completa desde copia limpia y verificación en MariaDB.

## Cumplimiento académico

| Requisito | Implementación | Prueba realizada | Evidencia | Estado |
|---|---|---|---|---|
| Mecanismos de seguridad | Sesiones, CSRF, validación, hash, aislamiento por propietario y tokens Sanctum | 50 pruebas y 51 comprobaciones HTTP/integración | docs/evidencias.md y docs/finanzas-api.md | Verificado localmente para las funciones disponibles |
| Web Service de terceros | Frankfurter en conversor web/API con caché, respaldo limitado y redondeo decimal | Fallas simuladas y consultas HTTPS reales en ambas direcciones | docs/conversor.md y docs/evidencias.md | Verificado localmente; prueba desde hosting pendiente |
| Web Services propios | API JSON de cuentas, categorías, movimientos, dashboard y tipo-cambio | Operaciones, errores, aislamiento y tokens verificados localmente | Colección Postman y docs/finanzas-api.md | Verificado localmente; publicación pendiente |
| Repositorio y aplicación publicados | Código en GitHub; destino gratuito previsto en alwaysdata | Repositorio accesible; aplicación pública sin verificar | README y docs/hosting-alwaysdata.md | Publicación pendiente |

Las evidencias distinguen capacidades disponibles, pruebas realizadas y verificaciones pendientes. Las funciones no se consideran acreditadas únicamente por existir código o configuración.
