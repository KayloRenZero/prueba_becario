<?php
// backend/pagos/checkout.php
require_once __DIR__ . '/../helpers.php';
requireLogin();
$pdo = getDB();
$uid = (int)$_SESSION['user_id'];

// Crear pago pendiente si viene servicio_id
if (isset($_GET['servicio_id'])) {
    $sid = (int)$_GET['servicio_id'];
    $st = $pdo->prepare('SELECT precio FROM servicios WHERE id = :id');
    $st->execute([':id' => $sid]);
    $s = $st->fetch();
    if ($s) {
        $ins = $pdo->prepare('INSERT INTO pagos (usuario_id, servicio_id, monto) VALUES (:u,:s,:m)');
        $ins->execute([':u' => $uid, ':s' => $sid, ':m' => $s['precio']]);
        $pagoId = (int)$pdo->lastInsertId();
        header('Location: checkout.php?pago_id=' . $pagoId);
        exit;
    }
}

// Simular pago exitoso
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pago_id']) && isset($_POST['simular'])) {
    if (verifyCsrf($_POST['csrf'] ?? null)) {
        $pid = (int)$_POST['pago_id'];
        $ref = 'SIM-' . strtoupper(bin2hex(random_bytes(6)));
        $up = $pdo->prepare('UPDATE pagos SET estado="pagado", referencia=:r WHERE id=:id AND usuario_id=:u');
        $up->execute([':r' => $ref, ':id' => $pid, ':u' => $uid]);
    }
    header('Location: checkout.php');
    exit;
}

$pagos = $pdo->prepare('SELECT p.*, s.nombre AS servicio FROM pagos p JOIN servicios s ON s.id=p.servicio_id WHERE p.usuario_id=:u ORDER BY p.id DESC');
$pagos->execute([':u' => $uid]);
$rows = $pagos->fetchAll();
$servicios = $pdo->query('SELECT id, nombre, precio FROM servicios WHERE activo=1')->fetchAll();
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Pagos</title><link rel="stylesheet" href="../../frontend/css/styles.css"></head>
<body><section class="section"><div class="container">
  <h2>Pagos <a href="../auth/logout.php">Salir</a></h2>

  <h3>Iniciar compra</h3>
  <?php foreach ($servicios as $s): ?>
    <a class="btn btn--primary" style="margin:4px" href="checkout.php?servicio_id=<?php echo (int)$s['id']; ?>">
      <?php echo e($s['nombre']); ?> ($<?php echo number_format((float)$s['precio'],2); ?>)
    </a>
  <?php endforeach; ?>

  <h3 style="margin-top:24px">Historial</h3>
  <table style="width:100%;border-collapse:collapse">
    <tr><th>ID</th><th>Servicio</th><th>Monto</th><th>Estado</th><th>Referencia</th><th>Acción</th></tr>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td><?php echo (int)$r['id']; ?></td>
      <td><?php echo e($r['servicio']); ?></td>
      <td>$<?php echo number_format((float)$r['monto'],2); ?></td>
      <td><?php echo e($r['estado']); ?></td>
      <td><?php echo e($r['referencia'] ?? '-'); ?></td>
      <td>
        <?php if ($r['estado'] === 'pendiente'): ?>
        <form method="post" style="display:inline">
          <input type="hidden" name="csrf" value="<?php echo e(csrfToken()); ?>">
          <input type="hidden" name="pago_id" value="<?php echo (int)$r['id']; ?>">
          <button type="submit" name="simular" value="1">Simular pago exitoso</button>
        </form>
        <?php else: ?>-<?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>

  <p style="margin-top:16px"><a href="../servicios/index.php">Volver a servicios</a></p>
</div></section></body></html>
