<?php
/**
 * bt_app_push.inc.php — Push a los teléfonos vía Expo Push API (fase 3, 20-ago-2026).
 * ================================================================
 * Los tokens los registra la app en `_app_dispositivos` (acción
 * `registrar_dispositivo` de bt_app_social.php). El envío es best-effort:
 * la campana in-app (`_app_notificaciones`) ya quedó escrita antes de llamar
 * acá, así que un fallo de Expo nunca rompe la acción del jugador.
 *
 * Sin cron: los envíos personales salen en línea desde notificar() (0-2
 * dispositivos por jugador) y las difusiones en lote desde el admin.
 * ponytail: envío en línea; mover a cola/cron si el volumen lo pide.
 * ================================================================
 */

const PUSH_URL         = 'https://exp.host/--/api/v2/push/send';
const PUSH_RECIBOS_URL = 'https://exp.host/--/api/v2/push/getReceipts';
const PUSH_LOTE        = 100; // tope de mensajes por request que acepta Expo
const PUSH_TIMEOUT     = 5;   // segundos: que un Expo lento no cuelgue la API
const PUSH_RECIBO_MIN  = 15;  // minutos antes de consultar el recibo (lo que pide Expo)

/**
 * ¿Este tipo de aviso está activo? Lo gobierna `_app_push_config` desde el
 * admin (Avisos app). Un tipo sin fila (p.ej. `sistema`) se considera activo.
 */
function pushActivo(mysqli $db, string $tipo): bool {
    $st = $db->prepare("SELECT activo FROM _app_push_config WHERE tipo = ? LIMIT 1");
    $st->bind_param('s', $tipo);
    $st->execute();
    $r = $st->get_result()->fetch_assoc();
    $st->close();
    return $r === null || (int)$r['activo'] === 1;
}

/**
 * Manda un aviso a los dispositivos de uno o varios jugadores.
 * Devuelve cuántos mensajes aceptó Expo. `destino` viaja en `data` y la app
 * navega ahí al tocar la notificación (leerla sigue exigiendo sesión).
 */
function pushAJugadores(mysqli $db, array $cis, string $tipo, string $titulo, string $cuerpo, ?string $destino): int {
    $cis = array_values(array_unique(array_filter(array_map('strval', $cis))));
    if (!$cis || !pushActivo($db, $tipo)) return 0;

    // Tokens + no-leídas por jugador (badge del ícono en iOS; Android lo ignora).
    $marcas = implode(',', array_fill(0, count($cis), '?'));
    $tipos  = str_repeat('s', count($cis));
    $st = $db->prepare(
        "SELECT d.expo_token, d.plataforma,
                (SELECT COUNT(*) FROM _app_notificaciones n WHERE n.ci = d.ci AND n.leida IS NULL) AS sin_leer
         FROM _app_dispositivos d
         WHERE d.ci IN ($marcas)"
    );
    $st->bind_param($tipos, ...$cis);
    $st->execute();
    $res = $st->get_result();

    $mensajes = [];
    while ($d = $res->fetch_assoc()) {
        $mensajes[] = [
            'to'        => $d['expo_token'],
            'title'     => $titulo,
            'body'      => $cuerpo !== '' ? $cuerpo : null,
            'data'      => ['destino' => $destino ?: '/notificaciones', 'tipo' => $tipo],
            'sound'     => 'default',
            'priority'  => 'high',
            'channelId' => 'default',
            'badge'     => (int)$d['sin_leer'],
        ];
    }
    $st->close();

    return $mensajes ? pushEnviar($db, $mensajes) : 0;
}

/**
 * Envío crudo a Expo en lotes de PUSH_LOTE. Borra de `_app_dispositivos` los
 * tokens que Expo reporta como `DeviceNotRegistered` (app desinstalada).
 * Antes de mandar, revisa los recibos pendientes del envío anterior: el token
 * muerto por REINSTALACIÓN da ticket "ok" y el error recién sale en el recibo
 * (pasó el 20-ago: `enviados` contaba teléfonos que ya no existían).
 */
