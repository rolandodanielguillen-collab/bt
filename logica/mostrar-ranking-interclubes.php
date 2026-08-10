<?php
/**
 * logica/mostrar-ranking-interclubes.php — Ranking de CLUBES del circuito (Interclubes)
 * Se llega desde ranking.php?url=<circuito>&ic (pestaña INTERCLUBES).
 *
 * Modelo (2026-08-10): cada categoría reparte puntos por posición final
 * (IC_PUNTOS_POSICION: campeón 100 · finalista 75 · 3° 60 · 4° 50 · participación 30).
 * El puntaje del club en un evento es la suma de sus 10 categorías; el ranking
 * acumula los eventos interclubes del circuito. Cálculo EN VIVO — sin tabla ni
 * recálculo: son 6 queries para todo el circuito.
 * ponytail: si algún día hay ~10 eventos y se nota, acá va la caché por firma
 * (misma receta que grafico-llaves-v2), no una tabla _ranking_clubes.
 */

if (isset($pagina)) {
    include_once "db/conection.inc.php";
    require_once "interclubes.functions.php";
} else {
    include_once "../db/conection.inc.php";
    require_once "../interclubes.functions.php";
}

// ── Circuito de la URL amigable ──────────────────────────────────────────────
// `fecha_fin` es lo que CIERRA el circuito (se carga en el admin → Circuitos):
// hasta ese día hay líder; pasada, hay campeón del circuito.
$idcircuito = 0;
$circuitoCerrado = false;
if (isset($_GET['url'])) {
    $st = $mysqli2->prepare("SELECT id, fecha_fin FROM _circuitos WHERE url_amigable=?");
    $st->bind_param('s', $_GET['url']);
    $st->execute();
    $rowC = $st->get_result()->fetch_assoc();
    $st->close();
    $idcircuito = (int)($rowC['id'] ?? 0);
    $finC = $rowC['fecha_fin'] ?? null;
    $circuitoCerrado = $finC && $finC !== '0000-00-00' && date('Y-m-d') >= $finC;
}

// Nombre visible: los nombres viejos de la base vienen en latin1 en algunas filas
function ric_txt($s): string {
    if (!mb_check_encoding((string)$s, 'UTF-8')) $s = mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
    return htmlspecialchars(mb_strtoupper(trim((string)$s), 'UTF-8'));
}

