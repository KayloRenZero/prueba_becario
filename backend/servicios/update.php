<?php
// backend/servicios/update.php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../lib/servicios.php';

requireAdmin();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$pdo = getDB();
$service = $id ? findService($pdo, $id) : null;

if ($service === null) {
    renderErrorPage(404, 'Servicio no encontrado', 'El servicio que buscas no existe o fue eliminado.');
}

$values = $service;
$errors = [];

if (isPost()) {
    ['data' => $values, 'errors' => $errors] = validateService($_POST);

    if (!verifyCsrf($_POST['csrf'] ?? null)) {
        http_response_code(403);
        $errors = ['form' => 'El formulario expiró. Vuelve a enviarlo.'];
    } elseif ($errors === []) {
        updateService($pdo, $id, $values);
        flash('success', sprintf('Cambios guardados en «%s».', $values['nombre']));
        redirect('index.php');
    } else {
        http_response_code(422);
    }
}

$pageTitle = 'Editar servicio';
$submitLabel = 'Guardar cambios';
require __DIR__ . '/../views/layout/header.php';
?>
<a class="back-link" href="index.php">← Servicios</a>
<div class="page-header">
  <h1 class="page-header__title">Editar servicio</h1>
</div>
<section class="panel panel--narrow">
  <?php require __DIR__ . '/../views/servicios/form.php'; ?>
</section>
<?php require __DIR__ . '/../views/layout/footer.php'; ?>
