<?php
// backend/lib/servicios.php
declare(strict_types=1);

const SERVICE_NAME_MAX = 120;          // VARCHAR(120) en la BD
const SERVICE_DESCRIPTION_MAX = 1000;
const SERVICE_PRICE_PATTERN = '/^\d{1,8}(\.\d{1,2})?$/';

/** Se lanza al intentar borrar un servicio que tiene pagos asociados. */
final class ServiceInUseException extends RuntimeException {}

function validateService(array $input): array {
    $nombre = trim(is_string($input['nombre'] ?? null) ? $input['nombre'] : '');
    $descripcion = trim(is_string($input['descripcion'] ?? null) ? $input['descripcion'] : '');
    $precio = normalizePrice($input['precio'] ?? '');
    $activo = filter_var($input['activo'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;

    $errors = [];

    if ($nombre === '') {
        $errors['nombre'] = 'El nombre es obligatorio.';
    } elseif (mb_strlen($nombre) > SERVICE_NAME_MAX) {
        $errors['nombre'] = sprintf('El nombre no puede superar %d caracteres.', SERVICE_NAME_MAX);
    }

    if (mb_strlen($descripcion) > SERVICE_DESCRIPTION_MAX) {
        $errors['descripcion'] = sprintf('La descripción no puede superar %d caracteres.', SERVICE_DESCRIPTION_MAX);
    }

    if ($precio === '') {
        $errors['precio'] = 'El precio es obligatorio.';
    } elseif (!preg_match(SERVICE_PRICE_PATTERN, $precio)) {
        $errors['precio'] = 'Ingresa un precio válido: número positivo con hasta 2 decimales (ej. 1500.50).';
    } else {
        $precio = number_format((float) $precio, 2, '.', ''); // "1500.5" → "1500.50"
    }

    return [
        'data' => [
            'nombre' => $nombre,
            'descripcion' => $descripcion === '' ? null : $descripcion,
            'precio' => $precio,
            'activo' => $activo,
        ],
        'errors' => $errors,
    ];
}

function normalizePrice(mixed $value): string {
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }
    return is_string($value) ? trim($value) : '';
}

/** @return list<array<string, mixed>> */
function listServices(PDO $pdo, bool $onlyActive = false): array {
    $sql = 'SELECT id, nombre, descripcion, precio, activo, actualizado_en FROM servicios';
    $params = [];
    if ($onlyActive) {
        $sql .= ' WHERE activo = :activo';
        $params[':activo'] = 1;
    }
    $stmt = $pdo->prepare($sql . ' ORDER BY id DESC');
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function findService(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare('SELECT id, nombre, descripcion, precio, activo FROM servicios WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $service = $stmt->fetch();
    return $service === false ? null : $service;
}

function createService(PDO $pdo, array $data): int {
    $stmt = $pdo->prepare(
        'INSERT INTO servicios (nombre, descripcion, precio, activo)
         VALUES (:nombre, :descripcion, :precio, :activo)'
    );
    $stmt->execute([
        ':nombre' => $data['nombre'],
        ':descripcion' => $data['descripcion'],
        ':precio' => $data['precio'],
        ':activo' => $data['activo'],
    ]);
    return (int) $pdo->lastInsertId();
}

function updateService(PDO $pdo, int $id, array $data): void {
    $stmt = $pdo->prepare(
        'UPDATE servicios
            SET nombre = :nombre, descripcion = :descripcion, precio = :precio, activo = :activo
          WHERE id = :id'
    );
    $stmt->execute([
        ':nombre' => $data['nombre'],
        ':descripcion' => $data['descripcion'],
        ':precio' => $data['precio'],
        ':activo' => $data['activo'],
        ':id' => $id,
    ]);
}

function deleteService(PDO $pdo, int $id): bool {
    try {
        $stmt = $pdo->prepare('DELETE FROM servicios WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        if ((int) ($e->errorInfo[1] ?? 0) === 1451) {
            throw new ServiceInUseException('El servicio tiene pagos asociados.', 0, $e);
        }
        throw $e;
    }
}
