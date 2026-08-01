<?php
/**
 * interclubes-registro.php — Auto-registro de clubes (torneos Interclubes)
 * ================================================================
 * La organización comparte UN link por evento:
 *   https://bt.com.py/interclubes-registro.php?ev=<sha1(id_evento)>
 * El dueño del club se registra y el sistema lo da de alta en
 * _p_clubes con su token propio, redirigiéndolo a su formulario
 * de inscripción (interclubes.php?token=...).
 * ================================================================
 */
session_start();
include_once "db/conection.inc.php";

// ── Resolver evento por sha1(id) (mismo patrón que el resto del sitio) ───────
$ev = isset($_GET['ev']) ? trim($_GET['ev']) : '';
$eventoIC = null;

if (preg_match('/^[a-f0-9]{40}$/i', $ev)) {
    $st = $mysqli2->prepare(
        "SELECT id, evento, nombre_evento2, estado, fecha, fecha_fin_inscripcion, flyer
           FROM _p_eventos
          WHERE sha1(id) = ? AND id_tipo_evento = 5 LIMIT 1");
    $st->bind_param('s', $ev);
    $st->execute();
    $eventoIC = $st->get_result()->fetch_assoc();
}

if (!$eventoIC) {
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>Link inválido</title></head>'
       . '<body style="font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#f4f6f8;margin:0;">'
       . '<div style="text-align:center;padding:24px;"><h2>Link inválido</h2><p>Consultá con la organización del torneo.</p></div></body></html>';
    exit;
}

$idEvento = (int)$eventoIC['id'];

// ── ¿Registro abierto? (mismo criterio que la inscripción) ───────────────────
$abierta = in_array($eventoIC['estado'], ['activo', 'registro'], true);
$fcierre = $eventoIC['fecha_fin_inscripcion'];
if ($abierta && $fcierre && $fcierre !== '0000-00-00' && date('Y-m-d') > $fcierre) {
    $abierta = false;
}

// ── POST: registrar club ─────────────────────────────────────────────────────
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $abierta) {
    $nombre = trim(strip_tags($_POST['nombre'] ?? ''));
    $resp   = trim(strip_tags($_POST['responsable'] ?? ''));
    $cel    = preg_replace('/[^0-9]/', '', $_POST['celular'] ?? '');
    $mail   = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);

    if ($nombre === '' || $resp === '' || $cel === '') {
        $error = 'Completá nombre del club, responsable y celular.';
    } elseif (mb_strlen($nombre) > 200) {
        $error = 'El nombre del club es demasiado largo.';
    } else {
        $st = $mysqli2->prepare("SELECT id FROM _p_clubes WHERE id_evento = ? AND nombre = ? LIMIT 1");
        $st->bind_param('is', $idEvento, $nombre);
        $st->execute();
        if ($st->get_result()->num_rows > 0) {
            $error = 'Ya hay un club registrado con ese nombre. Si es el tuyo, pedí tu link a la organización.';
        } else {
            $token = bin2hex(random_bytes(16));
            try {
                $st = $mysqli2->prepare(
                    "INSERT INTO _p_clubes (id_evento, nombre, responsable, celular, email, token) VALUES (?,?,?,?,?,?)");
                $st->bind_param('isssss', $idEvento, $nombre, $resp, $cel, $mail, $token);
                $st->execute();
                // Directo a su formulario de inscripción; la URL del navegador ES su acceso
                $_SESSION['ic_flash'] = ['ok', '¡Club registrado! Guardá la dirección de esta página (o agregala a favoritos): es tu acceso para inscribir a tus parejas.'];
                header('Location: interclubes.php?token=' . $token);
                exit;
            } catch (mysqli_sql_exception $e) {
                $error = 'Error al registrar el club. Revisá los datos e intentá de nuevo.';
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = 'El registro está cerrado.';
}

$flyerUrl = $eventoIC['flyer'] ? '/img/flyers/' . $eventoIC['flyer'] : '';
$fechaFmt = ($eventoIC['fecha'] && $eventoIC['fecha'] !== '0000-00-00') ? date('d/m/Y', strtotime($eventoIC['fecha'])) : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Registro de Clubes · <?= htmlspecialchars($eventoIC['evento']) ?></title>
<link rel="shortcut icon" href="/favicon.ico">
<style>
  :root { --azul:#0b6aa8; --azul-osc:#084d7a; --arena:#f6f1e7; --err:#d54141; --gris:#6b7684; --borde:#e2e6eb; }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--arena); color: #22303c; min-height: 100vh; }
  .wrap { max-width: 520px; margin: 0 auto; padding: 16px 14px 60px; }
  .head { background: linear-gradient(135deg, var(--azul), var(--azul-osc)); color: #fff; border-radius: 14px; padding: 20px 18px; margin-bottom: 16px; }
  .head .torneo { font-size: 13px; text-transform: uppercase; letter-spacing: 1px; opacity: .85; }
  .head h1 { font-size: 21px; margin: 4px 0 2px; }
  .head .fechas { font-size: 12px; opacity: .85; margin-top: 8px; }
  .flyer { width: 100%; border-radius: 12px; margin-bottom: 16px; display: block; }
  .card { background: #fff; border: 1px solid var(--borde); border-radius: 12px; padding: 18px 16px; }
  .card h2 { font-size: 16px; margin-bottom: 4px; }
  .card .sub { font-size: 13px; color: var(--gris); margin-bottom: 16px; }
  .campo { margin-bottom: 12px; }
  .campo label { display: block; font-size: 12px; color: var(--gris); margin-bottom: 4px; }
  .campo input { width: 100%; padding: 10px 11px; border: 1px solid var(--borde); border-radius: 8px; font-size: 15px; }
  .btn { width: 100%; border: none; border-radius: 10px; cursor: pointer; font-size: 15px; font-weight: 700; padding: 13px; background: var(--azul); color: #fff; margin-top: 6px; }
  .error { background: #fdeaea; color: var(--err); border: 1px solid #f3c4c4; padding: 11px 13px; border-radius: 10px; font-size: 13px; margin-bottom: 14px; font-weight: 600; }
  .cerrada { background: #fff4e0; color: #9a6a12; border: 1px solid #f0ddb2; padding: 12px 14px; border-radius: 10px; font-size: 14px; font-weight: 600; }
  .pie { text-align: center; font-size: 11px; color: var(--gris); margin-top: 22px; }
</style>
</head>
<body>
<div class="wrap">

  <div class="head">
    <div class="torneo">Torneo Interclubes · Registro de clubes</div>
    <h1><?= htmlspecialchars($eventoIC['evento']) ?><?= $eventoIC['nombre_evento2'] ? ' · ' . htmlspecialchars($eventoIC['nombre_evento2']) : '' ?></h1>
    <?= $fechaFmt ? '<div class="fechas">Fecha del torneo: <strong>' . $fechaFmt . '</strong></div>' : '' ?>
  </div>

  <?php if ($flyerUrl): ?><img class="flyer" src="<?= htmlspecialchars($flyerUrl) ?>" alt=""><?php endif; ?>

  <?php if (!$abierta): ?>
    <div class="cerrada">⚠️ El registro de clubes está cerrado. Consultá con la organización.</div>
  <?php else: ?>

  <div class="card">
    <h2>Registrá tu club</h2>
    <div class="sub">Al registrarte vas a acceder a tu formulario exclusivo para inscribir a las parejas de tu club. <strong>Guardá ese link.</strong></div>

    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="post">
      <div class="campo">
        <label>Nombre del club *</label>
        <input type="text" name="nombre" required maxlength="200" value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>">
      </div>
      <div class="campo">
        <label>Nombre del responsable *</label>
        <input type="text" name="responsable" required maxlength="200" value="<?= htmlspecialchars($_POST['responsable'] ?? '') ?>">
      </div>
      <div class="campo">
        <label>Celular del responsable *</label>
        <input type="tel" name="celular" required inputmode="numeric" placeholder="09..." value="<?= htmlspecialchars($_POST['celular'] ?? '') ?>">
      </div>
      <div class="campo">
        <label>Email</label>
        <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <button type="submit" class="btn">Registrar club →</button>
    </form>
  </div>

  <?php endif; ?>

  <div class="pie">bt.com.py · Cualquier consulta, contactá con la organización del torneo.</div>
</div>
</body>
</html>
