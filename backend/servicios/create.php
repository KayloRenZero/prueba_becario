<?php
// backend/servicios/create.php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../lib/servicios.php';

requireAdmin();

$values = ['nombre' => '', 'descripcion' => '', 'precio' => '', 'activo' => 1];
$errors = [];

if (isPost()) {
    ['data' => $values, 'errors' => $errors] = validateService($_POST);

    if (!verifyCsrf($_POST['csrf'] ?? null)) {
        http_response_code(403);
        $errors = ['form' => 'El formulario expiró. Vuelve a enviarlo.'];
    } elseif ($errors === []) {
        createService(getDB(), $values);
        flash('success', sprintf('Servicio «%s» creado.', $values['nombre']));
        redirect('index.php');
    } else {
        http_response_code(422);
    }
}

$pageTitle = 'Nuevo servicio';
$submitLabel = 'Crear servicio';
require __DIR__ . '/../views/layout/header.php';
?>
<a class="back-link" href="index.php">← Servicios</a>
<div class="page-header">
  <h1 class="page-header__title">Nuevo servicio</h1>
</div>
<section class="panel panel--narrow">
  <?php require __DIR__ . '/../views/servicios/form.php'; ?>
</section>
<?php require __DIR__ . '/../views/layout/footer.php'; ?>