// ── Eventos interclubes del circuito ─────────────────────────────────────────
// Solo CULMINADOS (regla del usuario 2026-08-10): un torneo suma al ranking
// recién cuando la organización lo cierra, igual que el ranking de jugadores.
$eventosIC = [];
if ($idcircuito) {
    $st = $mysqli2->prepare("SELECT id, evento, fecha FROM _p_eventos
        WHERE id_circuito=? AND id_tipo_evento=5 AND estado='culminado'
        ORDER BY fecha ASC, id ASC");
    $st->bind_param('i', $idcircuito);
    $st->execute();
    $res = $st->get_result();
    while ($r = $res->fetch_assoc()) $eventosIC[(int)$r['id']] = $r['evento'];
    $st->close();
}
// Fechas del circuito ya creadas pero todavía sin cerrar: mientras haya una,
// el circuito claramente sigue abierto aunque nadie haya cargado fecha_fin.
$fechasPendientes = 0;
if ($idcircuito) {
    $fechasPendientes = (int)($mysqli2->query("SELECT COUNT(*) c FROM _p_eventos
        WHERE id_circuito={$idcircuito} AND id_tipo_evento=5
          AND estado IN ('activo','registro')")->fetch_assoc()['c'] ?? 0);
}
if ($fechasPendientes > 0) $circuitoCerrado = false;

// Número de fecha dentro del circuito (los eventos ya vienen ordenados por fecha)
$nroFecha = [];
$n = 0;
foreach (array_keys($eventosIC) as $idEv) $nroFecha[$idEv] = ++$n;

// Selector de evento: TODOS (acumulado) o uno solo
$evSel = isset($_GET['ev']) ? abs((int)$_GET['ev']) : 0;
if (!isset($eventosIC[$evSel])) $evSel = 0;
$eventosVer = $evSel ? [$evSel => $eventosIC[$evSel]] : $eventosIC;

$ranking = [];      // clave de club => fila del ranking
$catNombre = [];    // id_categoria => nombre
$catsEnCurso = [];  // id_evento => cantidad de categorías sin final definida
$escalaEv = [];     // id_evento => [posición => puntos] vigente en esa fecha

if ($eventosVer) {
    $ids = implode(',', array_map('intval', array_keys($eventosVer)));

    $clubes = [];   // id_evento => [id_club => nombre]
    $res = $mysqli2->query("SELECT id, id_evento, nombre FROM _p_clubes WHERE id_evento IN ({$ids})");
    while ($r = $res->fetch_assoc()) $clubes[(int)$r['id_evento']][(int)$r['id']] = $r['nombre'];

    $sorteo = [];   // id_evento => id_categoria => [id_club => nombre]
    $res = $mysqli2->query("SELECT id_evento, id_categoria, id_club FROM _ic_sorteo WHERE id_evento IN ({$ids}) ORDER BY posicion ASC");
    while ($r = $res->fetch_assoc()) {
        $ev = (int)$r['id_evento']; $cl = (int)$r['id_club'];
        if (isset($clubes[$ev][$cl])) $sorteo[$ev][(int)$r['id_categoria']][$cl] = $clubes[$ev][$cl];
    }

    $partidos = [];  // id_evento => id_categoria => filas
    $res = $mysqli2->query("SELECT * FROM _ic_partidos WHERE id_evento IN ({$ids})");
    while ($r = $res->fetch_assoc()) $partidos[(int)$r['id_evento']][(int)$r['id_categoria']][] = $r;

    $llaves = [];    // id_evento => id_categoria => fase => fila
    $res = $mysqli2->query("SELECT id_evento, id_categoria, fase, clubA, clubB FROM _ic_llaves WHERE id_evento IN ({$ids})");
    while ($r = $res->fetch_assoc()) $llaves[(int)$r['id_evento']][(int)$r['id_categoria']][$r['fase']] = $r;

    $res = $mysqli2->query("SELECT id, categoria FROM _p_categorias");
    while ($r = $res->fetch_assoc()) $catNombre[(int)$r['id']] = $r['categoria'];

    $matriz = ic_puntos_matriz($mysqli2, array_keys($eventosVer));

    $etiquetaPos = [1 => 'Campeón', 2 => 'Finalista', 3 => 'Tercer puesto', 4 => 'Cuarto puesto', 0 => 'Participación'];

    foreach ($eventosVer as $idEv => $nombreEv) {
        $catsEnCurso[$idEv] = 0;
        $catsEv = $sorteo[$idEv] ?? [];
        uksort($catsEv, fn($a, $b) => strcmp($catNombre[$a] ?? '', $catNombre[$b] ?? ''));
        // Escala vigente en esta fecha (para el pie): la del admin si la cargaron,
        // si no la de por defecto. Se toma de la 1ra categoría — el editor de
        // interclubes guarda los mismos 5 valores en todas.
        $catRef = array_key_first($catsEv);
        if ($catRef !== null)
            foreach ([1, 2, 3, 4, 0] as $p) $escalaEv[$idEv][$p] = ic_puntos_pos($matriz, $idEv, $catRef, $p);
        foreach ($catsEv as $idCat => $clubesCat) {
            $posiciones = ic_posiciones_categoria(
                $clubesCat,
                $llaves[$idEv][$idCat] ?? [],
                $partidos[$idEv][$idCat] ?? []
            );
            if (!$posiciones) { $catsEnCurso[$idEv]++; continue; }  // final sin definir: no puntúa

            foreach ($posiciones as $idClub => $pos) {
                $nombreClub = $clubesCat[$idClub] ?? '';
                if ($nombreClub === '') continue;
                $k = ic_clave_club($nombreClub);
                if (!isset($ranking[$k])) {
                    $ranking[$k] = ['nombre' => $nombreClub, 'total' => 0,
                                    'conteo' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 0 => 0], 'eventos' => []];
                }
                $pts = ic_puntos_pos($matriz, $idEv, $idCat, $pos);
                $ranking[$k]['total'] += $pts;
                $ranking[$k]['conteo'][$pos]++;
                if (!isset($ranking[$k]['eventos'][$idEv]))
                    $ranking[$k]['eventos'][$idEv] = ['pts' => 0, 'conteo' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 0 => 0], 'cats' => []];
                $ranking[$k]['eventos'][$idEv]['pts'] += $pts;
                $ranking[$k]['eventos'][$idEv]['conteo'][$pos]++;
                $ranking[$k]['eventos'][$idEv]['cats'][] = [
                    'cat'   => $catNombre[$idCat] ?? "Cat. {$idCat}",
                    'pos'   => $pos,
                    'label' => $etiquetaPos[$pos],
                    'pts'   => $pts,
                ];
            }
        }
    }
}

// Orden: puntos → títulos → finales → terceros → cuartos → nombre
$clave = fn($c) => [$c['total'], $c['conteo'][1], $c['conteo'][2], $c['conteo'][3], $c['conteo'][4]];
uasort($ranking, function ($a, $b) use ($clave) {
    $cmp = $clave($b) <=> $clave($a);
    return $cmp !== 0 ? $cmp : strcmp($a['nombre'], $b['nombre']);
});
$ranking = array_values($ranking);

// Campeón general: el 1°, o varios si empatan en TODO el criterio de desempate
$campeones = [];
if ($ranking) {
    $campeones[] = $ranking[0];
    for ($i = 1; $i < count($ranking); $i++) {
        if ($clave($ranking[$i]) !== $clave($ranking[0])) break;
        $campeones[] = $ranking[$i];
    }
}
$maxPts = $ranking ? max(1, $ranking[0]['total']) : 1;

// Campeón de CADA evento (mismo desempate, dentro del evento): [id_evento => nombres]
$campeonEvento = [];
foreach (array_keys($eventosVer) as $idEv) {
    $mejor = null; $nombres = [];
    foreach ($ranking as $c) {
        if (!isset($c['eventos'][$idEv])) continue;
        $e = $c['eventos'][$idEv];
        $k = [$e['pts'], $e['conteo'][1], $e['conteo'][2], $e['conteo'][3], $e['conteo'][4]];
        if ($mejor === null || $k > $mejor) { $mejor = $k; $nombres = [$c['nombre']]; }
        elseif ($k === $mejor) $nombres[] = $c['nombre'];
    }
    if ($nombres) $campeonEvento[$idEv] = $nombres;
}

// Pestañas y selector: mismos parámetros de la URL
$qsBase = 'url=' . urlencode($_GET['url'] ?? '') . (isset($_GET['v3']) ? '&v3' : '');
$qsIC   = htmlspecialchars($qsBase) . '&amp;ic';
?>

<style type="text/css">
#ric-container, #ric-container * { box-sizing: border-box !important; font-family: 'DM Sans', Arial, sans-serif !important; line-height: normal !important; }
#ric-container .ric-titulo { text-align:center !important; font-size:1.25em !important; font-weight:800 !important; color:#1e3a5f !important; margin:20px 0 14px !important; padding:0 !important; background:none !important; border:none !important; }
/* Pestañas Jugadores / Interclubes */
#ric-container .rk-tabs { display:flex !important; gap:6px !important; background:#eef2f6 !important; border-radius:10px !important; padding:5px !important; margin:0 auto 18px !important; max-width:420px !important; }
#ric-container .rk-tab { flex:1 !important; text-align:center !important; padding:9px 6px !important; border-radius:7px !important; font-size:12px !important; font-weight:800 !important; letter-spacing:.04em !important; text-decoration:none !important; color:#64748b !important; background:none !important; }
#ric-container .rk-tab.on { background:#1e3a5f !important; color:#fff !important; box-shadow:0 1px 3px rgba(0,0,0,.15) !important; }
/* Hero campeón general */
#ric-container .ric-hero { background:linear-gradient(135deg,#f3d878 0%,#c9a227 100%) !important; border-radius:12px !important; padding:18px 20px !important; margin-bottom:18px !important; box-shadow:0 4px 18px rgba(201,162,39,.35) !important; text-align:center !important; }
#ric-container .ric-hero-lbl { font-size:10px !important; font-weight:800 !important; letter-spacing:.14em !important; color:#5c470a !important; text-transform:uppercase !important; }
#ric-container .ric-hero-club { font-size:22px !important; font-weight:900 !important; color:#3d2f05 !important; margin:4px 0 2px !important; line-height:1.2 !important; }
#ric-container .ric-hero-sub { font-size:12px !important; font-weight:700 !important; color:#6b5410 !important; }
/* Líder (circuito en curso): azul, no la chapa dorada del campeón */
#ric-container .ric-hero.lider { background:linear-gradient(135deg,#1e3a5f 0%,#0f2744 100%) !important; box-shadow:0 4px 18px rgba(15,39,68,.3) !important; }
#ric-container .ric-hero.lider .ric-hero-lbl { color:#93b4d8 !important; }
#ric-container .ric-hero.lider .ric-hero-club { color:#fff !important; }
#ric-container .ric-hero.lider .ric-hero-sub { color:#c7d7e8 !important; }
#ric-container .ric-hero.lider .ric-hero-evs { color:#c7d7e8 !important; border-top-color:rgba(255,255,255,.2) !important; }
#ric-container .ric-aviso { font-size:11px !important; color:#6b7280 !important; text-align:center !important; margin:-8px 0 18px !important; }
#ric-container .ric-hero-evs { font-size:11px !important; font-weight:600 !important; color:#6b5410 !important; margin-top:8px !important; padding-top:8px !important; border-top:1px solid rgba(93,71,10,.25) !important; line-height:1.6 !important; }
/* Selector de evento */
#ric-container .ric-evs { display:flex !important; flex-wrap:wrap !important; gap:6px !important; justify-content:center !important; margin-bottom:16px !important; }
#ric-container .ric-ev { font-size:11px !important; font-weight:700 !important; padding:7px 14px !important; border-radius:999px !important; text-decoration:none !important; background:#fff !important; color:#374151 !important; border:1px solid #d7dee7 !important; }
#ric-container .ric-ev.on { background:#1e3a5f !important; color:#fff !important; border-color:#1e3a5f !important; }
#ric-container .c-club small { display:block !important; font-size:10px !important; font-weight:600 !important; color:#9ca3af !important; line-height:1.4 !important; }
/* Barras top clubes */
#ric-container .ric-top { background:linear-gradient(135deg,#1e3a5f 0%,#0f2744 100%) !important; border-radius:12px !important; padding:20px 18px 16px !important; margin-bottom:20px !important; box-shadow:0 4px 20px rgba(0,0,0,.25) !important; }
#ric-container .ric-top-title { font-size:14px !important; font-weight:800 !important; color:#fff !important; margin:0 0 14px !important; text-transform:uppercase !important; letter-spacing:.06em !important; }
#ric-container .ric-brow { display:flex !important; align-items:center !important; gap:10px !important; margin-bottom:8px !important; }
#ric-container .ric-bpos { width:24px !important; height:24px !important; border-radius:50% !important; display:flex !important; align-items:center !important; justify-content:center !important; font-size:11px !important; font-weight:800 !important; color:#fff !important; flex-shrink:0 !important; background:rgba(255,255,255,.12) !important; }
#ric-container .ric-bpos.g { background:linear-gradient(135deg,#f59e0b,#d97706) !important; }
#ric-container .ric-bpos.s { background:linear-gradient(135deg,#94a3b8,#64748b) !important; }
#ric-container .ric-bpos.b { background:linear-gradient(135deg,#d97706,#92400e) !important; }
#ric-container .ric-bname { width:150px !important; flex-shrink:0 !important; font-size:11px !important; font-weight:700 !important; color:#e2e8f0 !important; white-space:nowrap !important; overflow:hidden !important; text-overflow:ellipsis !important; }
#ric-container .ric-bwrap { flex:1 !important; height:22px !important; background:rgba(255,255,255,.08) !important; border-radius:6px !important; overflow:hidden !important; min-width:0 !important; }
#ric-container .ric-bar { height:100% !important; border-radius:6px !important; display:flex !important; align-items:center !important; justify-content:flex-end !important; padding-right:8px !important; font-size:11px !important; font-weight:800 !important; color:#fff !important; min-width:38px !important; background:linear-gradient(90deg,#3b82f6,#60a5fa) !important; }
#ric-container .ric-bar.b0 { background:linear-gradient(90deg,#eab308,#facc15) !important; color:#422006 !important; }
/* Tabla */
#ric-container .ric-card { background:#fff !important; border-radius:10px !important; box-shadow:0 2px 8px rgba(0,0,0,.10) !important; overflow:hidden !important; margin-bottom:14px !important; }
#ric-container .ric-scroll { overflow-x:auto !important; -webkit-overflow-scrolling:touch !important; }
#ric-container .ric-thead { display:flex !important; background:#f0f4f8 !important; border-bottom:2px solid #dde3ea !important; min-width:560px !important; }
#ric-container .ric-th { padding:9px 6px !important; font-size:10px !important; text-transform:uppercase !important; color:#6b7280 !important; letter-spacing:.04em !important; font-weight:800 !important; white-space:nowrap !important; text-align:center !important; }
#ric-container .ric-row { display:flex !important; align-items:center !important; border-bottom:1px solid #eee !important; cursor:pointer !important; min-width:560px !important; }
#ric-container .ric-row:hover { background:#eef2ff !important; }
#ric-container .ric-row.par { background:#f8fafc !important; }
#ric-container .ric-td { padding:11px 6px !important; text-align:center !important; font-size:13px !important; color:#111827 !important; }
#ric-container .c-pos { width:44px !important; flex-shrink:0 !important; font-weight:800 !important; color:#2563eb !important; }
#ric-container .c-club { flex:1 !important; min-width:120px !important; text-align:left !important; font-weight:700 !important; }
#ric-container .c-n { width:56px !important; flex-shrink:0 !important; color:#6b7280 !important; font-weight:700 !important; }
#ric-container .c-n.oro { color:#b45309 !important; font-weight:900 !important; }
#ric-container .c-tot { width:76px !important; flex-shrink:0 !important; font-weight:900 !important; color:#16a34a !important; font-size:15px !important; }
#ric-container .c-chev { width:30px !important; flex-shrink:0 !important; color:#9ca3af !important; font-size:12px !important; }
#ric-container .ric-det { display:none !important; border-bottom:1px solid #eee !important; background:#f3f4f6 !important; padding:6px 14px 12px !important; }
#ric-container .ric-det.open { display:block !important; }
#ric-container .ric-det-ev { font-size:11px !important; font-weight:800 !important; text-transform:uppercase !important; letter-spacing:.05em !important; color:#1e3a5f !important; padding:10px 0 4px !important; }
#ric-container .ric-det-row { display:flex !important; align-items:center !important; border-top:1px solid #e5e7eb !important; padding:6px 0 !important; }
#ric-container .ric-det-cat { flex:1 !important; font-size:12px !important; color:#374151 !important; font-weight:600 !important; }
#ric-container .ric-det-pos { width:110px !important; font-size:11px !important; font-weight:800 !important; color:#6b7280 !important; text-align:right !important; }
#ric-container .ric-det-pos.p1 { color:#b45309 !important; }
#ric-container .ric-det-pos.p2 { color:#64748b !important; }
#ric-container .ric-det-pos.p3 { color:#92400e !important; }
#ric-container .ric-det-pts { width:56px !important; font-size:12px !important; font-weight:800 !important; color:#2563eb !important; text-align:right !important; }
#ric-container .ric-nota { font-size:11px !important; color:#6b7280 !important; text-align:center !important; margin:14px 0 0 !important; line-height:1.5 !important; }
#ric-container .ric-vacio { text-align:center !important; color:#6b7280 !important; padding:46px 20px !important; font-size:14px !important; }
@media (max-width:480px) {
  #ric-container .ric-hero-club { font-size:18px !important; }
  #ric-container .ric-bname { width:96px !important; font-size:10px !important; }
  #ric-container .ric-th { font-size:9px !important; padding:7px 4px !important; }
  #ric-container .ric-td { font-size:12px !important; padding:9px 4px !important; }
  #ric-container .c-n { width:44px !important; }
  #ric-container .c-tot { width:62px !important; font-size:13px !important; }
}
</style>

<div id="ric-container" style="max-width:860px; margin:0 auto; padding:0 12px 50px;">

  <h3 class="ric-titulo">Ranking Interclubes</h3>

  <div class="rk-tabs">
    <a class="rk-tab" href="?<?php echo htmlspecialchars($qsBase); ?>">JUGADORES</a>
    <a class="rk-tab on" href="?<?php echo htmlspecialchars($qsBase); ?>&amp;ic">INTERCLUBES</a>
  </div>

<?php if (!$ranking): ?>
  <div class="ric-vacio">Todavía no hay torneos interclubes culminados en este circuito.<br>
    <span style="font-size:12px;">Cada torneo entra al ranking cuando la organización lo cierra.</span></div>
<?php else: ?>

  <?php if (count($eventosIC) > 1): ?>
  <div class="ric-evs">
    <a class="ric-ev <?php echo $evSel ? '' : 'on'; ?>" href="?<?php echo $qsIC; ?>">CIRCUITO</a>
    <?php foreach ($eventosIC as $idEv => $nomEv): ?>
    <a class="ric-ev <?php echo $evSel === $idEv ? 'on' : ''; ?>" href="?<?php echo $qsIC; ?>&amp;ev=<?php echo $idEv; ?>"><?php echo $nroFecha[$idEv]; ?>ª FECHA</a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php
  // Con una fecha elegida: campeón de esa fecha. En el acumulado: campeón del
  // circuito solo si el circuito ya cerró; mientras corre hay LÍDER, no campeón.
  $esCampeon = $evSel || $circuitoCerrado;
  $heroLbl = $evSel ? ($nroFecha[$evSel] . 'ª fecha · Campeón')
                    : ($circuitoCerrado ? '🏆 Campeón del Circuito' : 'Líder del Circuito');
  ?>
  <div class="ric-hero <?php echo $esCampeon ? '' : 'lider'; ?>">
    <div class="ric-hero-lbl"><?php echo $heroLbl; ?></div>
    <div class="ric-hero-club"><?php echo implode(' + ', array_map(fn($c) => ric_txt($c['nombre']), $campeones)); ?></div>
    <div class="ric-hero-sub">
      <?php echo $campeones[0]['total']; ?> pts ·
      <?php echo $campeones[0]['conteo'][1]; ?> título<?php echo $campeones[0]['conteo'][1] == 1 ? '' : 's'; ?>
      <?php if (!$evSel): ?>
        · tras <?php echo count($eventosVer); ?> fecha<?php echo count($eventosVer) == 1 ? '' : 's'; ?>
        <?php if ($fechasPendientes > 0): ?>, falta<?php echo $fechasPendientes == 1 ? '' : 'n'; ?> <?php echo $fechasPendientes; ?><?php endif; ?>
      <?php endif; ?>
      <?php echo count($campeones) > 1 ? ' · empate en la cima' : ''; ?>
    </div>
    <?php if (!$evSel && count($eventosVer) > 1): ?>
    <div class="ric-hero-evs">
      <?php foreach ($eventosVer as $idEv => $nomEv): if (!isset($campeonEvento[$idEv])) continue; ?>
        <div><?php echo $nroFecha[$idEv]; ?>ª fecha · <?php echo ric_txt($nomEv); ?>: <strong><?php echo implode(' + ', array_map('ric_txt', $campeonEvento[$idEv])); ?></strong></div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php if (!$evSel && !$circuitoCerrado): ?>
  <div class="ric-aviso">El circuito sigue en juego: cada fecha suma al total. El campeón se define al cierre.</div>
  <?php endif; ?>

  <div class="ric-top">
    <div class="ric-top-title">Puntos por club</div>
    <?php foreach (array_slice($ranking, 0, 10) as $i => $c):
        $pct = max(8, round($c['total'] / $maxPts * 100));
        $cls = $i === 0 ? 'g' : ($i === 1 ? 's' : ($i === 2 ? 'b' : '')); ?>
    <div class="ric-brow">
      <div class="ric-bpos <?php echo $cls; ?>"><?php echo $i + 1; ?></div>
      <div class="ric-bname"><?php echo ric_txt($c['nombre']); ?></div>
      <div class="ric-bwrap"><div class="ric-bar <?php echo $i === 0 ? 'b0' : ''; ?>" style="width:<?php echo $pct; ?>%;"><?php echo $c['total']; ?></div></div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="ric-card">
    <div class="ric-scroll">
      <div class="ric-thead">
        <div class="ric-th c-pos">Pos</div>
        <div class="ric-th c-club" style="text-align:left !important;">Club</div>
        <div class="ric-th c-n" title="Participación">Part.</div>
        <div class="ric-th c-n">4°</div>
        <div class="ric-th c-n">🥉</div>
        <div class="ric-th c-n">🥈</div>
        <div class="ric-th c-n">🥇</div>
        <div class="ric-th c-tot">Total</div>
        <div class="ric-th c-chev"></div>
      </div>

      <?php foreach ($ranking as $i => $c): $det = 'ricd-' . $i; ?>
      <div class="ric-row <?php echo $i % 2 ? 'par' : ''; ?>" onclick="ricToggle('<?php echo $det; ?>')">
        <div class="ric-td c-pos"><?php echo $i + 1; ?></div>
        <div class="ric-td c-club"><?php echo ric_txt($c['nombre']); ?>
          <?php if (!$evSel && count($eventosVer) > 1): ?><small><?php echo count($c['eventos']); ?> de <?php echo count($eventosVer); ?> eventos</small><?php endif; ?>
        </div>
        <div class="ric-td c-n"><?php echo $c['conteo'][0]; ?></div>
        <div class="ric-td c-n"><?php echo $c['conteo'][4]; ?></div>
        <div class="ric-td c-n"><?php echo $c['conteo'][3]; ?></div>
        <div class="ric-td c-n"><?php echo $c['conteo'][2]; ?></div>
        <div class="ric-td c-n oro"><?php echo $c['conteo'][1]; ?></div>
        <div class="ric-td c-tot"><?php echo $c['total']; ?></div>
        <div class="ric-td c-chev">&#8964;</div>
      </div>

      <div id="<?php echo $det; ?>" class="ric-det">
        <?php foreach ($c['eventos'] as $idEv => $ev): ?>
          <div class="ric-det-ev"><?php echo ric_txt($eventosIC[$idEv] ?? ''); ?> — <?php echo $ev['pts']; ?> pts</div>
          <?php foreach ($ev['cats'] as $d): ?>
          <div class="ric-det-row">
            <div class="ric-det-cat"><?php echo ric_txt($d['cat']); ?></div>
            <div class="ric-det-pos p<?php echo $d['pos']; ?>"><?php echo $d['label']; ?></div>
            <div class="ric-det-pts"><?php echo $d['pts']; ?></div>
          </div>
          <?php endforeach; ?>
          <?php if (!empty($catsEnCurso[$idEv])): ?>
          <div class="ric-det-row" style="border-top:none !important;">
            <div class="ric-det-cat" style="font-style:italic;color:#9ca3af !important;">
              <?php echo $catsEnCurso[$idEv]; ?> categoría<?php echo $catsEnCurso[$idEv] == 1 ? '' : 's'; ?> en curso — todavía no puntúa<?php echo $catsEnCurso[$idEv] == 1 ? '' : 'n'; ?>
            </div>
          </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <?php
  // Cada fecha tiene su propia tabla de puntos (admin → Puntajes por evento). Si
  // todas las mostradas coinciden se detalla; si difieren, se dice y punto.
  $escalasDistintas = count(array_unique(array_map('json_encode', $escalaEv))) > 1;
  $esc1 = $escalaEv ? reset($escalaEv) : IC_PUNTOS_POSICION;
  ?>
  <div class="ric-nota">
    <?php if ($escalasDistintas): ?>
    Cada fecha tiene su propia tabla de puntos por posición; el detalle de cada club muestra lo que sumó en cada una.<br>
    <?php else: ?>
    Campeón <?php echo $esc1[1]; ?> · Finalista <?php echo $esc1[2]; ?> ·
    Tercer puesto <?php echo $esc1[3]; ?> · Cuarto <?php echo $esc1[4]; ?> ·
    Participación <?php echo $esc1[0]; ?>, en cada categoría.<br>
    <?php endif; ?>
    Desempate: más títulos, después finales, terceros y cuartos puestos.<br>
    Suman los torneos interclubes ya culminados del circuito.
  </div>

<?php endif; ?>
</div>

<script>
function ricToggle(id) {
  var d = document.getElementById(id);
  if (d) d.classList.toggle('open');
}
</script>
