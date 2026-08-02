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
        // Avance automático: crea semis/final apenas los resultados lo permiten
        ic_autogenerar_llaves($mysqli2, $idEvento, $idCat);

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

        // Duplas y jugadores de TODOS los clubes sorteados (grupos y llaves los comparten)
        $mapaTodos = [];
        foreach ($grupos as $g => $clubesG) foreach ($clubesG as $c) $mapaTodos[(int)$c['id_club']] = $c['nombre'];
        $duplas = [];
        foreach ($mapaTodos as $idCl => $nom) $duplas[$idCl] = ic_duplas($mysqli2, $idEvento, $idCat, $idCl);
        $jugadoresAll = [];
        foreach ($duplas as $idCl => $ds) foreach ($ds as $d) {
            $jugadoresAll[$idCl][] = ['ci' => $d['ci'], 'nombre' => $d['j1'] ?: $d['ci']];
            $jugadoresAll[$idCl][] = ['ci' => $d['ci_dupla'], 'nombre' => $d['j2'] ?: $d['ci_dupla']];
        }

        // Helper: arma la serie entre 2 clubes desde una lista de partidos
        $armarSerie = function (array $ms, int $a, int $b, int $slots) use ($mapaTodos, $nombres) {
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
                    'en_juego' => $m['en_juego'] ?? 'no',
                ];
            }
            return [
                'clubA' => $a, 'clubB' => $b,
                'nomA' => $mapaTodos[$a] ?? ('Club #' . $a), 'nomB' => $mapaTodos[$b] ?? ('Club #' . $b),
                'slots' => $slots, 'winsA' => $wA, 'winsB' => $wB,
                'definida' => $definida, 'ganador' => $ganador, 'necesita_desempate' => $necesitaDes,
                'partidos' => $msOut,
            ];
        };

        $out = [];
        $gruposCompletos = true;
        foreach ($grupos as $g => $clubesG) {
            $mapa = [];
            foreach ($clubesG as $c) $mapa[(int)$c['id_club']] = $c['nombre'];

            // Partidos del grupo (solo fase grupo)
            $partG = array_values(array_filter($partidos, fn($m) => (int)$m['grupo'] === $g && $m['fase'] === 'grupo'));

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
                $serie = $armarSerie($ms, $a, $b, $slots);
                if (!$serie['definida']) $gruposCompletos = false;
                $series[] = $serie;
            }
            if ($n < 2) $gruposCompletos = false;

            $out[] = [
                'grupo' => $g,
                'clubes' => array_map(fn($c) => ['id' => (int)$c['id_club'], 'nombre' => $c['nombre']], $clubesG),
                'series' => $series,
                'posiciones' => ic_posiciones($mapa, $partG, $slotsPorCruce),
            ];
        }
        if (count($grupos) < 2) $gruposCompletos = false;

        // ── Llaves (semis, final, 3er puesto) ──
        $st = $mysqli2->prepare("SELECT * FROM _ic_llaves WHERE id_evento = ? AND id_categoria = ?");
        $st->bind_param('ii', $idEvento, $idCat);
        $st->execute();
        $resL = $st->get_result();
        $llavesRows = [];
        while ($r = $resL->fetch_assoc()) $llavesRows[$r['fase']] = $r;

        $llaves = [];
        $semisDefinidas = 0;
        foreach (['semi1' => 'Semifinal 1', 'semi2' => 'Semifinal 2', 'final' => 'Final', 'tercer' => '3er Puesto'] as $fase => $label) {
            if (!isset($llavesRows[$fase])) continue;
            $a = (int)$llavesRows[$fase]['clubA']; $b = (int)$llavesRows[$fase]['clubB'];
            $slots = max(1, min(count($duplas[$a] ?? []), count($duplas[$b] ?? [])));
            $ms = array_values(array_filter($partidos, fn($m) => $m['fase'] === $fase));
            $serie = $armarSerie($ms, $a, $b, $slots);
            if (in_array($fase, ['semi1', 'semi2'], true) && $serie['definida']) $semisDefinidas++;
            $llaves[] = ['fase' => $fase, 'label' => $label] + $serie;
        }

        echo json_encode([
            'success' => true,
            'grupos' => $out,
            'duplas' => $duplas,
            'jugadores' => $jugadoresAll,
            'llaves' => $llaves,
            'llaves_generadas' => !empty($llavesRows),
            'puede_generar_llaves' => $gruposCompletos && empty($llavesRows),
            'puede_generar_final' => $semisDefinidas === 2 && !isset($llavesRows['final']),
        ]);
        exit;
    }

    // Generar semifinales desde las posiciones (1°G1 vs 2°G2, 1°G2 vs 2°G1)
    if ($action === 'generar_llaves') {
        $idCat = abs((int)($_GET['categoria'] ?? 0));
        if (!$idCat) { echo json_encode(['success' => false, 'error' => 'Falta categoría']); exit; }
        $st = $mysqli2->prepare("SELECT COUNT(*) c FROM _ic_llaves WHERE id_evento=? AND id_categoria=?");
        $st->bind_param('ii', $idEvento, $idCat);
        $st->execute();
        if ((int)$st->get_result()->fetch_assoc()['c'] > 0) {
            echo json_encode(['success' => false, 'error' => 'Las llaves ya fueron generadas']); exit;
        }
        // Posiciones por grupo
        $pos = [];
        foreach ([1, 2] as $g) {
            $st = $mysqli2->prepare(
                "SELECT s.id_club, cl.nombre FROM _ic_sorteo s JOIN _p_clubes cl ON cl.id = s.id_club
                  WHERE s.id_evento=? AND s.id_categoria=? AND s.grupo=? ORDER BY s.posicion ASC");
            $st->bind_param('iii', $idEvento, $idCat, $g);
            $st->execute();
            $resG = $st->get_result();
            $mapa = [];
            while ($r = $resG->fetch_assoc()) $mapa[(int)$r['id_club']] = $r['nombre'];
            if (count($mapa) < 2) { echo json_encode(['success' => false, 'error' => "El grupo $g no tiene suficientes clubes"]); exit; }
            $st = $mysqli2->prepare("SELECT * FROM _ic_partidos WHERE id_evento=? AND id_categoria=? AND grupo=? AND fase='grupo'");
            $st->bind_param('iii', $idEvento, $idCat, $g);
            $st->execute();
            $resP = $st->get_result();
            $partG = [];
            while ($r = $resP->fetch_assoc()) $partG[] = $r;
            $ids = array_keys($mapa);
            $slotsPorCruce = [];
            for ($i = 0; $i < count($ids); $i++) for ($j = $i + 1; $j < count($ids); $j++) {
                $slotsPorCruce[min($ids[$i], $ids[$j]) . '-' . max($ids[$i], $ids[$j])] =
                    max(1, min(count(ic_duplas($mysqli2, $idEvento, $idCat, $ids[$i])), count(ic_duplas($mysqli2, $idEvento, $idCat, $ids[$j]))));
            }
            $pos[$g] = ic_posiciones($mapa, $partG, $slotsPorCruce);
            // Todas las series del grupo deben estar definidas (sj = series jugadas por club)
            foreach ($pos[$g] as $p) if ($p['sj'] < count($mapa) - 1) {
                echo json_encode(['success' => false, 'error' => "El grupo $g todavía tiene series sin definir"]); exit;
            }
        }
        $st = $mysqli2->prepare("INSERT INTO _ic_llaves (id_evento, id_categoria, fase, clubA, clubB) VALUES (?,?,?,?,?)");
        foreach ([['semi1', $pos[1][0]['id_club'], $pos[2][1]['id_club']],
                  ['semi2', $pos[2][0]['id_club'], $pos[1][1]['id_club']]] as [$fase, $ca, $cb]) {
            $st->bind_param('iisii', $idEvento, $idCat, $fase, $ca, $cb);
            $st->execute();
        }
        echo json_encode(['success' => true]);
        exit;
    }

    // Generar final y 3er puesto desde las semis definidas
    if ($action === 'generar_final') {
        $idCat = abs((int)($_GET['categoria'] ?? 0));
        if (!$idCat) { echo json_encode(['success' => false, 'error' => 'Falta categoría']); exit; }
        $st = $mysqli2->prepare("SELECT * FROM _ic_llaves WHERE id_evento=? AND id_categoria=?");
        $st->bind_param('ii', $idEvento, $idCat);
        $st->execute();
        $resL = $st->get_result();
        $rows = [];
        while ($r = $resL->fetch_assoc()) $rows[$r['fase']] = $r;
        if (isset($rows['final'])) { echo json_encode(['success' => false, 'error' => 'La final ya fue generada']); exit; }
        if (!isset($rows['semi1']) || !isset($rows['semi2'])) { echo json_encode(['success' => false, 'error' => 'Faltan las semifinales']); exit; }
        $ganadores = $perdedores = [];
        foreach (['semi1', 'semi2'] as $fase) {
            $a = (int)$rows[$fase]['clubA']; $b = (int)$rows[$fase]['clubB'];
            $slots = max(1, min(count(ic_duplas($mysqli2, $idEvento, $idCat, $a)), count(ic_duplas($mysqli2, $idEvento, $idCat, $b))));
            $st = $mysqli2->prepare("SELECT * FROM _ic_partidos WHERE id_evento=? AND id_categoria=? AND fase=?");
            $st->bind_param('iis', $idEvento, $idCat, $fase);
            $st->execute();
            $resP = $st->get_result();
            $ms = [];
            while ($r = $resP->fetch_assoc()) $ms[] = $r;
            [, , $definida, $ganador] = ic_estado_serie($ms, $a, $b, $slots);
            if (!$definida) { echo json_encode(['success' => false, 'error' => "La $fase todavía no está definida"]); exit; }
            $ganadores[] = $ganador;
            $perdedores[] = $ganador === $a ? $b : $a;
        }
        $st = $mysqli2->prepare("INSERT INTO _ic_llaves (id_evento, id_categoria, fase, clubA, clubB) VALUES (?,?,?,?,?)");
        foreach ([['final', $ganadores[0], $ganadores[1]], ['tercer', $perdedores[0], $perdedores[1]]] as [$fase, $ca, $cb]) {
            $st->bind_param('iisii', $idEvento, $idCat, $fase, $ca, $cb);
            $st->execute();
        }
        echo json_encode(['success' => true]);
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
            $st = $mysqli2->prepare("UPDATE _ic_partidos SET s1c1=?,s1c2=?,s2c1=?,s2c2=?,s3c1=?,s3c2=?, en_juego='no' WHERE id=? AND id_evento=? LIMIT 1");
            $st->bind_param('iiiiiiii', $s['s1c1'], $s['s1c2'], $s['s2c1'], $s['s2c2'], $s['s3c1'], $s['s3c2'], $id, $idEvento);
            $st->execute();
            $st = $mysqli2->prepare("SELECT id_categoria FROM _ic_partidos WHERE id=? LIMIT 1");
            $st->bind_param('i', $id);
            $st->execute();
            $rc = $st->get_result()->fetch_assoc();
            if ($rc) ic_autogenerar_llaves($mysqli2, $idEvento, (int)$rc['id_categoria']);
            echo json_encode(['success' => true]);
            exit;
        }

        $fase = preg_replace('/[^a-z0-9]/', '', $_GET['fase'] ?? 'grupo');
        if (!in_array($fase, ['grupo', 'semi1', 'semi2', 'final', 'tercer'], true)) $fase = 'grupo';

        if (!$idCat || !$club1 || !$club2 || in_array('', $cis, true) || ($fase === 'grupo' && !$grupo)) {
            echo json_encode(['success' => false, 'error' => 'Faltan datos del partido']); exit;
        }
        if ($fase === 'grupo') {
            // Ambos clubes deben estar sorteados en ese grupo
            $st = $mysqli2->prepare("SELECT COUNT(*) c FROM _ic_sorteo WHERE id_evento=? AND id_categoria=? AND grupo=? AND id_club IN (?,?)");
            $st->bind_param('iiiii', $idEvento, $idCat, $grupo, $club1, $club2);
            $st->execute();
            if ((int)$st->get_result()->fetch_assoc()['c'] !== 2) {
                echo json_encode(['success' => false, 'error' => 'Los clubes no pertenecen a ese grupo']); exit;
            }
        } else {
            // El cruce debe existir en _ic_llaves con esos clubes
            $grupo = 0;
            $st = $mysqli2->prepare("SELECT clubA, clubB FROM _ic_llaves WHERE id_evento=? AND id_categoria=? AND fase=? LIMIT 1");
            $st->bind_param('iis', $idEvento, $idCat, $fase);
            $st->execute();
            $ll = $st->get_result()->fetch_assoc();
            if (!$ll || count(array_intersect([$club1, $club2], [(int)$ll['clubA'], (int)$ll['clubB']])) !== 2) {
                echo json_encode(['success' => false, 'error' => 'El cruce no corresponde a esa fase']); exit;
            }
        }
        $st = $mysqli2->prepare(
            "INSERT INTO _ic_partidos (id_evento, id_categoria, grupo, fase, club1, club2, es_desempate,
                ci1_a, ci1_b, ci2_a, ci2_b, s1c1, s1c2, s2c1, s2c2, s3c1, s3c2)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $st->bind_param('iiisiiissssiiiiii', $idEvento, $idCat, $grupo, $fase, $club1, $club2, $esDes,
            $cis['ci1_a'], $cis['ci1_b'], $cis['ci2_a'], $cis['ci2_b'],
            $s['s1c1'], $s['s1c2'], $s['s2c1'], $s['s2c2'], $s['s3c1'], $s['s3c2']);
        $st->execute();
        ic_autogenerar_llaves($mysqli2, $idEvento, $idCat);
        echo json_encode(['success' => true, 'id' => $mysqli2->insert_id]);
        exit;
    }

    // Marcar/desmarcar un partido EN JUEGO (crea la fila sin resultado si no existe)
    if ($action === 'en_juego') {
        $id = abs((int)($_GET['id'] ?? 0));
        if ($id) {
            $st = $mysqli2->prepare("UPDATE _ic_partidos SET en_juego = IF(en_juego='si','no','si') WHERE id=? AND id_evento=? LIMIT 1");
            $st->bind_param('ii', $id, $idEvento);
            $st->execute();
            echo json_encode(['success' => true]);
            exit;
        }
        $idCat  = abs((int)($_GET['categoria'] ?? 0));
        $grupo  = abs((int)($_GET['grupo'] ?? 0));
        $club1  = abs((int)($_GET['club1'] ?? 0));
        $club2  = abs((int)($_GET['club2'] ?? 0));
        $fase   = preg_replace('/[^a-z0-9]/', '', $_GET['fase'] ?? 'grupo');
        if (!in_array($fase, ['grupo', 'semi1', 'semi2', 'final', 'tercer'], true)) $fase = 'grupo';
        $cis = [];
        foreach (['ci1_a','ci1_b','ci2_a','ci2_b'] as $k) $cis[$k] = preg_replace('/[^0-9]/', '', $_GET[$k] ?? '');
        if (!$idCat || !$club1 || !$club2 || in_array('', $cis, true)) {
            echo json_encode(['success' => false, 'error' => 'Faltan datos']); exit;
        }
        if ($fase !== 'grupo') $grupo = 0;
        $st = $mysqli2->prepare(
            "INSERT INTO _ic_partidos (id_evento, id_categoria, grupo, fase, club1, club2, es_desempate, en_juego,
                ci1_a, ci1_b, ci2_a, ci2_b)
             VALUES (?,?,?,?,?,?,0,'si',?,?,?,?)");
        $st->bind_param('iiisiissss', $idEvento, $idCat, $grupo, $fase, $club1, $club2,
            $cis['ci1_a'], $cis['ci1_b'], $cis['ci2_a'], $cis['ci2_b']);
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
<title>Carga · <?= htmlspecialchars($evento['evento']) ?></title>
<link rel="shortcut icon" href="/favicon.ico">
<script>(function(){var t=localStorage.getItem('bt-theme');if(t)document.documentElement.setAttribute('data-theme',t);})();</script>
<style>
  :root { --bg:#111827; --card:#1f2937; --hd:#374151; --hd-tx:#fff; --border:#374151; --row:#243040;
          --text:#f3f4f6; --text2:#9ca3af; --input-bg:#111827; --accent:#3b82f6; --win:#4ade80; --modal:#1f2937; }
  [data-theme="light"] { --bg:hsl(210,20%,97%); --card:#fff; --hd:#374151; --hd-tx:#fff; --border:hsl(214,25%,85%); --row:#f8fafb;
          --text:hsl(220,20%,15%); --text2:hsl(215,14%,50%); --input-bg:#fff; --accent:#2563eb; --win:#16a34a; --modal:#fff; }
  * { box-sizing: border-box; }
  body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--bg); color: var(--text); margin: 0; }
  .wrap { max-width: 980px; margin: 0 auto; padding: 14px 12px 70px; }
  .topbar { display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap; margin-bottom: 12px; }
  .topbar h1 { font-size: 17px; margin: 0; }
  .topbar .sub { font-size: 11px; color: var(--text2); }
  .topbar .der { display: flex; gap: 8px; align-items: center; }
  .theme-btn, .lk { background: none; border: 1px solid var(--border); border-radius: 6px; padding: 5px 10px;
    color: var(--text2); cursor: pointer; font-size: 12px; text-decoration: none; font-family: inherit; }
  .theme-btn:hover, .lk:hover { border-color: var(--accent); color: var(--text); }
  .cat-pills { display: grid; grid-template-columns: repeat(3,1fr); gap: 6px; margin-bottom: 12px; }
  @media (min-width: 640px) { .cat-pills { grid-template-columns: repeat(5,1fr); } }
  .cat-pills button { text-align: center; font-size: 11px; font-weight: 700; padding: 8px 4px; border-radius: 8px; cursor: pointer;
    background: var(--card); color: var(--text); border: 1px solid var(--border); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-family: inherit; }
  .cat-pills button.on { background: var(--accent); color: #fff; border-color: var(--accent); }
  .tabs { display: flex; gap: 4px; background: var(--card); border: 1px solid var(--border); border-radius: 8px; padding: 4px; margin-bottom: 14px; max-width: 400px; }
  .tab-btn { flex: 1; padding: 8px; border: none; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer;
    background: transparent; color: var(--text2); font-family: inherit; }
  .tab-btn.active { background: var(--accent); color: #fff; }
  .tab-content { display: none; } .tab-content.active { display: block; }
  .groups-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 20px; }
  .group-head { display: flex; align-items: center; justify-content: space-between; margin: 8px 0; }
  .group-head .gname { font-weight: 700; font-size: 15px; }
  .btn-clasif { display: inline-flex; align-items: center; gap: 4px; background: none; border: 1px solid var(--border);
    border-radius: 6px; padding: 4px 10px; font-size: 12px; color: var(--text2); cursor: pointer; font-family: inherit; }
  /* Match cards */
  .match-card { background: var(--card); border-radius: 8px; border: 1px solid var(--border); overflow: hidden; margin-bottom: 12px; }
  .match-card.en-juego { border: 2px solid #EBA652; }
  .match-card.en-juego .match-header { background: #EBA652 !important; color: #1f2937; animation: pulse 2s infinite; }
  @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: .85; } }
  .match-header { background: var(--hd); color: var(--hd-tx); display: flex; align-items: center; justify-content: space-between; gap: 8px;
    padding: 9px 13px; cursor: pointer; border: none; width: 100%; text-align: left; font-family: inherit; }
  .match-header .round { font-size: 10px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; opacity: .8; white-space: nowrap; }
  .match-header .info { flex: 1; min-width: 0; display: flex; align-items: center; gap: 8px; }
  .match-header .summary { font-size: 12px; font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .badge-finalizado { background: #16a34a; color: #fff; font-size: 9px; font-weight: 800; padding: 2px 8px; border-radius: 999px; flex-shrink: 0; }
  .match-header .chevron { font-size: 10px; transition: transform .25s; }
  .match-card.abierta .chevron { transform: rotate(180deg); }
  .match-body { display: none; }
  .match-card.abierta .match-body { display: block; }
  .p-row { padding: 10px 13px; border-top: 1px solid var(--border); }
  .p-row.p-ej { background: rgba(235,166,82,.12); }
  .p-label { font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--text2); margin-bottom: 7px; }
  .ej-pill { background: #EBA652; color: #1f2937; padding: 1px 8px; border-radius: 999px; font-size: 9px; font-weight: 800; }
  .p-grid { display: grid; grid-template-columns: minmax(0,1fr) auto minmax(0,1fr); align-items: center; gap: 8px; }
  .p-team { min-width: 0; font-size: 12px; font-weight: 600; line-height: 1.45; }
  .p-team.right { text-align: right; }
  .p-club { display: block; font-size: 9px; font-weight: 800; letter-spacing: .05em; color: var(--accent); margin-bottom: 2px; }
  .p-win { color: var(--win); font-weight: 800; }
  .sets { display: flex; gap: 3px; align-items: center; justify-content: center; }
  .sets .ic { width: 34px; padding: 6px 2px; text-align: center; border: 1px solid var(--border); border-radius: 6px;
    font-size: 13px; font-weight: 700; background: var(--input-bg); color: var(--text); -moz-appearance: textfield; }
  .sets .ic::-webkit-outer-spin-button, .sets .ic::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
  .sets .ic:focus { outline: 2px solid var(--accent); border-color: var(--accent); }
  .sets .sep { color: var(--text2); font-size: 10px; }
  .acc { display: flex; gap: 6px; margin-top: 8px; flex-wrap: wrap; }
  .btn { border: none; border-radius: 7px; padding: 7px 12px; font-size: 12px; font-weight: 700; cursor: pointer; font-family: inherit; }
  .btn-ok { background: #16a34a; color: #fff; }
  .btn-gh { background: none; color: var(--text2); border: 1px solid var(--border); }
  .btn-del { background: rgba(213,65,65,.15); color: #f87171; }
  select.jug { padding: 6px; border: 1px solid var(--border); border-radius: 6px; font-size: 12px; max-width: 180px;
    margin: 2px 0; display: block; background: var(--input-bg); color: var(--text); font-family: inherit; }
  .msg { font-size: 12px; color: var(--text2); font-style: italic; }
  .sec-title { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .08em; color: var(--text2); margin: 18px 0 8px; }
  /* Modal clasificación */
  .modal-clasif-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.6); z-index: 9999; align-items: center; justify-content: center; padding: 16px; }
  .modal-clasif-overlay.show { display: flex; }
  .modal-clasif-box { background: var(--modal); border-radius: 10px; width: 100%; max-width: 460px; overflow: hidden; border: 1px solid var(--border); }
  .modal-clasif-header { background: var(--hd); color: var(--hd-tx); padding: 12px 16px; display: flex; justify-content: space-between; font-weight: 700; font-size: 14px; }
  .modal-clasif-header button { background: none; border: none; color: var(--hd-tx); font-size: 18px; cursor: pointer; }
  table.pos { width: 100%; border-collapse: collapse; font-size: 12px; }
  table.pos th { background: var(--row); color: var(--text2); font-size: 10px; text-transform: uppercase; padding: 8px 6px; }
  table.pos td { padding: 8px 6px; text-align: center; border-top: 1px solid var(--border); color: var(--text); }
  table.pos td.club { text-align: left; font-weight: 700; }
  table.pos tr.lider td { color: var(--win); }
  /* Bracket */
  .bracket-wrapper { overflow-x: auto; padding: 4px 0 12px; }
  .bracket-flex { display: flex; align-items: stretch; min-width: 560px; }
  .bracket-col { flex: 1 1 0; min-width: 0; display: flex; flex-direction: column; }
  .bracket-col-body { flex: 1; display: flex; flex-direction: column; justify-content: space-around; position: relative; gap: 28px; }
  .bracket-col-body .match-card { margin-bottom: 0; }
  .bracket-ronda-header { text-align: center; font-size: 11px; font-weight: 700; color: var(--text2); text-transform: uppercase; letter-spacing: .05em; padding: 6px 0 10px; }
  .bracket-conn { flex: 0 0 24px; position: relative; }
  .bracket-conn svg { position: absolute; inset: 0; width: 100%; height: 100%; }
  .bracket-conn svg line { stroke: var(--border); stroke-width: 1.5; }
  .campeon { text-align: center; margin: 10px 0 14px; }
  .campeon span { display: inline-block; background: #facc15; color: #713f12; font-weight: 800; font-size: 13px; padding: 7px 20px; border-radius: 999px; }
  .toast { position: fixed; bottom: 18px; left: 50%; transform: translateX(-50%); background: var(--accent); color: #fff;
    padding: 10px 18px; border-radius: 10px; font-size: 13px; display: none; z-index: 99999; }
</style>
</head>
<body>
<div class="wrap">
  <div class="topbar">
    <div>
      <h1>📊 Carga de resultados — <?= htmlspecialchars($evento['evento']) ?></h1>
      <div class="sub">P1: dupla 1 vs dupla 1 · P2: dupla 2 vs dupla 2 · serie 1-1 → desempate con dupla mezclada · las llaves avanzan solas</div>
    </div>
    <div class="der">
      <a class="lk" href="/interclubes_sorteo.php?evento=<?= $idEvento ?>">🎲 Sorteo</a>
      <a class="lk" href="/tvt_admin_v2.php">← Admin</a>
      <button class="theme-btn" onclick="toggleTheme()" title="Cambiar tema">◐</button>
    </div>
  </div>

  <div class="cat-pills" id="cats"></div>
  <div class="tabs" id="tabsBox" style="display:none;">
    <button class="tab-btn active" id="tabbtn-resultados" onclick="switchTab('resultados')">Grupos</button>
    <button class="tab-btn" id="tabbtn-clasificacion" onclick="switchTab('clasificacion')">SF →</button>
  </div>
  <div id="tab-resultados" class="tab-content active"></div>
  <div id="tab-clasificacion" class="tab-content"></div>
  <div id="sinCat" class="msg" style="padding:20px 0;">Seleccioná una categoría. Solo aparecen las que ya tienen sorteo cargado.</div>
  <div id="modales"></div>
</div>
<div class="toast" id="toast"></div>

<script>
const EVENTO = <?= $idEvento ?>;
let catActual = 0;
let DATA = null;

function toggleTheme() {
  const d = document.documentElement;
  const t = d.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
  d.setAttribute('data-theme', t);
  localStorage.setItem('bt-theme', t);
}
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
const UP = s => esc(String(s ?? '').toUpperCase());

async function cargarCats() {
  const r = await api({action: 'categorias'});
  if (!r.success) return;
  const box = document.getElementById('cats');
  box.innerHTML = '';
  r.categorias.forEach(c => {
    const b = document.createElement('button');
    b.id = 'catpill-' + c.id_categoria;
    b.textContent = c.categoria;
    b.onclick = () => { catActual = parseInt(c.id_categoria); marcarPill(); cargarEstado(); };
    box.appendChild(b);
  });
  if (!r.categorias.length) document.getElementById('sinCat').textContent = 'Todavía no hay sorteo cargado en ninguna categoría.';
}
function marcarPill() {
  document.querySelectorAll('.cat-pills button').forEach(p => p.classList.remove('on'));
  const p = document.getElementById('catpill-' + catActual);
  if (p) p.classList.add('on');
  document.getElementById('sinCat').style.display = 'none';
  document.getElementById('tabsBox').style.display = '';
}
function switchTab(t) {
  document.querySelectorAll('.tab-content').forEach(e => e.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(e => e.classList.remove('active'));
  document.getElementById('tab-' + t).classList.add('active');
  document.getElementById('tabbtn-' + t).classList.add('active');
  if (t === 'clasificacion') setTimeout(drawBracketLines, 100);
}

function setsInputs(pref, s) {
  s = s || [0,0,0,0,0,0];
  const inp = (id, v) => `<input class="ic" type="number" min="0" max="30" id="${pref}-${id}" value="${v || ''}" placeholder="0">`;
  return `<div class="sets">${inp('s1a',s[0])}<span class="sep">-</span>${inp('s1b',s[1])}<span class="sep">|</span>${inp('s2a',s[2])}<span class="sep">-</span>${inp('s2b',s[3])}<span class="sep">|</span>${inp('s3a',s[4])}<span class="sep">-</span>${inp('s3b',s[5])}</div>`;
}
function leerSets(pref) {
  const v = id => parseInt(document.getElementById(pref + '-' + id).value) || 0;
  return {s1c1: v('s1a'), s1c2: v('s1b'), s2c1: v('s2a'), s2c2: v('s2b'), s3c1: v('s3a'), s3c2: v('s3b')};
}

// Fila de partido (cargado o slot pendiente) con inputs
function filaPartido(m, i, serie, grupo, fase, sid) {
  const pref = `${sid}p${i}`;
  const dupA = DATA.duplas[serie.clubA] || [], dupB = DATA.duplas[serie.clubB] || [];
  let h = `<div class="p-row${m && m.en_juego === 'si' ? ' p-ej' : ''}">`;
  h += `<div class="p-label">Partido ${i+1} — Dupla ${i+1} vs Dupla ${i+1}${m && m.en_juego === 'si' ? ' <span class="ej-pill">🔴 EN JUEGO</span>' : ''}</div>`;
  if (m) {
    const g1 = m.ganador === 1, g2 = m.ganador === 2;
    h += `<div class="p-grid">
      <div class="p-team"><span class="p-club">${UP(m.club1 === serie.clubA ? serie.nomA : serie.nomB)}</span>
        <span class="${g1?'p-win':''}">${g1?'✓ ':''}${UP(m.n1a)}<br>${g1?'✓ ':''}${UP(m.n1b)}</span></div>
      ${setsInputs(pref, m.s)}
      <div class="p-team right"><span class="p-club">${UP(m.club2 === serie.clubA ? serie.nomA : serie.nomB)}</span>
        <span class="${g2?'p-win':''}">${UP(m.n2a)}${g2?' ✓':''}<br>${UP(m.n2b)}${g2?' ✓':''}</span></div>
    </div>
    <div class="acc"><button class="btn btn-ok" onclick="guardar('${pref}', {id: ${m.id}})">Guardar</button>
      <button class="btn btn-gh" onclick="toggleEnJuego(${m.id})">${m.en_juego === 'si' ? 'Quitar en juego' : '🎾 En juego'}</button>
      <button class="btn btn-del" onclick="borrar(${m.id})">Borrar</button></div>`;
  } else if (dupA[i] && dupB[i]) {
    const args = `{categoria: ${catActual}, grupo: ${grupo}, fase: '${fase}', club1: ${serie.clubA}, club2: ${serie.clubB},
      ci1_a: '${dupA[i].ci}', ci1_b: '${dupA[i].ci_dupla}', ci2_a: '${dupB[i].ci}', ci2_b: '${dupB[i].ci_dupla}'}`;
    h += `<div class="p-grid">
      <div class="p-team"><span class="p-club">${UP(serie.nomA)} ${i+1}</span>${UP(dupA[i].j1)}<br>${UP(dupA[i].j2)}</div>
      ${setsInputs(pref)}
      <div class="p-team right"><span class="p-club">${UP(serie.nomB)} ${i+1}</span>${UP(dupB[i].j1)}<br>${UP(dupB[i].j2)}</div>
    </div>
    <div class="acc"><button class="btn btn-ok" onclick="guardar('${pref}', {...${args}, es_desempate: 0})">Cargar resultado</button>
      <button class="btn btn-gh" onclick="marcarEnJuego(${args})">🎾 En juego</button></div>`;
  } else {
    h += `<div class="msg">Falta la dupla ${i+1} de ${!dupA[i] ? esc(serie.nomA) : esc(serie.nomB)}</div>`;
  }
  return h + `</div>`;
}

function serieBloque(serie, sid, grupo, fase, titulo) {
  const enJuego = serie.partidos.some(x => x.en_juego === 'si');
  let summary, badge = '';
  if (serie.definida) {
    summary = `${UP(serie.ganador === serie.clubA ? serie.nomA : serie.nomB)} (${Math.max(serie.winsA, serie.winsB)}-${Math.min(serie.winsA, serie.winsB)})`;
    badge = `<span class="badge-finalizado">FINALIZADO</span>`;
  } else if (serie.partidos.length) {
    summary = `Serie ${serie.winsA}-${serie.winsB}${serie.necesita_desempate ? ' · desempate' : ''}${enJuego ? ' 🔴 EN JUEGO' : ''}`;
  } else {
    summary = 'A continuación' + (enJuego ? ' 🔴 EN JUEGO' : '');
  }
  // Serie definida → colapsada; quedan abiertas solo las que faltan cargar
  let h = `<div class="match-card${serie.definida ? '' : ' abierta'}${enJuego ? ' en-juego' : ''}">
    <button class="match-header" onclick="toggleMatch(this)">
      <span class="round">${titulo}</span>
      <div class="info"><span class="summary">${UP(serie.nomA)} vs ${UP(serie.nomB)} — ${summary}</span>${badge}</div>
      <span class="chevron">▼</span>
    </button>
    <div class="match-body">`;
  const regs = serie.partidos.filter(x => !x.es_desempate);
  for (let i = 0; i < serie.slots; i++) h += filaPartido(regs[i], i, serie, grupo, fase, sid);
  // Desempate
  const des = serie.partidos.find(x => x.es_desempate);
  if (des || serie.necesita_desempate) {
    const pref = `${sid}des`;
    h += `<div class="p-row${des && des.en_juego === 'si' ? ' p-ej' : ''}" style="background:rgba(124,58,237,.08);">
      <div class="p-label" style="color:#a78bfa;">★ Desempate — dupla mezclada${des && des.en_juego === 'si' ? ' <span class="ej-pill">🔴 EN JUEGO</span>' : ''}</div>`;
    if (des) {
      const g1 = des.ganador === 1, g2 = des.ganador === 2;
      h += `<div class="p-grid">
        <div class="p-team"><span class="p-club">${UP(des.club1 === serie.clubA ? serie.nomA : serie.nomB)}</span>
          <span class="${g1?'p-win':''}">${g1?'✓ ':''}${UP(des.n1a)}<br>${g1?'✓ ':''}${UP(des.n1b)}</span></div>
        ${setsInputs(pref, des.s)}
        <div class="p-team right"><span class="p-club">${UP(des.club2 === serie.clubA ? serie.nomA : serie.nomB)}</span>
          <span class="${g2?'p-win':''}">${UP(des.n2a)}${g2?' ✓':''}<br>${UP(des.n2b)}${g2?' ✓':''}</span></div>
      </div>
      <div class="acc"><button class="btn btn-ok" onclick="guardar('${pref}', {id: ${des.id}})">Guardar</button>
        <button class="btn btn-gh" onclick="toggleEnJuego(${des.id})">${des.en_juego === 'si' ? 'Quitar en juego' : '🎾 En juego'}</button>
        <button class="btn btn-del" onclick="borrar(${des.id})">Borrar</button></div>`;
    } else {
      const selJug = (club, nom, id) => {
        let o = `<select class="jug" id="${pref}-${id}"><option value="">Jugador de ${esc(nom)}...</option>`;
        (DATA.jugadores[club] || []).forEach(j => o += `<option value="${esc(j.ci)}">${esc(j.nombre)}</option>`);
        return o + '</select>';
      };
      h += `<div class="p-grid">
        <div class="p-team">${selJug(serie.clubA, serie.nomA, 'j1a')}${selJug(serie.clubA, serie.nomA, 'j1b')}</div>
        ${setsInputs(pref)}
        <div class="p-team right">${selJug(serie.clubB, serie.nomB, 'j2a')}${selJug(serie.clubB, serie.nomB, 'j2b')}</div>
      </div>
      <div class="acc"><button class="btn btn-ok" onclick="guardarDesempate('${pref}', ${catActual}, ${grupo}, '${fase}', ${serie.clubA}, ${serie.clubB})">Cargar desempate</button></div>`;
    }
    h += `</div>`;
  }
  return h + `</div></div>`;
}

async function cargarEstado() {
  const r = await api({action: 'estado', categoria: catActual});
  if (!r.success) { toast(r.error || 'Error'); return; }
  DATA = r;

  // ── Tab Grupos: lado a lado + modal clasificación ──
  let h = `<div class="groups-grid">`;
  let modales = '';
  r.grupos.forEach(g => {
    h += `<div style="min-width:0;">
      <div class="group-head"><span class="gname">GRUPO ${g.grupo}</span>
        <button class="btn-clasif" onclick="openModalClasif('modal-clasif-${g.grupo}')">&#9776; Clasificación</button></div>`;
    g.series.forEach((serie, si) => { h += serieBloque(serie, `g${g.grupo}s${si}`, g.grupo, 'grupo', `Serie ${si+1}`); });
    if (!g.series.length) h += `<div class="msg">Sin enfrentamientos (faltan clubes en el sorteo)</div>`;
    h += `</div>`;
    let filas = '';
    g.posiciones.forEach((p, i) => {
      filas += `<tr class="${i === 0 && p.pts > 0 ? 'lider' : ''}"><td class="club">${i+1}. ${UP(p.club)}</td>
        <td>${p.sg}-${p.sp}</td><td>${p.pg}-${p.pp}</td><td>${p.setsF}-${p.setsC}</td><td><b>${p.pts}</b></td></tr>`;
    });
    modales += `<div class="modal-clasif-overlay" id="modal-clasif-${g.grupo}" onclick="if(event.target===this)closeModalClasif('modal-clasif-${g.grupo}')">
      <div class="modal-clasif-box">
        <div class="modal-clasif-header"><span>Clasificación — Grupo ${g.grupo}</span><button onclick="closeModalClasif('modal-clasif-${g.grupo}')">&times;</button></div>
        <table class="pos"><tr><th style="text-align:left;">Club</th><th>Series</th><th>Partidos</th><th>Sets</th><th>Pts</th></tr>${filas}</table>
      </div></div>`;
  });
  h += `</div>`;
  document.getElementById('tab-resultados').innerHTML = h;
  document.getElementById('modales').innerHTML = modales;

  // ── Tab SF: bracket con conectores + 3er puesto abajo ──
  let hs = '';
  const porFase = {};
  (r.llaves || []).forEach(s => porFase[s.fase] = s);
  if (r.llaves_generadas) {
    const final = porFase['final'];
    if (final && final.definida) {
      hs += `<div class="campeon"><span>🏆 CAMPEÓN: ${UP(final.ganador === final.clubA ? final.nomA : final.nomB)}</span></div>`;
    }
    hs += `<div class="bracket-wrapper"><div class="bracket-flex">
      <div class="bracket-col"><div class="bracket-ronda-header">Semifinales</div><div class="bracket-col-body" id="bracket-body-sf">`;
    ['semi1', 'semi2'].forEach((f, i) => { if (porFase[f]) hs += serieBloque(porFase[f], `ll${f}`, 0, f, porFase[f].label); });
    hs += `</div></div>
      <div class="bracket-conn" id="bracket-conn-sf-fn"><svg></svg></div>
      <div class="bracket-col"><div class="bracket-ronda-header">Final</div><div class="bracket-col-body" id="bracket-body-fn">`;
    hs += final ? serieBloque(final, 'llfinal', 0, 'final', '🏆 Final')
                : `<div class="match-card"><div class="match-header"><span class="round">🏆 Final</span><div class="info"><span class="summary msg">A definir — ganadores de las semis</span></div></div></div>`;
    hs += `</div></div></div></div>`;
    hs += `<div class="sec-title">3er Puesto</div>`;
    hs += porFase['tercer'] ? serieBloque(porFase['tercer'], 'lltercer', 0, 'tercer', '3er Puesto')
                            : `<div class="msg">Se define con los perdedores de las semifinales.</div>`;
  } else {
    hs = `<div class="msg" style="padding:16px 0;">Las semifinales se arman solas al completarse todas las series de ambos grupos (1° Grupo 1 vs 2° Grupo 2 y 1° Grupo 2 vs 2° Grupo 1).</div>`;
  }
  document.getElementById('tab-clasificacion').innerHTML = hs;
  if (document.getElementById('tab-clasificacion').classList.contains('active')) setTimeout(drawBracketLines, 100);
}

function toggleMatch(btn) {
  btn.closest('.match-card').classList.toggle('abierta');
  setTimeout(drawBracketLines, 50);
}
function openModalClasif(id) { document.getElementById(id).classList.add('show'); }
function closeModalClasif(id) { document.getElementById(id).classList.remove('show'); }

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
    }
  });
}
window.addEventListener('resize', drawBracketLines);

async function guardar(pref, extra) {
  const r = await api({action: 'guardar_partido', ...extra, ...leerSets(pref)});
  if (!r.success) { toast(r.error || 'Error'); return; }
  toast('Guardado ✓');
  cargarEstado();
}
async function guardarDesempate(pref, cat, grupo, fase, clubA, clubB) {
  const v = id => document.getElementById(pref + '-' + id).value;
  if (!v('j1a') || !v('j1b') || !v('j2a') || !v('j2b')) { toast('Elegí los 4 jugadores'); return; }
  if (v('j1a') === v('j1b') || v('j2a') === v('j2b')) { toast('Los jugadores de una dupla deben ser distintos'); return; }
  await guardar(pref, {categoria: cat, grupo, fase, club1: clubA, club2: clubB, es_desempate: 1,
    ci1_a: v('j1a'), ci1_b: v('j1b'), ci2_a: v('j2a'), ci2_b: v('j2b')});
}
async function marcarEnJuego(args) {
  const r = await api({action: 'en_juego', ...args});
  if (!r.success) { toast(r.error || 'Error'); return; }
  toast('Partido en juego 🎾');
  cargarEstado();
}
async function toggleEnJuego(id) {
  await api({action: 'en_juego', id});
  cargarEstado();
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