function pushEnviar(mysqli $db, array $mensajes): int {
    try { pushRevisarRecibos($db); } catch (Throwable $e) { error_log('bt push recibos: ' . $e->getMessage()); }
    $ok = 0;
    foreach (array_chunk($mensajes, PUSH_LOTE) as $lote) {
        $ch = curl_init(PUSH_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_POSTFIELDS     => json_encode($lote, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => PUSH_TIMEOUT,
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false) { error_log("bt push: curl falló: $err"); continue; }
        $resp = json_decode($raw, true);
        // `data` viene alineado 1 a 1 con el lote enviado.
        $tickets = $resp['data'] ?? null;
        if (!is_array($tickets)) { error_log('bt push: respuesta rara de Expo: ' . mb_substr($raw, 0, 300)); continue; }

        foreach ($tickets as $i => $t) {
            if (($t['status'] ?? '') === 'ok') {
                $ok++;
                // El recibo (con el veredicto real de FCM/APNs) se revisa después.
                if (!empty($t['id'])) {
                    $st = $db->prepare("INSERT IGNORE INTO _app_push_tickets (id, expo_token) VALUES (?, ?)");
                    $st->bind_param('ss', $t['id'], $lote[$i]['to']);
                    $st->execute();
                    $st->close();
                }
                continue;
            }
            $detalle = $t['details']['error'] ?? ($t['message'] ?? 'error');
            if ($detalle === 'DeviceNotRegistered') {
                $token = $lote[$i]['to'];
                $st = $db->prepare("DELETE FROM _app_dispositivos WHERE expo_token = ?");
                $st->bind_param('s', $token);
                $st->execute();
                $st->close();
            } else {
                error_log("bt push: ticket con error: $detalle");
            }
        }
    }
    return $ok;
}

/**
 * Consulta a Expo los recibos de envíos anteriores (≥ PUSH_RECIBO_MIN min) y
 * borra los dispositivos cuyo recibo dice `DeviceNotRegistered` — el caso
 * típico es una reinstalación de la app: el ticket dio "ok" pero FCM ya no
 * conoce ese token. Los tickets consultados se descartan; los que Expo aún no
 * resolvió se reintentan en el próximo envío.
 */
function pushRevisarRecibos(mysqli $db): void {
    $res = $db->query(
        "SELECT id, expo_token FROM _app_push_tickets
         WHERE creado < NOW() - INTERVAL " . PUSH_RECIBO_MIN . " MINUTE
         ORDER BY creado ASC LIMIT 300"
    );
    $pendientes = [];
    while ($x = $res->fetch_assoc()) $pendientes[$x['id']] = $x['expo_token'];
    if (!$pendientes) return;

    $ch = curl_init(PUSH_RECIBOS_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_POSTFIELDS     => json_encode(['ids' => array_keys($pendientes)]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => PUSH_TIMEOUT,
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);
    if ($raw === false) return;

    $recibos = json_decode($raw, true)['data'] ?? null;
    if (!is_array($recibos)) return;

    $del = $db->prepare("DELETE FROM _app_dispositivos WHERE expo_token = ?");
    $fin = $db->prepare("DELETE FROM _app_push_tickets WHERE id = ?");
    foreach ($recibos as $id => $r) {
        if (($r['status'] ?? '') === 'error') {
            $detalle = $r['details']['error'] ?? ($r['message'] ?? 'error');
            if ($detalle === 'DeviceNotRegistered' && isset($pendientes[$id])) {
                $del->bind_param('s', $pendientes[$id]);
                $del->execute();
            } else {
                error_log("bt push: recibo con error: $detalle");
            }
        }
        $fin->bind_param('s', $id);
        $fin->execute();
    }
    $del->close();
    $fin->close();
    // Tickets que Expo nunca resolvió (los recibos viejos se descartan de su lado).
    $db->query("DELETE FROM _app_push_tickets WHERE creado < NOW() - INTERVAL 2 DAY");
}
