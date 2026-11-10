<?php
// backend/servicios/update.php
require_once __DIR__ . '/../helpers.php';
requireLogin();
$pdo = getDB();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM servicios WHERE id = :id');
$stmt->execute([':id' => $id]);
$serv = $stmt->fetch();
if (!$serv) { http_response_code(404); echo 'No encontrado'; exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf'] ?? null)) {
        $error = 'Token inválido.';
    } else {
        $nombre = trim($_POST['nombre'] ?? '');
        $desc = trim($_POST['descripcion'] ?? '');
        $precio = (float)($_POST['precio'] ?? 0);
        $activo = isset($_POST['activo']) ? 1 : 0;
        if ($nombre === '' || $precio < 0) {
            $error = 'Datos inválidos.';
        } else {
            $up = $pdo->prepare('UPDATE servicios SET nombre=:n, descripcion=:d, precio=:p, activo=:a WHERE id=:id');
            $up->execute([':n'=>$nombre,':d'=>$desc,':p'=>$precio,':a'=>$activo,':id'=>$id]);
            header('Location: index.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Editar servicio</title><link rel="stylesheet" href="../../frontend/css/styles.css"></head>
<body><section class="section"><div class="container">
  <h2>Editar servicio</h2>
  <?php if ($error): ?><p style="color:#c0392b"><?php echo e($error); ?></p><?php endif; ?>
  <form method="post" class="form">
    <input type="hidden" name="csrf" value="<?php echo e(csrfToken()); ?>">
    <input type="hidden" name="id" value="<?php echo (int)$serv['id']; ?>">
    <div class="field"><label>Nombre</label><input name="nombre" value="<?php echo e($serv['nombre']); ?>" required></div>
    <div class="field"><label>Descripción</label><textarea name="descripcion" rows="3"><?php echo e($serv['descripcion']); ?></textarea></div>
    <div class="field"><label>Precio</label><input name="precio" type="number" step="0.01" value="<?php echo e($serv['precio']); ?>" required></div>
    <div class="field"><label><input type="checkbox" name="activo" <?php echo $serv['activo']?'checked':''; ?>> Activo</label></div>
    <button class="btn btn--primary" type="submit">Guardar</button>
    <a href="index.php">Cancelar</a>
  </form>
</div></section></body></html>
