# Transferencias internas y presupuestos

## Transferencias

Las transferencias mueven pesos mexicanos entre dos cuentas propias, activas y distintas. Se registran desde `/transferencias/create` y se consultan en `/transferencias`, con filtros por cuenta origen o destino y fechas. El historial tiene paginación y conserva sus filtros. Las cuentas archivadas siguen disponibles como filtro de consulta.

La fecha debe ser válida, igual o posterior al inicio de ambas cuentas y no posterior al día de negocio en America/Mexico_City. El importe es una cadena decimal positiva, con hasta trece enteros y dos decimales. La descripción es opcional y admite hasta 500 caracteres.

**Una transferencia registrada no se puede editar ni eliminar.** El formulario muestra esta regla antes de confirmar y solicita confirmación. No existen rutas de modificación o eliminación; la API responde 405 para esos métodos sobre una transferencia. No se conectan bancos ni se ejecutan pagos o transferencias bancarias.

El origen se descuenta y el destino se abona mediante una sola operación persistida en una transacción. Cualquier saldo negativo actual o histórico revierte el registro completo. Las transferencias no crean movimientos de ingreso/gasto, no alteran sus indicadores mensuales y no consumen presupuestos.

### Orden financiero y concurrencia

`AccountLedger` calcula el saldo de cuentas y valida su historial integrando movimientos y transferencias. El saldo es inicial + ingresos − gastos + transferencias recibidas − enviadas. Los eventos se ordenan por fecha ascendente y después por `operacion_id`, un identificador global asignado al registrar cada operación. Una edición mantiene su identificador y puede cambiar su fecha solo si el nuevo historial completo es válido. Dos operaciones de la misma fecha respetan su orden de registro; un ingreso registrado después no financia retroactivamente una salida anterior de ese día.

La migración añade `operaciones_financieras`, asigna orden a los movimientos existentes conservando su orden por ID y crea `transferencias` y `presupuestos`. No recrea cuentas, usuarios ni movimientos. La actualización debe ejecutarse sin escrituras concurrentes de la aplicación; conservar una copia de seguridad fuera del repositorio antes de actualizar un entorno con datos importantes.

Las escrituras bloquean primero el usuario y después las cuentas involucradas por identificador ascendente. Movimientos y transferencias comparten esta disciplina. Editar, cambiar de cuenta o eliminar un ingreso vuelve a validar las transferencias posteriores de las cuentas afectadas. No se mantiene un saldo mutable duplicado.

Una cuenta con movimientos o transferencias no puede cambiar su saldo ni fecha inicial. DELETE la archiva y conserva el historial; solo una cuenta sin operaciones se elimina. El archivo la excluye del saldo total activo y bloquea nuevas transferencias. Su historial de transferencias sigue consultable.

### Idempotencia

`clave_idempotencia` es un UUID obligatorio, único por usuario. El formulario genera la clave al abrirse y la conserva en la validación y los reenvíos. Para una operación nueva se debe abrir un formulario nuevo. La API debe conservar la clave cuando reintente una solicitud cuyo resultado desconoce.

- Primer registro correcto: HTTP 201 y `reutilizada: false`.
- Misma clave y mismos datos: HTTP 200, mismo ID y `reutilizada: true`, sin nuevo descuento o abono. También funciona si después se archivó una cuenta.
- Misma clave con datos diferentes: HTTP 422 y error en `clave_idempotencia`; se conserva el registro original.
- Una solicitud rechazada no consume la clave ni deja efectos parciales.

La comparación utiliza los IDs enteros, el importe normalizado a dos decimales, fecha y descripción con extremos recortados; la descripción ausente o vacía se normaliza a null. `20` y `20.00` son equivalentes. El UUID se normaliza a minúsculas. La restricción SQL y el bloqueo del usuario impiden duplicados simultáneos. Otro usuario tiene su propio espacio de claves.

## Presupuestos

`/presupuestos` permite crear, consultar, editar y eliminar presupuestos, con filtros por mes y categoría y paginación. Solo admite categorías propias de gasto. El mes utiliza `AAAA-MM`, entre 1900-01 y 2199-12; puede planificarse un mes futuro. El límite es positivo y utiliza las mismas reglas decimales que los importes financieros. La combinación usuario/categoría/mes es única, también al editar.

