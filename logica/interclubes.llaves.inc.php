<?php
/**
 * logica/interclubes.llaves.inc.php — Desarrollo Interclubes (diseño todos-vs-todos)
 * Posiciones + series de grupos + llaves, con match-cards colapsables,
 * pill EN JUEGO naranja y badge FINALIZADO, igual que el TVT.
 */
if (isset($pagina)):
    include_once "db/conection.inc.php";
    require_once "interclubes.functions.php";
else:
    include "../db/conection.inc.php";
    require_once "../interclubes.functions.php";
endif;

$SHA1evento = isset($_GET['evento']) ? trim($_GET['evento']) : '';
$rowEvento  = null;
if (preg_match('/^[a-f0-9]{40}$/i', $SHA1evento)) {
    $st = $mysqli2->prepare(
        "SELECT id, evento, nombre_evento2, boton_fixture FROM _p_eventos
          WHERE sha1(id) = ? AND id_tipo_evento = 5 LIMIT 1");
    $st->bind_param('s', $SHA1evento);
    $st->execute();
    $rowEvento = $st->get_result()->fetch_assoc();
}
if (!$rowEvento) { echo '<div style="text-align:center;padding:60px;">Evento no encontrado.</div>'; return; }
if ($rowEvento['boton_fixture'] === 'oculto' && !isset($_GET['tp'])) {
    echo '<div>Datos temporalmente bloqueados!</div><div>Por favor vuelva más tarde</div>'; return;
}
$idEventos = (int)$rowEvento['id'];

// ── Categorías con sorteo ────────────────────────────────────────────────────
$st = $mysqli2->prepare(
    "SELECT s.id_categoria, COALESCE(cat.categoria,'?') AS categoria
       FROM _ic_sorteo s LEFT JOIN _p_categorias cat ON cat.id = s.id_categoria
      WHERE s.id_evento = ? GROUP BY s.id_categoria ORDER BY cat.categoria ASC");
$st->bind_param('i', $idEventos);
$st->execute();
$res = $st->get_result();
$catsIC = [];
while ($r = $res->fetch_assoc()) $catsIC[(int)$r['id_categoria']] = $r['categoria'];

$idCat = isset($_GET['categoria']) ? abs((int)$_GET['categoria']) : 0;
if (!isset($catsIC[$idCat])) $idCat = array_key_first($catsIC) ?: 0;

// ── Datos de la categoría seleccionada ───────────────────────────────────────
$grupos = $partidos = $nombres = $duplas = []; $mapaTodos = [];
if ($idCat) {
    $st = $mysqli2->prepare(
        "SELECT s.grupo, s.id_club, cl.nombre FROM _ic_sorteo s JOIN _p_clubes cl ON cl.id = s.id_club
          WHERE s.id_evento = ? AND s.id_categoria = ? ORDER BY s.grupo ASC, s.posicion ASC");
    $st->bind_param('ii', $idEventos, $idCat);
    $st->execute();
    $res = $st->get_result();
    while ($r = $res->fetch_assoc()) {
        $grupos[(int)$r['grupo']][] = $r;
        $mapaTodos[(int)$r['id_club']] = $r['nombre'];
    }
    $st = $mysqli2->prepare("SELECT * FROM _ic_partidos WHERE id_evento = ? AND id_categoria = ?");
    $st->bind_param('ii', $idEventos, $idCat);
    $st->execute();
    $res = $st->get_result();
    $cis = [];
    while ($r = $res->fetch_assoc()) { $partidos[] = $r; foreach (['ci1_a','ci1_b','ci2_a','ci2_b'] as $k) $cis[$r[$k]] = 1; }
    if ($cis) {
        $in = "'" . implode("','", array_map(fn($c) => $mysqli2->real_escape_string($c), array_keys($cis))) . "'";
        $r2 = $mysqli2->query("SELECT ci, TRIM(CONCAT(COALESCE(nombre,''),' ',COALESCE(apellido,''))) n FROM _p_usuarios WHERE TRIM(ci) IN ($in)");
        while ($x = $r2->fetch_assoc()) $nombres[trim($x['ci'])] = $x['n'];
    }
    foreach ($mapaTodos as $idCl => $n) $duplas[$idCl] = ic_duplas($mysqli2, $idEventos, $idCat, $idCl);
}

function icn($s) {
    if (!mb_check_encoding((string)$s, 'UTF-8')) $s = mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
    return htmlspecialchars(mb_strtoupper(trim((string)$s), 'UTF-8'));
}

// Render de una serie como match-card estilo TVT
function ic_card_serie($label, $a, $b, array $ms, int $slots, array $ctx) {
    ['mapaTodos' => $mapaTodos, 'nombres' => $nombres, 'duplas' => $duplas] = $ctx;
    usort($ms, fn($x, $y) => [(int)$x['es_desempate'], (int)$x['id']] <=> [(int)$y['es_desempate'], (int)$y['id']]);
    [$wA, $wB, $definida, $ganador, $necesitaDes] = ic_estado_serie($ms, $a, $b, $slots);
    $enJuego = false;
    foreach ($ms as $m) if (($m['en_juego'] ?? 'no') === 'si') $enJuego = true;

    $nomA = $mapaTodos[$a] ?? ('Club #' . $a); $nomB = $mapaTodos[$b] ?? ('Club #' . $b);
    if ($definida) {
        $summary = icn($ganador === $a ? $nomA : $nomB) . ' (' . max($wA, $wB) . '-' . min($wA, $wB) . ')';
        $badge = "<span class='badge-finalizado'>FINALIZADO</span>";
    } elseif ($ms) {
        $summary = "Serie {$wA}-{$wB}" . ($necesitaDes ? ' · desempate' : '') . ($enJuego ? ' 🔴 EN JUEGO' : '');
        $badge = '';
    } else {
        $summary = 'A continuación' . ($enJuego ? ' 🔴 EN JUEGO' : '');
        $badge = '';
    }
    $clase = $enJuego ? 'en-juego' : ($definida ? 'finalizado' : '');

    $h = "<div class='match-card {$clase}'>
      <button class='match-header' onclick='toggleMatch(this)'>
        <span class='round'>{$label}</span>
        <div class='info'><span class='summary'>" . icn($nomA) . " vs " . icn($nomB) . " — {$summary}</span>{$badge}</div>
        <span class='chevron'>▼</span>
      </button>
      <div class='match-body'>";

    // Partidos regulares por slot + desempate
    $regs = array_values(array_filter($ms, fn($m) => !(int)$m['es_desempate']));
    $des  = array_values(array_filter($ms, fn($m) => (int)$m['es_desempate']));
    $dupA = $duplas[$a] ?? []; $dupB = $duplas[$b] ?? [];

    for ($i = 0; $i < $slots; $i++) {
        $m = $regs[$i] ?? null;
        $h .= ic_fila_partido("Partido " . ($i + 1), $m, $a, $b, $nomA, $nomB,
            $dupA[$i] ?? null, $dupB[$i] ?? null, $nombres);
    }
    foreach ($des as $m) {
        $h .= ic_fila_partido("★ Desempate", $m, $a, $b, $nomA, $nomB, null, null, $nombres);
    }
    if ($necesitaDes && !$des) {
        $h .= "<div class='p-row'><div class='p-label'>★ Desempate — dupla mezclada</div><div class='p-pend'>Por definir</div></div>";
    }
    return $h . "</div></div>";
}

// Una fila de partido dentro de la serie (con o sin resultado)
function ic_fila_partido($label, $m, $a, $b, $nomA, $nomB, $dupSlotA, $dupSlotB, $nombres) {
    if (!$m) {
        if (!$dupSlotA || !$dupSlotB) return '';
        $j = fn($d, $k1, $k2) => icn(($d[$k1] ?: '')) . '<br>' . icn(($d[$k2] ?: ''));
        return "<div class='p-row'><div class='p-label'>{$label}</div>
          <div class='p-grid'>
            <div class='p-team'><span class='p-club'>" . icn($nomA) . "</span>" . $j($dupSlotA, 'j1', 'j2') . "</div>
            <div class='p-score p-pend'>por<br>jugar</div>
            <div class='p-team right'><span class='p-club'>" . icn($nomB) . "</span>" . $j($dupSlotB, 'j1', 'j2') . "</div>
          </div></div>";
    }
    [$s1, $s2] = ic_sets_partido($m);
    $gan = ic_ganador_partido($m);
    $ej  = ($m['en_juego'] ?? 'no') === 'si';
    $score = [];
    foreach ([['s1c1','s1c2'], ['s2c1','s2c2'], ['s3c1','s3c2']] as [$ka, $kb]) {
        if ((int)$m[$ka] === 0 && (int)$m[$kb] === 0) continue;
        $score[] = "<span class='set'>" . (int)$m[$ka] . "-" . (int)$m[$kb] . "</span>";
    }
    $lado = fn($ka, $kb, $win) =>
        "<span class='" . ($win ? 'p-win' : '') . "'>" . ($win ? '✓ ' : '') . icn($nombres[$m[$ka]] ?? $m[$ka]) . "<br>" .
        ($win ? '✓ ' : '') . icn($nombres[$m[$kb]] ?? $m[$kb]) . "</span>";
    $clubDeM1 = ((int)$m['club1'] === $a) ? $nomA : $nomB;
    $clubDeM2 = ((int)$m['club2'] === $a) ? $nomA : $nomB;
    return "<div class='p-row" . ($ej ? " p-ej" : "") . "'>
      <div class='p-label'>{$label}" . ($ej ? " <span class='ej-pill'>🔴 EN JUEGO</span>" : "") . "</div>
      <div class='p-grid'>
        <div class='p-team'><span class='p-club'>" . icn($clubDeM1) . "</span>" . $lado('ci1_a', 'ci1_b', $gan === 1) . "</div>
        <div class='p-score'>" . ($score ? implode(' ', $score) : "<span class='p-pend'>-</span>") . "</div>
        <div class='p-team right'><span class='p-club'>" . icn($clubDeM2) . "</span>" . $lado('ci2_a', 'ci2_b', $gan === 2) . "</div>
      </div></div>";
}

$ctx = ['mapaTodos' => $mapaTodos, 'nombres' => $nombres, 'duplas' => $duplas];
$qsBase = htmlspecialchars(($rowEvento['nombre_evento2'] ? '' : '') . ($_GET['url'] ?? ''), ENT_QUOTES);
$slotsCruce = function ($x, $y) use ($duplas) {
    return max(1, min(count($duplas[$x] ?? []), count($duplas[$y] ?? [])));
};

// Llaves de la categoría
$llaves = [];
if ($idCat) {
    $st = $mysqli2->prepare("SELECT fase, clubA, clubB FROM _ic_llaves WHERE id_evento=? AND id_categoria=?");
    $st->bind_param('ii', $idEventos, $idCat);
    $st->execute();
    $res = $st->get_result();
    while ($r = $res->fetch_assoc()) $llaves[$r['fase']] = $r;
}
$msDeFase = fn($fase) => array_values(array_filter($partidos, fn($m) => $m['fase'] === $fase));
$campeon = '';
if (isset($llaves['final'])) {
    $fa = (int)$llaves['final']['clubA']; $fb = (int)$llaves['final']['clubB'];
    [, , $defF, $ganF] = ic_estado_serie($msDeFase('final'), $fa, $fb, $slotsCruce($fa, $fb));
    if ($defF) $campeon = $mapaTodos[$ganF] ?? '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($rowEvento['evento']); ?> · Interclubes - BT.com.py</title>
<style>
  body { font-family: 'Segoe UI', system-ui, sans-serif; background: hsl(210,20%,97%); color: hsl(220,20%,15%); margin: 0; }
  .wrap { max-width: 760px; margin: 0 auto; padding: 14px 12px 60px; }
  h2 { font-size: 22px; margin: 14px 0 4px; text-align: center; line-height: 1.3; }
  .sub-ev { text-align: center; font-size: 12px; color: hsl(215,14%,50%); margin-bottom: 14px; }
  .top-btns { display: flex; justify-content: center; gap: 10px; margin-bottom: 16px; flex-wrap: wrap; }
  .top-btns a { text-decoration: none; font-size: 13px; font-weight: 600; padding: 8px 20px; border-radius: 8px; }
  .b-sec { background: #fff; color: #374151; border: 1px solid hsl(214,25%,80%); }
  .b-pri { background: #2563eb; color: #fff; }
  .cat-pills { display: grid; grid-template-columns: repeat(3,1fr); gap: 6px; margin-bottom: 16px; }
  @media (min-width: 640px) { .cat-pills { grid-template-columns: repeat(5,1fr); } }
  .cat-pills a { text-align: center; font-size: 11px; font-weight: 700; padding: 8px 4px; border-radius: 8px; text-decoration: none;
    background: #fff; color: #374151; border: 1px solid hsl(214,25%,85%); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .cat-pills a.on { background: #374151; color: #fff; border-color: #374151; }
  .sec-title { font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: .08em; color: hsl(215,14%,45%); margin: 20px 0 8px; }
  .campeon { text-align: center; margin: 14px 0; }
  .campeon span { display: inline-block; background: #facc15; color: #713f12; font-weight: 800; font-size: 14px; padding: 8px 22px; border-radius: 999px; }
  table.pos { width: 100%; border-collapse: collapse; font-size: 12px; background: #fff; border: 1px solid hsl(214,25%,85%); border-radius: 8px; overflow: hidden; margin-bottom: 10px; }
  table.pos th { background: #374151; color: #fff; font-size: 10px; text-transform: uppercase; letter-spacing: .05em; padding: 7px 6px; }
  table.pos td { padding: 7px 6px; text-align: center; border-top: 1px solid hsl(214,25%,90%); }
  table.pos td.club { text-align: left; font-weight: 700; }
  table.pos tr.lider td { background: #ecfdf5; }
  /* Match cards (diseño todos-vs-todos) */
  .match-card { background: #fff; border-radius: 8px; border: 1px solid hsl(214,25%,85%); overflow: hidden; margin-bottom: 12px; box-shadow: 0 1px 2px rgba(0,0,0,.05); }
  .match-card.en-juego { border: 2px solid #EBA652; box-shadow: 0 2px 8px rgba(235,166,82,.3); }
  .match-card.en-juego .match-header { background: #EBA652 !important; animation: pulse 2s infinite; }
  @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: .85; } }
  .match-header { background: #374151; color: #fff; display: flex; align-items: center; justify-content: space-between; gap: 8px;
    padding: 10px 14px; cursor: pointer; border: none; width: 100%; text-align: left; font-family: inherit; }
  .match-header .round { font-size: 10px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: #cbd5e1; white-space: nowrap; }
  .match-header .info { flex: 1; min-width: 0; display: flex; align-items: center; gap: 8px; }
  .match-header .summary { font-size: 12px; font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .badge-finalizado { background: #16a34a; color: #fff; font-size: 9px; font-weight: 800; padding: 2px 8px; border-radius: 999px; letter-spacing: .05em; flex-shrink: 0; }
  .match-header .chevron { font-size: 10px; transition: transform .25s; }
  .match-card.abierta .chevron { transform: rotate(180deg); }
  .match-body { display: none; }
  .match-card.abierta .match-body { display: block; }
  .p-row { padding: 10px 14px; border-top: 1px solid hsl(214,25%,92%); }
  .p-row.p-ej { background: #fff7ec; }
  .p-label { font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: hsl(215,14%,50%); margin-bottom: 6px; }
  .ej-pill { background: #EBA652; color: #fff; padding: 1px 8px; border-radius: 999px; font-size: 9px; }
  .p-grid { display: flex; align-items: center; gap: 10px; }
  .p-team { flex: 1; min-width: 0; font-size: 12px; font-weight: 600; line-height: 1.45; }
  .p-team.right { text-align: right; }
  .p-club { display: block; font-size: 9px; font-weight: 800; letter-spacing: .05em; color: #2563eb; margin-bottom: 2px; }
  .p-win { color: #16a34a; font-weight: 800; }
  .p-score { flex-shrink: 0; text-align: center; font-weight: 800; font-size: 13px; }
  .p-score .set { display: inline-block; background: hsl(210,15%,93%); border-radius: 6px; padding: 3px 7px; margin: 1px; }
  .p-pend { color: hsl(215,14%,60%); font-size: 11px; font-weight: 600; font-style: italic; }
</style>
</head>
<body>
<div class="wrap">
  <h2><?php echo icn($rowEvento['evento']); ?></h2>
  <div class="sub-ev">Torneo Interclubes · Desarrollo</div>
  <div class="top-btns">
    <a class="b-sec" href="/grafico-interclubes.php?<?php echo htmlspecialchars($_SERVER['QUERY_STRING']); ?>">Información</a>
    <a class="b-pri" href="#">Llaves</a>
  </div>

  <?php if (!$catsIC): ?>
    <div style="text-align:center;color:hsl(215,14%,50%);padding:40px 0;">El sorteo todavía no fue cargado.</div>
  <?php else: ?>

  <div class="cat-pills">
    <?php foreach ($catsIC as $cid => $cnom):
        $qs = preg_replace('/&categoria=\d+/', '', $_SERVER['QUERY_STRING']); ?>
      <a class="<?php echo $cid === $idCat ? 'on' : ''; ?>" href="?<?php echo htmlspecialchars($qs); ?>&categoria=<?php echo $cid; ?>"><?php echo htmlspecialchars($cnom); ?></a>
    <?php endforeach; ?>
  </div>

  <?php if ($campeon): ?>
    <div class="campeon"><span>🏆 CAMPEÓN: <?php echo icn($campeon); ?></span></div>
  <?php endif; ?>

  <?php
  // ── Llaves primero (si existen) ──
  $labelsF = ['semi1' => 'Semifinal 1', 'semi2' => 'Semifinal 2', 'final' => '🏆 Final', 'tercer' => '3er Puesto'];
  if ($llaves): ?>
    <div class="sec-title">Llaves — Semis, Final y 3er Puesto</div>
    <?php foreach ($labelsF as $fase => $lbl):
        if (!isset($llaves[$fase])) continue;
        $ca = (int)$llaves[$fase]['clubA']; $cb = (int)$llaves[$fase]['clubB'];
        echo ic_card_serie($lbl, $ca, $cb, $msDeFase($fase), $slotsCruce($ca, $cb), $ctx);
    endforeach;
  endif;

  // ── Grupos ──
  foreach ($grupos as $g => $clubesG):
      $mapaG = [];
      foreach ($clubesG as $c) $mapaG[(int)$c['id_club']] = $c['nombre'];
      $partG = array_values(array_filter($partidos, fn($m) => (int)$m['grupo'] === $g && $m['fase'] === 'grupo'));
      $ids = array_column($clubesG, 'id_club');
      $slotsPorCruce = [];
      for ($i = 0; $i < count($ids); $i++) for ($j = $i + 1; $j < count($ids); $j++)
          $slotsPorCruce[min($ids[$i], $ids[$j]) . '-' . max($ids[$i], $ids[$j])] = $slotsCruce((int)$ids[$i], (int)$ids[$j]);
      $posiciones = ic_posiciones($mapaG, $partG, $slotsPorCruce);
  ?>
    <div class="sec-title">Grupo <?php echo $g; ?> — Posiciones</div>
    <table class="pos">
      <tr><th style="text-align:left;">Club</th><th>Series</th><th>Partidos</th><th>Sets</th><th>Pts</th></tr>
      <?php foreach ($posiciones as $ip => $p): ?>
      <tr class="<?php echo $ip === 0 && $p['pts'] > 0 ? 'lider' : ''; ?>">
        <td class="club"><?php echo ($ip + 1) . '. ' . icn($p['club']); ?></td>
        <td><?php echo $p['sg']; ?>-<?php echo $p['sp']; ?></td>
        <td><?php echo $p['pg']; ?>-<?php echo $p['pp']; ?></td>
        <td><?php echo $p['setsF']; ?>-<?php echo $p['setsC']; ?></td>
        <td><b><?php echo $p['pts']; ?></b></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php
    $nS = 0;
    for ($i = 0; $i < count($clubesG); $i++) for ($j = $i + 1; $j < count($clubesG); $j++):
        $a = (int)$clubesG[$i]['id_club']; $b = (int)$clubesG[$j]['id_club'];
        $k = min($a, $b) . '-' . max($a, $b);
        $ms = array_values(array_filter($partG, fn($m) =>
            (min((int)$m['club1'], (int)$m['club2']) . '-' . max((int)$m['club1'], (int)$m['club2'])) === $k));
        $nS++;
        echo ic_card_serie("G{$g} · Serie {$nS}", $a, $b, $ms, $slotsPorCruce[$k], $ctx);
    endfor;
  endforeach; ?>

  <?php endif; ?>
</div>
<script>
function toggleMatch(btn) { btn.closest('.match-card').classList.toggle('abierta'); }
function ocultarDiv() { const d = document.getElementById('cargando'); if (d) d.style.display = 'none'; }
ocultarDiv();
</script>
</body>
</html>
