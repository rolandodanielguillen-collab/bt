<?php
/**
 * interclubes_resultados.php — Carga de resultados y posiciones (Interclubes)
 * ================================================================
 * Uso: interclubes_resultados.php?evento=<id>   (requiere sesión de admin)
 * Por categoría y grupo: cada serie club vs club muestra sus partidos:
 *   Partido 1 = dupla 1 vs dupla 1, Partido 2 = dupla 2 vs dupla 2, …
 *   (2 sets + 3er set si empatan). Serie empatada → partido de desempate
 *   con dupla mezclada (se eligen los jugadores).
 * La tabla de posiciones del grupo se recalcula sola.
 * ================================================================
 */
session_start();
include_once "db/conection.inc.php";
require_once __DIR__ . '/interclubes.functions.php';

if (!isset($_SESSION['admin_id'])) {
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Resultados Interclubes</title></head>'
       . '<body style="font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#f4f6f8;margin:0;">'
       . '<div style="text-align:center;padding:24px;"><h2>Sesión requerida</h2><p>Iniciá sesión en el <a href="/tvt_admin_v2.php">panel de administración</a> y volvé a abrir esta página.</p></div></body></html>';
    exit;
}

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

    // Categorías que ya tienen sorteo cargado
    if ($action === 'categorias') {
        $st = $mysqli2->prepare(
            "SELECT s.id_categoria, COALESCE(cat.categoria,'?') AS categoria, COUNT(DISTINCT s.id_club) AS clubes
               FROM _ic_sorteo s
               LEFT JOIN _p_categorias cat ON cat.id = s.id_categoria
              WHERE s.id_evento = ?
              GROUP BY s.id_categoria
              ORDER BY cat.categoria ASC");
        $st->bind_param('i', $idEvento);
        $st->execute();
        $res = $st->get_result();
        $cats = [];
        while ($r = $res->fetch_assoc()) $cats[] = $r;
        echo json_encode(['success' => true, 'categorias' => $cats]);
        exit;
    }

    // Estado completo de una categoría: grupos, series, partidos, posiciones
    if ($action === 'estado') {
        $idCat = abs((int)($_GET['categoria'] ?? 0));
        if (!$idCat) { echo json_encode(['success' => false, 'error' => 'Falta categoría']); exit; }

        // Sorteo → clubes por grupo
        $st = $mysqli2->prepare(
            "SELECT s.grupo, s.id_club, cl.nombre
               FROM _ic_sorteo s JOIN _p_clubes cl ON cl.id = s.id_club
              WHERE s.id_evento = ? AND s.id_categoria = ?
              ORDER BY s.grupo ASC, s.posicion ASC");
        $st->bind_param('ii', $idEvento, $idCat);
        $st->execute();
        $res = $st->get_result();
        $grupos = [];   // [g => [ [id_club, nombre] ]]
        while ($r = $res->fetch_assoc()) $grupos[(int)$r['grupo']][] = $r;

        // Partidos de la categoría
        $st = $mysqli2->prepare("SELECT * FROM _ic_partidos WHERE id_evento = ? AND id_categoria = ?");
        $st->bind_param('ii', $idEvento, $idCat);
        $st->execute();
        $res = $st->get_result();
        $partidos = [];
        while ($r = $res->fetch_assoc()) $partidos[] = $r;

        // Nombres de jugadores usados en los partidos
        $cis = [];
        foreach ($partidos as $m) foreach (['ci1_a','ci1_b','ci2_a','ci2_b'] as $k) $cis[$m[$k]] = 1;
        $nombres = [];
        if ($cis) {
            $in = "'" . implode("','", array_map(fn($c) => $mysqli2->real_escape_string($c), array_keys($cis))) . "'";
            $r2 = $mysqli2->query("SELECT ci, TRIM(CONCAT(COALESCE(nombre,''),' ',COALESCE(apellido,''))) n FROM _p_usuarios WHERE TRIM(ci) IN ($in)");
            while ($x = $r2->fetch_assoc()) $nombres[trim($x['ci'])] = $x['n'];
        }

        $out = [];
        foreach ($grupos as $g => $clubesG) {
            $mapa = [];
            foreach ($clubesG as $c) $mapa[(int)$c['id_club']] = $c['nombre'];

            // Duplas por club
            $duplas = [];
            foreach ($mapa as $idCl => $nom) $duplas[$idCl] = ic_duplas($mysqli2, $idEvento, $idCat, $idCl);

            // Partidos del grupo
            $partG = array_values(array_filter($partidos, fn($m) => (int)$m['grupo'] === $g));

            // Series round robin
            $series = [];
            $slotsPorCruce = [];
            $n = count($clubesG);
            for ($i = 0; $i < $n; $i++) for ($j = $i + 1; $j < $n; $j++) {
                $a = (int)$clubesG[$i]['id_club']; $b = (int)$clubesG[$j]['id_club'];
                $k = min($a, $b) . '-' . max($a, $b);
                $slots = max(1, min(count($duplas[$a]), count($duplas[$b])));
                $slotsPorCruce[$k] = $slots;
                $ms = array_values(array_filter($partG, fn($m) =>
                    (min((int)$m['club1'], (int)$m['club2']) . '-' . max((int)$m['club1'], (int)$m['club2'])) === $k));
                usort($ms, fn($x, $y) => [(int)$x['es_desempate'], (int)$x['id']] <=> [(int)$y['es_desempate'], (int)$y['id']]);
                [$wA, $wB, $definida, $ganador, $necesitaDes] = ic_estado_serie($ms, $a, $b, $slots);
                $msOut = [];
                foreach ($ms as $m) {
                    [$s1, $s2] = ic_sets_partido($m);
                    $msOut[] = [
                        'id' => (int)$m['id'], 'es_desempate' => (int)$m['es_desempate'],
                        'club1' => (int)$m['club1'], 'club2' => (int)$m['club2'],
                        'ci1_a' => $m['ci1_a'], 'ci1_b' => $m['ci1_b'], 'ci2_a' => $m['ci2_a'], 'ci2_b' => $m['ci2_b'],
                        'n1a' => $nombres[$m['ci1_a']] ?? $m['ci1_a'], 'n1b' => $nombres[$m['ci1_b']] ?? $m['ci1_b'],
                        'n2a' => $nombres[$m['ci2_a']] ?? $m['ci2_a'], 'n2b' => $nombres[$m['ci2_b']] ?? $m['ci2_b'],
                        's' => [(int)$m['s1c1'], (int)$m['s1c2'], (int)$m['s2c1'], (int)$m['s2c2'], (int)$m['s3c1'], (int)$m['s3c2']],
                        'sets' => [$s1, $s2], 'ganador' => ic_ganador_partido($m),
                    ];
                }
                $series[] = [
                    'clubA' => $a, 'clubB' => $b, 'nomA' => $mapa[$a], 'nomB' => $mapa[$b],
                    'slots' => $slots, 'winsA' => $wA, 'winsB' => $wB,
                    'definida' => $definida, 'ganador' => $ganador, 'necesita_desempate' => $necesitaDes,
                    'partidos' => $msOut,
                ];
            }

            // Jugadores por club (para el desempate mezclado)
            $jugadores = [];
            foreach ($duplas as $idCl => $ds) {
                foreach ($ds as $ix => $d) {
                    $jugadores[$idCl][] = ['ci' => $d['ci'], 'nombre' => $d['j1'] ?: $d['ci']];
                    $jugadores[$idCl][] = ['ci' => $d['ci_dupla'], 'nombre' => $d['j2'] ?: $d['ci_dupla']];
                }
            }

            $out[] = [
                'grupo' => $g,
                'clubes' => array_map(fn($c) => ['id' => (int)$c['id_club'], 'nombre' => $c['nombre']], $clubesG),
                'duplas' => $duplas,
                'jugadores' => $jugadores,
                'series' => $series,
                'posiciones' => ic_posiciones($mapa, $partG, $slotsPorCruce),
            ];
        }
        echo json_encode(['success' => true, 'grupos' => $out]);
        exit;
    }

    // Guardar partido (alta o edición de sets)
    if ($action === 'guardar_partido') {
        $id     = abs((int)($_GET['id'] ?? 0));
        $idCat  = abs((int)($_GET['categoria'] ?? 0));
        $grupo  = abs((int)($_GET['grupo'] ?? 0));
        $club1  = abs((int)($_GET['club1'] ?? 0));
        $club2  = abs((int)($_GET['club2'] ?? 0));
        $esDes  = (int)($_GET['es_desempate'] ?? 0) ? 1 : 0;
        $cis    = [];
        foreach (['ci1_a','ci1_b','ci2_a','ci2_b'] as $k) $cis[$k] = preg_replace('/[^0-9]/', '', $_GET[$k] ?? '');
        $s = [];
        foreach (['s1c1','s1c2','s2c1','s2c2','s3c1','s3c2'] as $k) {
            $v = (int)($_GET[$k] ?? 0);
            if ($v < 0 || $v > 30) { echo json_encode(['success' => false, 'error' => 'Games inválidos']); exit; }
            $s[$k] = $v;
        }
        // Debe haber un ganador
        $tmp = array_merge($s, ['club1' => 1, 'club2' => 2, 'es_desempate' => 0]);
        if (ic_ganador_partido($tmp) === 0) {
            echo json_encode(['success' => false, 'error' => 'El partido debe tener un ganador (cargá el set de desempate si empataron)']); exit;
        }

        if ($id) {
            $st = $mysqli2->prepare("UPDATE _ic_partidos SET s1c1=?,s1c2=?,s2c1=?,s2c2=?,s3c1=?,s3c2=? WHERE id=? AND id_evento=? LIMIT 1");
            $st->bind_param('iiiiiiii', $s['s1c1'], $s['s1c2'], $s['s2c1'], $s['s2c2'], $s['s3c1'], $s['s3c2'], $id, $idEvento);
            $st->execute();
            echo json_encode(['success' => true]);
            exit;
        }

        if (!$idCat || !$grupo || !$club1 || !$club2 || in_array('', $cis, true)) {
            echo json_encode(['success' => false, 'error' => 'Faltan datos del partido']); exit;
        }
        // Ambos clubes deben estar sorteados en ese grupo
        $st = $mysqli2->prepare("SELECT COUNT(*) c FROM _ic_sorteo WHERE id_evento=? AND id_categoria=? AND grupo=? AND id_club IN (?,?)");
        $st->bind_param('iiiii', $idEvento, $idCat, $grupo, $club1, $club2);
        $st->execute();
        if ((int)$st->get_result()->fetch_assoc()['c'] !== 2) {
            echo json_encode(['success' => false, 'error' => 'Los clubes no pertenecen a ese grupo']); exit;
        }
        $st = $mysqli2->prepare(
            "INSERT INTO _ic_partidos (id_evento, id_categoria, grupo, club1, club2, es_desempate,
                ci1_a, ci1_b, ci2_a, ci2_b, s1c1, s1c2, s2c1, s2c2, s3c1, s3c2)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $st->bind_param('iiiiiissssiiiiii', $idEvento, $idCat, $grupo, $club1, $club2, $esDes,
            $cis['ci1_a'], $cis['ci1_b'], $cis['ci2_a'], $cis['ci2_b'],
            $s['s1c1'], $s['s1c2'], $s['s2c1'], $s['s2c2'], $s['s3c1'], $s['s3c2']);
        $st->execute();
        echo json_encode(['success' => true, 'id' => $mysqli2->insert_id]);
        exit;
    }

    if ($action === 'borrar_partido') {
        $id = abs((int)($_GET['id'] ?? 0));
        $st = $mysqli2->prepare("DELETE FROM _ic_partidos WHERE id = ? AND id_evento = ? LIMIT 1");
        $st->bind_param('ii', $id, $idEvento);
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
<title>Resultados · <?= htmlspecialchars($evento['evento']) ?></title>
<link rel="shortcut icon" href="/favicon.ico">
<style>
  :root { --azul:#0b6aa8; --azul-osc:#084d7a; --arena:#f6f1e7; --ok:#1c9c50; --err:#d54141; --gris:#6b7684; --borde:#e2e6eb; --morado:#7c3aed; }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--arena); color: #22303c; min-height: 100vh; }
  .wrap { max-width: 900px; margin: 0 auto; padding: 16px 14px 60px; }
  .head { background: linear-gradient(135deg, var(--azul), var(--azul-osc)); color: #fff; border-radius: 14px; padding: 18px; margin-bottom: 14px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; }
  .head h1 { font-size: 19px; }
  .head .sub { font-size: 12px; opacity: .85; margin-top: 3px; }
  .cats { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 14px; }
  .cat-pill { border: 1px solid var(--borde); background: #fff; border-radius: 20px; padding: 7px 14px; font-size: 13px; font-weight: 600; cursor: pointer; }
  .cat-pill.activa { background: var(--azul); color: #fff; border-color: var(--azul); }
  .panel { background: #fff; border: 1px solid var(--borde); border-radius: 12px; padding: 16px; margin-bottom: 14px; }
  .panel h2 { font-size: 14px; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 10px; }
  .panel.g1 h2 { color: var(--azul); } .panel.g2 h2 { color: var(--morado); }
  table.pos { width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 14px; }
  table.pos th { background: #f1f4f7; text-transform: uppercase; font-size: 10px; letter-spacing: .5px; color: var(--gris); padding: 6px; text-align: center; }
  table.pos td { padding: 7px 6px; border-bottom: 1px solid var(--borde); text-align: center; }
  table.pos td.club { text-align: left; font-weight: 700; }
  table.pos tr:first-child td { background: #f4faf6; }
  .serie { border: 1px solid var(--borde); border-radius: 10px; margin-bottom: 12px; overflow: hidden; }
  .serie-h { padding: 10px 13px; background: #f8fafb; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 6px; }
  .serie-h .titulo { font-weight: 800; font-size: 14px; }
  .badge { font-size: 11px; font-weight: 800; padding: 3px 10px; border-radius: 12px; }
  .badge.ok { background: #e2f6e9; color: var(--ok); }
  .badge.pend { background: #fff4e0; color: #9a6a12; }
  .badge.des { background: #f3e8ff; color: var(--morado); }
  .partido { padding: 10px 13px; border-top: 1px solid var(--borde); }
  .partido .pt { font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .5px; color: var(--gris); margin-bottom: 5px; }
  .partido .pt .des { color: var(--morado); }
  .lados { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; font-size: 13px; }
  .lados .dupla { flex: 1; min-width: 150px; }
  .lados .dupla .club-mini { font-size: 10px; font-weight: 800; color: var(--azul); text-transform: uppercase; }
  .lados .dupla div { line-height: 1.35; }
  .lados .dupla.gano div { font-weight: 800; }
  .sets { display: flex; gap: 4px; align-items: center; }
  .sets input { width: 38px; padding: 6px 2px; text-align: center; border: 1px solid var(--borde); border-radius: 6px; font-size: 13px; font-weight: 700; }
  .sets .sep { color: var(--gris); font-size: 11px; }
  .acc { display: flex; gap: 6px; margin-top: 7px; }
  .btn { border: none; border-radius: 7px; padding: 7px 12px; font-size: 12px; font-weight: 700; cursor: pointer; }
  .btn-ok { background: var(--ok); color: #fff; }
  .btn-gh { background: #eef2f5; color: var(--gris); }
  .btn-del { background: #fdeaea; color: var(--err); }
  select.jug { padding: 6px; border: 1px solid var(--borde); border-radius: 6px; font-size: 12px; max-width: 170px; margin: 2px 0; display: block; }
  .msg { font-size: 13px; color: var(--gris); font-style: italic; }
  .toast { position: fixed; bottom: 18px; left: 50%; transform: translateX(-50%); background: #22303c; color: #fff; padding: 10px 18px; border-radius: 10px; font-size: 13px; display: none; z-index: 99; }
</style>
</head>
<body>
<div class="wrap">
  <div class="head">
    <div>
      <h1>📊 Resultados — <?= htmlspecialchars($evento['evento']) ?></h1>
      <div class="sub">Partido 1: dupla 1 vs dupla 1 · Partido 2: dupla 2 vs dupla 2 · serie empatada → desempate con dupla mezclada</div>
    </div>
    <div style="display:flex;gap:12px;">
      <a href="/interclubes_sorteo.php?evento=<?= $idEvento ?>" style="color:#fff;font-size:12px;">🎲 Sorteo</a>
      <a href="/tvt_admin_v2.php" style="color:#fff;font-size:12px;">← Admin</a>
    </div>
  </div>

  <div class="cats" id="cats"></div>
  <div id="contenido"></div>
  <div id="sinCat" class="panel msg">Seleccioná una categoría. Solo aparecen las que ya tienen sorteo cargado.</div>
</div>
<div class="toast" id="toast"></div>

<script>
const EVENTO = <?= $idEvento ?>;
let catActual = 0;

async function api(params) {
  const q = new URLSearchParams({evento: EVENTO, ...params}).toString();
  const r = await fetch('interclubes_resultados.php?' + q);
  return r.json();
}
function toast(m) {
  const t = document.getElementById('toast');
  t.textContent = m; t.style.display = 'block';
  setTimeout(() => t.style.display = 'none', 2200);
}
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

async function cargarCats() {
  const r = await api({action: 'categorias'});
  if (!r.success) return;
  const box = document.getElementById('cats');
  box.innerHTML = r.categorias.length ? '' : '<div class="msg">Todavía no hay sorteo cargado en ninguna categoría.</div>';
  r.categorias.forEach(c => {
    const b = document.createElement('button');
    b.className = 'cat-pill'; b.id = 'catpill-' + c.id_categoria;
    b.textContent = c.categoria;
    b.onclick = () => { catActual = parseInt(c.id_categoria); marcarPill(); cargarEstado(); };
    box.appendChild(b);
  });
}
function marcarPill() {
  document.querySelectorAll('.cat-pill').forEach(p => p.classList.remove('activa'));
  const p = document.getElementById('catpill-' + catActual);
  if (p) p.classList.add('activa');
  document.getElementById('sinCat').style.display = 'none';
}

function setsInputs(pref, s) {
  s = s || [0,0,0,0,0,0];
  return `<div class="sets">
    <input type="number" min="0" max="30" id="${pref}-s1a" value="${s[0]||''}" placeholder="0"><span class="sep">-</span><input type="number" min="0" max="30" id="${pref}-s1b" value="${s[1]||''}" placeholder="0">
    <span class="sep">|</span>
    <input type="number" min="0" max="30" id="${pref}-s2a" value="${s[2]||''}" placeholder="0"><span class="sep">-</span><input type="number" min="0" max="30" id="${pref}-s2b" value="${s[3]||''}" placeholder="0">
    <span class="sep">|</span>
    <input type="number" min="0" max="30" id="${pref}-s3a" value="${s[4]||''}" placeholder="0"><span class="sep">-</span><input type="number" min="0" max="30" id="${pref}-s3b" value="${s[5]||''}" placeholder="0">
  </div>`;
}
function leerSets(pref) {
  const v = id => parseInt(document.getElementById(pref + '-' + id).value) || 0;
  return {s1c1: v('s1a'), s1c2: v('s1b'), s2c1: v('s2a'), s2c2: v('s2b'), s3c1: v('s3a'), s3c2: v('s3b')};
}

async function cargarEstado() {
  const r = await api({action: 'estado', categoria: catActual});
  if (!r.success) { toast(r.error || 'Error'); return; }
  const box = document.getElementById('contenido');
  box.innerHTML = '';
  r.grupos.forEach(g => {
    let h = `<div class="panel g${g.grupo}"><h2>Grupo ${g.grupo} — Posiciones</h2>`;
    h += `<table class="pos"><tr><th>#</th><th style="text-align:left;">Club</th><th>SJ</th><th>SG</th><th>SP</th><th>PG</th><th>PP</th><th>Sets</th><th>PTS</th></tr>`;
    g.posiciones.forEach((p, i) => {
      h += `<tr><td>${i+1}</td><td class="club">${esc(p.club)}</td><td>${p.sj}</td><td>${p.sg}</td><td>${p.sp}</td><td>${p.pg}</td><td>${p.pp}</td><td>${p.setsF}-${p.setsC}</td><td><b>${p.pts}</b></td></tr>`;
    });
    h += `</table>`;

    g.series.forEach((serie, si) => {
      const sid = `g${g.grupo}s${si}`;
      let badge;
      if (serie.definida) badge = `<span class="badge ok">Ganó ${esc(serie.ganador === serie.clubA ? serie.nomA : serie.nomB)} ${Math.max(serie.winsA, serie.winsB)}-${Math.min(serie.winsA, serie.winsB)}</span>`;
      else if (serie.necesita_desempate) badge = `<span class="badge des">Serie ${serie.winsA}-${serie.winsB} → DESEMPATE</span>`;
      else badge = `<span class="badge pend">Serie ${serie.winsA}-${serie.winsB}</span>`;
      h += `<div class="serie"><div class="serie-h"><span class="titulo">${esc(serie.nomA)} <span style="color:var(--gris);font-size:11px;">VS</span> ${esc(serie.nomB)}</span>${badge}</div>`;

      const dupA = g.duplas[serie.clubA] || [], dupB = g.duplas[serie.clubB] || [];
      // Slots regulares
      for (let i = 0; i < serie.slots; i++) {
        const m = serie.partidos.filter(x => !x.es_desempate)[i];
        const pref = `${sid}p${i}`;
        h += `<div class="partido"><div class="pt">Partido ${i+1} — Dupla ${i+1} vs Dupla ${i+1}</div>`;
        if (m) {
          h += renderLados(m, serie);
          h += setsInputs(pref, m.s);
          h += `<div class="acc"><button class="btn btn-ok" onclick="guardar('${pref}', {id: ${m.id}})">Guardar cambios</button>
                <button class="btn btn-del" onclick="borrar(${m.id})">Borrar</button></div>`;
        } else if (dupA[i] && dupB[i]) {
          h += `<div class="lados">
            <div class="dupla"><div class="club-mini">${esc(serie.nomA)} ${i+1}</div><div>${esc(dupA[i].j1)}</div><div>${esc(dupA[i].j2)}</div></div>
            <div class="dupla"><div class="club-mini">${esc(serie.nomB)} ${i+1}</div><div>${esc(dupB[i].j1)}</div><div>${esc(dupB[i].j2)}</div></div>
          </div>`;
          h += setsInputs(pref);
          h += `<div class="acc"><button class="btn btn-ok" onclick="guardar('${pref}', {categoria: ${catActual}, grupo: ${g.grupo}, club1: ${serie.clubA}, club2: ${serie.clubB}, es_desempate: 0,
            ci1_a: '${dupA[i].ci}', ci1_b: '${dupA[i].ci_dupla}', ci2_a: '${dupB[i].ci}', ci2_b: '${dupB[i].ci_dupla}'})">Cargar resultado</button></div>`;
        } else {
          h += `<div class="msg">Falta la dupla ${i+1} de ${esc(!dupA[i] ? serie.nomA : serie.nomB)}</div>`;
        }
        h += `</div>`;
      }
      // Desempate
      const des = serie.partidos.find(x => x.es_desempate);
      if (des || serie.necesita_desempate) {
        const pref = `${sid}des`;
        h += `<div class="partido" style="background:#fbf7ff;"><div class="pt"><span class="des">★ Desempate — dupla mezclada</span></div>`;
        if (des) {
          h += renderLados(des, serie);
          h += setsInputs(pref, des.s);
          h += `<div class="acc"><button class="btn btn-ok" onclick="guardar('${pref}', {id: ${des.id}})">Guardar cambios</button>
                <button class="btn btn-del" onclick="borrar(${des.id})">Borrar</button></div>`;
        } else {
          const selJug = (club, nom, id) => {
            let o = `<select class="jug" id="${pref}-${id}"><option value="">Jugador de ${esc(nom)}...</option>`;
            (g.jugadores[club] || []).forEach(j => o += `<option value="${esc(j.ci)}">${esc(j.nombre)}</option>`);
            return o + '</select>';
          };
          h += `<div class="lados">
            <div class="dupla"><div class="club-mini">${esc(serie.nomA)}</div>${selJug(serie.clubA, serie.nomA, 'j1a')}${selJug(serie.clubA, serie.nomA, 'j1b')}</div>
            <div class="dupla"><div class="club-mini">${esc(serie.nomB)}</div>${selJug(serie.clubB, serie.nomB, 'j2a')}${selJug(serie.clubB, serie.nomB, 'j2b')}</div>
          </div>`;
          h += setsInputs(pref);
          h += `<div class="acc"><button class="btn btn-ok" onclick="guardarDesempate('${pref}', ${catActual}, ${g.grupo}, ${serie.clubA}, ${serie.clubB})">Cargar desempate</button></div>`;
        }
        h += `</div>`;
      }
      h += `</div>`;
    });
    if (!g.series.length) h += `<div class="msg">Sin enfrentamientos (faltan clubes en el sorteo)</div>`;
    h += `</div>`;
    box.innerHTML += h;
  });
}

function renderLados(m, serie) {
  const ganA = m.ganador === 1, ganB = m.ganador === 2;
  const score = `${m.sets[0]}-${m.sets[1]}`;
  return `<div class="lados">
    <div class="dupla ${ganA ? 'gano' : ''}"><div class="club-mini">${esc(m.club1 === serie.clubA ? serie.nomA : serie.nomB)} ${ganA ? '✓' : ''}</div><div>${esc(m.n1a)}</div><div>${esc(m.n1b)}</div></div>
    <div style="font-weight:800;color:var(--gris);font-size:12px;">${score}</div>
    <div class="dupla ${ganB ? 'gano' : ''}"><div class="club-mini">${esc(m.club2 === serie.clubA ? serie.nomA : serie.nomB)} ${ganB ? '✓' : ''}</div><div>${esc(m.n2a)}</div><div>${esc(m.n2b)}</div></div>
  </div>`;
}

async function guardar(pref, extra) {
  const r = await api({action: 'guardar_partido', ...extra, ...leerSets(pref)});
  if (!r.success) { toast(r.error || 'Error'); return; }
  toast('Guardado ✓');
  cargarEstado();
}
async function guardarDesempate(pref, cat, grupo, clubA, clubB) {
  const v = id => document.getElementById(pref + '-' + id).value;
  if (!v('j1a') || !v('j1b') || !v('j2a') || !v('j2b')) { toast('Elegí los 4 jugadores'); return; }
  if (v('j1a') === v('j1b') || v('j2a') === v('j2b')) { toast('Los jugadores de una dupla deben ser distintos'); return; }
  await guardar(pref, {categoria: cat, grupo, club1: clubA, club2: clubB, es_desempate: 1,
    ci1_a: v('j1a'), ci1_b: v('j1b'), ci2_a: v('j2a'), ci2_b: v('j2b')});
}
async function borrar(id) {
  if (!confirm('¿Borrar este partido?')) return;
  await api({action: 'borrar_partido', id});
  cargarEstado();
}

cargarCats();
</script>
</body>
</html>
