<?php
// backend/pagos/checkout.php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../lib/servicios.php';
require_once __DIR__ . '/../lib/pagos.php';
require_once __DIR__ . '/../lib/stripe.php';

requireLogin();
$user = currentUser();
$pdo = getDB();

// Todo lo que cambia datos llega por POST con token CSRF y termina en una redirección (PRG).
if (isPost()) {
    if (!verifyCsrf($_POST['csrf'] ?? null)) {
        renderErrorPage(403, 'Solicitud no válida', 'El formulario expiró. Recarga la página e inténtalo de nuevo.');
    }
    match (postString('accion')) {
        'crear' => startPurchase($pdo, $user),
        'confirmar' => confirmPayment($pdo, $user),
        'rechazar' => rejectPayment($pdo, $user),
        'stripe' => payWithStripe($pdo, $user),
        default => renderErrorPage(400, 'Acción no válida', 'La acción solicitada no existe.'),
    };
}

/* ---------------------------------------------------------------------
   GET: ?servicio_id=N muestra la confirmación (no crea nada: GET no
   modifica datos); ?pago_id=N muestra un pago del usuario.
   --------------------------------------------------------------------- */
$selectedService = null;
$serviceParam = filter_input(INPUT_GET, 'servicio_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($serviceParam !== null) {
    $selectedService = is_int($serviceParam) ? findPurchasableService($pdo, $serviceParam) : null;
    if ($selectedService === null) {
        flash('error', 'El servicio que elegiste no existe o ya no está disponible.');
        redirect('checkout.php');
    }
}

$currentPayment = null;
$paymentParam = filter_input(INPUT_GET, 'pago_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($paymentParam !== null) {
    $currentPayment = is_int($paymentParam) ? findUserPayment($pdo, $paymentParam, $user['id']) : null;
    if ($currentPayment === null) {
        flash('error', 'No encontramos ese pago en tu historial.');
        redirect('checkout.php');
    }
    if (queryString('stripe') === 'cancelado') {
        flash('info', 'Cancelaste el pago en Stripe. Tu pago sigue pendiente: puedes completarlo cuando quieras.');
        redirect('checkout.php?pago_id=' . (int) $currentPayment['id']);
    }
}

$services = listServices($pdo, onlyActive: true);
$payments = listUserPayments($pdo, $user['id']);

/* ---------------------------------------------------------------------
   Acciones (POST)
   --------------------------------------------------------------------- */

function startPurchase(PDO $pdo, array $user): void {
    $serviceId = postId('servicio_id');
    $service = $serviceId === null ? null : findPurchasableService($pdo, $serviceId);
    if ($service === null) {
        flash('error', 'El servicio que elegiste no existe o ya no está disponible.');
        redirect('checkout.php');
    }

    // Un clic doble o volver a pulsar "Contratar" no debe llenar el historial de pendientes.
    $pendingId = findPendingPaymentId($pdo, $user['id'], (int) $service['id']);
    if ($pendingId !== null) {
        flash('info', 'Ya tenías un pago pendiente de este servicio: puedes completarlo aquí.');
        redirect('checkout.php?pago_id=' . $pendingId);
    }

    $paymentId = createPendingPayment($pdo, $user['id'], $service);
    flash('success', sprintf('Creamos tu pago %s. Elige cómo completarlo.', paymentFolio($paymentId)));
    redirect('checkout.php?pago_id=' . $paymentId);
}

function confirmPayment(PDO $pdo, array $user): void {
    $payment = postedPayment($pdo, $user);
    $reference = confirmSimulatedPayment($pdo, (int) $payment['id'], $user['id']);

    if ($reference === null) {
        flash('error', 'Este pago ya no está pendiente, así que no se puede volver a cobrar.');
    } else {
        flash('success', sprintf('Pago confirmado. Tu referencia es %s.', $reference));
    }
    redirect('checkout.php?pago_id=' . (int) $payment['id']);
}

function rejectPayment(PDO $pdo, array $user): void {
    $payment = postedPayment($pdo, $user);

    if (markPaymentFailed($pdo, (int) $payment['id'], $user['id'])) {
        flash('error', sprintf('El pago %s fue rechazado (simulación). Puedes intentarlo de nuevo.', paymentFolio((int) $payment['id'])));
    } else {
        flash('error', 'Este pago ya no está pendiente.');
    }
    redirect('checkout.php?pago_id=' . (int) $payment['id']);
}

