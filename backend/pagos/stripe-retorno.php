<?php
// backend/pagos/stripe-retorno.php
// Stripe redirige aquí cuando el usuario termina de pagar en su página.
// La URL dice "ya pagué", pero eso lo controla el navegador: el estado se decide
// consultando la sesión directamente a Stripe desde el servidor.
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../lib/pagos.php';
require_once __DIR__ . '/../lib/stripe.php';

requireLogin();
$user = currentUser();
$pdo = getDB();

if (!stripeEnabled()) {
    renderErrorPage(404, 'Página no disponible', 'El pago con Stripe no está configurado en este entorno.');
}

$sessionId = queryString('session_id');
try {
    $session = retrieveStripeCheckoutSession($sessionId);
} catch (StripeException $e) {
    error_log('[stripe] ' . $e->getMessage());
    flash('error', 'No pudimos verificar tu pago con Stripe. Revisa tu historial en unos minutos.');
    redirect('checkout.php');
}

$paymentId = (int) ($session['client_reference_id'] ?? 0);
$payment = findUserPayment($pdo, $paymentId, $user['id']);
$mismatch = $payment === null
    ? 'el pago no existe o pertenece a otro usuario'
    : stripeSessionMismatch($session, $payment, $user['id']);

if ($mismatch !== null) {
    error_log(sprintf('[stripe] Sesión %s rechazada: %s.', $sessionId, $mismatch));
    flash('error', 'No pudimos validar ese pago.');
    redirect('checkout.php');
}

if (($session['payment_status'] ?? '') !== 'paid') {
    flash('info', 'Stripe todavía no confirma el cobro. Tu pago sigue pendiente.');
    redirect('checkout.php?pago_id=' . $paymentId);
}

$reference = $session['payment_intent'] ?? null; // "pi_…": el identificador del cobro en Stripe
if (!is_string($reference) || $reference === '') {
    error_log(sprintf('[stripe] Sesión %s pagada sin payment_intent.', $sessionId));
    flash('error', 'No pudimos validar ese pago.');
    redirect('checkout.php?pago_id=' . $paymentId);
}

if (markPaymentPaid($pdo, $paymentId, $user['id'], $reference)) {
    flash('success', sprintf('Pago confirmado con Stripe. Tu referencia es %s.', $reference));
} elseif ($payment['estado'] === PAYMENT_PAID && $payment['referencia'] === $reference) {
    // Recargar esta página no cambia nada: la operación es idempotente.
    flash('info', 'Este pago ya estaba confirmado.');
} else {
    error_log(sprintf('[stripe] Sesión %s pagada, pero el pago %d ya no estaba pendiente.', $sessionId, $paymentId));
    flash('error', 'Este pago ya no está pendiente. Si se realizó un cargo, contáctanos para revisarlo.');
}
redirect('checkout.php?pago_id=' . $paymentId);
