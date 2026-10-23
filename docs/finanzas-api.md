# Finanzas y API de Cuentahorro

Cuentahorro permite administrar cuentas, categorías, ingresos y gastos desde la interfaz web y una API REST. Utiliza autenticación de Laravel, Blade y Bootstrap local. No incluye transferencias, presupuestos ni conversor.

## Datos, propiedad e integridad

- Cuentas: propietario, nombre, tipo (`efectivo`, `bancaria`, `ahorro`, `otra`), saldo inicial DECIMAL(15,2), fecha inicial y estado archivado. El saldo y la fecha inicial quedan bloqueados cuando hay movimientos. El inicial no es un ingreso mensual.
- Categorías propias de ingreso/gasto: nombre normalizado (espacios, mayúsculas y acentos) único por usuario/tipo. Las utilizadas pueden renombrarse, pero no cambiar de tipo ni eliminarse.
- Movimientos: cuenta, categoría compatible, tipo, importe positivo, fecha y descripción opcional hasta 500 caracteres. Fechas desde el inicio de cuenta hasta hoy, inclusive. Claves foráneas compuestas evitan relacionar recursos de propietarios distintos, sin cascadas destructivas.
- Propietario obtenido de autenticación. Enviar user_id se rechaza. Recursos ajenos o inexistentes: 404. Referencias ajenas en entradas/filtros: 422.
- Dinero como cadenas decimales, sin comas ni notación científica, máximo 13 enteros y 2 decimales. En JSON enviar `"10.25"`, no `10.25`. Sumas/restas/formato con BCMath; agregados SQL sobre DECIMAL, sin cálculos con float.
- Saldo = inicial + ingresos − gastos. Orden histórico estable: fecha e ID ascendentes. Crear, editar, eliminar, cambiar fecha o cambiar cuenta recalcula todos los saldos posteriores afectados. Un negativo intermedio revierte la operación completa aunque el saldo final fuera positivo.
- Cada escritura bloquea primero la fila del usuario y después las cuentas afectadas por ID ascendente. Transacción con hasta tres intentos por deadlock. No hay saldos mutables duplicados que puedan desincronizarse.
- DELETE de cuenta sin movimientos elimina; con movimientos archiva. Las archivadas no aceptan nuevos movimientos; se reactivan editando el estado. Para editar un movimiento de cuenta archivada hay que reactivarla; eliminarlo sigue sujeto a saldo histórico.
- Dashboard: saldo total de **cuentas activas**; ingresos/gastos/balance mensual de **todas** las cuentas, incluidas archivadas; cinco últimos movimientos. Fechas y periodos America/Mexico_City, timestamps UTC.

`app/Services/FinanceService.php` concentra validación, propiedad, reglas, filtros y transacciones. Los controladores comparten la misma lógica para Blade y JSON. `app/Support/Money.php` formatea MXN sin float. Las migraciones se aplican con una credencial de esquema separada de la credencial de uso de la aplicación.

## API

Base local `http://127.0.0.1:8000`. Responde JSON incluso sin Accept; se recomienda `Accept: application/json` y `Content-Type: application/json` al enviar datos.

| Método | Ruta | Función |
|---|---|---|
| POST | /api/tokens | Emitir token mediante email, password y nombre_dispositivo |
| DELETE | /api/tokens/actual | Revocar token Bearer actual |
| GET / POST | /api/cuentas | Listar paginado / crear |
| GET / PUT / DELETE | /api/cuentas/{id} | Consultar / editar / eliminar o archivar |
| GET / POST | /api/categorias | Listar paginado / crear |
| GET / PUT / DELETE | /api/categorias/{id} | Consultar / editar / eliminar si está libre |
| GET / POST | /api/movimientos | Listar filtrado y paginado / crear |
| GET / PUT / DELETE | /api/movimientos/{id} | Consultar / editar / eliminar |
| GET | /api/dashboard | Indicadores y movimientos propios |

PUT requiere campos de creación; archivada y descripcion son opcionales. PATCH usa la misma validación completa, no actualización parcial. Listados: page >=1, per_page 1–50 (defecto 15). Movimientos: cuenta_id, categoria_id, tipo, desde, hasta; fechas inclusivas, orden descendente por fecha/ID y enlaces web que conservan filtros.

Cuenta de ejemplo (usar una fecha válida al ejecutarlo):

