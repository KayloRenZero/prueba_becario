<?php
// backend/auth/login.php
require_once __DIR__ . '/../helpers.php';

startSession();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $token = $_POST['csrf'] ?? '';

    if (!verifyCsrf($token)) {
        $error = 'Token inválido.';
    } else {
        $stmt = getDB()->prepare('SELECT id, nombre, password, rol FROM usuarios WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['nombre'];
            $_SESSION['user_rol'] = $user['rol'];
            header('Location: ../servicios/index.php');
            exit;
        } else {
            $error = 'Credenciales incorrectas.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Login — Grupo Albardi</title>
  <link rel="stylesheet" href="../../frontend/css/styles.css">
</head>
<body>
  <section class="section">
    <div class="container">
      <h2>Iniciar sesión</h2>
      <?php if ($error): ?><p style="color:#c0392b"><?php echo e($error); ?></p><?php endif; ?>
      <form method="post" class="form">
        <input type="hidden" name="csrf" value="<?php echo e(csrfToken()); ?>">
        <div class="field">
          <label>Email</label>
          <input type="email" name="email" required>
        </div>
        <div class="field">
          <label>Contraseña</label>
          <input type="password" name="password" required>
        </div>
        <button class="btn btn--primary" type="submit">Entrar</button>
      </form>
      <p><a href="../../frontend/index.html">Volver</a></p>
    </div>
  </section>
</body>
</html>
