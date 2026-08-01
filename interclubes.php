<?php
/**
 * interclubes.php — Inscripción de parejas por CLUB (torneos Interclubes)
 * ================================================================
 * El dueño del club recibe una URL con token único:
 *   https://bt.com.py/interclubes.php?token=XXXXXXXX
 * Desde ahí inscribe hasta 2 parejas por categoría habilitada.
 * Sin login: el token identifica club + evento.
 * ================================================================
 */
session_start();
include_once "db/conection.inc.php";

const MAX_PAREJAS_POR_CAT = 2;

// ── Resolver token → club + evento ───────────────────────────────────────────
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$club  = null;
$eventoIC = null;

if (preg_match('/^[a-f0-9]{32}$/i', $token)) {
    $st = $mysqli2->prepare(
        "SELECT c.id AS id_club, c.nombre AS club, c.responsable, c.estado AS estado_club,
                e.id AS id_evento, e.evento, e.nombre_evento2, e.estado AS estado_evento,
                e.fecha, e.fecha_fin_inscripcion, e.flyer, e.descripcion, e.id_tipo_evento
           FROM _p_clubes c
           JOIN _p_eventos e ON e.id = c.id_evento
          WHERE c.token = ? LIMIT 1");
    $st->bind_param('s', $token);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    if ($row && $row['estado_club'] === 'activo') {
        $club     = $row;
        $eventoIC = $row;
    }
}

if (!$club) {
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>Link inválido</title></head>'
       . '<body style="font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#f4f6f8;margin:0;">'
       . '<div style="text-align:center;padding:24px;"><h2>Link inválido o vencido</h2><p>Consultá con la organización del torneo para obtener un nuevo link de inscripción.</p></div></body></html>';
    exit;
}

$idClub   = (int)$club['id_club'];
$idEvento = (int)$club['id_evento'];

// ── ¿Inscripción abierta? ─────────────────────────────────────────────────────
$abierta = in_array($eventoIC['estado_evento'], ['activo', 'registro'], true);
$fcierre = $eventoIC['fecha_fin_inscripcion'];
if ($abierta && $fcierre && $fcierre !== '0000-00-00' && date('Y-m-d') > $fcierre) {
    $abierta = false;
}

// ── Enmascarado (mismo criterio que inscripcion.php) ─────────────────────────
function maskText($str) {
    $str = trim((string)$str);
    if ($str === '') return '';
    $masked = [];
    foreach (explode(' ', $str) as $w) {
        $masked[] = (mb_strlen($w) <= 2) ? $w : mb_substr($w, 0, 1) . str_repeat('*', mb_strlen($w) - 1);
    }
    return implode(' ', $masked);
}
function maskPhone($str) {
    $str = preg_replace('/[^0-9]/', '', (string)$str);
    return (strlen($str) <= 4) ? $str : substr($str, 0, 4) . str_repeat('*', strlen($str) - 4);
}

// ── AJAX: buscar CI (solo con token válido) ──────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'buscar_ci') {
    header('Content-Type: application/json; charset=utf-8');
    $ci = ltrim(preg_replace('/[^0-9]/', '', $_GET['ci'] ?? ''), '0');
    if (strlen($ci) < 5) { echo json_encode(['found' => false]); exit; }

    $st = $mysqli2->prepare("SELECT nombre, apellido, cel FROM _p_usuarios WHERE TRIM(ci)=? LIMIT 1");
    $st->bind_param('s', $ci);
    $st->execute();
    $u = $st->get_result()->fetch_assoc();
    if ($u) {
        echo json_encode([
            'found' => true, 'source' => 'usuarios',
            'nombre' => maskText($u['nombre']), 'apellido' => maskText($u['apellido']),
            'cel' => maskPhone($u['cel']),
        ]);
        exit;
    }
    $st = $mysqli2->prepare("SELECT nombre, apellido FROM _ci_py WHERE ci = ? LIMIT 1");
    $st->bind_param('s', $ci);
    $st->execute();
    $c = $st->get_result()->fetch_assoc();
    if ($c) {
        echo json_encode([
            'found' => true, 'source' => 'ci_py',
            'nombre' => maskText($c['nombre']), 'apellido' => maskText($c['apellido']), 'cel' => '',
        ]);
        exit;
    }
    echo json_encode(['found' => false]);
    exit;
}

// ── Helpers de datos ─────────────────────────────────────────────────────────

