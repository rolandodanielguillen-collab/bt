<?php
/**
 * bt_app_admin.actions.php — acciones de tvt_api.php para la app (18-ago-2026)
 * ================================================================
 * Se incluye desde tvt_api.php ANTES del "Acción no reconocida", así reutiliza
 * su auth de sesión de admin, $mysqli2, resp()/respErr() y el bt_log.
 * Secciones del admin: Moderación (denuncias) y Avisos app (push + difusiones).
 * Sólo superadmin (el moderador es el dueño).
 * ================================================================
 */
if (!isset($mysqli2) || !function_exists('resp')) { http_response_code(500); exit; }
require_once __DIR__ . '/bt_app_moderacion.inc.php';

$accionesApp = ['mod_denuncias', 'mod_resolver', 'mod_jugador', 'mod_suspender', 'mod_levantar', 'mod_pendientes',
                'avisos_config', 'avisos_toggle', 'difusiones', 'difusion_crear'];
if (in_array($action, $accionesApp, true)) {
    if (($_SESSION['admin_tipo'] ?? '') !== 'superadmin') respErr('Sólo superadmin');
    $adminActor = (string)($_SESSION['admin_user'] ?? 'admin');

    /** Nombre + CI de un jugador para mostrar. */
    $nombreCi = function (?string $ci) use ($mysqli2): string {
        if (!$ci) return '';
        $st = $mysqli2->prepare("SELECT CONCAT(nombre, ' ', apellido) n FROM _p_usuarios WHERE ci = ? LIMIT 1");
        $st->bind_param('s', $ci); $st->execute(); $r = $st->get_result()->fetch_assoc(); $st->close();
        return trim(($r['n'] ?? '') . " ($ci)");
    };

    // ── Moderación ─────────────────────────────────────────────────
    if ($action === 'mod_pendientes') {
        resp(['success' => true, 'pendientes' => (int)$mysqli2->query("SELECT COUNT(*) c FROM _app_denuncias WHERE estado = 'pendiente'")->fetch_assoc()['c']]);
    }

    if ($action === 'mod_denuncias') {
        $estado = strGet('estado', 'pendiente');
        $where = in_array($estado, ['pendiente', 'resuelta', 'descartada'], true) ? "WHERE d.estado = '$estado'" : '';
        $r = $mysqli2->query("SELECT d.*, CONCAT(u1.nombre,' ',u1.apellido) denunciante, CONCAT(u2.nombre,' ',u2.apellido) denunciado,
                                     j.suspendido_hasta, j.suspendido_motivo
                              FROM _app_denuncias d
                              LEFT JOIN _p_usuarios u1 ON u1.ci = d.ci_denunciante
                              LEFT JOIN _p_usuarios u2 ON u2.ci = d.ci_denunciado
                              LEFT JOIN _app_jugadores j ON j.ci = d.ci_denunciado
                              $where ORDER BY d.creado DESC LIMIT 200");
        $out = [];
        while ($d = $r->fetch_assoc()) {
            $out[] = ['id' => (int)$d['id'], 'tipo' => $d['tipo'], 'refId' => $d['ref_id'] ? (int)$d['ref_id'] : null, 'motivo' => $d['motivo'],
                      'detalle' => $d['detalle'], 'texto' => $d['texto'], 'creado' => $d['creado'], 'estado' => $d['estado'],
                      'accion' => $d['accion'], 'resueltoPor' => $d['resuelto_por'], 'resueltoEn' => $d['resuelto_en'],
                      'denunciante' => trim(($d['denunciante'] ?? '') . " ({$d['ci_denunciante']})"), 'ciDenunciante' => $d['ci_denunciante'],
                      'denunciado' => trim(($d['denunciado'] ?? '') . " ({$d['ci_denunciado']})"), 'ciDenunciado' => $d['ci_denunciado'],
                      'suspendidoHasta' => $d['suspendido_hasta'], 'suspendidoMotivo' => $d['suspendido_motivo']];
        }
        resp(['success' => true, 'denuncias' => $out]);
    }

    /** Aplica una suspensión y avisa al jugador por notificación in-app. */
    $suspender = function (string $ci, string $dias, string $motivo) use ($mysqli2, $adminActor): string {
        $hasta = $dias === 'perm' ? '9999-12-31 00:00:00' : date('Y-m-d H:i:s', time() + ((int)$dias) * 86400);
        $st = $mysqli2->prepare("INSERT INTO _app_jugadores (ci, suspendido_hasta, suspendido_motivo, suspendido_por) VALUES (?, ?, ?, ?)
                                 ON DUPLICATE KEY UPDATE suspendido_hasta = VALUES(suspendido_hasta), suspendido_motivo = VALUES(suspendido_motivo), suspendido_por = VALUES(suspendido_por)");
        $st->bind_param('ssss', $ci, $hasta, $motivo, $adminActor); $st->execute(); $st->close();
        $txt = $dias === 'perm' ? 'de forma permanente' : "hasta el " . date('d/m/Y', strtotime($hasta));
        notificar($mysqli2, $ci, 'sistema', 'Tu cuenta fue suspendida en la comunidad', "Suspensión $txt. Motivo: $motivo. Podés escribir a soporte@bt.com.py.", null);
        return $hasta;
    };

    if ($action === 'mod_resolver') {
        $id = intGet('id'); $acc = strGet('accion'); $motivo = strGet('motivo', 'Incumplimiento de las normas de la comunidad');
        if (!$id) respErr('Falta id');
        $st = $mysqli2->prepare("SELECT * FROM _app_denuncias WHERE id = ? LIMIT 1"); $st->bind_param('i', $id); $st->execute();
        $d = $st->get_result()->fetch_assoc(); $st->close();
        if (!$d) respErr('Denuncia inexistente');
        $ok = in_array($acc, ['descartar', 'borrar', 'suspender_7', 'suspender_30', 'suspender_perm', 'borrar_suspender_7', 'borrar_suspender_30', 'borrar_suspender_perm'], true);
        if (!$ok) respErr('Acción inválida');

        if (str_starts_with($acc, 'borrar') && $d['ref_id']) {
            $q = ['post' => "UPDATE _app_posts SET estado = 'borrado' WHERE id = ?",
                  'comentario' => "UPDATE _app_comentarios SET estado = 'borrado' WHERE id = ?",
                  'dupla' => "UPDATE _app_busca_dupla SET estado = 'cerrada' WHERE id = ?",
                  'mensaje' => "UPDATE _app_mensajes SET texto = '[mensaje eliminado por moderación]' WHERE id = ?"][$d['tipo']] ?? null;
            if ($q) { $st = $mysqli2->prepare($q); $st->bind_param('i', $d['ref_id']); $st->execute(); $st->close(); }
            if ($d['tipo'] === 'comentario') { $mysqli2->query("UPDATE _app_posts p SET comentarios = (SELECT COUNT(*) FROM _app_comentarios c WHERE c.id_post = p.id AND c.estado = 'publicado') WHERE p.id = (SELECT id_post FROM _app_comentarios WHERE id = " . (int)$d['ref_id'] . ")"); }
        }
        if (str_contains($acc, 'suspender') && $d['ci_denunciado']) {
            $dias = str_ends_with($acc, 'perm') ? 'perm' : (str_ends_with($acc, '30') ? '30' : '7');
            $suspender((string)$d['ci_denunciado'], $dias, $motivo);
        }
        $estado = $acc === 'descartar' ? 'descartada' : 'resuelta';
        $st = $mysqli2->prepare("UPDATE _app_denuncias SET estado = ?, accion = ?, resuelto_por = ?, resuelto_en = NOW() WHERE id = ?");
        $st->bind_param('sssi', $estado, $acc, $adminActor, $id); $st->execute(); $st->close();
        // Todas las denuncias pendientes del mismo contenido se cierran juntas.
        if ($d['ref_id']) {
            $st = $mysqli2->prepare("UPDATE _app_denuncias SET estado = ?, accion = ?, resuelto_por = ?, resuelto_en = NOW() WHERE estado = 'pendiente' AND tipo = ? AND ref_id = ?");
            $st->bind_param('ssssi', $estado, $acc, $adminActor, $d['tipo'], $d['ref_id']); $st->execute(); $st->close();
        }
        resp(['success' => true]);
    }

    if ($action === 'mod_suspender') {
        $ci = preg_replace('/\D/', '', strGet('ci')); $dias = strGet('dias', '7'); $motivo = strGet('motivo', 'Incumplimiento de las normas de la comunidad');
        if (!$ci) respErr('Falta CI');
        if (!in_array($dias, ['7', '30', 'perm'], true)) respErr('Días inválidos');
        resp(['success' => true, 'hasta' => $suspender($ci, $dias, $motivo)]);
    }

    if ($action === 'mod_levantar') {
        $ci = preg_replace('/\D/', '', strGet('ci'));
        $st = $mysqli2->prepare("UPDATE _app_jugadores SET suspendido_hasta = NULL, suspendido_motivo = NULL WHERE ci = ?");
        $st->bind_param('s', $ci); $st->execute(); $st->close();
        notificar($mysqli2, $ci, 'sistema', 'Tu suspensión terminó', 'Ya podés volver a participar en la comunidad.', '/comunidad');
        resp(['success' => true]);
    }

    if ($action === 'mod_jugador') {
        $ci = preg_replace('/\D/', '', strGet('ci'));
        $j = jugadorApp($mysqli2, $ci);
        $r = $mysqli2->query("SELECT COUNT(*) c FROM _app_denuncias WHERE ci_denunciado = '" . $mysqli2->real_escape_string($ci) . "'")->fetch_assoc();
        $p = $mysqli2->query("SELECT COUNT(*) c FROM _app_posts WHERE ci_autor = '" . $mysqli2->real_escape_string($ci) . "' AND estado = 'publicado'")->fetch_assoc();
        resp(['success' => true, 'jugador' => ['ci' => $ci, 'nombre' => $nombreCi($ci), 'terminos' => $j['terminos_en'], 'suspendidoHasta' => $j['suspendido_hasta'], 'suspendidoMotivo' => $j['suspendido_motivo'],
              'denunciasRecibidas' => (int)$r['c'], 'posts' => (int)$p['c'], 'eliminadoEn' => $j['eliminado_en']]]);
    }

    // ── Avisos app (push) ──────────────────────────────────────────
    if ($action === 'avisos_config') {
        $r = $mysqli2->query("SELECT tipo, titulo, activo, orden FROM _app_push_config ORDER BY orden ASC");
        $out = []; while ($x = $r->fetch_assoc()) $out[] = ['tipo' => $x['tipo'], 'titulo' => $x['titulo'], 'activo' => (bool)$x['activo']];
        $disp = (int)$mysqli2->query("SELECT COUNT(*) c FROM _app_dispositivos")->fetch_assoc()['c'];
        $jug = (int)$mysqli2->query("SELECT COUNT(*) c FROM _app_jugadores WHERE terminos_en IS NOT NULL AND eliminado_en IS NULL")->fetch_assoc()['c'];
        resp(['success' => true, 'config' => $out, 'dispositivos' => $disp, 'jugadoresApp' => $jug]);
    }

    if ($action === 'avisos_toggle') {
        $tipo = strGet('tipo'); $activo = strGet('activo') === '1' ? 1 : 0;
        $st = $mysqli2->prepare("UPDATE _app_push_config SET activo = ? WHERE tipo = ?");
        $st->bind_param('is', $activo, $tipo); $st->execute(); $st->close();
        resp(['success' => true]);
    }

    if ($action === 'difusiones') {
        $r = $mysqli2->query("SELECT id, titulo, cuerpo, destino, filtro, filtro_id, creado_por, creado, enviado_en, enviados FROM _app_difusiones ORDER BY creado DESC LIMIT 50");
        $out = []; while ($x = $r->fetch_assoc()) $out[] = $x;
        resp(['success' => true, 'difusiones' => $out]);
    }

    /**
     * difusion_crear — la deja lista para el cron de push (fase siguiente) y ADEMÁS
     * la pone ya en la campana (in-app) de los jugadores que usan la app.
     */
    if ($action === 'difusion_crear') {
        $titulo = mb_substr(strGet('titulo'), 0, 120); $cuerpo = mb_substr(strGet('cuerpo'), 0, 500);
        $destino = mb_substr(strGet('destino'), 0, 160) ?: null; $filtro = strGet('filtro', 'todos'); $filtroId = intGet('filtro_id', 0) ?: null;
        if ($titulo === '' || $cuerpo === '') respErr('Falta título o texto');
        if (!in_array($filtro, ['todos', 'evento', 'circuito'], true)) respErr('Filtro inválido');
        $st = $mysqli2->prepare("INSERT INTO _app_difusiones (titulo, cuerpo, destino, filtro, filtro_id, creado_por) VALUES (?, ?, ?, ?, ?, ?)");
        $st->bind_param('ssssis', $titulo, $cuerpo, $destino, $filtro, $filtroId, $adminActor); $st->execute();
        $idDif = (int)$mysqli2->insert_id; $st->close();

        // Destinatarios: todos los que usan la app, o los inscriptos del evento / del circuito.
        if ($filtro === 'evento' && $filtroId) {
            $q = "SELECT DISTINCT i.ci FROM _p_incripciones i WHERE i.id_evento = $filtroId AND i.ci IN (SELECT ci FROM _app_jugadores WHERE eliminado_en IS NULL)";
        } elseif ($filtro === 'circuito' && $filtroId) {
            $q = "SELECT DISTINCT i.ci FROM _p_incripciones i JOIN _p_eventos e ON e.id = i.id_evento WHERE e.id_circuito = $filtroId AND i.ci IN (SELECT ci FROM _app_jugadores WHERE eliminado_en IS NULL)";
        } else {
            $q = "SELECT ci FROM _app_jugadores WHERE eliminado_en IS NULL";
        }
        $n = 0; $r = $mysqli2->query($q);
        while ($x = $r->fetch_assoc()) { notificar($mysqli2, (string)$x['ci'], 'difusion', $titulo, $cuerpo, $destino); $n++; }
        resp(['success' => true, 'id' => $idDif, 'inApp' => $n]);
    }
}
