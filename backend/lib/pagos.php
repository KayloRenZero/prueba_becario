<?php
// backend/lib/pagos.php
declare(strict_types=1);

/*
 * Ciclo de vida de un pago (máquina de estados):
 *
 *   pendiente ──► pagado    (simulación o Stripe confirmado; se genera la referencia)
 *       └──────► fallido    (pago rechazado)
 *
 * "pagado" y "fallido" son estados finales: ninguna acción los cambia.
 */
const PAYMENT_PENDING = 'pendiente';
const PAYMENT_PAID = 'pagado';
const PAYMENT_FAILED = 'fallido';

/** @return list<array<string, mixed>> Pagos del usuario, del más reciente al más antiguo. */
function listUserPayments(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare(
        'SELECT p.id, p.servicio_id, p.monto, p.estado, p.referencia, p.creado_en, s.nombre AS servicio
           FROM pagos p
           JOIN servicios s ON s.id = p.servicio_id
          WHERE p.usuario_id = :usuario
          ORDER BY p.id DESC'
    );
    $stmt->execute([':usuario' => $userId]);
    return $stmt->fetchAll();
}

/** Busca un pago SOLO entre los del usuario: así nadie puede ver o pagar el pago de otro (IDOR). */
function findUserPayment(PDO $pdo, int $paymentId, int $userId): ?array {
    $stmt = $pdo->prepare(
        'SELECT p.id, p.servicio_id, p.monto, p.estado, p.referencia, p.creado_en, s.nombre AS servicio
           FROM pagos p
           JOIN servicios s ON s.id = p.servicio_id
          WHERE p.id = :id AND p.usuario_id = :usuario'
    );
    $stmt->execute([':id' => $paymentId, ':usuario' => $userId]);
    $payment = $stmt->fetch();
    return $payment === false ? null : $payment;
}

function findPendingPaymentId(PDO $pdo, int $userId, int $serviceId): ?int {
    $stmt = $pdo->prepare(
        'SELECT id FROM pagos
          WHERE usuario_id = :usuario AND servicio_id = :servicio AND estado = :estado
          ORDER BY id DESC
          LIMIT 1'
    );
    $stmt->execute([':usuario' => $userId, ':servicio' => $serviceId, ':estado' => PAYMENT_PENDING]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int) $id;
}

/**
 * Crea un pago pendiente con el precio vigente del servicio. El monto queda
 * congelado: si después cambia el precio del catálogo, el historial no cambia.
 */
function createPendingPayment(PDO $pdo, int $userId, array $service): int {
    $stmt = $pdo->prepare(
        'INSERT INTO pagos (usuario_id, servicio_id, monto, estado)
         VALUES (:usuario, :servicio, :monto, :estado)'
    );
    $stmt->execute([
        ':usuario' => $userId,
        ':servicio' => (int) $service['id'],
        ':monto' => $service['precio'],
        ':estado' => PAYMENT_PENDING,
    ]);
    return (int) $pdo->lastInsertId();
}

/**
 * Transición pendiente → pagado. La condición "estado = pendiente" dentro del
 * UPDATE hace la operación atómica: si llegan dos clics a la vez, solo uno
 * cambia la fila y el otro recibe false (no se paga dos veces).
 */
function markPaymentPaid(PDO $pdo, int $paymentId, int $userId, string $reference): bool {
    $stmt = $pdo->prepare(
        'UPDATE pagos SET estado = :pagado, referencia = :referencia
          WHERE id = :id AND usuario_id = :usuario AND estado = :pendiente'
    );
    $stmt->execute([
        ':pagado' => PAYMENT_PAID,
        ':referencia' => $reference,
        ':id' => $paymentId,
        ':usuario' => $userId,
        ':pendiente' => PAYMENT_PENDING,
    ]);
    return $stmt->rowCount() === 1;
}

/** Transición pendiente → fallido (pago rechazado). */
function markPaymentFailed(PDO $pdo, int $paymentId, int $userId): bool {
    $stmt = $pdo->prepare(
        'UPDATE pagos SET estado = :fallido
          WHERE id = :id AND usuario_id = :usuario AND estado = :pendiente'
    );
    $stmt->execute([
        ':fallido' => PAYMENT_FAILED,
        ':id' => $paymentId,
        ':usuario' => $userId,
        ':pendiente' => PAYMENT_PENDING,
    ]);
    return $stmt->rowCount() === 1;
}

/**
 * Confirma un pago simulado y devuelve la referencia generada, o null si el
 * pago ya no estaba pendiente. La columna referencia es UNIQUE: ante una
 * colisión (error 1062, muy improbable) se reintenta con otra referencia.
 */
function confirmSimulatedPayment(PDO $pdo, int $paymentId, int $userId): ?string {
    for ($attempt = 1; $attempt <= 3; $attempt++) {
        $reference = 'SIM-' . strtoupper(bin2hex(random_bytes(6))); // 12 hex aleatorios (CSPRNG)
        try {
            return markPaymentPaid($pdo, $paymentId, $userId, $reference) ? $reference : null;
        } catch (PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) !== 1062) {
                throw $e;
            }
        }
    }
    throw new RuntimeException('No se pudo generar una referencia de pago única.');
}

/** @return array{0: string, 1: string} Etiqueta y variante de .badge para cada estado. */
function paymentStatusBadge(string $status): array {
    return match ($status) {
        PAYMENT_PAID => ['Pagado', 'success'],
        PAYMENT_FAILED => ['Fallido', 'danger'],
        default => ['Pendiente', 'warning'],
    };
}

/** Folio legible para el usuario: 7 → "#000007". */
function paymentFolio(int $paymentId): string {
    return '#' . str_pad((string) $paymentId, 6, '0', STR_PAD_LEFT);
}
