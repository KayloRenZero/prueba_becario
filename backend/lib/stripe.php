<?php
// backend/lib/stripe.php
declare(strict_types=1);

/*
 * Stripe Checkout en modo prueba, sin SDK: dos llamadas HTTP a la API de Stripe.
 *
 *   1. POST /v1/checkout/sessions      → Stripe devuelve la URL de su página de pago.
 *   2. GET  /v1/checkout/sessions/{id} → al volver, el SERVIDOR pregunta a Stripe si
 *      se cobró. Nunca se confía en que el navegador diga "ya pagué".
 *
 * La llave secreta vive solo en config.php (ignorado por git) y nunca llega al navegador.
 */

final class StripeException extends RuntimeException {}

const STRIPE_CURRENCY = 'mxn';
// Valida el ID antes de ponerlo en la URL de la API: "../" u otros caracteres no pasan.
const STRIPE_SESSION_ID_PATTERN = '/^cs_test_[A-Za-z0-9]{10,200}$/';

/** Solo se activa con una llave de PRUEBA (sk_test_…): este entorno nunca hace cobros reales. */
function stripeEnabled(): bool {
    return defined('STRIPE_SECRET_KEY')
        && is_string(STRIPE_SECRET_KEY)
        && str_starts_with(STRIPE_SECRET_KEY, 'sk_test_');
}

/** "1500.50" → 150050. Stripe recibe los montos en centavos, como entero. */
function amountToCents(string $amount): int {
    return (int) round((float) $amount * 100);
}

/**
 * URL pública del proyecto, para las URLs de retorno. APP_URL en config.php evita
 * depender de la cabecera Host, que la envía el cliente.
 */
function appBaseUrl(): string {
    if (defined('APP_URL') && is_string(APP_URL) && APP_URL !== '') {
        return rtrim(APP_URL, '/');
    }
    $scheme = isHttps() ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // SCRIPT_NAME = /<proyecto>/backend/<módulo>/<página>.php → 3 niveles arriba = /<proyecto>
    $basePath = rtrim(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'), 3), '/\\');
    return $scheme . '://' . $host . $basePath;
}

/** @return array{success: string, cancel: string} */
function stripeReturnUrls(int $paymentId): array {
    $pagos = appBaseUrl() . '/backend/pagos/';
    return [
        // Stripe reemplaza {CHECKOUT_SESSION_ID} por el ID real de la sesión.
        'success' => $pagos . 'stripe-retorno.php?session_id={CHECKOUT_SESSION_ID}',
        'cancel' => $pagos . 'checkout.php?pago_id=' . $paymentId . '&stripe=cancelado',
    ];
}

/**
 * Llamada HTTP a la API de Stripe.
 *
 * @param array<string, mixed> $params
 * @param list<string> $headers
 * @return array<string, mixed>
 * @throws StripeException
 */
function stripeRequest(string $method, string $path, array $params = [], array $headers = []): array {
    $apiBase = defined('STRIPE_API_BASE') && is_string(STRIPE_API_BASE) && STRIPE_API_BASE !== ''
        ? rtrim(STRIPE_API_BASE, '/')   // solo para pruebas contra un simulador local
        : 'https://api.stripe.com';
    $url = $apiBase . $path;
    $body = http_build_query($params); // Stripe usa form-encoding: line_items[0][quantity]=1

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => STRIPE_SECRET_KEY . ':', // autenticación básica: la llave es el usuario
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => $headers,
        // La verificación TLS queda activada (valor por defecto): nunca desactivar SSL_VERIFYPEER.
    ];
    if ($method === 'POST') {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = $body;
    } elseif ($params !== []) {
        $url .= '?' . $body;
    }

    $curl = curl_init($url);
    curl_setopt_array($curl, $options);
    $raw = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

    if ($raw === false) {
        throw new StripeException('Sin conexión con Stripe: ' . curl_error($curl));
    }
    $data = json_decode((string) $raw, true);
    if (!is_array($data)) {
        throw new StripeException(sprintf('Respuesta no válida de Stripe (HTTP %d).', $status));
    }
    if ($status < 200 || $status >= 300) {
        throw new StripeException(sprintf('Stripe respondió HTTP %d: %s', $status, $data['error']['message'] ?? 'sin detalle'));
    }
    return $data;
}

/**
 * Crea la sesión de pago de Stripe para un pago pendiente.
 *
 * @return array<string, mixed> Sesión de Stripe; su campo "url" es la página de pago.
 */
function createStripeCheckoutSession(array $payment, array $user): array {
    $urls = stripeReturnUrls((int) $payment['id']);

    return stripeRequest('POST', '/v1/checkout/sessions', [
        'mode' => 'payment',
        'success_url' => $urls['success'],
        'cancel_url' => $urls['cancel'],
        'client_reference_id' => (string) $payment['id'],
        'metadata' => [
            'pago_id' => (string) $payment['id'],
            'usuario_id' => (string) $user['id'],
        ],
        'line_items' => [[
            'quantity' => 1,
            'price_data' => [
                'currency' => STRIPE_CURRENCY,
                'unit_amount' => amountToCents((string) $payment['monto']),
                'product_data' => ['name' => (string) $payment['servicio']],
            ],
        ]],
    ], [
        // Si el usuario pulsa dos veces, Stripe devuelve la MISMA sesión en vez de crear otra.
        'Idempotency-Key: pago-' . hash('sha256', $payment['id'] . '|' . $payment['creado_en'] . '|' . $payment['monto']),
    ]);
}

/** @throws StripeException */
function retrieveStripeCheckoutSession(string $sessionId): array {
    if (!preg_match(STRIPE_SESSION_ID_PATTERN, $sessionId)) {
        throw new StripeException('Identificador de sesión de Stripe no válido.');
    }
    return stripeRequest('GET', '/v1/checkout/sessions/' . $sessionId);
}

/**
 * Comprueba que la sesión de Stripe corresponde a ESTE pago y que se cobró lo correcto.
 *
 * @return string|null El motivo del rechazo, o null si todo coincide.
 */
function stripeSessionMismatch(array $session, array $payment, int $userId): ?string {
    if (($session['client_reference_id'] ?? null) !== (string) $payment['id']) {
        return 'la sesión pertenece a otro pago';
    }
    if (($session['metadata']['usuario_id'] ?? null) !== (string) $userId) {
        return 'la sesión pertenece a otro usuario';
    }
    if ((int) ($session['amount_total'] ?? -1) !== amountToCents((string) $payment['monto'])) {
        return 'el monto cobrado no coincide';
    }
    if (strtolower((string) ($session['currency'] ?? '')) !== STRIPE_CURRENCY) {
        return 'la moneda no coincide';
    }
    return null;
}
