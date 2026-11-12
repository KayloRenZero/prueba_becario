<?php
/**
 * backend/helpers.php
 *
 * Convención de rutas: todas las páginas viven en backend/<módulo>/<página>.php,
 * por eso las rutas relativas como "../auth/login.php" funcionan desde cualquiera
 * de ellas sin depender del nombre de la carpeta donde se instale el proyecto.
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';

const SESSION_NAME = 'albardi_session';
const SESSION_IDLE_SECONDS = 1800; // 30 minutos sin actividad cierran la sesión
const LOGIN_PAGE = '../auth/login.php';

error_reporting(E_ALL);
ini_set('display_errors', isDevelopment() ? '1' : '0');
ini_set('log_errors', '1');
set_exception_handler('renderUncaughtException');

function isDevelopment(): bool {
    return defined('APP_ENV') && APP_ENV === 'development';
}

/* =====================================================================
   Sesión
   ===================================================================== */

function startSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,                     // la cookie muere al cerrar el navegador
        'path' => '/',
        'secure' => isHttps(),               // solo por HTTPS cuando el sitio lo usa
        'httponly' => true,                  // JS no puede leerla: mitiga robo de sesión por XSS
        'samesite' => 'Lax',                 // no viaja en POST desde otros sitios: defensa extra anti-CSRF
    ]);
    ini_set('session.use_strict_mode', '1'); // rechaza IDs de sesión que el servidor no emitió
    session_start();

    expireIdleSession();
}

function isHttps(): bool {
    return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
}

function expireIdleSession(): void {
    $now = time();
    $lastActivity = $_SESSION['last_activity'] ?? $now;

    if (isset($_SESSION['user_id']) && $now - $lastActivity > SESSION_IDLE_SECONDS) {
        resetSession();
        flash('info', 'Tu sesión expiró por inactividad. Vuelve a iniciar sesión.');
    }
    $_SESSION['last_activity'] = $now;
}

/**
 * Vacía la sesión y cambia su ID. regenerate_id(true) borra en el servidor los
 * datos del ID anterior, así que una cookie robada deja de servir.
 */
function resetSession(): void {
    $_SESSION = [];
    session_regenerate_id(true);
}

/* =====================================================================
   CSRF: un token aleatorio por sesión que cada formulario debe reenviar.
   ===================================================================== */

function csrfToken(): string {
    startSession();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verifyCsrf(mixed $token): bool {
    startSession();
    // hash_equals compara en tiempo constante: no filtra cuántos caracteres coinciden.
    return isset($_SESSION['csrf']) && is_string($token) && hash_equals($_SESSION['csrf'], $token);
}

function csrfField(): string {
    return '<input type="hidden" name="csrf" value="' . e(csrfToken()) . '">';
}

/* =====================================================================
   Autenticación y autorización
   ===================================================================== */

function currentUser(): ?array {
    startSession();
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    return [
        'id' => (int) $_SESSION['user_id'],
        'nombre' => (string) $_SESSION['user_name'],
        'rol' => (string) $_SESSION['user_rol'],
    ];
}

function loginUser(array $user): void {
    startSession();
    session_regenerate_id(true); // nuevo ID tras autenticarse: evita la fijación de sesión
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_name'] = $user['nombre'];
    $_SESSION['user_rol'] = $user['rol'];
    unset($_SESSION['csrf']);    // token CSRF nuevo para la sesión autenticada
}

function logoutUser(): void {
    startSession();
    resetSession();
}

/** Página de inicio según el rol, relativa a cualquier página del backend. */
function homePathFor(string $rol): string {
    return $rol === 'admin' ? '../servicios/index.php' : '../pagos/checkout.php';
}

function requireLogin(): void {
    if (currentUser() === null) {
        flash('info', 'Inicia sesión para continuar.');
        redirect(LOGIN_PAGE);
    }
}

function requireAdmin(): void {
    requireLogin();
    if (currentUser()['rol'] !== 'admin') {
        renderErrorPage(403, 'Acceso denegado', 'Tu cuenta no tiene permisos para administrar servicios.');
    }
}

/* =====================================================================
   Peticiones y respuestas
   ===================================================================== */

function isPost(): bool {
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Lee un campo de texto de $_POST; si no existe o no es string (p. ej. un array), devuelve ''. */
function postString(string $key): string {
    $value = $_POST[$key] ?? '';
    return is_string($value) ? $value : '';
}

function redirect(string $location): void {
    header('Location: ' . $location, true, 303);
    exit;
}

function jsonResponse(int $status, array $payload, array $headers = []): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    foreach ($headers as $name => $value) {
        header($name . ': ' . $value);
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

/* =====================================================================
   Mensajes flash: se guardan en la sesión y se muestran una sola vez,
   normalmente en la página a la que se redirige tras un POST.
   ===================================================================== */

function flash(string $type, string $message): void {
    startSession();
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** @return list<array{type: string, message: string}> */
function takeFlashes(): array {
    startSession();
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/* =====================================================================
   Salida HTML
   ===================================================================== */

/** Escapa para HTML (contenido y atributos). Usar SIEMPRE al imprimir datos. */
function e(string|int|float|null $value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function asset(string $path): string {
    $file = dirname(__DIR__) . '/' . $path;
    $version = is_file($file) ? filemtime($file) : 0;
    return '../../' . $path . '?v=' . $version;
}

function formatMoney(string|int|float $amount): string {
    return '$' . number_format((float) $amount, 2) . ' MXN';
}

function renderErrorPage(int $status, string $title, string $message): void {
    http_response_code($status);
    $pageTitle = $title;
    require __DIR__ . '/views/layout/header.php';
    require __DIR__ . '/views/error.php';
    require __DIR__ . '/views/layout/footer.php';
    exit;
}

/** Último recurso ante errores no controlados: se registra el detalle y se muestra un mensaje genérico. */
function renderUncaughtException(Throwable $e): void {
    error_log(sprintf('[%s] %s en %s:%d', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()));
    $message = isDevelopment() ? $e->getMessage() : 'Inténtalo de nuevo en unos minutos.';

    if (headers_sent()) {
        echo '<p>' . e($message) . '</p>';
        return;
    }
    renderErrorPage(500, 'Ocurrió un error inesperado', $message);
}
