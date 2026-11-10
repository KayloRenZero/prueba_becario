<?php
// backend/api/servicios.php
require_once __DIR__ . '/../helpers.php';
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDB();

if ($method === 'GET') {
    $rows = $pdo->query('SELECT id, nombre, descripcion, precio FROM servicios WHERE activo=1 ORDER BY id DESC')->fetchAll();
    echo json_encode($rows, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'POST') {
    startSession();
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'No autenticado']);
        exit;
    }
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $nombre = trim($input['nombre'] ?? '');
    $desc = trim($input['descripcion'] ?? '');
    $precio = (float)($input['precio'] ?? 0);
    if ($nombre === '' || $precio < 0) {
        http_response_code(422);
        echo json_encode(['error' => 'Datos inválidos']);
        exit;
    }
    $stmt = $pdo->prepare('INSERT INTO servicios (nombre, descripcion, precio) VALUES (:n, :d, :p)');
    $stmt->execute([':n' => $nombre, ':d' => $desc, ':p' => $precio]);
    echo json_encode(['id' => (int)$pdo->lastInsertId()], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);
