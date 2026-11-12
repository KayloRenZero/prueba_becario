<?php
// backend/db.php
require_once __DIR__ . '/config.php';

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        // DB_PORT es opcional en config.php: si no se define, se usa el puerto estándar de MySQL.
        $port = defined('DB_PORT') ? (int) DB_PORT : 3306;
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, $port, DB_NAME);
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false, // prepared statements reales en el servidor
            ]);
        } catch (PDOException $e) {
            error_log('[db] Fallo de conexión: ' . $e->getMessage());
            throw new RuntimeException('No se pudo conectar a la base de datos.');
        }
    }
    return $pdo;
}
