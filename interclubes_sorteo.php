<?php
/**
 * interclubes_sorteo.php — Carga EN VIVO del sorteo público (Interclubes)
 * ================================================================
 * Uso: interclubes_sorteo.php?evento=<id>   (requiere sesión de admin)
 * Por categoría: los clubes participantes se asignan a Grupo 1 o
 * Grupo 2 (hasta 3 por grupo) a medida que salen sorteados.
 * Los enfrentamientos (todos vs todos dentro del grupo) se generan solos.
 * La vista pública (grafico-interclubes) refleja cada asignación al instante.
 * ================================================================
 */
session_start();
header_remove('X-Powered-By');
include_once "db/conection.inc.php";

if (!isset($_SESSION['admin_id'])) {
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sorteo Interclubes</title></head>'
       . '<body style="font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#f4f6f8;margin:0;">'
       . '<div style="text-align:center;padding:24px;"><h2>Sesión requerida</h2><p>Iniciá sesión en el <a href="/tvt_admin_v2.php">panel de administración</a> y volvé a abrir esta página.</p></div></body></html>';
    exit;
}

// ponytail: modelo fijo del torneo — 2 grupos de 3 clubes por categoría
const IC_GRUPOS = 2;
const IC_CLUBES_POR_GRUPO = 3;

$idEvento = isset($_GET['evento']) ? abs((int)$_GET['evento']) : 0;
$st = $mysqli2->prepare("SELECT id, evento FROM _p_eventos WHERE id = ? AND id_tipo_evento = 5 LIMIT 1");
$st->bind_param('i', $idEvento);
$st->execute();
$evento = $st->get_result()->fetch_assoc();
if (!$evento) { http_response_code(404); exit('Evento interclubes no encontrado'); }

