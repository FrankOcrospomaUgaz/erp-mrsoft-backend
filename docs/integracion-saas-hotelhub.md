# Integración ERP ↔ HotelHUB (avisos de pago y sucursales)

## Qué hace

- El ERP relaciona un cliente con una sucursal de HotelHUB mediante el ID de la sucursal externa.
- Al iniciar sesión, HotelHUB pregunta al ERP por los avisos de cuotas SaaS pendientes de ese cliente.
- Los pagos aprobados en el ERP reducen el saldo pendiente y, al cubrir la cuota, su aviso desaparece.
- El panel del ERP permite desactivar y reactivar la sucursal. HotelHUB aplica el estado tanto al iniciar sesión como en las rutas protegidas.
- A partir del umbral configurable de atraso, el panel ERP muestra una alerta administrativa. El ERP no suspende automáticamente: un administrador decide y confirma el corte.
- La falla temporal de conexión entre sistemas no impide iniciar sesión en HotelHUB; se registra en su log y no se muestra información de pago vieja.

## Configuración de despliegue

Configurar el mismo secreto aleatorio, largo y privado en ambos servidores. No enviarlo al navegador ni guardarlo en el repositorio.

ERP `.env`:

```dotenv
HOTELHUB_BASE_URL=https://hotelhub.example.com
HOTELHUB_INTEGRATION_SECRET=<secreto-compartido>
```

HotelHUB `.env`:

```dotenv
ERP_API_BASE_URL=https://erp-api.example.com
ERP_INTEGRATION_SECRET=<secreto-compartido>
```

Después de cambiar variables de Laravel, ejecutar `php artisan config:cache` (o limpiar caché en desarrollo). Ambos hosts deben usar HTTPS y sus relojes deben estar sincronizados. Las firmas HMAC tienen tolerancia de cinco minutos.

## Activación inicial

1. Desplegar primero el endpoint firmado de HotelHUB y el endpoint firmado del ERP.
2. Aplicar migraciones del ERP: `php artisan migrate --force`.
3. Abrir **Ventas → Integraciones SaaS** en el ERP.
4. Vincular cada cliente con el ID real de su sucursal en HotelHUB. No asumir que el ID ERP y el ID HotelHUB coinciden.
5. Ajustar días previos al vencimiento y días de atraso para mostrar la alerta administrativa.
6. Confirmar una sucursal de prueba antes de gestionar las demás.

## Protocolo firmado

Headers en ambas direcciones:

```text
X-SaaS-Timestamp: <Unix timestamp en segundos>
X-SaaS-Signature: <hex HMAC-SHA256>
```

Firma: `HMAC_SHA256(secret, timestamp + LF + cuerpo HTTP exacto)`. El receptor verifica ventana temporal y compara la firma en tiempo constante.

ERP endpoints:

- `POST /api/integrations/hotelhub/notices` (firma HMAC): body `{ "branch_id": "<ID HotelHUB>" }`.
- `GET /api/saas/hotelhub/branches` (token Sanctum de administrador).
- `POST /api/saas/hotelhub/branches` para crear el vínculo con `cliente_id`, `external_branch_id`, `branch_name`.
- `PATCH /api/saas/hotelhub/branches/{id}/status` con `{ "active": false }` o `true`; el backend ERP firma y llama a HotelHUB antes de guardar el estado.
- `GET/PUT /api/saas/payment-notice-settings` para consultar y guardar los dos umbrales.
- `GET /api/saas/hotelhub/alerts` para la lista administrativa de ramas con deuda que alcanzó el umbral.

HotelHUB endpoint:

- `PATCH /api/integrations/erp/branches/{branchId}/status` (firma HMAC), body `{ "active": false }` o `true`.

## Reglas de avisos

- Antes del vencimiento, dentro del rango configurable, aparece un recordatorio al iniciar sesión.
- Desde el vencimiento aparece un aviso de atraso.
- Al alcanzar el umbral de suspensión, el aviso indica que el administrador revisará el corte ese día y el ERP muestra una alerta.
- El cálculo considera pagos sin eliminar y aprobados (o pagos antiguos sin estado de revisión); las operaciones rechazadas no reducen el saldo.
- Si la cuota queda totalmente cubierta, deja de generar aviso. Las cuotas parciales muestran el saldo pendiente.

## Adaptar otro SaaS

Cada SaaS debe implementar un adaptador pequeño con estas responsabilidades:

1. Guardar en ERP un vínculo explícito `{provider, external_branch_id, cliente_id}`; nunca inferir IDs entre sistemas.
2. En el login, enviar el ID externo a `POST /api/integrations/{provider}/notices` firmado con el protocolo anterior y mostrar los avisos devueltos.
3. Aceptar una mutación de estado firmada `PATCH /api/integrations/erp/branches/{id}/status` y hacer que el middleware de acceso/login aplique ese estado.
4. No permitir cortes por una llamada sin firma, expirada o no autorizada; hacer el cambio idempotente y auditarlo.
5. Añadir los endpoints del proveedor a `SaasIntegrationController`/adaptador correspondiente y exponer la operación desde el panel ERP. Mantener las reglas de pago y cálculo en el ERP.

Los nombres de proveedor, ruta y modelo de sucursal pueden variar; firma, vínculo explícito, respuesta de avisos y control centralizado son el contrato compartido.

## Operación y diagnóstico

- ERP: revisar logs Laravel si la llamada remota devuelve timeout o error; el estado local no se cambia hasta que HotelHUB confirma.
- HotelHUB: revisar `storage/logs/laravel.log` ante errores consultando avisos ERP.
- Un secreto vacío o distinto causa HTTP 401; verificar ambos valores sin registrarlos en tickets o respuestas visibles.
- La disponibilidad/estado sincronizado se puede verificar desde el panel **Integraciones SaaS**.