function payWithStripe(PDO $pdo, array $user): void {
    $payment = postedPayment($pdo, $user);
    $back = 'checkout.php?pago_id=' . (int) $payment['id'];

    if (!stripeEnabled()) {
        flash('error', 'El pago con Stripe no está configurado en este entorno. Usa la simulación.');
        redirect($back);
    }
    if ($payment['estado'] !== PAYMENT_PENDING) {
        flash('error', 'Este pago ya no está pendiente.');
        redirect($back);
    }

    try {
        $session = createStripeCheckoutSession($payment, $user);
    } catch (StripeException $e) {
        error_log('[stripe] ' . $e->getMessage()); // el detalle va al log, no a la pantalla
        flash('error', 'No pudimos conectar con Stripe. Inténtalo de nuevo o usa la simulación.');
        redirect($back);
    }

    $url = $session['url'] ?? '';
    if (!is_string($url) || !preg_match('#^https?://#', $url)) {
        error_log('[stripe] La sesión no trae una URL de pago válida.');
        flash('error', 'No pudimos conectar con Stripe. Inténtalo de nuevo o usa la simulación.');
        redirect($back);
    }
    redirect($url); // el usuario paga en la página de Stripe y vuelve a stripe-retorno.php
}

/* ---------------------------------------------------------------------
   Utilidades de esta página
   --------------------------------------------------------------------- */