// ── AJAX ─────────────────────────────────────────────────────────────────────
if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['action'];

    // Categorías del evento que tienen clubes con parejas inscriptas
    if ($action === 'categorias') {
        $st = $mysqli2->prepare(
            "SELECT rec.id_categoria, cat.categoria,
                    COUNT(DISTINCT i.id_club) AS clubes
               FROM _relacion_evento_categoria rec
               JOIN _p_categorias cat ON cat.id = rec.id_categoria
               LEFT JOIN _p_incripciones i
                 ON i.id_evento = rec.id_evento AND i.id_categoria = rec.id_categoria
                AND i.id_club IS NOT NULL AND i.estado <> 'bloqueado'
              WHERE rec.id_evento = ? AND rec.estado = 'activo'
              GROUP BY rec.id_categoria
              ORDER BY rec.orden_visualizacion ASC, cat.categoria ASC");
        $st->bind_param('i', $idEvento);
        $st->execute();
        $res = $st->get_result();
        $cats = [];
        while ($r = $res->fetch_assoc()) $cats[] = $r;
        echo json_encode(['success' => true, 'categorias' => $cats]);
        exit;
    }

    // Estado de una categoría: clubes participantes + asignaciones actuales
    if ($action === 'estado') {
        $idCat = abs((int)($_GET['categoria'] ?? 0));
        if (!$idCat) { echo json_encode(['success' => false, 'error' => 'Falta categoría']); exit; }
        // Clubes con al menos 1 pareja en la categoría
        $st = $mysqli2->prepare(
            "SELECT c.id, c.nombre, FLOOR(COUNT(i.id)/2) AS parejas
               FROM _p_clubes c
               JOIN _p_incripciones i ON i.id_club = c.id
                AND i.id_evento = c.id_evento AND i.id_categoria = ? AND i.estado <> 'bloqueado'
              WHERE c.id_evento = ?
              GROUP BY c.id
              ORDER BY c.nombre ASC");
        $st->bind_param('ii', $idCat, $idEvento);
        $st->execute();
        $res = $st->get_result();
        $clubes = [];
        while ($r = $res->fetch_assoc()) $clubes[] = $r;

        $st = $mysqli2->prepare(
            "SELECT s.id_club, s.grupo, s.posicion
               FROM _ic_sorteo s WHERE s.id_evento = ? AND s.id_categoria = ?
              ORDER BY s.grupo ASC, s.posicion ASC");
        $st->bind_param('ii', $idEvento, $idCat);
        $st->execute();
        $res = $st->get_result();
        $asignados = [];
        while ($r = $res->fetch_assoc()) $asignados[] = $r;

        echo json_encode(['success' => true, 'clubes' => $clubes, 'asignados' => $asignados]);
        exit;
    }

    // Asignar club a un grupo (posición = orden de salida en el sorteo)
    if ($action === 'asignar') {
        $idCat  = abs((int)($_GET['categoria'] ?? 0));
        $idClub = abs((int)($_GET['id_club'] ?? 0));
        $grupo  = abs((int)($_GET['grupo'] ?? 0));
        if (!$idCat || !$idClub || $grupo < 1 || $grupo > IC_GRUPOS) {
            echo json_encode(['success' => false, 'error' => 'Parámetros inválidos']); exit;
        }
        // ¿Grupo lleno?
        $st = $mysqli2->prepare("SELECT COUNT(*) c FROM _ic_sorteo WHERE id_evento=? AND id_categoria=? AND grupo=?");
        $st->bind_param('iii', $idEvento, $idCat, $grupo);
        $st->execute();
        if ((int)$st->get_result()->fetch_assoc()['c'] >= IC_CLUBES_POR_GRUPO) {
            echo json_encode(['success' => false, 'error' => 'El grupo ya tiene ' . IC_CLUBES_POR_GRUPO . ' clubes']); exit;
        }
        // ¿Club ya asignado?
        $st = $mysqli2->prepare("SELECT id FROM _ic_sorteo WHERE id_evento=? AND id_categoria=? AND id_club=? LIMIT 1");
        $st->bind_param('iii', $idEvento, $idCat, $idClub);
        $st->execute();
        if ($st->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'error' => 'Ese club ya fue sorteado en esta categoría']); exit;
        }
        $st = $mysqli2->prepare("SELECT COALESCE(MAX(posicion),0)+1 p FROM _ic_sorteo WHERE id_evento=? AND id_categoria=? AND grupo=?");
        $st->bind_param('iii', $idEvento, $idCat, $grupo);
        $st->execute();
        $pos = (int)$st->get_result()->fetch_assoc()['p'];
        $st = $mysqli2->prepare("INSERT INTO _ic_sorteo (id_evento, id_categoria, id_club, grupo, posicion) VALUES (?,?,?,?,?)");
        $st->bind_param('iiiii', $idEvento, $idCat, $idClub, $grupo, $pos);
        $st->execute();
        echo json_encode(['success' => true]);
        exit;
    }

    // Quitar un club del sorteo (corrección) y compactar posiciones
    if ($action === 'quitar') {
        $idCat  = abs((int)($_GET['categoria'] ?? 0));
        $idClub = abs((int)($_GET['id_club'] ?? 0));
        $st = $mysqli2->prepare("DELETE FROM _ic_sorteo WHERE id_evento=? AND id_categoria=? AND id_club=?");
        $st->bind_param('iii', $idEvento, $idCat, $idClub);
        $st->execute();
        foreach ([1, 2] as $g) {
            $st = $mysqli2->prepare("SELECT id FROM _ic_sorteo WHERE id_evento=? AND id_categoria=? AND grupo=? ORDER BY posicion ASC");
            $st->bind_param('iii', $idEvento, $idCat, $g);
            $st->execute();
            $res = $st->get_result();
            $p = 0;
            while ($r = $res->fetch_assoc()) {
                $p++;
                $mysqli2->query("UPDATE _ic_sorteo SET posicion={$p} WHERE id=" . (int)$r['id']);
            }
        }
        echo json_encode(['success' => true]);
        exit;
    }

    // Limpiar toda la categoría
    if ($action === 'limpiar') {
        $idCat = abs((int)($_GET['categoria'] ?? 0));
        $st = $mysqli2->prepare("DELETE FROM _ic_sorteo WHERE id_evento=? AND id_categoria=?");
        $st->bind_param('ii', $idEvento, $idCat);
        $st->execute();
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Acción no reconocida']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Sorteo · <?= htmlspecialchars($evento['evento']) ?></title>
<link rel="shortcut icon" href="/favicon.ico">
<style>
  :root { --azul:#0b6aa8; --azul-osc:#084d7a; --arena:#f6f1e7; --ok:#1c9c50; --err:#d54141; --gris:#6b7684; --borde:#e2e6eb; }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--arena); color: #22303c; min-height: 100vh; }
  .wrap { max-width: 860px; margin: 0 auto; padding: 16px 14px 60px; }
  .head { background: linear-gradient(135deg, var(--azul), var(--azul-osc)); color: #fff; border-radius: 14px; padding: 18px; margin-bottom: 14px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; }
  .head h1 { font-size: 19px; }
  .head .sub { font-size: 12px; opacity: .85; margin-top: 3px; }
  .cats { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 14px; }
  .cat-pill { border: 1px solid var(--borde); background: #fff; border-radius: 20px; padding: 7px 14px; font-size: 13px; font-weight: 600; cursor: pointer; }
  .cat-pill.activa { background: var(--azul); color: #fff; border-color: var(--azul); }
  .cat-pill .n { font-size: 11px; opacity: .7; }
  .panel { background: #fff; border: 1px solid var(--borde); border-radius: 12px; padding: 16px; margin-bottom: 14px; }
  .panel h2 { font-size: 14px; text-transform: uppercase; letter-spacing: .5px; color: var(--gris); margin-bottom: 10px; }
  .pool { display: flex; gap: 8px; flex-wrap: wrap; }
  .club-chip { border: 1px solid var(--borde); border-radius: 10px; padding: 9px 12px; background: #fafbfc; display: flex; align-items: center; gap: 10px; }
  .club-chip .nombre { font-weight: 700; font-size: 14px; }
  .club-chip .parejas { font-size: 11px; color: var(--gris); }
  .club-chip button { border: none; border-radius: 7px; padding: 7px 11px; font-weight: 800; font-size: 13px; cursor: pointer; color: #fff; }
  .btn-g1 { background: var(--azul); }
  .btn-g2 { background: #7c3aed; }
  .grupos { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
  @media (max-width: 640px) { .grupos { grid-template-columns: 1fr; } }
  .grupo { border: 2px solid var(--borde); border-radius: 12px; overflow: hidden; }
  .grupo .gh { padding: 10px 14px; font-weight: 800; color: #fff; display: flex; justify-content: space-between; align-items: center; }
  .grupo.g1 .gh { background: var(--azul); }
  .grupo.g2 .gh { background: #7c3aed; }
  .grupo .slot { padding: 11px 14px; border-top: 1px solid var(--borde); font-weight: 700; font-size: 14px; display: flex; justify-content: space-between; align-items: center; }
  .grupo .slot.vacio { color: #b8c2cc; font-weight: 500; font-style: italic; }
  .grupo .slot .quitar { border: none; background: transparent; color: var(--err); cursor: pointer; font-size: 13px; padding: 2px 6px; }
  .enf { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
  @media (max-width: 640px) { .enf { grid-template-columns: 1fr; } }
  .enf .lista { border: 1px solid var(--borde); border-radius: 10px; padding: 10px 12px; }
  .enf .lista .t { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 6px; }
  .enf .lista.l1 .t { color: var(--azul); } .enf .lista.l2 .t { color: #7c3aed; }
  .enf .vs { padding: 6px 0; border-top: 1px dashed var(--borde); font-size: 13px; font-weight: 600; }
  .enf .vs:first-of-type { border-top: none; }
  .enf .vs span { color: var(--gris); font-weight: 800; font-size: 11px; padding: 0 6px; }
  .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
  .btn-limpiar { border: 1px solid #f3c4c4; background: #fdeaea; color: var(--err); border-radius: 8px; padding: 7px 12px; font-size: 12px; font-weight: 700; cursor: pointer; }
  .msg { font-size: 13px; color: var(--gris); font-style: italic; }
  .toast { position: fixed; bottom: 18px; left: 50%; transform: translateX(-50%); background: #22303c; color: #fff; padding: 10px 18px; border-radius: 10px; font-size: 13px; display: none; z-index: 99; }
</style>
</head>
<body>
<div class="wrap">
  <div class="head">
    <div>
      <h1>🎲 Sorteo público — <?= htmlspecialchars($evento['evento']) ?></h1>
      <div class="sub">2 grupos de <?= IC_CLUBES_POR_GRUPO ?> clubes por categoría · cada clic se publica al instante en la vista del torneo</div>
    </div>
    <a href="/tvt_admin_v2.php" style="color:#fff;font-size:12px;">← Volver al admin</a>
  </div>

  <div class="cats" id="cats"></div>

  <div id="contenido" style="display:none;">
    <div class="panel">
      <div class="toolbar">
        <h2 style="margin:0;">Clubes por sortear</h2>
        <button class="btn-limpiar" onclick="limpiarCategoria()">Limpiar categoría</button>
      </div>
      <div class="pool" id="pool"></div>
    </div>

    <div class="panel">
      <h2>Grupos</h2>
      <div class="grupos">
        <div class="grupo g1"><div class="gh">GRUPO 1 <span id="g1n"></span></div><div id="g1"></div></div>
        <div class="grupo g2"><div class="gh">GRUPO 2 <span id="g2n"></span></div><div id="g2"></div></div>
      </div>
    </div>

    <div class="panel">
      <h2>Enfrentamientos (todos vs todos por grupo)</h2>
      <div class="enf">
        <div class="lista l1"><div class="t">Grupo 1</div><div id="enf1" class="msg">Asigná clubes para generar</div></div>
        <div class="lista l2"><div class="t">Grupo 2</div><div id="enf2" class="msg">Asigná clubes para generar</div></div>
      </div>
    </div>
  </div>

  <div id="sinCat" class="panel msg">Seleccioná una categoría para cargar el sorteo.</div>
</div>
<div class="toast" id="toast"></div>

<script>
const EVENTO = <?= $idEvento ?>;
const MAX_POR_GRUPO = <?= IC_CLUBES_POR_GRUPO ?>;
let catActual = 0;
let catsData = [];

async function api(params) {
  const q = new URLSearchParams({evento: EVENTO, ...params}).toString();
  const r = await fetch('interclubes_sorteo.php?' + q);
  return r.json();
}
function toast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg; t.style.display = 'block';
  setTimeout(() => { t.style.display = 'none'; }, 1800);
}

async function cargarCats() {
  const r = await api({action: 'categorias'});
  if (!r.success) return;
  catsData = r.categorias;
  const box = document.getElementById('cats');
  box.innerHTML = '';
  r.categorias.forEach(c => {
    const b = document.createElement('button');
    b.className = 'cat-pill';
    b.id = 'catpill-' + c.id_categoria;
    b.innerHTML = `${c.categoria} <span class="n">(${c.clubes} clubes)</span>`;
    b.onclick = () => seleccionarCat(parseInt(c.id_categoria));
    box.appendChild(b);
  });
}

function seleccionarCat(idCat) {
  catActual = idCat;
  document.querySelectorAll('.cat-pill').forEach(p => p.classList.remove('activa'));
  const pill = document.getElementById('catpill-' + idCat);
  if (pill) pill.classList.add('activa');
  document.getElementById('contenido').style.display = '';
  document.getElementById('sinCat').style.display = 'none';
  cargarEstado();
}

async function cargarEstado() {
  const r = await api({action: 'estado', categoria: catActual});
  if (!r.success) { toast(r.error || 'Error'); return; }
  const asignadosIds = r.asignados.map(a => parseInt(a.id_club));
  const nombreClub = {};
  r.clubes.forEach(c => { nombreClub[parseInt(c.id)] = c.nombre; });

  // Pool: clubes aún no sorteados
  const pool = document.getElementById('pool');
  const pendientes = r.clubes.filter(c => !asignadosIds.includes(parseInt(c.id)));
  pool.innerHTML = pendientes.length ? '' : '<div class="msg">Todos los clubes ya fueron sorteados ✓</div>';
  pendientes.forEach(c => {
    const d = document.createElement('div');
    d.className = 'club-chip';
    d.innerHTML = `<div><div class="nombre">${c.nombre}</div><div class="parejas">${c.parejas} pareja(s)</div></div>
      <button class="btn-g1" onclick="asignar(${c.id},1)">→ G1</button>
      <button class="btn-g2" onclick="asignar(${c.id},2)">→ G2</button>`;
    pool.appendChild(d);
  });

  // Grupos
  [1, 2].forEach(g => {
    const en = r.asignados.filter(a => parseInt(a.grupo) === g);
    const box = document.getElementById('g' + g);
    document.getElementById('g' + g + 'n').textContent = en.length + '/' + MAX_POR_GRUPO;
    box.innerHTML = '';
    en.forEach(a => {
      const idc = parseInt(a.id_club);
      box.innerHTML += `<div class="slot"><span>${a.posicion}. ${nombreClub[idc] || 'Club #' + idc}</span>
        <button class="quitar" onclick="quitar(${idc})" title="Quitar del sorteo">✕</button></div>`;
    });
    for (let i = en.length; i < MAX_POR_GRUPO; i++) {
      box.innerHTML += '<div class="slot vacio">— libre —</div>';
    }
    // Enfrentamientos round robin
    const enfBox = document.getElementById('enf' + g);
    if (en.length < 2) {
      enfBox.className = 'msg';
      enfBox.textContent = 'Asigná al menos 2 clubes';
    } else {
      enfBox.className = '';
      let h = '';
      for (let i = 0; i < en.length; i++)
        for (let j = i + 1; j < en.length; j++)
          h += `<div class="vs">${nombreClub[parseInt(en[i].id_club)]}<span>VS</span>${nombreClub[parseInt(en[j].id_club)]}</div>`;
      enfBox.innerHTML = h;
    }
  });
}

async function asignar(idClub, grupo) {
  const r = await api({action: 'asignar', categoria: catActual, id_club: idClub, grupo});
  if (!r.success) { toast(r.error || 'Error'); }
  cargarEstado();
}
async function quitar(idClub) {
  if (!confirm('¿Quitar este club del sorteo de la categoría?')) return;
  await api({action: 'quitar', categoria: catActual, id_club: idClub});
  cargarEstado();
}
async function limpiarCategoria() {
  if (!confirm('¿Borrar TODO el sorteo de esta categoría?')) return;
  await api({action: 'limpiar', categoria: catActual});
  cargarEstado();
}

cargarCats();
</script>
</body>
</html>
