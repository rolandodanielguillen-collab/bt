<?php
/**
 * bt_app_apns.php — Envío DIRECTO a Apple (APNs), sin pasar por Expo.
 * ================================================================
 * Sólo diagnóstico, se corre por CLI:
 *
 *   php bt_app_apns.php <ci> [badge] [titulo]
 *
 * Existe porque el numerito del ícono no aparece en el iPhone aunque Expo
 * acepte el envío y el permiso esté concedido (20-ago-2026). Si el badge SÍ
 * aparece por esta vía, el problema está en la capa de Expo; si tampoco,
 * está del lado del teléfono.
 *
 * Necesita `_app_dispositivos.token_nativo` (lo manda la app) y la llave APNs
 * en /home/bt.com.py/.secrets/AuthKey_<KEY_ID>.p8.
 * ================================================================
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

const APNS_KEY_ID   = '7PQGAJ8238';
const APNS_TEAM_ID  = 'MP6XMBUFRJ';
const APNS_BUNDLE   = 'py.com.bt.app';
const APNS_KEY_PATH = '/home/bt.com.py/.secrets/AuthKey_7PQGAJ8238.p8';
/** La app de TestFlight/App Store usa producción; sandbox es sólo para builds de Xcode. */
const APNS_HOST = 'https://api.push.apple.com';

require __DIR__ . '/db/conection.inc.php';

/** JWT ES256 para APNs. La firma va cruda (r||s): el DER de OpenSSL da 403. */
function apnsToken(): string {
    $clave = file_get_contents(APNS_KEY_PATH);
    if ($clave === false) throw new RuntimeException('No pude leer la llave APNs');
    $b64 = fn(array $o): string => rtrim(strtr(base64_encode(json_encode($o)), '+/', '-_'), '=');
    $cabecera = $b64(['alg' => 'ES256', 'kid' => APNS_KEY_ID]);
    $cuerpo   = $b64(['iss' => APNS_TEAM_ID, 'iat' => time()]);
    $firmaDer = '';
    openssl_sign("$cabecera.$cuerpo", $firmaDer, openssl_pkey_get_private($clave), OPENSSL_ALGO_SHA256);
    // DER (30 len 02 lenR R 02 lenS S) → 64 bytes crudos (r||s).
    $pos = 3;
    $lenR = ord($firmaDer[$pos]); $pos++;
    $r = ltrim(substr($firmaDer, $pos, $lenR), "\x00"); $pos += $lenR + 1;
    $lenS = ord($firmaDer[$pos]); $pos++;
    $s = ltrim(substr($firmaDer, $pos, $lenS), "\x00");
    $crudo = str_pad($r, 32, "\x00", STR_PAD_LEFT) . str_pad($s, 32, "\x00", STR_PAD_LEFT);
    return "$cabecera.$cuerpo." . rtrim(strtr(base64_encode($crudo), '+/', '-_'), '=');
}

$ci     = $argv[1] ?? '';
$badge  = isset($argv[2]) ? (int)$argv[2] : 3;
$titulo = $argv[3] ?? 'Prueba directa a Apple';
if ($ci === '') { fwrite(STDERR, "uso: php bt_app_apns.php <ci> [badge] [titulo]\n"); exit(1); }

$st = $mysqli2->prepare("SELECT token_nativo FROM _app_dispositivos WHERE ci = ? AND plataforma = 'ios' AND token_nativo IS NOT NULL ORDER BY id DESC LIMIT 1");
$st->bind_param('s', $ci);
$st->execute();
$fila = $st->get_result()->fetch_assoc();
$st->close();
if (!$fila) { fwrite(STDERR, "Ese jugador no tiene token nativo de iOS todavía (hace falta abrir la app con el build nuevo).\n"); exit(1); }

$payload = json_encode(['aps' => [
    'alert' => ['title' => $titulo, 'body' => "El icono deberia mostrar $badge"],
    'badge' => $badge,
    'sound' => 'default',
]]);

$ch = curl_init(APNS_HOST . '/3/device/' . $fila['token_nativo']);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER         => true,
    CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_2_0,
    CURLOPT_HTTPHEADER     => [
        'authorization: bearer ' . apnsToken(),
        'apns-topic: ' . APNS_BUNDLE,
        'apns-push-type: alert',
        'apns-priority: 10',
        'content-type: application/json',
    ],
]);
$respuesta = curl_exec($ch);
$estado = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "APNs HTTP $estado\n";
if ($error) echo "curl: $error\n";
if ($estado !== 200) echo trim($respuesta) . "\n";
echo $estado === 200 ? "Enviado con badge=$badge — mirá el ícono.\n" : "FALLÓ.\n";
