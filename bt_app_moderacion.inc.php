<?php
/**
 * bt_app_moderacion.inc.php — Reglas de la comunidad de la app (18-ago-2026)
 * ================================================================
 * Lo que las tiendas exigen por contenido de usuarios (Google Play UGC /
 * App Store 1.2): términos aceptados, filtro básico, denunciar, bloquear,
 * suspensión. Lo usa bt_app_social.php (app) y tvt_api.php (admin).
 *
 * Config fuera del docroot: /home/bt.com.py/.bt_app.env con
 *   MODERACION_MAIL, TELEGRAM_BOT_TOKEN, TELEGRAM_CHAT_ID
 * ================================================================
 */

const TERMINOS_VERSION = '2026-08-18';
const MODERACION_ENV = '/home/bt.com.py/.bt_app.env';
/** Segundos mínimos entre publicaciones del mismo jugador (anti-flood). */
const FLOOD_SEGUNDOS = 10;

/** Palabras que no pasan (minúsculas, sin acentos). ponytail: lista corta a mano; el moderador es el filtro real. */
const PALABRAS_PROHIBIDAS = [
    'puto', 'puta', 'mierda', 'pelotudo', 'pelotuda', 'forro', 'forra', 'hijo de puta', 'hdp',
    'conchudo', 'conchuda', 'boludo', 'boluda', 'idiota', 'imbecil', 'estupido', 'estupida',
    'marica', 'maricon', 'negro de mierda', 'kurepa', 'nazi',
];

function modEnv(): array {
    static $env = null;
    if ($env !== null) return $env;
    $env = [];
    if (is_readable(MODERACION_ENV)) {
        foreach (file(MODERACION_ENV, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
            if ($l[0] === '#' || !str_contains($l, '=')) continue;
            [$k, $v] = explode('=', $l, 2);
            $env[trim($k)] = trim($v, " \t\"'");
        }
    }
    return $env;
}

/** Sin acentos ni mayúsculas, para comparar contra la lista. */
function modNormalizar(string $s): string {
    $s = mb_strtolower($s, 'UTF-8');
    $s = strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    return preg_replace('/[^a-z0-9 ]+/', ' ', $s);
}

/** true si el texto contiene alguna palabra prohibida (palabra entera). */
function textoProhibido(string $texto): bool {
    $n = ' ' . preg_replace('/\s+/', ' ', modNormalizar($texto)) . ' ';
    foreach (PALABRAS_PROHIBIDAS as $p) {
        if (str_contains($n, ' ' . $p . ' ')) return true;
    }
    return false;
}

/** Fila de _app_jugadores del CI (la crea si no existe). */
function jugadorApp(mysqli $db, string $ci): array {
    $st = $db->prepare("INSERT IGNORE INTO _app_jugadores (ci) VALUES (?)");
    $st->bind_param('s', $ci); $st->execute(); $st->close();
    $st = $db->prepare("SELECT ci, terminos_version, terminos_en, suspendido_hasta, suspendido_motivo, eliminado_en FROM _app_jugadores WHERE ci = ? LIMIT 1");
    $st->bind_param('s', $ci); $st->execute();
    $row = $st->get_result()->fetch_assoc(); $st->close();
    return $row ?: ['ci' => $ci];
}

function terminosAceptados(array $j): bool {
    return ($j['terminos_version'] ?? '') === TERMINOS_VERSION;
}

/** null si puede escribir; si no, [hasta, motivo]. */
function suspension(array $j): ?array {
    $h = $j['suspendido_hasta'] ?? null;
    if (!$h) return null;
    if ($h !== '9999-12-31 00:00:00' && strtotime($h) < time()) return null;
    return ['hasta' => $h, 'motivo' => $j['suspendido_motivo'] ?? ''];
}

/** CIs que YO bloqueé + CIs que me bloquearon a mí (para chats es en los dos sentidos). */
function bloqueosDe(mysqli $db, string $ci): array {
    $st = $db->prepare("SELECT ci_bloqueado AS otro FROM _app_bloqueos WHERE ci = ? UNION SELECT ci FROM _app_bloqueos WHERE ci_bloqueado = ?");
    $st->bind_param('ss', $ci, $ci); $st->execute();
    $r = $st->get_result(); $out = [];
    while ($x = $r->fetch_assoc()) $out[] = (string)$x['otro'];
    $st->close();
    return $out;
}

/** Sólo los que YO bloqueé (para no ver su muro/comentarios). */
function bloqueadosPor(mysqli $db, string $ci): array {
    $st = $db->prepare("SELECT ci_bloqueado FROM _app_bloqueos WHERE ci = ?");
    $st->bind_param('s', $ci); $st->execute();
    $r = $st->get_result(); $out = [];
    while ($x = $r->fetch_assoc()) $out[] = (string)$x['ci_bloqueado'];
    $st->close();
    return $out;
}

/** Cláusula "AND col NOT IN (...)" segura, o '' si la lista está vacía. */
function sqlNoEn(mysqli $db, string $col, array $cis): string {
    if (!$cis) return '';
    return " AND $col NOT IN (" . implode(',', array_map(fn($c) => "'" . $db->real_escape_string($c) . "'", $cis)) . ")";
}

/**
 * Notificación in-app (campana / pantalla Notificaciones). El push de fase 3
 * lee de acá. `destino` es la ruta de la app (p.ej. /chat/12).
 */
function notificar(mysqli $db, string $ci, string $tipo, string $titulo, string $cuerpo = '', ?string $destino = null): void {
    if ($ci === '') return;
    $st = $db->prepare("INSERT INTO _app_notificaciones (ci, tipo, titulo, cuerpo, destino) VALUES (?, ?, ?, ?, ?)");
    $st->bind_param('sssss', $ci, $tipo, $titulo, $cuerpo, $destino);
    $st->execute(); $st->close();
}

/** Aviso al moderador: mail + Telegram (los dos best-effort; el admin siempre lo lista). */
function avisarModerador(string $asunto, string $texto): void {
    $env = modEnv();
    $mail = $env['MODERACION_MAIL'] ?? 'soporte@bt.com.py';
    @mail($mail, '[BT app] ' . $asunto, $texto . "\n\nModeración: https://bt.com.py/tvt_admin_v2.php (Moderación)", "From: app@bt.com.py\r\nContent-Type: text/plain; charset=UTF-8");
    $tok = $env['TELEGRAM_BOT_TOKEN'] ?? ''; $chat = $env['TELEGRAM_CHAT_ID'] ?? '';
    if ($tok && $chat) {
        $ch = curl_init("https://api.telegram.org/bot{$tok}/sendMessage");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['chat_id' => $chat, 'text' => "🛡️ BT app — {$asunto}\n{$texto}"]),
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 6,
        ]);
        curl_exec($ch); curl_close($ch);
    }
}
