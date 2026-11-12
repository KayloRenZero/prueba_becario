<?php
// backend/auth/login.php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../lib/usuarios.php';

$user = currentUser();
if ($user !== null) {
    redirect(homePathFor($user['rol']));
}

$email = '';
$error = '';

if (isPost()) {
    $email = trim(postString('email'));
    $password = postString('password');

    if (!verifyCsrf($_POST['csrf'] ?? null)) {
        http_response_code(403);
        $error = 'Tu formulario expiró. Recarga la página e inténtalo de nuevo.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        http_response_code(422);
        $error = 'Ingresa un correo electrónico válido y tu contraseña.';
    } else {
        $authenticated = authenticate(getDB(), $email, $password);
        if ($authenticated !== null) {
            loginUser($authenticated);
            redirect(homePathFor($authenticated['rol']));
        }
        // Mensaje genérico: no se revela si falló el correo o la contraseña.
        http_response_code(401);
        $error = 'Correo o contraseña incorrectos.';
    }
}

$pageTitle = 'Iniciar sesión';
require __DIR__ . '/../views/layout/header.php';
?>
<section class="auth-card" aria-labelledby="login-title">
  <h1 class="auth-card__title" id="login-title">Iniciar sesión</h1>
  <p class="auth-card__lead">Accede al portal de clientes de Grupo Albardi.</p>

  <?php if ($error !== ''): ?>
    <p class="alert alert--error" role="alert"><?= e($error) ?></p>
  <?php endif; ?>

  <form class="admin-form" method="post">
    <?= csrfField() ?>

    <div class="field">
      <label class="field__label" for="email">Correo electrónico</label>
      <input class="field__input" id="email" name="email" type="email" maxlength="150"
             autocomplete="username" required value="<?= e($email) ?>"<?= $email === '' ? ' autofocus' : '' ?>>
    </div>

    <div class="field">
      <label class="field__label" for="password">Contraseña</label>
      <input class="field__input" id="password" name="password" type="password"
             autocomplete="current-password" required<?= $email !== '' ? ' autofocus' : '' ?>>
    </div>

    <button class="btn btn--primary btn--block" type="submit">Entrar</button>
  </form>

  <p class="auth-card__back"><a href="../../frontend/index.html">← Volver al sitio</a></p>
</section>
<?php require __DIR__ . '/../views/layout/footer.php'; ?>
