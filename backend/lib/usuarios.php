<?php
// backend/lib/usuarios.php
declare(strict_types=1);

// Hash bcrypt de una contraseña aleatoria que nadie conoce. Se verifica contra él
// cuando el email no existe, para que la respuesta tarde lo mismo que con un email
// real y un atacante no pueda averiguar qué cuentas existen midiendo tiempos.
const DUMMY_PASSWORD_HASH = '$2y$10$AmMNERvHavRfjHmk9VzwhOHI8X0VTO9dIU3/krKpd.pr8UcZrHmq6';

function authenticate(PDO $pdo, string $email, string $password): ?array {
    $user = findUserByEmail($pdo, $email);
    $hash = $user['password'] ?? DUMMY_PASSWORD_HASH;

    if (!password_verify($password, $hash) || $user === null) {
        return null;
    }

    if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        updatePasswordHash($pdo, (int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
    }

    unset($user['password']);
    return $user;
}

function findUserByEmail(PDO $pdo, string $email): ?array {
    $stmt = $pdo->prepare('SELECT id, nombre, email, password, rol FROM usuarios WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();
    return $user === false ? null : $user;
}

function updatePasswordHash(PDO $pdo, int $userId, string $hash): void {
    $stmt = $pdo->prepare('UPDATE usuarios SET password = :password WHERE id = :id');
    $stmt->execute([':password' => $hash, ':id' => $userId]);
}
