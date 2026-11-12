<?php
// backend/auth/logout.php
require_once __DIR__ . '/../helpers.php';

if (!isPost()) {
    header('Allow: POST');
    renderErrorPage(405, 'Método no permitido', 'Usa el botón «Cerrar sesión» del panel.');
}

if (!verifyCsrf($_POST['csrf'] ?? null)) {
    renderErrorPage(403, 'Solicitud no válida', 'El formulario expiró. Vuelve a intentarlo desde el panel.');
}

logoutUser();
flash('success', 'Cerraste sesión correctamente.');
redirect('login.php');
