<?php
/**
 * Cabecera común del panel. Requiere $pageTitle.
 * Las rutas son relativas a backend/<módulo>/<página>.php (ver helpers.php).
 *
 * @var string $pageTitle
 */
$user = currentUser();
$flashes = takeFlashes();
$section = basename(dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$navLinks = [];
if ($user !== null) {
    if ($user['rol'] === 'admin') {
        $navLinks['servicios'] = ['Servicios', '../servicios/index.php'];
    }
    $navLinks['pagos'] = ['Pagos', '../pagos/checkout.php'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <meta name="csrf-token" content="<?= e(csrfToken()) ?>">
  <title><?= e($pageTitle) ?> — Grupo Albardi</title>
  <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%231f4e79'/%3E%3Cpath d='M9 23 16 9l7 14M12 18h8' fill='none' stroke='%23fff' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E">
  <link rel="stylesheet" href="<?= e(asset('frontend/css/styles.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('frontend/css/admin.css')) ?>">
  <script src="<?= e(asset('frontend/js/admin.js')) ?>" defer></script>
</head>
<body class="admin">
  <a class="skip-link" href="#main">Saltar al contenido principal</a>

  <header class="admin-bar">
    <div class="container admin-bar__inner">
      <a class="brand" href="<?= e($user !== null ? homePathFor($user['rol']) : '../../frontend/index.html') ?>">
        <svg class="brand__logo" viewBox="0 0 32 32" width="32" height="32" aria-hidden="true" focusable="false">
          <rect width="32" height="32" rx="8" />
          <path d="M9 23 16 9l7 14M12 18h8" />
        </svg>
        <span>Grupo Albardi</span>
        <span class="admin-bar__tag">Portal</span>
      </a>

      <?php if ($user !== null): ?>
        <nav class="admin-bar__nav" aria-label="Panel">
          <?php foreach ($navLinks as $key => [$label, $href]): ?>
            <a href="<?= e($href) ?>"<?= $key === $section ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
          <?php endforeach; ?>
          <a href="../../frontend/index.html">Ver sitio</a>
        </nav>

        <div class="admin-bar__user">
          <span class="admin-bar__name">Hola, <strong><?= e($user['nombre']) ?></strong></span>
          <form method="post" action="../auth/logout.php">
            <?= csrfField() ?>
            <button class="btn btn--sm btn--secondary" type="submit">Cerrar sesión</button>
          </form>
        </div>
      <?php endif; ?>
    </div>
  </header>

  <main id="main" class="admin-main" tabindex="-1">
    <div class="container">
      <?php foreach ($flashes as $flash): ?>
        <p class="alert alert--<?= e($flash['type']) ?>" role="<?= $flash['type'] === 'error' ? 'alert' : 'status' ?>">
          <?= e($flash['message']) ?>
        </p>
      <?php endforeach; ?>
