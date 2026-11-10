<?php
// backend/servicios/index.php
require_once __DIR__ . '/../helpers.php';
requireLogin();
$pdo = getDB();
$rows = $pdo->query('SELECT * FROM servicios ORDER BY id DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Servicios</title><link rel="stylesheet" href="../../frontend/css/styles.css"></head>
<body>
<section class="section"><div class="container">
  <h2>Servicios <a class="btn btn--primary" href="create.php">Nuevo</a> <a href="../auth/logout.php">Salir</a></h2>
  <table style="width:100%;border-collapse:collapse">
    <tr><th>ID</th><th>Nombre</th><th>Precio</th><th>Activo</th><th>Acciones</th></tr>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td><?php echo (int)$r['id']; ?></td>
      <td><?php echo e($r['nombre']); ?></td>
      <td>$<?php echo number_format((float)$r['precio'],2); ?></td>
      <td><?php echo $r['activo'] ? 'Sí' : 'No'; ?></td>
      <td>
        <a href="update.php?id=<?php echo (int)$r['id']; ?>">Editar</a>
        <form method="post" action="delete.php" style="display:inline" onsubmit="return confirm('¿Eliminar?')">
          <input type="hidden" name="csrf" value="<?php echo e(csrfToken()); ?>">
          <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
          <button type="submit">Eliminar</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div></section>
</body></html>
