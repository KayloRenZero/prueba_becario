<?php
// backend/servicios/delete.php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../lib/servicios.php';

requireAdmin();

if (!isPost()) {
    header('Allow: POST');
    renderErrorPage(405, 'Método no permitido', 'Los servicios se eliminan desde el botón del listado.');
}

if (!verifyCsrf($_POST['csrf'] ?? null)) {
    renderErrorPage(403, 'Solicitud no válida', 'El formulario expiró. Vuelve a intentarlo desde el listado.');
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$pdo = getDB();
$service = $id ? findService($pdo, $id) : null;

if ($service === null) {
    flash('error', 'El servicio que intentaste eliminar ya no existe.');
    redirect('index.php');
}

try {
    deleteService($pdo, $id);
    flash('success', sprintf('Servicio «%s» eliminado.', $service['nombre']));
} catch (ServiceInUseException) {
    flash('error', sprintf(
        'No se puede eliminar «%s» porque tiene pagos registrados. Desactívalo para ocultarlo del sitio.',
        $service['nombre']
    ));
}

redirect('index.php');
