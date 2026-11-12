# Pagos con Stripe Checkout (modo prueba)

El portal ofrece dos formas de completar un pago pendiente:

- **Simulación** (siempre disponible): botones *Simular pago exitoso* y *Simular pago rechazado*.
- **Stripe Checkout en modo prueba** (opcional): se activa al configurar una llave secreta de prueba. Ningún cobro es real.

## Cómo activarlo

1. Crea una cuenta gratuita en <https://dashboard.stripe.com/register>. El modo de prueba no pide datos fiscales ni bancarios.
2. En el dashboard, con **Modo de prueba** activado, entra a *Desarrolladores → Claves de API* y copia la **clave secreta** (empieza con `sk_test_`).
3. En `backend/config.php` (ignorado por git) agrega:
   ```php
   define('APP_URL', 'http://localhost/entrega_becario');   // sin "/" final
   define('STRIPE_SECRET_KEY', 'sk_test_...');
   ```
4. Inicia sesión, entra a **Pagos**, contrata un servicio y pulsa **Pagar con Stripe (modo prueba)**.
5. Paga en la página de Stripe con una tarjeta de prueba, con cualquier fecha futura, CVC y código postal:

   | Tarjeta | Resultado |
   |---------|-----------|
   | `4242 4242 4242 4242` | Pago aprobado |
   | `4000 0000 0000 0002` | Tarjeta rechazada |
   | `4000 0025 0000 3155` | Pide autenticación 3D Secure |

6. Stripe te devuelve al portal. El pago queda **Pagado** con una referencia `pi_…` (el identificador del cobro en Stripe), que también aparece en el dashboard.

Si `STRIPE_SECRET_KEY` está vacía, o no es una llave de prueba (`sk_test_`), el botón no aparece y solo se ofrece la simulación.

## Cómo funciona

```
Portal (checkout.php)            Stripe                         Portal (stripe-retorno.php)
──────────────────────           ──────                         ───────────────────────────
POST accion=stripe ───────────►  POST /v1/checkout/sessions
                                 (monto en centavos, MXN,
                                  pago_id, usuario_id)
redirige a session.url ◄───────  { id: cs_test_…, url }
          │
          └─ el usuario paga en la página de Stripe ──────────►  GET ?session_id=cs_test_…
                                                                     │
                                 GET /v1/checkout/sessions/{id} ◄────┘  el SERVIDOR consulta a Stripe
                                 { payment_status: paid, … } ──────►  verifica y marca "pagado"
```

La integración no usa SDK: son dos llamadas HTTP con cURL (`backend/lib/stripe.php`).

## Decisiones de seguridad

| Riesgo | Defensa |
|--------|---------|
| Filtrar la llave secreta | Vive solo en `config.php`, que está en `.gitignore`. Nunca llega al navegador ni a los logs. |
| Cobros reales por error | Solo se activa con llaves `sk_test_`. |
| Que alguien abra la URL de retorno sin haber pagado | El estado no se decide por la URL: el servidor consulta la sesión a Stripe y exige `payment_status = paid`. |
| Reutilizar la sesión pagada de otro pago o de otro usuario | Se comprueban `client_reference_id` (pago), `metadata.usuario_id`, el **monto en centavos** y la **moneda** contra la BD. |
| Inyectar rutas en la URL de la API (`session_id=../../v1/…`) | El `session_id` se valida con `^cs_test_[A-Za-z0-9]+$` antes de usarse. |
| Doble clic: dos sesiones para el mismo pago | Cabecera `Idempotency-Key` derivada del pago: Stripe devuelve la misma sesión. |
| Cobrar dos veces el mismo pago en la BD | La transición `pendiente → pagado` es un `UPDATE … WHERE estado = 'pendiente'` atómico. Recargar el retorno es idempotente. |
| Stripe caído o lento | Tiempo límite de 5 s para conectar y 20 s en total. El error se registra en el log, el usuario ve un aviso y el pago sigue pendiente. |
| Conexión interceptada | La verificación TLS de cURL queda activada. XAMPP ya trae `curl.cainfo` configurado en `php.ini`. |

**Por qué la sesión sobrevive al volver de Stripe:** la cookie de sesión usa `SameSite=Lax`, que sí viaja en una navegación GET de nivel superior que llega desde otro sitio, como la redirección de Stripe. Con `Strict`, el usuario volvería sin sesión.

## Pendiente para producción

- **Webhook `checkout.session.completed`** con verificación de firma (cabecera `Stripe-Signature` y secreto `whsec_…`). Hoy el pago se confirma cuando el usuario vuelve al portal. Si cierra la pestaña antes, el pago queda pendiente aunque Stripe haya cobrado. El webhook lo confirmaría de todos modos. En local se prueba con Stripe CLI: `stripe listen --forward-to localhost/entrega_becario/backend/pagos/webhook.php`.
- **Fijar la versión de la API** con la cabecera `Stripe-Version`, para que un cambio en la versión predeterminada de la cuenta no altere las respuestas.
- **Llaves de producción** (`sk_live_`) en variables de entorno del servidor, no en archivos.
- Stripe exige un mínimo de **$10.00 MXN** por cobro. Los servicios actuales lo superan.

## Cómo se probó

- **Contra un simulador local de la API de Stripe**, sin cuenta real. El simulador imita la autenticación, el formato de los parámetros, los errores y la idempotencia de Stripe. Se cubrieron 36 casos: sesión creada con los datos correctos, idempotencia, pago aprobado, cancelado y sin completar, monto, moneda y usuario manipulados, `session_id` malicioso, sesión inexistente, Stripe caído y ausencia de la llave en HTML y logs.
- **Conectividad real:** desde PHP se verificó la conexión TLS con `api.stripe.com`. Con una llave inválida, Stripe responde `401`, lo que confirma que la autenticación y los certificados funcionan.
- **Sin probar todavía:** un pago completo con una cuenta real de Stripe. Para hacerlo, basta con seguir la sección *Cómo activarlo*.
