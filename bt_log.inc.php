<?php
/**
 * bt_log.inc.php — Registro de acciones del admin (tabla `_bt_log`).
 *
 * Se llama UNA vez por punto de entrada (tvt_api, interclubes_resultados,
 * interclubes_sorteo, interclubes.php), no una vez por acción: así las acciones
 * nuevas quedan cubiertas solas.
 *
 * Solo se registran ESCRITURAS. La regla es el verbo con que empieza la acción,
 * no una lista de acciones — mantener una lista se olvida.
 * Lo consulta el superadmin desde tvt_admin_v2 (sección Log).
 */

// Verbos que implican escritura. Todo lo demás (kpis, estado, eventos…) es lectura.
const BT_LOG_VERBOS = ['crear', 'editar', 'eliminar', 'borrar', 'guardar', 'definir',
                       'cambiar', 'generar', 'regenerar', 'asignar', 'quitar', 'limpiar',
                       'toggle', 'agregar', 'actualizar', 'promover', 'en_juego'];

// Nunca guardar el valor de estos parámetros (aunque hoy no viajen por querystring).
const BT_LOG_OCULTOS = ['pass', 'password', 'clave', 'token', 'su_token'];

function bt_log_es_escritura(string $accion): bool {
    foreach (BT_LOG_VERBOS as $v) {
        if (strncmp($accion, $v, strlen($v)) === 0) return true;
    }
    return false;
}

/**
 * @param string $actor  quién lo hizo (usuario admin, o "club: NOMBRE")
 * @param string $origen archivo/pantalla de origen
 * @param string $accion nombre de la acción
 * @param array  $datos  parámetros a guardar (se limpian los sensibles)
 */
function bt_log(mysqli $db, string $actor, string $origen, string $accion, array $datos = []): void {
    if ($accion === '' || !bt_log_es_escritura($accion)) return;
    unset($datos['action'], $datos['accion']);   // ya va en su propia columna
    foreach ($datos as $k => $v) {
        if (in_array(strtolower($k), BT_LOG_OCULTOS, true)) $datos[$k] = '***';
        elseif (is_string($v) && strlen($v) > 200) $datos[$k] = substr($v, 0, 200) . '…';
    }
    $detalle = $datos ? json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
    $ip = substr((string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    // El log nunca puede tumbar la acción que está registrando.
    try {
        $st = $db->prepare("INSERT INTO _bt_log (actor, origen, accion, detalle, ip) VALUES (?,?,?,?,?)");
        $st->bind_param('sssss', $actor, $origen, $accion, $detalle, $ip);
        $st->execute();
    } catch (Throwable $e) {
        error_log('bt_log: ' . $e->getMessage());
    }
}
