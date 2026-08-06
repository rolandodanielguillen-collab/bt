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
       JOIN _relacion_evento_categoria rec
         ON rec.id_evento = s.id_evento AND rec.id_categoria = s.id_categoria
        AND rec.estado = 'activo' AND rec.visualizar_en_llaves = 'si'
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

// Badge de pareja: PAREJA N si los CIs coinciden con una dupla inscripta; si no, MIXTA
function ic_badge_pareja(array $duplasClub, $ciA, $ciB) {
    foreach ($duplasClub as $k => $d) {
        $par = [trim((string)$d['ci']), trim((string)$d['ci_dupla'])];
        if (in_array(trim((string)$ciA), $par, true) && in_array(trim((string)$ciB), $par, true) && (string)$ciA !== (string)$ciB)
            return "<span class='pj-badge'>PAREJA " . ($k + 1) . "</span>";
    }
    return "<span class='pj-badge mixta'>MIXTA</span>";
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

    $h = "<div class='match-card" . ($definida ? '' : ' abierta') . " {$clase}'>
      <button class='match-header' onclick='toggleMatch(this)'>
        <span class='round'>{$label}</span>
        <div class='info'><span class='summary'>" . icn($nomA) . " vs " . icn($nomB) . " — {$summary}</span>{$badge}</div>
        <span class='chevron'>▼</span>
      </button>
      <div class='match-body'>";

    // Partidos regulares por slot + desempate (los jugadores se definen partido a partido)
    $regs = array_values(array_filter($ms, fn($m) => !(int)$m['es_desempate']));
    $des  = array_values(array_filter($ms, fn($m) => (int)$m['es_desempate']));

    for ($i = 0; $i < $slots; $i++) {
        $m = $regs[$i] ?? null;
        $bL = $m ? ic_badge_pareja($duplas[(int)$m['club1']] ?? [], $m['ci1_a'], $m['ci1_b']) : '';
        $bR = $m ? ic_badge_pareja($duplas[(int)$m['club2']] ?? [], $m['ci2_a'], $m['ci2_b']) : '';
        $h .= ic_fila_partido("Partido " . ($i + 1), $m, $a, $b, $nomA, $nomB, $nombres, $bL, $bR);
    }
    foreach ($des as $m) {
        $h .= ic_fila_partido("★ Desempate", $m, $a, $b, $nomA, $nomB, $nombres,
            ic_badge_pareja($duplas[(int)$m['club1']] ?? [], $m['ci1_a'], $m['ci1_b']),
            ic_badge_pareja($duplas[(int)$m['club2']] ?? [], $m['ci2_a'], $m['ci2_b']));
    }
    if ($necesitaDes && !$des) {
        $h .= "<div class='p-row'><div class='p-label'>★ Desempate — dupla mezclada</div><div class='p-pend'>Por definir</div></div>";
    }
    return $h . "</div></div>";
}

// Card placeholder del bracket (cruce aún no definido): el público ve quién jugará vs quién
function ic_card_placeholder($label, $texto) {
    return "<div class='match-card'><div class='match-header' style='background:#6b7280;cursor:default;'><span class='round'>{$label}</span>
      <div class='info'><span class='summary' style='font-style:italic;'>{$texto}</span></div></div></div>";
}

