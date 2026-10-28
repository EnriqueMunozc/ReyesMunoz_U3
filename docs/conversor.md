# Conversor USD/MXN

La conversión es informativa: no crea movimientos, no edita cuentas ni modifica saldos. La pantalla `/conversor` requiere sesión web y está disponible desde la navegación. El endpoint `GET /api/tipo-cambio` requiere un token Bearer vigente con la habilidad `finanzas`.

## Contrato externo

Contrato comprobado el 23 de octubre de 2026 en la [documentación oficial de Frankfurter](https://frankfurter.dev/) y su [OpenAPI v2](https://api.frankfurter.dev/v2/openapi.json). El cliente utiliza `GET https://api.frankfurter.dev/v2/rate/{base}/{quote}`, sin clave API. La respuesta contiene `date`, `base`, `quote` y `rate`. Sin fecha solicitada, devuelve la tasa de referencia más reciente disponible; la aplicación no ofrece consultas históricas.

Frankfurter combina fuentes de referencia. La fecha de la observación puede ser anterior al día de consulta, por ejemplo durante fines de semana. Las tasas no son cotizaciones en tiempo real ni precios de compra/venta bancaria. La aplicación consulta cada dirección directamente; no calcula la tasa inversa con una división aproximada.

`FrankfurterClient` concentra HTTPS, timeouts y validación para el conversor y el diagnóstico `cuentahorro:check-hosting`. Solo acepta HTTPS, conserva la verificación de certificados y no sigue redirecciones. Comprueba estado 200, tamaño máximo de 16 KiB, JSON válido, monedas coincidentes, fecha de calendario válida no futura y tasa positiva de hasta seis enteros y dieciocho decimales. Los literales numéricos del JSON se conservan como cadenas antes de decodificar; no pasan por float.

## Parámetros y respuesta propia

| Parámetro | Reglas |
|---|---|
| `base` | Obligatorio: `USD` o `MXN`, en mayúsculas |
| `destino` | Obligatorio: `USD` o `MXN`, distinto de `base` |
| `importe` | Opcional; positivo, máximo `999999999.99`, hasta dos decimales, sin comas ni notación científica. Vacío equivale a consultar solo la tasa. |

Ejemplos con un token configurado únicamente en variables locales de Postman:

```http
GET /api/tipo-cambio?base=USD&destino=MXN
GET /api/tipo-cambio?base=USD&destino=MXN&importe=100.00
GET /api/tipo-cambio?base=MXN&destino=USD&importe=100.00
Accept: application/json
Authorization: Bearer {{token}}
```

Respuesta ilustrativa con datos de ensayo, no una cotización vigente:

```json
{
  "data": {
    "base": "USD",
    "destino": "MXN",
    "tasa": "18.500000000000000000",
    "fecha_referencia": "2026-10-23",
    "consultado_en": "2026-10-23T18:00:00+00:00",
    "proveedor": "Frankfurter",
    "estado": "cache",
    "importe": "100.00",
    "resultado": "1850.00",
    "redondeo": "mitad_hacia_arriba",
    "informativa": true,
    "aviso": "Tasa de referencia diaria; no es una cotización en tiempo real."
  }
}
```

Sin importe, `importe` y `resultado` son null. Las tasas e importes presentes son cadenas decimales. El producto conserva veinte decimales con BCMath y se redondea una sola vez a dos: se suma `0.005` y se trunca a escala dos. Para importes positivos equivale a redondear la mitad hacia arriba; `1 × 0.055` produce `0.06`, mientras `1 × 1.004999999999999999` produce `1.00`.

Errores: 401 sin token/vencido/revocado; 403 sin habilidad; 422 para parámetros inválidos; 429 por el límite general de la API; 503 si no existe una tasa utilizable. El 503 incluye `code: tipo_cambio_no_disponible`, mensaje público y `Retry-After`. La pantalla muestra el error y conserva disponibles las demás secciones.

## Caché y disponibilidad

La caché se comparte entre usuarios y entre web/API porque contiene únicamente tasas públicas, nunca importes ni resultados personales. Se utiliza el almacén de Laravel configurado en `CACHE_STORE`; en producción debe ser persistente, como `file`, y escribible. No utilizar `array` para producción.

| Variable | Predeterminado | Uso |
|---|---|---|
| `FRANKFURTER_CONNECT_TIMEOUT` | 3 segundos | Límite para conectar |
| `FRANKFURTER_TIMEOUT` | 8 segundos | Límite total de la petición |
| `FRANKFURTER_CACHE_SECONDS` | 3600 segundos | Vigencia desde la consulta correcta |
| `FRANKFURTER_MAX_AGE_SECONDS` | 604800 segundos | Antigüedad máxima de consulta y fecha de referencia |
| `FRANKFURTER_RETRY_SECONDS` | 60 segundos | Pausa entre consultas fallidas |
| `HTTP_CA_BUNDLE` | Verificación TLS del sistema | Ruta opcional a certificados CA confiables |

Los intervalos se interpretan como enteros positivos, con mínimo de un segundo. La antigüedad de la fecha de referencia se calcula desde las 00:00 UTC de ese día. Tanto esta antigüedad como la del momento de consulta deben estar dentro del límite, incluso si la caché seguiría vigente. Una nueva consulta de una observación demasiado antigua no renueva su validez.

Estados públicos:

- `consulta`: respuesta recién obtenida y validada.
- `cache`: último resultado dentro de la vigencia; otra conversión no llama al proveedor.
- `respaldo`: la caché venció y el proveedor falló, está en pausa de reintento o no pudo obtenerse el bloqueo de consulta. Se conserva el momento original y se muestra una advertencia.

Se conserva el último resultado válido por par hasta que exista otro válido o se elimine la caché. Nunca se sirve fuera del límite de antigüedad. Una falla no lo reemplaza ni reinicia su fecha. Un bloqueo por par y una segunda lectura evitan consultas simultáneas duplicadas. La pausa también se aplica sin respaldo para evitar una nueva llamada por cada conversión durante una falla. No existe actualización forzada desde parámetros del usuario.

## Pruebas y evidencias

```powershell
php artisan test --filter=ExchangeRateTest
php artisan test
php scripts/test-auth-http.php
php artisan cuentahorro:check-hosting
```

Las pruebas automatizadas utilizan exclusivamente la base aislada y respuestas simuladas; prohíben llamadas externas inesperadas. Cubren ambas direcciones, precisión y redondeo, importes/parámetros inválidos, autenticación, caché vigente/vencida, renovación, timeout, error HTTP, JSON inválido, campos incorrectos, respaldo permitido/caducado, ausencia de datos y conservación de movimientos existentes y saldos. El script HTTP siembra tasas sintéticas en su caché aislada y comprueba web/API con sesiones y encabezados reales.

La consulta HTTPS real se ejecuta por separado, sin simular el cliente. El diagnóstico evita la caché para comprobar conectividad actual. Los resultados y la [captura del conversor](evidencias/conversor-pruebas.png) se registran en [evidencias](evidencias.md). La carpeta 6 de Postman incluye consultas, conversiones y errores; el proveedor real puede producir 503 y la colección lo distingue de una tasa disponible.