El consumo suma los gastos reales de esa categoría y usuario dentro del mes de negocio, incluyendo cuentas archivadas. No incluye ingresos, saldos iniciales ni transferencias. Se consulta al mostrar el presupuesto: crear, reclasificar, cambiar fecha, editar o eliminar gastos actualiza automáticamente el consumo, sin sincronización manual.

- Disponible = máximo entre límite − consumo y cero.
- Exceso = máximo entre consumo − límite y cero.
- Porcentaje utilizado = consumo × 100 / límite, redondeado a dos decimales con mitad hacia arriba mediante BCMath.
- El porcentaje puede superar 100 %. La barra visual se limita a 100 % y el exceso se muestra por separado.

El presupuesto es informativo: superar el límite no impide registrar un gasto con saldo suficiente. Eliminar un presupuesto conserva todos los gastos. Una categoría referenciada por un presupuesto no puede eliminarse ni cambiar a ingreso hasta eliminar ese presupuesto; se puede renombrar.

El dashboard muestra hasta cinco presupuestos del mes actual, su consumo, disponible y exceso, y un enlace al listado completo. Las transferencias tienen acceso propio; no se duplican en los indicadores de ingreso/gasto.

## API protegida

Todas las rutas requieren Bearer Sanctum vigente con la habilidad `finanzas`. Los errores y las respuestas son JSON. Recursos ajenos/inexistentes: 404; referencias ajenas o datos inválidos: 422; sin token: 401; sin habilidad: 403. Se conserva el límite general de 120 solicitudes por minuto de la API.

| Método | Ruta | Uso |
|---|---|---|
| GET / POST | /api/transferencias | Listar / registrar |
| GET | /api/transferencias/{id} | Consultar |
| GET / POST | /api/presupuestos | Listar / crear |
| GET / PUT / PATCH / DELETE | /api/presupuestos/{id} | Consultar / actualizar / eliminar |

PUT y PATCH de presupuestos requieren los tres campos de creación. Los listados incluyen `meta` con total, página actual, última página y tamaño; `per_page` admite 1–50. Transferencias acepta `cuenta_id`, `desde` y `hasta`; presupuestos acepta `mes` y `categoria_id`. No enviar `user_id`; la propiedad se obtiene del usuario autenticado.

Ejemplo de transferencia con identificadores ilustrativos; utilizar cuentas propias reales de la demostración:

```json
{
  "origen_id": 1,
  "destino_id": 2,
  "importe": "25.50",
  "fecha": "2026-10-27",
  "descripcion": "Ahorro del mes",
  "clave_idempotencia": "e5278609-1087-4edc-84a5-c4be948ce20f"
}
```

Presupuesto de ejemplo:

```json
{"categoria_id": 3, "mes": "2026-10", "limite": "100.00"}
```

El recurso presupuesto incluye `categoria` e `indicadores`: `consumo`, `disponible`, `exceso`, `porcentaje_utilizado` y `ancho_barra`. Todas estas cantidades y el límite son cadenas decimales. Los saldos de cuentas ya incluyen transferencias. La colección Postman incorpora ejemplos de idempotencia, conflicto, inmutabilidad, duplicados, periodos y CRUD de presupuestos en la carpeta 7; la carpeta 8 cierra la demostración. Las cuentas con transferencias quedan archivadas y el destino conserva el abono de demostración; no se borran para simular una limpieza.

## Instalación y verificación

```powershell
php artisan migrate --env=migrations
php artisan test
php scripts/test-auth-http.php
php artisan serve --host=127.0.0.1 --port=8000
```

No repetir la preparación de recursos ni sobrescribir los `.env`. No se requieren paquetes nuevos. Las pruebas destructivas utilizan exclusivamente cuentahorro_testing/cuentahorro_test. Ejecutar suite y script HTTP secuencialmente.

La suite cubre dos usuarios, reglas históricas, archivo, validaciones y consumo dinámico. El script comprueba HTTP con CSRF y Bearer reales y lanza procesos independientes para: dos gastos por los mismos fondos; reenvíos simultáneos de la misma transferencia; gasto y transferencia concurrentes. Los helpers de pruebas no son endpoints. Las identidades y operaciones sintéticas se eliminan al terminar. Resultados y capturas en [evidencias](evidencias.md).