```json
{"nombre":"Efectivo","tipo":"efectivo","saldo_inicial":"100.00","fecha_inicial":"2026-10-23"}
```

Categoría y movimiento de ejemplo (sustituir identificadores por los propios):

```json
{"nombre":"Alimentos","tipo":"gasto"}
```

```json
{"cuenta_id":1,"categoria_id":1,"tipo":"gasto","importe":"20.50","fecha":"2026-10-23","descripcion":"Compra manual"}
```

Éxito: `{"data": ...}`, creación 201, demás operaciones 200. Listados incluyen `meta: {current_page,last_page,per_page,total}`; mutaciones añaden message. Error: `{"message":"..."}`, con errors por campo en 422. Nunca trazas, consultas SQL ni credenciales.

- 401: sin token, inválido/vencido/revocado o credenciales incorrectas.
- 403: token sin habilidad finanzas.
- 404: recurso ajeno/inexistente.
- 405: método incorrecto.
- 422: validación o regla financiera.
- 429: límite de solicitudes, con encabezados de espera.

## Sanctum y Postman

Sanctum 4.3.3, compatible con Laravel 13 mediante Composer. Token con hash en SQL, vencimiento de ocho horas y habilidad finanzas. Authorization Bearer; la API no acepta cookies web como sustituto. La web sigue con sesiones y CSRF. Emisión limitada a cinco solicitudes por correo/IP y veinte por IP por minuto; API autenticada a 120/minuto. No colocar tokens en URLs o logs.

1. Registrar un usuario por web.
2. Importar `docs/postman/Cuentahorro.postman_collection.json` y `Cuentahorro.local.postman_environment.json`.
3. Seleccionar entorno y completar email/password solo localmente. Los valores sensibles compartidos están vacíos.
4. Ejecutar carpetas 1–4 en orden: guardan localmente token e IDs y calculan fecha en America/Mexico_City. Incluyen operaciones correctas y errores 401/404/422.
5. Carpeta 5: registrar otro usuario y obtener su token independientemente; ponerlo como other_token local. Debe obtener 404 sobre cuenta del primero. Omitir hasta configurarlo.
6. Carpeta 6: archivo/reactivación, eliminación de ejemplos y revocación. Última solicitud: 401. Puede quedar la categoría de ingreso de demostración, editable desde web.

La colección incluye **34 solicitudes con aserciones de estado y JSON**. Las operaciones equivalentes se verificaron mediante el script HTTP; no se ha registrado una ejecución de la colección desde la aplicación Postman. Mantener las credenciales y tokens en variables locales privadas y excluirlos de las exportaciones y de Git. Para repetir los ejemplos, utilizar categorías nuevas o eliminar previamente las categorías libres de demostración.

## Pruebas y demostración

```powershell
php artisan migrate --env=migrations
php artisan cuentahorro:check-hosting
php artisan test
php scripts/test-auth-http.php
php artisan serve --host=127.0.0.1 --port=8000
```

Las pruebas destructivas usan exclusivamente cuentahorro_testing/cuentahorro_test. Ejecutar suite y script en orden, sin concurrencia entre suites. El script HTTP limpia solo sus identidades sintéticas. financial-test-worker.php es un helper CLI de prueba concurrente, no un endpoint.

Resultados: 36 pruebas / 328 aserciones y 43 comprobaciones HTTP/integración, incluida concurrencia real mediante dos procesos sobre una cuenta. Conserva los tests de autenticación. Registro de rutas cacheable y diagnóstico Frankfurter operativo.

Guion: crear cuenta, categorías e ingreso/gasto; verificar indicadores; editar/cambiar cuenta; demostrar gasto sin fondos y modificación histórica rechazada; filtrar; archivar/reactivar; demostrar JSON, 401/404/422 y revocación. Usar datos de demostración propios.

Evidencia visual: [dashboard de pruebas](evidencias/dashboard-pruebas.png), con datos desechables de la base aislada. Inspección en navegador de escritorio; CSS por breakpoints y tablas adaptables, sin prueba en dispositivo físico.

## Pendientes

Conversor y /api/tipo-cambio con caché/fallas para el usuario, transferencias, presupuestos, instalación completa desde copia limpia y publicación gratuita en alwaysdata. La consulta técnica CLI a Frankfurter sigue funcionando. Migraciones diseñadas con Schema Builder para MySQL/MariaDB; aún no probadas en el hosting real. No existe URL pública confirmada de aplicación.