function postId(string $key): ?int {
    $id = filter_var(postString($key), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $id === false ? null : $id;
}

/** Pago indicado en el formulario; si no existe o es de otro usuario, termina la petición. */
function postedPayment(PDO $pdo, array $user): array {
    $paymentId = postId('pago_id');
    $payment = $paymentId === null ? null : findUserPayment($pdo, $paymentId, $user['id']);
    if ($payment === null) {
        flash('error', 'No encontramos ese pago en tu historial.');
        redirect('checkout.php');
    }
    return $payment;
}

function findPurchasableService(PDO $pdo, int $serviceId): ?array {
    $service = findService($pdo, $serviceId);
    return $service !== null && (int) $service['activo'] === 1 ? $service : null;
}

function paymentForm(string $action, string $field, int $id, string $label, string $class, string $ariaLabel = ''): string {
    return '<form method="post">' . csrfField()
        . '<input type="hidden" name="accion" value="' . e($action) . '">'
        . '<input type="hidden" name="' . e($field) . '" value="' . $id . '">'
        . '<button class="btn ' . e($class) . '" type="submit"'
        . ($ariaLabel !== '' ? ' aria-label="' . e($ariaLabel) . '"' : '') . '>' . e($label) . '</button>'
        . '</form>';
}

function formatDateTime(string $value): string {
    return date('d/m/Y H:i', strtotime($value));
}

/* ---------------------------------------------------------------------
   Vista
   --------------------------------------------------------------------- */
$pageTitle = 'Pagos';
require __DIR__ . '/../views/layout/header.php';
?>
<div class="page-header">
  <div>
    <h1 class="page-header__title">Pagos</h1>
    <p class="page-header__meta">Contrata un servicio y consulta el estado de tus pagos.</p>
  </div>
</div>

<?php if ($selectedService !== null): ?>
  <section class="panel panel--narrow checkout-step" aria-labelledby="confirm-title">
    <h2 class="checkout-step__title" id="confirm-title">Confirma tu compra</h2>
    <dl class="summary">
      <div><dt>Servicio</dt><dd><?= e($selectedService['nombre']) ?></dd></div>
      <div><dt>Total</dt><dd class="summary__amount"><?= e(formatMoney($selectedService['precio'])) ?></dd></div>
    </dl>
    <?php if (!empty($selectedService['descripcion'])): ?>
      <p class="checkout-step__text"><?= e($selectedService['descripcion']) ?></p>
    <?php endif; ?>
    <div class="form-actions">
      <?= paymentForm('crear', 'servicio_id', (int) $selectedService['id'], 'Continuar al pago', 'btn--primary') ?>
      <a class="btn btn--secondary" href="checkout.php">Cancelar</a>
    </div>
  </section>
<?php endif; ?>

<?php if ($currentPayment !== null): ?>
  <?php
  $paymentId = (int) $currentPayment['id'];
  [$statusLabel, $statusVariant] = paymentStatusBadge($currentPayment['estado']);
  ?>
  <section class="panel panel--narrow checkout-step" aria-labelledby="payment-title">
    <div class="checkout-step__header">
      <h2 class="checkout-step__title" id="payment-title">Pago <?= e(paymentFolio($paymentId)) ?></h2>
      <span class="badge badge--<?= e($statusVariant) ?>"><?= e($statusLabel) ?></span>
    </div>
    <dl class="summary">
      <div><dt>Servicio</dt><dd><?= e($currentPayment['servicio']) ?></dd></div>
      <div><dt>Monto</dt><dd class="summary__amount"><?= e(formatMoney($currentPayment['monto'])) ?></dd></div>
      <div><dt>Creado</dt><dd><?= e(formatDateTime($currentPayment['creado_en'])) ?></dd></div>
      <?php if ($currentPayment['referencia'] !== null): ?>
        <div><dt>Referencia</dt><dd><code class="reference"><?= e($currentPayment['referencia']) ?></code></dd></div>
      <?php endif; ?>
    </dl>

    <?php if ($currentPayment['estado'] === PAYMENT_PENDING): ?>
      <p class="checkout-step__text">
        Entorno de prueba: elige el resultado del pago. No se realiza ningún cargo real.
      </p>
      <div class="form-actions">
        <?= paymentForm('confirmar', 'pago_id', $paymentId, 'Simular pago exitoso', 'btn--primary') ?>
        <?= paymentForm('rechazar', 'pago_id', $paymentId, 'Simular pago rechazado', 'btn--danger') ?>
        <?php if (stripeEnabled()): ?>
          <?= paymentForm('stripe', 'pago_id', $paymentId, 'Pagar con Stripe (modo prueba)', 'btn--accent') ?>
        <?php endif; ?>
      </div>
    <?php elseif ($currentPayment['estado'] === PAYMENT_PAID): ?>
      <p class="checkout-step__text">¡Gracias por tu compra! Conserva la referencia para cualquier aclaración.</p>
    <?php else: ?>
      <p class="checkout-step__text">El pago fue rechazado y no se realizó ningún cargo.</p>
      <div class="form-actions">
        <?= paymentForm('crear', 'servicio_id', (int) $currentPayment['servicio_id'], 'Intentar de nuevo', 'btn--primary') ?>
      </div>
    <?php endif; ?>
  </section>
<?php endif; ?>

<section class="checkout-section" aria-labelledby="catalog-title">
  <h2 class="section-title" id="catalog-title">Servicios disponibles</h2>
  <?php if ($services === []): ?>
    <div class="notice">
      <p class="notice__title">Por ahora no hay servicios disponibles.</p>
      <p class="notice__text">Vuelve pronto o escríbenos desde el sitio.</p>
    </div>
  <?php else: ?>
    <ul class="cards" role="list">
      <?php foreach ($services as $index => $service): ?>
        <li class="card" style="--i: <?= min($index, 8) ?>">
          <span class="card__badge" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($service['nombre'], 0, 1))) ?></span>
          <h3 class="card__title"><?= e($service['nombre']) ?></h3>
          <p class="card__text"><?= e($service['descripcion'] ?? 'Pregúntanos por los detalles de este servicio.') ?></p>
          <div class="card__footer">
            <p class="card__price"><?= e(formatMoney($service['precio'])) ?></p>
            <?= paymentForm('crear', 'servicio_id', (int) $service['id'], 'Contratar', 'btn--primary btn--sm', 'Contratar: ' . $service['nombre']) ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<section class="checkout-section" aria-labelledby="history-title">
  <h2 class="section-title" id="history-title">Historial de pagos</h2>
  <?php if ($payments === []): ?>
    <div class="notice">
      <p class="notice__title">Aún no tienes pagos.</p>
      <p class="notice__text">Elige un servicio de la lista para comenzar.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <caption class="visually-hidden">Historial de pagos de tu cuenta</caption>
        <thead>
          <tr>
            <th scope="col">Folio</th>
            <th scope="col">Servicio</th>
            <th scope="col">Monto</th>
            <th scope="col">Estado</th>
            <th scope="col">Referencia</th>
            <th scope="col">Fecha</th>
            <th scope="col"><span class="visually-hidden">Acciones</span></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($payments as $payment): ?>
            <?php [$statusLabel, $statusVariant] = paymentStatusBadge($payment['estado']); ?>
            <tr>
              <td class="table__num"><?= e(paymentFolio((int) $payment['id'])) ?></td>
              <td><?= e($payment['servicio']) ?></td>
              <td class="table__num"><?= e(formatMoney($payment['monto'])) ?></td>
              <td><span class="badge badge--<?= e($statusVariant) ?>"><?= e($statusLabel) ?></span></td>
              <td>
                <?php if ($payment['referencia'] !== null): ?>
                  <code class="reference"><?= e($payment['referencia']) ?></code>
                <?php else: ?>
                  <span class="table__date" aria-label="Sin referencia">—</span>
                <?php endif; ?>
              </td>
              <td class="table__date">
                <time datetime="<?= e(date('c', strtotime($payment['creado_en']))) ?>"><?= e(formatDateTime($payment['creado_en'])) ?></time>
              </td>
              <td>
                <div class="actions">
                  <?php if ($payment['estado'] === PAYMENT_PENDING): ?>
                    <a class="btn btn--sm btn--primary" href="checkout.php?pago_id=<?= (int) $payment['id'] ?>">
                      Completar<span class="visually-hidden"> el pago <?= e(paymentFolio((int) $payment['id'])) ?></span>
                    </a>
                  <?php else: ?>
                    <a class="btn btn--sm btn--secondary" href="checkout.php?pago_id=<?= (int) $payment['id'] ?>">
                      Ver<span class="visually-hidden"> el pago <?= e(paymentFolio((int) $payment['id'])) ?></span>
                    </a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/../views/layout/footer.php'; ?>
