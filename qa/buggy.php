<?php
// qa/buggy.php  (NO MODIFICAR LA LÓGICA DE NEGOCIO, SOLO CORREGIR BUGS)
require_once __DIR__ . '/../backend/helpers.php';
startSession();

$pdo = getDB();
$busqueda = $_GET['q'] ?? '';
// FIX BUG 1: SQL injection -> prepared statement
$stmt = $pdo->prepare("SELECT id, nombre, email FROM usuarios WHERE nombre LIKE :q");
$stmt->execute([':q' => '%' . $busqueda . '%']);
$usuarios = $stmt->fetchAll();

// FIX BUG 2: XSS -> escapar salida
foreach ($usuarios as $u) {
    echo "<p>Usuario: " . e($u['nombre']) . " - " . e($u['email']) . "</p>";
}

// FIX BUG 3: validación de email
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "Email inválido";
    } else {
        $ins = $pdo->prepare('INSERT INTO tickets (usuario_id, asunto, mensaje) VALUES (1, :a, :m)');
        $ins->execute([':a' => $_POST['asunto'] ?? '', ':m' => $_POST['mensaje'] ?? '']);
        echo "Ticket creado";
    }
}

// FIX BUG 4: condición correcta (>= 18)
$edad = (int)($_GET['edad'] ?? 0);
if ($edad >= 18) {
    echo "Acceso permitido";
} else {
    echo "Acceso denegado";
}

// FIX BUG 5: typo de variable
$username = $_GET['user'] ?? 'invitado';
echo "Hola, " . $username;