// Categorías habilitadas del evento
function categoriasEvento($mysqli2, $idEvento) {
    $st = $mysqli2->prepare(
        "SELECT rec.id_categoria, cat.categoria, rec.sexo
           FROM _relacion_evento_categoria rec
           JOIN _p_categorias cat ON cat.id = rec.id_categoria
          WHERE rec.id_evento = ? AND rec.estado = 'activo'
          ORDER BY rec.orden_visualizacion ASC, cat.categoria ASC");
    $st->bind_param('i', $idEvento);
    $st->execute();
    $res = $st->get_result();
    $cats = [];
    while ($r = $res->fetch_assoc()) $cats[(int)$r['id_categoria']] = $r;
    return $cats;
}

// Parejas del club por categoría (una fila por pareja, no espejo)
function parejasClub($mysqli2, $idClub, $idEvento) {
    $st = $mysqli2->prepare(
        "SELECT i.id_categoria, i.ci, i.ci_dupla,
                CONCAT(COALESCE(u1.nombre,''),' ',COALESCE(u1.apellido,'')) AS j1,
                CONCAT(COALESCE(u2.nombre,''),' ',COALESCE(u2.apellido,'')) AS j2
           FROM _p_incripciones i
           LEFT JOIN _p_usuarios u1 ON TRIM(u1.ci) = TRIM(i.ci)
           LEFT JOIN _p_usuarios u2 ON TRIM(u2.ci) = TRIM(i.ci_dupla)
          WHERE i.id_club = ? AND i.id_evento = ? AND i.estado <> 'bloqueado'
            AND CAST(i.ci AS UNSIGNED) < CAST(i.ci_dupla AS UNSIGNED)
          ORDER BY i.id ASC");
    $st->bind_param('ii', $idClub, $idEvento);
    $st->execute();
    $res = $st->get_result();
    $porCat = [];
    while ($r = $res->fetch_assoc()) $porCat[(int)$r['id_categoria']][] = $r;
    return $porCat;
}

// Asegura que el jugador exista en _p_usuarios. No modifica jugadores existentes.
// Devuelve '' si ok, o mensaje de error.
function asegurarJugador($mysqli2, $ci, $nombre, $apellido, $cel, $sexoCat) {
    $st = $mysqli2->prepare("SELECT id FROM _p_usuarios WHERE TRIM(ci)=? LIMIT 1");
    $st->bind_param('s', $ci);
    $st->execute();
    if ($st->get_result()->num_rows > 0) return '';

    // Padrón: nombre/apellido oficiales pisan lo tipeado
    $st = $mysqli2->prepare("SELECT nombre, apellido FROM _ci_py WHERE ci = ? LIMIT 1");
    $st->bind_param('s', $ci);
    $st->execute();
    if ($p = $st->get_result()->fetch_assoc()) {
        $nombre   = $p['nombre'];
        $apellido = $p['apellido'];
    }
    if ($nombre === '' || $apellido === '') return "Completá nombre y apellido del jugador con CI $ci.";

    // Sexo solo cuando la categoría lo define (hombre/mujer); en mixto queda sin especificar
    $sexo = in_array($sexoCat, ['hombre', 'mujer'], true) ? $sexoCat : null;
    $st = $mysqli2->prepare(
        "INSERT INTO _p_usuarios (nombre, apellido, cel, ci, sexo, tipo_documento, registro, tipo, medio)
         VALUES (?,?,?,?,?, 'CI', 'inscripcion', 'jugador', 'interclubes')");
    $st->bind_param('sssss', $nombre, $apellido, $cel, $ci, $sexo);
    if (!$st->execute()) return 'Error al registrar al jugador con CI ' . $ci . '.';
    return '';
}

// ── POST: agregar / quitar pareja ────────────────────────────────────────────
$flash = null; // ['ok'|'err', mensaje]

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if (!$abierta) {
        $flash = ['err', 'La inscripción está cerrada.'];
    } elseif ($accion === 'agregar_pareja') {
        $cats  = categoriasEvento($mysqli2, $idEvento);
        $idCat = (int)($_POST['categoria'] ?? 0);
        $ci1   = ltrim(preg_replace('/[^0-9]/', '', $_POST['p1_ci'] ?? ''), '0');
        $ci2   = ltrim(preg_replace('/[^0-9]/', '', $_POST['p2_ci'] ?? ''), '0');
        $limpiar = fn($k) => trim(strip_tags($_POST[$k] ?? ''));

        if (!isset($cats[$idCat])) {
            $flash = ['err', 'Categoría inválida para este torneo.'];
        } elseif ((int)$ci1 < 100000 || (int)$ci2 < 100000) {
            $flash = ['err', 'Cédula inválida. Verificá los datos.'];
        } elseif ($ci1 === $ci2) {
            $flash = ['err', 'Ambos jugadores tienen la misma cédula.'];
        } else {
            // Tope de parejas del club en la categoría
            $st = $mysqli2->prepare(
                "SELECT FLOOR(COUNT(*)/2) AS c FROM _p_incripciones
                  WHERE id_club = ? AND id_evento = ? AND id_categoria = ? AND estado <> 'bloqueado'");
            $st->bind_param('iii', $idClub, $idEvento, $idCat);
            $st->execute();
            $nParejas = (int)$st->get_result()->fetch_assoc()['c'];

            // Jugador ya inscripto en la categoría (cualquier club)
            $st = $mysqli2->prepare(
                "SELECT id FROM _p_incripciones
                  WHERE (ci = ? OR ci = ?) AND id_categoria = ? AND id_evento = ?
                    AND estado <> 'bloqueado' LIMIT 1");
            $st->bind_param('ssii', $ci1, $ci2, $idCat, $idEvento);
            $st->execute();
            $yaInscripto = $st->get_result()->num_rows > 0;

            if ($nParejas >= MAX_PAREJAS_POR_CAT) {
                $flash = ['err', 'Tu club ya tiene ' . MAX_PAREJAS_POR_CAT . ' parejas en ' . $cats[$idCat]['categoria'] . '.'];
            } elseif ($yaInscripto) {
                $flash = ['err', 'Alguno de los jugadores ya está inscripto en esa categoría.'];
            } else {
                $sexoCat = $cats[$idCat]['sexo'] ?? '';
                $e1 = asegurarJugador($mysqli2, $ci1, $limpiar('p1_nombre'), $limpiar('p1_apellido'), preg_replace('/[^0-9]/', '', $_POST['p1_cel'] ?? ''), $sexoCat);
                $e2 = $e1 === '' ? asegurarJugador($mysqli2, $ci2, $limpiar('p2_nombre'), $limpiar('p2_apellido'), preg_replace('/[^0-9]/', '', $_POST['p2_cel'] ?? ''), $sexoCat) : '';
                if ($e1 !== '' || $e2 !== '') {
                    $flash = ['err', $e1 !== '' ? $e1 : $e2];
                } else {
                    // Par espejo, igual que la inscripción individual
                    $st = $mysqli2->prepare(
                        "INSERT INTO _p_incripciones (id_evento, ci, ci_dupla, id_categoria, id_club, estado, phorario, comentario, medio)
                         VALUES (?,?,?,?,?, 'inscripto', 'no', '', 'interclubes')");
                    $st->bind_param('issii', $idEvento, $ci1, $ci2, $idCat, $idClub);
                    $ok1 = $st->execute();
                    $st->bind_param('issii', $idEvento, $ci2, $ci1, $idCat, $idClub);
                    $ok2 = $st->execute();
                    $flash = ($ok1 && $ok2)
                        ? ['ok', 'Pareja inscripta en ' . $cats[$idCat]['categoria'] . '.']
                        : ['err', 'Error al guardar la inscripción. Intentá de nuevo.'];
                }
            }
        }
    } elseif ($accion === 'quitar_pareja') {
        $idCat = (int)($_POST['categoria'] ?? 0);
        $ci1   = preg_replace('/[^0-9]/', '', $_POST['ci1'] ?? '');
        $ci2   = preg_replace('/[^0-9]/', '', $_POST['ci2'] ?? '');
        if ($idCat && $ci1 !== '' && $ci2 !== '') {
            $st = $mysqli2->prepare(
                "DELETE FROM _p_incripciones
                  WHERE id_club = ? AND id_evento = ? AND id_categoria = ? AND estado <> 'bloqueado'
                    AND ((ci = ? AND ci_dupla = ?) OR (ci = ? AND ci_dupla = ?))");
            $st->bind_param('iiissss', $idClub, $idEvento, $idCat, $ci1, $ci2, $ci2, $ci1);
            $st->execute();
            $flash = $st->affected_rows > 0
                ? ['ok', 'Pareja eliminada.']
                : ['err', 'No se encontró la pareja.'];
        } else {
            $flash = ['err', 'Datos incompletos.'];
        }
    }

    // PRG: redirect para evitar re-envíos con F5
    $_SESSION['ic_flash'] = $flash;
    header('Location: interclubes.php?token=' . urlencode($token));
    exit;
}

if (isset($_SESSION['ic_flash'])) {
    $flash = $_SESSION['ic_flash'];
    unset($_SESSION['ic_flash']);
}

// ── Datos para render ────────────────────────────────────────────────────────
$cats    = categoriasEvento($mysqli2, $idEvento);
$parejas = parejasClub($mysqli2, $idClub, $idEvento);
$totalParejas = 0;
foreach ($parejas as $lp) $totalParejas += count($lp);

$flyerUrl = $eventoIC['flyer'] ? '/img/flyers/' . $eventoIC['flyer'] : '';
// Cronograma: imagen cargada en "Imagen del Programa" del admin (va dentro de descripcion)
$cronoUrl = '';
if (preg_match('/src="([^"]+)"/', $eventoIC['descripcion'] ?? '', $mCrono)) {
    $cronoUrl = $mCrono[1];
}
$fechaFmt = ($eventoIC['fecha'] && $eventoIC['fecha'] !== '0000-00-00') ? date('d/m/Y', strtotime($eventoIC['fecha'])) : '';
$cierreFmt = ($fcierre && $fcierre !== '0000-00-00') ? date('d/m/Y', strtotime($fcierre)) : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Inscripción Interclubes · <?= htmlspecialchars($eventoIC['evento']) ?></title>
<link rel="shortcut icon" href="/favicon.ico">
<style>
  :root {
    --azul: #0b6aa8; --azul-osc: #084d7a; --arena: #f6f1e7; --ok: #1c9c50;
    --err: #d54141; --gris: #6b7684; --borde: #e2e6eb; --card: #ffffff;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--arena); color: #22303c; min-height: 100vh; }
  .wrap { max-width: 680px; margin: 0 auto; padding: 16px 14px 60px; }
  .head { background: linear-gradient(135deg, var(--azul), var(--azul-osc)); color: #fff; border-radius: 14px; padding: 20px 18px; margin-bottom: 16px; }
  .head .torneo { font-size: 13px; text-transform: uppercase; letter-spacing: 1px; opacity: .85; }
  .head h1 { font-size: 22px; margin: 4px 0 2px; }
  .head .club { font-size: 16px; font-weight: 600; margin-top: 10px; background: rgba(255,255,255,.15); display: inline-block; padding: 5px 12px; border-radius: 20px; }
  .head .fechas { font-size: 12px; opacity: .85; margin-top: 10px; }
  .flash { padding: 12px 14px; border-radius: 10px; font-size: 14px; margin-bottom: 14px; font-weight: 600; }
  .flash.ok  { background: #e2f6e9; color: var(--ok); border: 1px solid #b8e6c9; }
  .flash.err { background: #fdeaea; color: var(--err); border: 1px solid #f3c4c4; }
  .cerrada { background: #fff4e0; color: #9a6a12; border: 1px solid #f0ddb2; padding: 12px 14px; border-radius: 10px; font-size: 14px; margin-bottom: 14px; font-weight: 600; }
  .resumen { font-size: 13px; color: var(--gris); margin-bottom: 14px; }
  .cat { background: var(--card); border: 1px solid var(--borde); border-radius: 12px; margin-bottom: 12px; overflow: hidden; }
  .cat-h { display: flex; justify-content: space-between; align-items: center; padding: 13px 15px; }
  .cat-h .nombre { font-weight: 700; font-size: 15px; }
  .cat-h .cupo { font-size: 11px; color: var(--gris); background: var(--arena); padding: 3px 9px; border-radius: 12px; white-space: nowrap; }
  .cat-h .cupo.full { background: #e2f6e9; color: var(--ok); }
  .pareja { display: flex; justify-content: space-between; align-items: center; padding: 9px 15px; border-top: 1px solid var(--borde); font-size: 13px; }
  .pareja .nombres { line-height: 1.5; }
  .pareja .ci-chico { color: var(--gris); font-size: 11px; }
  .btn { border: none; border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: 600; padding: 9px 14px; }
  .btn-add { background: var(--azul); color: #fff; margin: 10px 15px 13px; }
  .btn-add:disabled { background: #b8c2cc; cursor: default; }
  .btn-del { background: transparent; color: var(--err); font-size: 12px; padding: 6px 8px; }
  .btn-ok { background: var(--ok); color: #fff; }
  .btn-gh { background: transparent; color: var(--gris); }
  .form-pareja { border-top: 1px solid var(--borde); padding: 13px 15px; background: #fafbfc; display: none; }
  .form-pareja.abierto { display: block; }
  .jug { margin-bottom: 12px; }
  .jug .titulo { font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--azul); margin-bottom: 6px; }
  .fila { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 8px; }
  .campo label { display: block; font-size: 11px; color: var(--gris); margin-bottom: 3px; }
  .campo input { width: 100%; padding: 9px 10px; border: 1px solid var(--borde); border-radius: 8px; font-size: 14px; background: #fff; }
  .campo input[readonly] { background: #eef2f5; color: #5a6570; }
  .estado-ci { font-size: 11px; margin-top: 3px; min-height: 14px; }
  .estado-ci.ok { color: var(--ok); }
  .estado-ci.warn { color: #9a6a12; }
  .acciones { display: flex; gap: 8px; margin-top: 4px; }
  .vacio { padding: 10px 15px; border-top: 1px solid var(--borde); font-size: 13px; color: var(--gris); font-style: italic; }
  .pie { text-align: center; font-size: 11px; color: var(--gris); margin-top: 24px; }
</style>
</head>
<body>
<div class="wrap">

  <div class="head">
    <div class="torneo">Torneo Interclubes</div>
    <h1><?= htmlspecialchars($eventoIC['evento']) ?><?= $eventoIC['nombre_evento2'] ? ' · ' . htmlspecialchars($eventoIC['nombre_evento2']) : '' ?></h1>
    <div class="club">🏢 <?= htmlspecialchars($club['club']) ?></div>
    <div class="fechas">
      <?= $fechaFmt ? 'Fecha del torneo: <strong>' . $fechaFmt . '</strong>' : '' ?>
      <?= $cierreFmt ? ' &nbsp;·&nbsp; Cierre de inscripción: <strong>' . $cierreFmt . '</strong>' : '' ?>
    </div>
  </div>

  <?php if ($cronoUrl): ?>
    <img src="<?= htmlspecialchars($cronoUrl) ?>" alt="Cronograma" style="width:100%;border-radius:12px;margin-bottom:16px;display:block;">
  <?php endif; ?>

  <?php if ($flash): ?>
    <div class="flash <?= $flash[0] ?>"><?= htmlspecialchars($flash[1]) ?></div>
  <?php endif; ?>

  <?php if (!$abierta): ?>
    <div class="cerrada">⚠️ La inscripción está cerrada. Las parejas cargadas quedan como definitivas.</div>
  <?php endif; ?>

  <div class="resumen">
    Podés inscribir hasta <strong><?= MAX_PAREJAS_POR_CAT ?> parejas por categoría</strong>.
    Parejas cargadas: <strong><?= $totalParejas ?></strong>
  </div>

  <?php foreach ($cats as $idCat => $cat):
      $lp = $parejas[$idCat] ?? [];
      $n  = count($lp);
      $puedeAgregar = $abierta && $n < MAX_PAREJAS_POR_CAT;
  ?>
  <div class="cat">
    <div class="cat-h">
      <span class="nombre"><?= htmlspecialchars($cat['categoria']) ?></span>
      <span class="cupo <?= $n >= MAX_PAREJAS_POR_CAT ? 'full' : '' ?>"><?= $n ?>/<?= MAX_PAREJAS_POR_CAT ?> parejas</span>
    </div>

    <?php if (!$n): ?>
      <div class="vacio">Sin parejas cargadas</div>
    <?php endif; ?>

    <?php foreach ($lp as $p): ?>
    <div class="pareja">
      <div class="nombres">
        <?= htmlspecialchars(trim($p['j1']) ?: 'CI ' . $p['ci']) ?> <span class="ci-chico">(<?= htmlspecialchars($p['ci']) ?>)</span><br>
        <?= htmlspecialchars(trim($p['j2']) ?: 'CI ' . $p['ci_dupla']) ?> <span class="ci-chico">(<?= htmlspecialchars($p['ci_dupla']) ?>)</span>
      </div>
      <?php if ($abierta): ?>
      <form method="post" onsubmit="return confirm('¿Quitar esta pareja?')">
        <input type="hidden" name="accion" value="quitar_pareja">
        <input type="hidden" name="categoria" value="<?= $idCat ?>">
        <input type="hidden" name="ci1" value="<?= htmlspecialchars($p['ci']) ?>">
        <input type="hidden" name="ci2" value="<?= htmlspecialchars($p['ci_dupla']) ?>">
        <button type="submit" class="btn btn-del">✕ Quitar</button>
      </form>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <?php if ($puedeAgregar): ?>
    <button type="button" class="btn btn-add" onclick="toggleForm(<?= $idCat ?>)">+ Agregar pareja</button>
    <form method="post" class="form-pareja" id="form-<?= $idCat ?>">
      <input type="hidden" name="accion" value="agregar_pareja">
      <input type="hidden" name="categoria" value="<?= $idCat ?>">
      <?php for ($j = 1; $j <= 2; $j++): ?>
      <div class="jug">
        <div class="titulo">Jugador <?= $j ?></div>
        <div class="fila">
          <div class="campo">
            <label>Cédula *</label>
            <input type="tel" name="p<?= $j ?>_ci" required inputmode="numeric" autocomplete="off"
                   onblur="buscarCI(this, <?= $idCat ?>, <?= $j ?>)">
            <div class="estado-ci" id="est-<?= $idCat ?>-<?= $j ?>"></div>
          </div>
          <div class="campo">
            <label>Celular</label>
            <input type="tel" name="p<?= $j ?>_cel" inputmode="numeric" autocomplete="off">
          </div>
        </div>
        <div class="fila">
          <div class="campo">
            <label>Nombre *</label>
            <input type="text" name="p<?= $j ?>_nombre" autocomplete="off">
          </div>
          <div class="campo">
            <label>Apellido *</label>
            <input type="text" name="p<?= $j ?>_apellido" autocomplete="off">
          </div>
        </div>
      </div>
      <?php endfor; ?>
      <div class="acciones">
        <button type="submit" class="btn btn-ok">✓ Inscribir pareja</button>
        <button type="button" class="btn btn-gh" onclick="toggleForm(<?= $idCat ?>)">Cancelar</button>
      </div>
    </form>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>

  <?php if (!$cats): ?>
    <div class="cat"><div class="vacio">El torneo aún no tiene categorías habilitadas.</div></div>
  <?php endif; ?>

  <div class="pie">bt.com.py · Cualquier consulta, contactá con la organización del torneo.</div>
</div>

<script>
const TOKEN = <?= json_encode($token) ?>;

function toggleForm(idCat) {
  document.getElementById('form-' + idCat).classList.toggle('abierto');
}

// Autocompletar por CI: si el jugador existe, muestra sus datos (enmascarados)
// y bloquea los campos; si no existe, habilita la carga manual.
async function buscarCI(input, idCat, j) {
  const ci = input.value.replace(/[^0-9]/g, '');
  const form = document.getElementById('form-' + idCat);
  const fNombre = form.querySelector('[name="p' + j + '_nombre"]');
  const fApellido = form.querySelector('[name="p' + j + '_apellido"]');
  const fCel = form.querySelector('[name="p' + j + '_cel"]');
  const est = document.getElementById('est-' + idCat + '-' + j);
  fNombre.value = ''; fApellido.value = ''; fCel.value = ''; est.textContent = '';
  fNombre.readOnly = false; fApellido.readOnly = false; fCel.readOnly = false;
  if (ci.length < 5) return;
  est.textContent = 'Buscando...'; est.className = 'estado-ci';
  try {
    const r = await fetch('interclubes.php?token=' + encodeURIComponent(TOKEN) + '&action=buscar_ci&ci=' + ci);
    const d = await r.json();
    if (d.found) {
      fNombre.value = d.nombre; fApellido.value = d.apellido;
      fNombre.readOnly = true; fApellido.readOnly = true;
      if (d.source === 'usuarios') {
        // Jugador ya registrado: sus datos no se tocan
        fCel.value = d.cel || '';
        fCel.readOnly = true;
      }
      est.textContent = '✓ Jugador encontrado — los datos se completan solos';
      est.className = 'estado-ci ok';
    } else {
      est.textContent = 'Jugador nuevo: completá nombre y apellido';
      est.className = 'estado-ci warn';
    }
  } catch (e) {
    est.textContent = '';
  }
}
</script>
</body>
</html>
