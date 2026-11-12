<?php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../lib/servicios.php';

// En la API, cualquier excepción no controlada también responde en JSON (no en HTML).
set_exception_handler(static function (Throwable $e): void {
    error_log(sprintf('[api] %s: %s en %s:%d', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()));
    jsonResponse(500, ['error' => 'Error interno del servidor.']);
});

match ($_SERVER['REQUEST_METHOD'] ?? 'GET') {
    'GET' => handleList(),
    'POST' => handleCreate(),
    default => jsonResponse(405, ['error' => 'Método no permitido.'], ['Allow' => 'GET, POST']),
};

function handleList(): void {
    $services = array_map(static fn(array $s): array => [
        'id' => (int) $s['id'],
        'nombre' => $s['nombre'],
        'descripcion' => $s['descripcion'],
        'precio' => $s['precio'],
    ], listServices(getDB(), onlyActive: true));

    jsonResponse(200, $services);
}

function handleCreate(): void {
    $user = currentUser();
    if ($user === null) {
        jsonResponse(401, ['error' => 'Debes iniciar sesión.']);
    }
    if ($user['rol'] !== 'admin') {
        jsonResponse(403, ['error' => 'Solo un administrador puede crear servicios.']);
    }
    if (!verifyCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        jsonResponse(403, ['error' => 'Token CSRF inválido o ausente (cabecera X-CSRF-Token).']);
    }
    if (!str_starts_with(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) {
        jsonResponse(415, ['error' => 'El cuerpo debe enviarse como JSON (Content-Type: application/json).']);
    }

    try {
        $input = json_decode((string) file_get_contents('php://input'), true, 16, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        jsonResponse(400, ['error' => 'El cuerpo no es un JSON válido.']);
    }
    if (!is_array($input)) {
        jsonResponse(400, ['error' => 'El cuerpo debe ser un objeto JSON.']);
    }

    // Si el cliente no indica "activo", el servicio se publica por defecto.
    ['data' => $data, 'errors' => $errors] = validateService($input + ['activo' => true]);
    if ($errors !== []) {
        jsonResponse(422, ['error' => 'Datos inválidos.', 'campos' => $errors]);
    }

    $id = createService(getDB(), $data);
    jsonResponse(201, [
        'id' => $id,
        'nombre' => $data['nombre'],
        'descripcion' => $data['descripcion'],
        'precio' => $data['precio'],
        'activo' => (bool) $data['activo'],
    ]);
}