// Un partido dentro de la serie: mini-card COLAPSADA con el estado a la vista
// (FINALIZADO verde / 🔴 EN JUEGO naranja / por jugar gris). Clic = detalle.
function ic_fila_partido($label, $m, $a, $b, $nomA, $nomB, $nombres, $badgeL = '', $badgeR = '') {
    $card = function ($clase, $resumen, $detalle) use ($label) {
        return "<div class='pm-card{$clase}'>
          <button class='pm-head' onclick='togglePartido(this)'>
            <span class='pm-title'>{$label}</span>
            <span class='pm-sum'>{$resumen}</span>
            <span class='pm-chev'>▼</span>
          </button>
          <div class='pm-body'>{$detalle}</div></div>";
    };
    if (!$m) {
        return $card(' pm-pend', "<span class='p-pend'>por jugar</span>",
            "<div class='p-grid'>
            <div class='p-team'><span class='p-club'>" . icn($nomA) . "</span><span class='p-pend'>Jugadores a designar</span></div>
            <div class='p-score p-pend'>por<br>jugar</div>
            <div class='p-team right'><span class='p-club'>" . icn($nomB) . "</span><span class='p-pend'>Jugadores a designar</span></div>
          </div>");
    }
    $gan = ic_ganador_partido($m);
    $ej  = ($m['en_juego'] ?? 'no') === 'si';
    // Cada set jugado = fila "S1 6-4" (games del club izquierdo primero);
    // los sets sin cargar no se muestran
    $score = [];
    $pares = [];
    $nSet = 0;
    foreach ([['s1c1','s1c2'], ['s2c1','s2c2'], ['s3c1','s3c2']] as [$ka, $kb]) {
        $nSet++;
        if ((int)$m[$ka] === 0 && (int)$m[$kb] === 0) continue;
        $score[] = "<span class='set'><span class='set-n'>S{$nSet}</span>" . (int)$m[$ka] . "-" . (int)$m[$kb] . "</span>";
        $pares[] = [(int)$m[$ka], (int)$m[$kb]];
    }
    // Resumen del header: con ganador, los games se muestran desde SU perspectiva
    // (ej. ARENA BAR 6-4 aunque esté del lado derecho / sea club2)
    $miniFmt = fn(bool $flip) => implode(' · ', array_map(fn($p) => $flip ? "{$p[1]}-{$p[0]}" : "{$p[0]}-{$p[1]}", $pares));
    $lado = fn($ka, $kb, $win) =>
        "<span class='" . ($win ? 'p-win' : '') . "'>" . ($win ? '✓ ' : '') . icn($nombres[$m[$ka]] ?? $m[$ka]) . "<br>" .
        ($win ? '✓ ' : '') . icn($nombres[$m[$kb]] ?? $m[$kb]) . "</span>";
    $clubDeM1 = ((int)$m['club1'] === $a) ? $nomA : $nomB;
    $clubDeM2 = ((int)$m['club2'] === $a) ? $nomA : $nomB;
    $detalle = "<div class='p-grid'>
        <div class='p-team'><span class='p-club'>" . icn($clubDeM1) . " {$badgeL}</span>" . $lado('ci1_a', 'ci1_b', $gan === 1) . "</div>
        <div class='p-score'>" . ($score ? implode(' ', $score) : "<span class='p-pend'>-</span>") . "</div>
        <div class='p-team right'><span class='p-club'>" . icn($clubDeM2) . " {$badgeR}</span>" . $lado('ci2_a', 'ci2_b', $gan === 2) . "</div>
      </div>";
    $miniTxt = $pares ? " <b class='pm-mini'>" . $miniFmt(false) . "</b>" : '';
    if ($ej) {
        // Partido en juego: card abierta para que el parcial se vea sin clic
        return $card(' pm-ej abierta', "<span class='ej-pill'>🔴 EN JUEGO</span>{$miniTxt}", $detalle);
    }
    if ($gan) {
        $ganadorNom = icn($gan === 1 ? $clubDeM1 : $clubDeM2);
        $miniWin = $pares ? " <b class='pm-mini'>" . $miniFmt($gan === 2) . "</b>" : '';
        return $card(' pm-fin', "<span class='badge-finalizado'>FINALIZADO</span> <b class='pm-mini'>{$ganadorNom}</b>{$miniWin}", $detalle);
    }
    // Jugadores ya definidos (fila 0-0 o parcial empatado): card abierta para verlos
    return $card(' pm-pend abierta', "<span class='p-pend'>" . ($pares ? "parcial{$miniTxt}" : 'por jugar') . "</span>", $detalle);
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
$campeon = $vice = $tercero = '';
if (isset($llaves['final'])) {
    $fa = (int)$llaves['final']['clubA']; $fb = (int)$llaves['final']['clubB'];
    [, , $defF, $ganF] = ic_estado_serie($msDeFase('final'), $fa, $fb, $slotsCruce($fa, $fb));
    if ($defF) {
        $campeon = $mapaTodos[$ganF] ?? '';
        $vice    = $mapaTodos[$ganF === $fa ? $fb : $fa] ?? '';
    }
}
if (isset($llaves['tercer'])) {
    $ta = (int)$llaves['tercer']['clubA']; $tb = (int)$llaves['tercer']['clubB'];
    [, , $defT, $ganT] = ic_estado_serie($msDeFase('tercer'), $ta, $tb, $slotsCruce($ta, $tb));
    if ($defT) $tercero = $mapaTodos[$ganT] ?? '';
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
  .wrap { max-width: 900px; margin: 0 auto; padding: 14px 12px 60px; }
  h2 { font-size: 22px; margin: 14px 0 4px; text-align: center; line-height: 1.3; }
  .sub-ev { text-align: center; font-size: 12px; color: hsl(215,14%,50%); margin-bottom: 14px; }
  .top-btns { display: flex; justify-content: center; gap: 10px; margin-bottom: 16px; flex-wrap: wrap; }
  .top-btns a { text-decoration: none; font-size: 13px; font-weight: 600; padding: 8px 20px; border-radius: 8px; }
  .b-sec { background: #fff; color: #374151; border: 1px solid hsl(214,25%,80%); }
  .b-pri { background: #2563eb; color: #fff; }
  .cat-pills { display: grid; grid-template-columns: repeat(3,1fr); gap: 6px; margin-bottom: 14px; }
  @media (min-width: 640px) { .cat-pills { grid-template-columns: repeat(5,1fr); } }
  .cat-pills a { text-align: center; font-size: 11px; font-weight: 700; padding: 8px 4px; border-radius: 8px; text-decoration: none;
    background: #fff; color: #374151; border: 1px solid hsl(214,25%,85%); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .cat-pills a.on { background: #374151; color: #fff; border-color: #374151; }
  /* Tabs (igual TVT) */
  .tabs { display: flex; gap: 4px; background: hsl(210,15%,93%); border-radius: 8px; padding: 4px; margin-bottom: 16px; max-width: 400px; }
  .tab-btn { flex: 1; padding: 8px; border: none; border-radius: 6px; font-size: 14px; font-weight: 500; cursor: pointer;
    background: transparent; color: hsl(215,14%,50%); transition: all .2s; font-family: inherit; }
  .tab-btn.active { background: #fff; color: hsl(220,20%,15%); box-shadow: 0 1px 3px rgba(0,0,0,.1); }
  .tab-content { display: none; }
  .tab-content.active { display: block; }
  /* Grilla de grupos lado a lado (igual TVT) */
  .groups-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 20px; width: 100%; }
  .group-head { display: flex; align-items: center; justify-content: space-between; margin: 10px 0; }
  .group-head .gname { font-weight: 700; font-size: 16px; }
  .btn-clasif { display: inline-flex; align-items: center; gap: 4px; background: none; border: 1px solid hsl(214,25%,75%);
    border-radius: 6px; padding: 4px 10px; font-size: 12px; color: hsl(215,14%,50%); cursor: pointer; font-family: inherit; }
  /* Modal Clasificación (igual TVT) */
  .modal-clasif-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,.5);
    z-index: 9999; align-items: center; justify-content: center; padding: 16px; }
  .modal-clasif-overlay.show { display: flex; }
  .modal-clasif-box { background: #fff; border-radius: 10px; width: 100%; max-width: 440px; overflow: hidden; box-shadow: 0 8px 30px rgba(0,0,0,.2); }
  .modal-clasif-header { background: #374151; color: #fff; padding: 12px 16px; display: flex; align-items: center; justify-content: space-between; font-weight: 700; font-size: 14px; }
  .modal-clasif-header button { background: none; border: none; color: #fff; font-size: 18px; cursor: pointer; }
  table.pos { width: 100%; border-collapse: collapse; font-size: 12px; }
  table.pos th { background: hsl(210,15%,95%); color: hsl(215,14%,40%); font-size: 10px; text-transform: uppercase; letter-spacing: .05em; padding: 8px 6px; }
  table.pos td { padding: 8px 6px; text-align: center; border-top: 1px solid hsl(214,25%,90%); }
  table.pos td.club { text-align: left; font-weight: 700; }
  table.pos tr.lider td { background: #ecfdf5; }
  table.pos td.saldo-pos { color: #059669; font-weight: 700; }
  table.pos td.saldo-neg { color: #dc2626; font-weight: 700; }
  .pos-nota { font-size: 10px; color: hsl(215,14%,50%); padding: 8px 12px; border-top: 1px solid hsl(214,25%,90%); }
  /* Podio escalonado: campeón, vice al medio y tercero al otro extremo,
     bloques oro/plata/bronce de altura decreciente apoyados en la base de la card */
  .podio { display: flex; align-items: flex-end; justify-content: center; gap: 10px; max-width: 480px;
    margin: 18px auto; background: #fff; border: 1px solid hsl(214,25%,86%); border-radius: 10px;
    padding: 18px 18px 0; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
  .podio-col { flex: 1 1 0; min-width: 0; display: flex; flex-direction: column; align-items: center; text-align: center; }
  .podio-label { font-size: 9px; font-weight: 800; text-transform: uppercase; letter-spacing: .1em; color: hsl(215,14%,50%); }
  .podio-club { font-weight: 800; color: hsl(220,20%,15%); margin: 2px 0 8px; overflow: hidden;
    text-overflow: ellipsis; white-space: nowrap; max-width: 100%; }
  .podio-1 .podio-club { font-size: 16px; }
  .podio-2 .podio-club { font-size: 13px; }
  .podio-3 .podio-club { font-size: 11px; }
  .podio-bloque { width: 100%; border-radius: 8px 8px 0 0; display: flex; align-items: flex-start;
    justify-content: center; padding-top: 7px; font-weight: 800; }
  .podio-1 .podio-bloque { height: 84px; background: linear-gradient(180deg,#f3d878,#c9a227); color: #4d3c07; font-size: 20px; }
  .podio-2 .podio-bloque { height: 54px; background: linear-gradient(180deg,#e8eaee,#b3bac4); color: #40474f; font-size: 16px; }
  .podio-3 .podio-bloque { height: 32px; background: linear-gradient(180deg,#d8a25e,#a9702f); color: #40270c; font-size: 13px; }
  /* Bracket eliminatorio (igual TVT) */
  .bracket-wrapper { overflow-x: auto; -webkit-overflow-scrolling: touch; padding: 8px 0 16px; }
  .bracket-container { display: flex; align-items: stretch; min-width: max-content; gap: 0; }
  .bracket-ronda { flex: 0 0 165px; display: flex; flex-direction: column; }
  .bracket-ronda-header { text-align: center; font-size: 11px; font-weight: 600; color: hsl(215,14%,50%); text-transform: uppercase; letter-spacing: .05em; padding: 6px 0 10px; white-space: nowrap; }
  .bracket-ronda-body { display: flex; flex-direction: column; justify-content: space-around; flex: 1; position: relative; }
  .bracket-card { background: #fff; border-radius: 6px; border: 1px solid hsl(214,25%,85%); overflow: hidden; margin: 4px 3px; position: relative; z-index: 1; }
  .bracket-card-enjuego { border: 2px solid #EBA652; }
  .bracket-hd { background: #374151; color: #fff; padding: 4px 8px; font-size: 11px; display: flex; align-items: center; gap: 6px; }
  .bracket-hd-enjuego { background: #EBA652; }
  .bracket-hd-pend { background: #6b7280; }
  .bracket-hd .bk-pf { font-weight: 600; }
  .bracket-hd .bk-badge { font-size: 8px; letter-spacing: .05em; color: #86efac; }
  .bracket-hd .bk-badge-ej { font-size: 8px; letter-spacing: .05em; color: #fff; }
  .bracket-row { display: flex; align-items: center; justify-content: space-between; padding: 6px 8px; border-bottom: 1px solid hsl(214,25%,90%); font-size: 11px; line-height: 1.25; }
  .bracket-row:last-of-type { border-bottom: none; }
  .bracket-row-winner { background: #e8f4f8; }
  .bracket-row .bk-na { flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .bracket-row-winner .bk-na { font-weight: 700; }
  .bracket-row .bk-sc { font-size: 13px; font-weight: 700; min-width: 16px; text-align: right; flex-shrink: 0; }
  .bracket-pend-nm { font-style: italic; color: hsl(215,14%,60%); }
  .bracket-conn { flex: 0 0 20px; position: relative; }
  .bracket-conn svg { position: absolute; top: 0; left: 0; width: 100%; height: 100%; }
  .bracket-conn svg line { stroke: hsl(214,25%,75%); stroke-width: 1.5; }
  @media (min-width: 768px) { .bracket-ronda { flex: 0 0 210px; } .bracket-conn { flex: 0 0 28px; } }
  /* Bracket con cards completas: semis a la izquierda, final a la derecha */
  .bracket-flex { display: flex; align-items: stretch; min-width: 560px; }
  .bracket-col { flex: 1 1 0; min-width: 0; display: flex; flex-direction: column; }
  .bracket-col-body { flex: 1; display: flex; flex-direction: column; justify-content: space-around; position: relative; gap: 28px; }
  .bracket-col-body .match-card { margin-bottom: 0; }
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
  .pj-badge { display: inline-block; background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe;
    border-radius: 999px; padding: 0 6px; font-size: 8px; font-weight: 800; letter-spacing: .04em; }
  .pj-badge.mixta { background: #f5f3ff; color: #7c3aed; border-color: #ddd6fe; }
  .p-win { color: #16a34a; font-weight: 800; }
  .p-score { flex-shrink: 0; text-align: center; font-weight: 800; font-size: 13px;
    display: flex; flex-direction: column; align-items: center; gap: 2px; }
  .p-score .set { display: flex; align-items: center; gap: 4px; background: hsl(210,15%,93%);
    border-radius: 6px; padding: 2px 8px; }
  .p-score .set .set-n { font-size: 8px; font-weight: 800; color: hsl(215,14%,50%); }
  .p-pend { color: hsl(215,14%,60%); font-size: 11px; font-weight: 600; font-style: italic; }
  .tercer-box { max-width: 520px; }
  /* Mini-card por partido dentro de la serie (colapsada; el header muestra el estado) */
  .pm-card { border: 1px solid hsl(214,25%,88%); border-radius: 8px; margin: 8px 10px; overflow: hidden; background: #fff; }
  .pm-head { width: 100%; display: flex; align-items: center; gap: 8px; padding: 8px 12px; border: none;
    background: hsl(210,20%,96%); cursor: pointer; font-family: inherit; text-align: left; }
  .pm-title { font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em;
    color: hsl(215,14%,45%); white-space: nowrap; }
  .pm-sum { flex: 1; min-width: 0; font-size: 11px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .pm-mini { font-weight: 800; color: hsl(220,20%,25%); }
  .pm-chev { font-size: 9px; color: hsl(215,14%,55%); transition: transform .25s; }
  .pm-card.abierta .pm-chev { transform: rotate(180deg); }
  .pm-body { display: none; padding: 10px 12px; }
  .pm-card.abierta .pm-body { display: block; }
  .pm-card.pm-ej { border-color: #EBA652; }
  .pm-card.pm-ej .pm-head { background: rgba(235,166,82,.18); }
  .pm-card.pm-fin .pm-head { background: rgba(22,163,74,.10); }
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

  <!-- Tabs: Resultados | SF → (igual TVT) -->
  <div class="tabs">
    <button class="tab-btn active" id="tabbtn-resultados" onclick="switchTab('resultados')">Resultados</button>
    <button class="tab-btn" id="tabbtn-clasificacion" onclick="switchTab('clasificacion')">SF &rarr;</button>
  </div>

  <!-- Tab Resultados: grupos lado a lado -->
  <div id="tab-resultados" class="tab-content active">
    <div class="groups-grid">
      <?php foreach ($grupos as $g => $clubesG):
          $mapaG = [];
          foreach ($clubesG as $c) $mapaG[(int)$c['id_club']] = $c['nombre'];
          $partG = array_values(array_filter($partidos, fn($m) => (int)$m['grupo'] === $g && $m['fase'] === 'grupo'));
          $ids = array_column($clubesG, 'id_club');
          $slotsPorCruce = [];
          for ($i = 0; $i < count($ids); $i++) for ($j = $i + 1; $j < count($ids); $j++)
              $slotsPorCruce[min($ids[$i], $ids[$j]) . '-' . max($ids[$i], $ids[$j])] = $slotsCruce((int)$ids[$i], (int)$ids[$j]);
          $posiciones = ic_posiciones($mapaG, $partG, $slotsPorCruce);
      ?>
      <div style="width:100%;">
        <div class="group-head">
          <span class="gname">GRUPO <?php echo $g; ?></span>
          <button class="btn-clasif" onclick="openModalClasif('modal-clasif-<?php echo $g; ?>')">&#9776; Clasificación</button>
        </div>
        <?php
        $nS = 0;
        for ($i = 0; $i < count($clubesG); $i++) for ($j = $i + 1; $j < count($clubesG); $j++):
            $a = (int)$clubesG[$i]['id_club']; $b = (int)$clubesG[$j]['id_club'];
            $k = min($a, $b) . '-' . max($a, $b);
            $ms = array_values(array_filter($partG, fn($m) =>
                (min((int)$m['club1'], (int)$m['club2']) . '-' . max((int)$m['club1'], (int)$m['club2'])) === $k));
            $nS++;
            echo ic_card_serie("Serie {$nS}", $a, $b, $ms, $slotsPorCruce[$k], $ctx);
        endfor;
        ?>
      </div>

      <!-- Modal clasificación -->
      <div class="modal-clasif-overlay" id="modal-clasif-<?php echo $g; ?>" onclick="if(event.target===this)closeModalClasif('modal-clasif-<?php echo $g; ?>')">
        <div class="modal-clasif-box">
          <div class="modal-clasif-header">
            <span>Clasificación — Grupo <?php echo $g; ?></span>
            <button onclick="closeModalClasif('modal-clasif-<?php echo $g; ?>')">&times;</button>
          </div>
          <table class="pos">
            <tr><th style="text-align:left;">Club</th><th>Series</th><th>Games</th><th>Pts</th></tr>
            <?php foreach ($posiciones as $ip => $p):
                $saldoG = $p['gamesF'] - $p['gamesC'];
                $clsSaldo = $saldoG > 0 ? 'saldo-pos' : ($saldoG < 0 ? 'saldo-neg' : ''); ?>
            <tr class="<?php echo $ip === 0 && $p['pts'] > 0 ? 'lider' : ''; ?>">
              <td class="club"><?php echo ($ip + 1) . '. ' . icn($p['club']); ?></td>
              <td><?php echo $p['sg']; ?>-<?php echo $p['sp']; ?></td>
              <td class="<?php echo $clsSaldo; ?>"><?php echo ($saldoG > 0 ? '+' : '') . $saldoG; ?></td>
              <td><b><?php echo $p['pts']; ?></b></td>
            </tr>
            <?php endforeach; ?>
          </table>
          <div class="pos-nota">Desempate: saldo de games (el 3er partido de la serie vale &plusmn;1)</div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Tab SF: semis y final (cards completas) con conectores + 3er puesto abajo.
       Visible SIEMPRE: antes de definirse las llaves muestra los cruces como placeholder. -->
  <div id="tab-clasificacion" class="tab-content">
    <?php if ($campeon): ?>
      <div class="podio">
        <div class="podio-col podio-1">
          <span class="podio-label">Campeón</span>
          <span class="podio-club"><?php echo icn($campeon); ?></span>
          <div class="podio-bloque">1</div>
        </div>
        <?php if ($vice): ?>
        <div class="podio-col podio-2">
          <span class="podio-label">Vice campeón</span>
          <span class="podio-club"><?php echo icn($vice); ?></span>
          <div class="podio-bloque">2</div>
        </div>
        <?php endif; ?>
        <?php if ($tercero): ?>
        <div class="podio-col podio-3">
          <span class="podio-label">Tercer puesto</span>
          <span class="podio-club"><?php echo icn($tercero); ?></span>
          <div class="podio-bloque">3</div>
        </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="bracket-wrapper">
      <div class="bracket-flex">
        <div class="bracket-col">
          <div class="bracket-ronda-header">Semifinales</div>
          <div class="bracket-col-body" id="bracket-body-sf">
            <?php
            foreach (['semi1' => ['Semifinal 1', '1° Grupo 1 vs 2° Grupo 2'],
                      'semi2' => ['Semifinal 2', '1° Grupo 2 vs 2° Grupo 1']] as $fase => [$lbl, $ph]):
                if (isset($llaves[$fase])):
                    $ca = (int)$llaves[$fase]['clubA']; $cb = (int)$llaves[$fase]['clubB'];
                    echo ic_card_serie($lbl, $ca, $cb, $msDeFase($fase), $slotsCruce($ca, $cb), $ctx);
                else:
                    echo ic_card_placeholder($lbl, $ph);
                endif;
            endforeach;
            ?>
          </div>
        </div>
        <div class="bracket-conn" id="bracket-conn-sf-fn"><svg></svg></div>
        <div class="bracket-col">
          <div class="bracket-ronda-header">Final</div>
          <div class="bracket-col-body" id="bracket-body-fn">
            <?php
            if (isset($llaves['final'])):
                $ca = (int)$llaves['final']['clubA']; $cb = (int)$llaves['final']['clubB'];
                echo ic_card_serie('🏆 Final', $ca, $cb, $msDeFase('final'), $slotsCruce($ca, $cb), $ctx);
            else:
                echo ic_card_placeholder('🏆 Final', isset($llaves['semi1']) ? 'A definir — ganadores de las semis' : 'Ganador SF1 vs Ganador SF2');
            endif;
            ?>
          </div>
        </div>
      </div>
    </div>

    <!-- 3er puesto, separado y abajo (ancho de una columna del bracket, no toda la página) -->
    <div class="bracket-ronda-header" style="text-align:left;margin-top:20px;">3er Puesto</div>
    <div class="tercer-box">
    <?php if (isset($llaves['tercer'])):
        $ca = (int)$llaves['tercer']['clubA']; $cb = (int)$llaves['tercer']['clubB'];
        echo ic_card_serie('3er Puesto', $ca, $cb, $msDeFase('tercer'), $slotsCruce($ca, $cb), $ctx);
    else:
        echo ic_card_placeholder('3er Puesto', 'Perdedores de las semifinales');
    endif; ?>
    </div>
  </div>

  <?php endif; ?>
</div>
<script>
function togglePartido(btn) {
  btn.closest('.pm-card').classList.toggle('abierta');
  if (typeof drawBracketLines === 'function') setTimeout(drawBracketLines, 60);
}
function toggleMatch(btn) {
  btn.closest('.match-card').classList.toggle('abierta');
  setTimeout(drawBracketLines, 50); // las alturas cambian al desplegar
}
function switchTab(t) {
  document.querySelectorAll('.tab-content').forEach(e => e.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(e => e.classList.remove('active'));
  const c = document.getElementById('tab-' + t);
  const b = document.getElementById('tabbtn-' + t);
  if (c) c.classList.add('active');
  if (b) b.classList.add('active');
  if (t === 'clasificacion') setTimeout(drawBracketLines, 100);
}
// Conectores del bracket (igual TVT): líneas SVG entre columnas
function drawBracketLines() {
  document.querySelectorAll('.bracket-conn').forEach(function (conn) {
    const svg = conn.querySelector('svg');
    if (!svg) return;
    svg.innerHTML = '';
    const ids = conn.id.replace('bracket-conn-', '').split('-');
    const fb = document.getElementById('bracket-body-' + ids[0]);
    const tb = document.getElementById('bracket-body-' + ids[1]);
    if (!fb || !tb) return;
    const fc = fb.querySelectorAll('.match-card');
    const tc = tb.querySelectorAll('.match-card');
    const cr = conn.getBoundingClientRect();
    const ml = (x1, y1, x2, y2) => {
      const l = document.createElementNS('http://www.w3.org/2000/svg', 'line');
      l.setAttribute('x1', x1); l.setAttribute('y1', y1);
      l.setAttribute('x2', x2); l.setAttribute('y2', y2);
      svg.appendChild(l);
    };
    if (fc.length >= 2 && tc.length >= 1) {
      const r1 = fc[0].getBoundingClientRect(), r2 = fc[1].getBoundingClientRect(), rt = tc[0].getBoundingClientRect();
      const y1 = Math.round(r1.top + r1.height / 2 - cr.top);
      const y2 = Math.round(r2.top + r2.height / 2 - cr.top);
      const yt = Math.round(rt.top + rt.height / 2 - cr.top);
      const w = cr.width, mx = Math.round(w / 2);
      ml(0, y1, mx, y1); ml(0, y2, mx, y2); ml(mx, y1, mx, y2); ml(mx, yt, w, yt);
    } else if (fc.length === 1 && tc.length === 1) {
      const rs = fc[0].getBoundingClientRect(), rt = tc[0].getBoundingClientRect();
      ml(0, Math.round(rs.top + rs.height / 2 - cr.top), cr.width, Math.round(rt.top + rt.height / 2 - cr.top));
    }
  });
}
window.addEventListener('resize', drawBracketLines);
function openModalClasif(id) { document.getElementById(id).classList.add('show'); }
function closeModalClasif(id) { document.getElementById(id).classList.remove('show'); }
function ocultarDiv() { const d = document.getElementById('cargando'); if (d) d.style.display = 'none'; }
ocultarDiv();
</script>
</body>
</html>
