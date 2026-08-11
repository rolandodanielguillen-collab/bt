<?php
/**
 * bt_app_auth.inc.php — Base compartida de la API de la app móvil.
 * ================================================================
 * Lo usan `bt_app_auth.php` y `bt_app_social.php`. Archivo NUEVO y aislado:
 * no lo toca ni lo necesita nada del sitio existente.
 *
 * Acá viven: conexión, helpers JSON, lectura de input y validación de token.
 * Toda consulta con datos del usuario va con sentencia preparada.
 * ================================================================
 */

if (!defined('BT_APP')) define('BT_APP', true);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
ini_set('display_errors', 0);
error_reporting(E_ALL);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') { http_response_code(204); exit; }

set_exception_handler(function (Throwable $e) {
    error_log('bt_app ' . ($_GET['action'] ?? '?') . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Error interno']);
    exit;
});

// ── Conexión BD (mismo patrón que el resto del sitio) ────────────
if (file_exists(__DIR__ . "/db/conection.inc.php")) {
    include_once __DIR__ . "/db/conection.inc.php";
    @include_once __DIR__ . "/funciones.php";
} else {
    include_once $_SERVER['DOCUMENT_ROOT'] . "/db/conection.inc.php";
    @include_once $_SERVER['DOCUMENT_ROOT'] . "/funciones.php";
}

function resp($data) { echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
function respErr($msg, $code = 400) { http_response_code($code); resp(['success' => false, 'error' => $msg]); }

/**
 * Input del request. Acepta querystring, form y JSON — la app manda JSON.
 * Devuelve siempre escalar: `?x[]=1` no puede colarse como array.
 */
function inp(string $k, $default = '') {
    static $body = null;
    if ($body === null) {
        $raw = file_get_contents('php://input');
        $body = $raw ? (json_decode($raw, true) ?: []) : [];
        if (!is_array($body)) $body = [];
    }
    foreach ([$_GET, $_POST, $body] as $fuente) {
        if (isset($fuente[$k]) && !is_array($fuente[$k])) return trim((string)$fuente[$k]);
    }
    return $default;
}

function inpInt(string $k, int $default = 0): int {
    $v = inp($k, '');
    return $v === '' ? $default : abs((int)$v);
}

/** Sólo dígitos: las cédulas del padrón se guardan sin puntos. */
function normalizarCi(string $ci): string { return preg_replace('/\D/', '', $ci); }

/** El token viaja en `Authorization: Bearer <token>`. */
function tokenDelRequest(): string {
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+([A-Za-z0-9]+)/', $h, $m)) return $m[1];
    return inp('token', '');
}

/**
 * Devuelve el jugador logueado, o corta con 401.
 *
 * Usa la sesión que YA emite el login del sitio (`POST /api/usuario`, que
 * inserta en `_sesion_usuario`). La app no tiene login propio: se loguea
 * contra el mismo endpoint que la web y trae ese token acá.
 *
 * Nota: el token del sitio es `sha1(id.email)` — determinístico y sin
 * vencimiento. Validarlo contra `_sesion_usuario` es más de lo que hace hoy
 * `api/decode.token.inc.php` (que no valida nada), pero no arregla el fondo.
 * El arreglo real es token aleatorio + expiración, y va aparte.
 */
function jugadorActual(mysqli $db): array {
    $token = tokenDelRequest();
    if ($token === '') respErr('No autenticado', 401);

    $st = $db->prepare(
        "SELECT s.ci, u.nombre, u.apellido, u.ciudad, u.email, u.cel
         FROM _sesion_usuario s
         JOIN _p_usuarios u ON u.ci = s.ci
         WHERE s.token = ?
         ORDER BY s.id DESC
         LIMIT 1"
    );
    $st->bind_param('s', $token);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();

    if (!$row) respErr('Sesión inválida', 401);
    return $row;
}

/** Nombre visible de un jugador, ya armado. */
function nombreJugador(?array $u): string {
    if (!$u) return '';
    return trim(($u['nombre'] ?? '') . ' ' . ($u['apellido'] ?? ''));
}
