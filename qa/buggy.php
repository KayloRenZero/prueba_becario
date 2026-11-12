<?php
// qa/buggy.php  (NO MODIFICAR LA LÓGICA DE NEGOCIO, SOLO CORREGIR BUGS)
require_once __DIR__ . '/../backend/helpers.php';
startSession();

$pdo = getDB();
// FIX BUG 7: "?q[]=x" llega como array; queryString() lo trata como búsqueda vacía
$busqueda = queryString('q');
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
    // FIX BUG 7: postString() descarta arrays (trim() con un array lanzaba TypeError -> 500)
    $email = trim(postString('email'));
    $asunto = trim(postString('asunto'));
    $mensaje = trim(postString('mensaje'));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "Email inválido";
    // FIX BUG 8: asunto y mensaje obligatorios y dentro del tamaño de su columna
    // (asunto VARCHAR(150) en caracteres; mensaje TEXT, 65 535 bytes)
    } elseif ($asunto === '' || mb_strlen($asunto) > 150) {
        echo "Asunto inválido: es obligatorio y admite hasta 150 caracteres";
    } elseif ($mensaje === '' || strlen($mensaje) > 65535) {
        echo "Mensaje inválido: es obligatorio";
    } else {
        $ins = $pdo->prepare('INSERT INTO tickets (usuario_id, asunto, mensaje) VALUES (1, :a, :m)');
        $ins->execute([':a' => $asunto, ':m' => $mensaje]);
        echo "Ticket creado";
    }
}

// FIX BUG 4: condición correcta (>= 18)
// FIX BUG 9: (int) convertía "18abc" en 18 y "1e3" en 1000; ahora solo se acepta un entero real
$edad = filter_input(INPUT_GET, 'edad', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 0, 'max_range' => 150],
]);
if (is_int($edad) && $edad >= 18) {
    echo "Acceso permitido";
} else {
    echo "Acceso denegado";
}

// FIX BUG 5: typo de variable
// FIX BUG 6: XSS reflejado -> el parámetro de la URL se escapa antes de imprimirlo
$username = queryString('user', 'invitado');
echo "Hola, " . e($username);
