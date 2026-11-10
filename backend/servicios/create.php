<?php
// backend/servicios/create.php
require_once __DIR__ . '/../helpers.php';
requireLogin();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf'] ?? null)) {
        $error = 'Token inválido.';
    } else {
        $nombre = trim($_POST['nombre'] ?? '');
        $desc = trim($_POST['descripcion'] ?? '');
        $precio = (float)($_POST['precio'] ?? 0);
        if ($nombre === '' || $precio < 0) {
            $error = 'Datos inválidos.';
        } else {
            $stmt = getDB()->prepare('INSERT INTO servicios (nombre, descripcion, precio) VALUES (:n, :d, :p)');
            $stmt->execute([':n' => $nombre, ':d' => $desc, ':p' => $precio]);
            header('Location: index.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Nuevo servicio</title><link rel="stylesheet" href="../../frontend/css/styles.css"></head>
<body><section class="section"><div class="container">
  <h2>Nuevo servicio</h2>
  <?php if ($error): ?><p style="color:#c0392b"><?php echo e($error); ?></p><?php endif; ?>
  <form method="post" class="form">
    <input type="hidden" name="csrf" value="<?php echo e(csrfToken()); ?>">
    <div class="field"><label>Nombre</label><input name="nombre" required></div>
    <div class="field"><label>Descripción</label><textarea name="descripcion" rows="3"></textarea></div>
    <div class="field"><label>Precio</label><input name="precio" type="number" step="0.01" required></div>
    <button class="btn btn--primary" type="submit">Guardar</button>
    <a href="index.php">Cancelar</a>
  </form>
</div></section></body></html>
