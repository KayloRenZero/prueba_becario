<?php
/**
 * Página de error genérica (403, 404, 405, 500...). La incluye renderErrorPage().
 *
 * @var int $status
 * @var string $title
 * @var string $message
 */
$backUser = currentUser();
?>
<section class="panel panel--narrow error-page" aria-labelledby="error-title">
  <p class="error-page__code">Error <?= (int) $status ?></p>
  <h1 class="page-header__title" id="error-title"><?= e($title) ?></h1>
  <p class="error-page__message"><?= e($message) ?></p>
  <a class="btn btn--primary" href="<?= e($backUser !== null ? homePathFor($backUser['rol']) : LOGIN_PAGE) ?>">
    <?= $backUser !== null ? 'Volver al panel' : 'Ir a iniciar sesión' ?>
  </a>
</section>
