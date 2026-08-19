<?php
/**
 * app_link.php — respaldo web de los links públicos de la app (19-ago-2026).
 * ================================================================
 * `https://bt.com.py/app/<ruta>` (reescrito por .htaccess: ^app/(.*)$).
 * Si la app está instalada, Android/iOS abren la app directo (App Links /
 * Universal Links verificados por /.well-known/). Si no, o si se abre dentro
 * del navegador de Instagram/WhatsApp, cae acá: flyer + "Abrir en la app",
 * "Descargar" y "Ver en la web". Los meta OG hacen que el link compartido
 * muestre el flyer del evento como vista previa.
 *
 * Rutas: torneo/<id> · inscripcion/<id> · estadisticas · ranking ·
 *        comunidad · comunidad/duplas
 * ================================================================
 */
require_once __DIR__ . '/db/conection.inc.php';

const APP_PACKAGE = 'py.com.bt.app';
const APP_SCHEME  = 'btapp';
const APK_URL     = 'https://bt.com.py/app/bt.apk';
const SITIO       = 'https://bt.com.py';

$ruta   = trim((string)($_GET['ruta'] ?? ''), '/');
$ruta   = preg_replace('~[^a-z0-9/_-]~i', '', $ruta) ?? '';
$partes = $ruta === '' ? [] : explode('/', $ruta);
$seccion = $partes[0] ?? '';
$arg     = $partes[1] ?? '';

$titulo = 'Beach Tennis PY';
$desc   = 'La app de Beach Tennis Paraguay: torneos, inscripciones, ranking, estadísticas y comunidad.';
$imagen = SITIO . '/logo-bt.com.png'; // el mismo og:image de plantilla.php
$web    = SITIO . '/';
$evento = null;

if (($seccion === 'torneo' || $seccion === 'inscripcion') && ctype_digit($arg)) {
    $st = $mysqli2->prepare("SELECT id, evento, nombre_evento2, fecha, fecha_fin, flyer, url_amigable FROM _p_eventos WHERE id = ? LIMIT 1");
    $id = (int)$arg;
    $st->bind_param('i', $id);
    $st->execute();
    $evento = $st->get_result()->fetch_assoc();
    $st->close();
    if ($evento) {
        $titulo = trim($evento['evento'] . ' ' . ($evento['nombre_evento2'] ?? ''));
        $desc   = ($seccion === 'inscripcion' ? 'Inscribite desde la app. ' : '') . 'Del ' . date('d/m/Y', strtotime($evento['fecha'])) . ($evento['fecha_fin'] && $evento['fecha_fin'] !== $evento['fecha'] ? ' al ' . date('d/m/Y', strtotime($evento['fecha_fin'])) : '');
        if (!empty($evento['flyer'])) $imagen = SITIO . '/img/flyers/' . rawurlencode($evento['flyer']);
        if (!empty($evento['url_amigable'])) $web = SITIO . '/torneo-' . $evento['url_amigable'];
    }
} elseif ($seccion === 'ranking') {
    $titulo = 'Ranking · Beach Tennis PY'; $desc = 'Ranking de jugadores e interclubes, en la app.';
} elseif ($seccion === 'estadisticas') {
    $titulo = 'Estadísticas · Beach Tennis PY'; $desc = 'Partidos jugados, ganados y perdidos de cada jugador, en la app.'; $web = SITIO . '/estadisticas.php';
} elseif ($seccion === 'comunidad') {
    $titulo = ($arg === 'duplas' ? 'Busco dupla' : 'Comunidad') . ' · Beach Tennis PY';
    $desc   = $arg === 'duplas' ? '¿Te falta compañero/a? Publicá tu búsqueda en la comunidad de la app.' : 'Muro, búsqueda de dupla y chats entre jugadores, en la app.';
}

$urlApp    = SITIO . '/app/' . $ruta;                                   // App Link (Android/iOS con la app)
$urlScheme = APP_SCHEME . '://app/' . $ruta;                            // esquema propio (iOS / fallback)
$urlIntent = 'intent://bt.com.py/app/' . $ruta . '#Intent;scheme=https;package=' . APP_PACKAGE . ';S.browser_fallback_url=' . rawurlencode(APK_URL) . ';end'; // Chrome Android
$esAndroid = stripos($_SERVER['HTTP_USER_AGENT'] ?? '', 'android') !== false;
$esIos     = preg_match('/iphone|ipad|ipod/i', $_SERVER['HTTP_USER_AGENT'] ?? '') === 1;
$abrir     = $esAndroid ? $urlIntent : ($esIos ? $urlScheme : $urlApp);
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $h($titulo) ?></title>
<meta name="description" content="<?= $h($desc) ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Beach Tennis PY">
<meta property="og:title" content="<?= $h($titulo) ?>">
<meta property="og:description" content="<?= $h($desc) ?>">
<meta property="og:image" content="<?= $h($imagen) ?>">
<meta property="og:url" content="<?= $h($urlApp) ?>">
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" href="/favicon.ico">
<style>
  :root { --navy:#091426; --azul:#316bf3; --naranja:#f97316; --gris:#64748b; --borde:#c5c6cd; }
  * { box-sizing:border-box; }
  body { margin:0; font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif; background:#eef2f7; color:#0f172a; }
  header { background:var(--navy); padding:18px; text-align:center; }
  header img { height:44px; }
  main { max-width:420px; margin:0 auto; padding:18px; }
  .card { background:#fff; border:1px solid var(--borde); border-radius:18px; overflow:hidden; }
  .card img.flyer { display:block; width:100%; aspect-ratio:4/5; object-fit:cover; background:#0b1a33; }
  .cuerpo { padding:18px; }
  h1 { font-size:20px; margin:0 0 6px; line-height:1.25; }
  p { margin:0 0 16px; color:var(--gris); font-size:14px; line-height:1.45; }
  a.btn { display:block; text-align:center; text-decoration:none; font-weight:700; font-size:14px; letter-spacing:.04em; padding:14px; border-radius:12px; margin-bottom:10px; }
  .btn-app { background:var(--azul); color:#fff; }
  .btn-apk { background:var(--naranja); color:#fff; }
  .btn-web { background:#fff; color:var(--navy); border:1px solid var(--borde); }
  small { display:block; color:var(--gris); font-size:12px; text-align:center; margin-top:10px; line-height:1.5; }
</style>
</head>
<body>
<header><img src="/logo-bt.com.png" alt="Beach Tennis PY"></header>
<main>
  <div class="card">
    <?php if ($evento && !empty($evento['flyer'])): ?><img class="flyer" src="<?= $h($imagen) ?>" alt="<?= $h($titulo) ?>"><?php endif; ?>
    <div class="cuerpo">
      <h1><?= $h($titulo) ?></h1>
      <p><?= $h($desc) ?></p>
      <a class="btn btn-app" href="<?= $h($abrir) ?>">ABRIR EN LA APP</a>
      <?php if (!$esIos): ?><a class="btn btn-apk" href="<?= $h(APK_URL) ?>">DESCARGAR LA APP (ANDROID)</a><?php endif; ?>
      <a class="btn btn-web" href="<?= $h($web) ?>">VER EN LA WEB</a>
      <small>¿Ya tenés la app instalada y no se abrió sola? Tocá "Abrir en la app".<?= $esIos ? ' La versión para iPhone llega pronto.' : '' ?></small>
    </div>
  </div>
</main>
</body>
</html>
