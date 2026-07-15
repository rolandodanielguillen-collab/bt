<?php
/**
 * tvt_api.php — API Backend para tvt_admin_v2.php
 * ================================================================
 * Devuelve JSON para todas las secciones del dashboard.
 * Uso: tvt_api.php?action=kpis  o  tvt_api.php?action=eventos
 * ================================================================
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

// ── Auth check ──────────────────────────────────────────────────
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

// ── Conexión BD ──────────────────────────────────────────────────
if (file_exists("db/conection.inc.php")) {
    include_once "db/conection.inc.php";
    @include_once "funciones.php";
} else {
    include_once $_SERVER['DOCUMENT_ROOT'] . "/db/conection.inc.php";
    @include_once $_SERVER['DOCUMENT_ROOT'] . "/funciones.php";
}

// ── Helpers ──────────────────────────────────────────────────────
function resp($data) { echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
function respErr($msg) { resp(['success' => false, 'error' => $msg]); }
function intGet($k, $d = 0) { return isset($_GET[$k]) ? abs((int)$_GET[$k]) : $d; }
function strGet($k, $d = '') { return isset($_GET[$k]) ? trim($_GET[$k]) : (isset($_POST[$k]) ? trim($_POST[$k]) : $d); }

require_once __DIR__ . '/propagacion.functions.php';

$action = strGet('action');
if (!$action) respErr('Falta parámetro action');

// ══════════════════════════════════════════════════════════════════
// ACTION: kpis — KPIs del dashboard principal
// ══════════════════════════════════════════════════════════════════
if ($action === 'kpis') {
    // Eventos activos
    $r = $mysqli2->query("SELECT COUNT(*) as c FROM _p_eventos WHERE estado IN ('activo','registro')");
    $eventosActivos = $r ? (int)$r->fetch_assoc()['c'] : 0;

    // Total inscripciones (de _p_incripciones, no bloqueados)
    // Cada pareja se registra doble (A->B y B->A), dividir entre 2
    $r = $mysqli2->query("SELECT COUNT(*) as c FROM _p_incripciones WHERE estado<>'bloqueado'");
    $totalInscr = $r ? (int)floor((int)$r->fetch_assoc()['c'] / 2) : 0;

    // Jugadores registrados
    $r = $mysqli2->query("SELECT COUNT(*) as c FROM _p_usuarios");
    $totalJug = $r ? (int)$r->fetch_assoc()['c'] : 0;

    // Partidos generados
    $r = $mysqli2->query("SELECT COUNT(*) as c FROM _todosvstodos");
    $totalPartidos = $r ? (int)$r->fetch_assoc()['c'] : 0;

    // Partidos con resultado cargado
    $r = $mysqli2->query("SELECT COUNT(*) as c FROM _todosvstodos WHERE rusultado_equipo1 > 0 OR resultado_equipo2 > 0");
    $partidosConRes = $r ? (int)$r->fetch_assoc()['c'] : 0;

    resp([
        'success' => true,
        'kpis' => [
            'eventos_activos'   => $eventosActivos,
            'total_inscripciones' => $totalInscr,
            'jugadores'         => $totalJug,
            'total_partidos'    => $totalPartidos,
            'partidos_jugados'  => $partidosConRes,
            'partidos_pendientes' => $totalPartidos - $partidosConRes,
        ]
    ]);
}

// ══════════════════════════════════════════════════════════════════
// ACTION: eventos — Lista de eventos
// ══════════════════════════════════════════════════════════════════
if ($action === 'eventos') {
    $filtro = strGet('estado');
    $where = "WHERE estado != 'inactivo'"; // Never show inactive events
    if ($filtro && in_array($filtro, ['activo','registro','culminado'])) {
        $where = "WHERE estado = '$filtro'";
    }
    $sql = "SELECT id, evento, url_amigable, fecha, fecha_fin, estado,
                   sha1(id) as sha1_id
            FROM _p_eventos $where ORDER BY id DESC LIMIT 50";
    $r = $mysqli2->query($sql);
    $eventos = [];
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            // Contar categorías desde _relacion_evento_categoria (fuente real)
            $rc = $mysqli2->query("SELECT COUNT(*) as c FROM _relacion_evento_categoria WHERE id_evento='{$row['id']}'");
            $row['categorias'] = $rc ? (int)$rc->fetch_assoc()['c'] : 0;
            // Contar equipos (parejas generadas en _equipos)
            $re = $mysqli2->query("SELECT COUNT(*) as c FROM _equipos WHERE id_evento={$row['id']}");
            $row['equipos'] = $re ? (int)$re->fetch_assoc()['c'] : 0;
            // Contar inscripciones reales (de _p_incripciones, dividir entre 2)
            $ri = $mysqli2->query("SELECT COUNT(*) as c FROM _p_incripciones WHERE id_evento='{$row['id']}' AND estado<>'bloqueado'");
            $row['inscripciones'] = $ri ? (int)floor((int)$ri->fetch_assoc()['c'] / 2) : 0;
            // Contar partidos
            $rp = $mysqli2->query("SELECT COUNT(*) as c FROM _todosvstodos WHERE evento={$row['id']}");
            $row['partidos'] = $rp ? (int)$rp->fetch_assoc()['c'] : 0;
            // Contar sorteos listos (categorías con partidos > 0)
            $rs = $mysqli2->query("SELECT COUNT(DISTINCT categoria) as c FROM _todosvstodos WHERE evento={$row['id']}");
            $row['sorteos_listos'] = $rs ? (int)$rs->fetch_assoc()['c'] : 0;
            // Contar parejas en _tabla_parejas
            $rtp = $mysqli2->query("SELECT COUNT(*) as c FROM _tabla_parejas WHERE evento={$row['id']}");
            $row['parejas_generadas'] = $rtp ? (int)$rtp->fetch_assoc()['c'] : 0;
            $eventos[] = $row;
        }
    }
    resp(['success' => true, 'eventos' => $eventos]);
}

// ══════════════════════════════════════════════════════════════════
// ACTION: categorias — Categorías de un evento (con datos TVT Admin)
// ══════════════════════════════════════════════════════════════════
if ($action === 'categorias') {
    $idEvento = intGet('evento');
    if (!$idEvento) respErr('Falta evento');

    // Evento data
    $revt = $mysqli2->query("SELECT id, evento, sha1(id) as sha1_id, url_amigable FROM _p_eventos WHERE id=$idEvento LIMIT 1");
    $evData = $revt ? $revt->fetch_assoc() : null;

    // ── FIX: Partir desde _relacion_evento_categoria para incluir TODAS las categorías ──
    // Antes se partía de _p_incripciones, lo cual excluía categorías sin inscripciones (ej: OPEN FEM.)
    $sql = "SELECT rec.id_categoria,
                   c.categoria as nombre,
                   rec.id as id_relacion,
                   rec.cupo,
                   rec.visualizar_en_llaves,
                   rec.estado as cat_estado
            FROM _relacion_evento_categoria rec
            LEFT JOIN _p_categorias c ON c.id = rec.id_categoria
            WHERE rec.id_evento = $idEvento
            ORDER BY rec.orden_visualizacion ASC, c.categoria ASC";
    $r = $mysqli2->query($sql);
    $cats = [];
    $totalParejas = 0;
    $totalPartidos = 0;
    $sorteosListos = 0;
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $cid = (int)$row['id_categoria'];

            // Contar inscriptos desde _p_incripciones (cada pareja registra doble)
            $ri = $mysqli2->query("SELECT FLOOR(COUNT(*)/2) as c FROM _p_incripciones 
                WHERE id_evento='$idEvento' AND id_categoria=$cid AND estado <> 'bloqueado'");
            $inscriptos = $ri ? (int)$ri->fetch_assoc()['c'] : 0;
            $row['inscriptos'] = $inscriptos;

            // Count equipos generados (from _equipos table)
            $req = $mysqli2->query("SELECT COUNT(*) as c FROM _equipos WHERE id_evento=$idEvento AND id_categoria=$cid");
            $row['parejas'] = $req ? (int)$req->fetch_assoc()['c'] : 0;

            // Use inscriptos for calculations if no equipos yet
            $n = $row['parejas'] > 0 ? (int)$row['parejas'] : $inscriptos;

            // Count partidos
            $rpt = $mysqli2->query("SELECT COUNT(*) as c FROM _todosvstodos WHERE evento=$idEvento AND categoria=$cid");
            $row['partidos'] = $rpt ? (int)$rpt->fetch_assoc()['c'] : 0;

            $totalParejas += $inscriptos;
            $totalPartidos += (int)$row['partidos'];
            if ((int)$row['partidos'] > 0) $sorteosListos++;

            // Buscar plantilla
            $rp = $mysqli2->query("SELECT id, nombre FROM _tvt_plantillas WHERE activo=1 AND min_parejas<=$n AND max_parejas>=$n LIMIT 1");
            $row['plantilla'] = ($rp && $rp->num_rows > 0) ? $rp->fetch_assoc() : null;
            $row['nombre'] = $row['nombre'] ?: "Cat. {$cid}";

            // Calcular grupos automáticos (default si no hay sorteo)
            if ($n <= 5) {
                $nGrupos = 1; $g4 = 0; $g3 = 0;
            } else {
                $nGrupos = (int)ceil($n / 4);
                $g4 = $n - (3 * $nGrupos);
                $g3 = $nGrupos - $g4;
            }
            $row['grupos_auto'] = $nGrupos;
            $row['g3'] = max(0, $g3);
            $row['g4'] = max(0, $g4);

            // Si ya hay sorteo, leer la cantidad de grupos REAL desde _todosvstodos
            if ((int)$row['partidos'] > 0) {
                $rgr = $mysqli2->query("SELECT COUNT(DISTINCT grupo) as c FROM _todosvstodos WHERE evento=$idEvento AND categoria={$row['id_categoria']} AND grupo < 13");
                $row['grupos_reales'] = $rgr ? (int)$rgr->fetch_assoc()['c'] : $nGrupos;
            } else {
                $row['grupos_reales'] = 0;
            }

            // Estimar partidos (round robin por grupo)
            $estPartidos = 0;
            if ($n <= 5) {
                $estPartidos = $n * ($n - 1) / 2;
            } else {
                $estPartidos = max(0,$g3) * 3 + max(0,$g4) * 6; // 3C2=3, 4C2=6
            }
            $row['est_partidos'] = (int)$estPartidos;

            // Max grupos viable
            $row['max_grupos'] = min(12, max(1, (int)floor($n / 2)));

            // Parejas en _tabla_parejas
            $rtp = $mysqli2->query("SELECT COUNT(*) as c FROM _tabla_parejas WHERE evento=$idEvento AND categoria={$row['id_categoria']}");
            $row['parejas_tabla'] = $rtp ? (int)$rtp->fetch_assoc()['c'] : 0;

            // Partidos con resultado cargado (para deshabilitar reset/regenerar)
            // bt.com.py no tiene columna 'resultado' - detectar por scores > 0
            $rcr = $mysqli2->query("SELECT COUNT(*) as c FROM _todosvstodos 
                WHERE evento=$idEvento AND categoria={$row['id_categoria']} 
                AND (rusultado_equipo1 > 0 OR resultado_equipo2 > 0)");
            $row['partidos_con_resultado'] = $rcr ? (int)$rcr->fetch_assoc()['c'] : 0;

            $cats[] = $row;
        }
    }
    resp([
        'success' => true,
        'categorias' => $cats,
        'evento' => $evData,
        'resumen' => [
            'total_categorias' => count($cats),
            'total_parejas' => $totalParejas,
            'sorteos_listos' => $sorteosListos,
            'total_partidos' => $totalPartidos,
        ]
    ]);
}

// ══════════════════════════════════════════════════════════════════
// ACTION: inscripciones — Equipos inscritos
// ══════════════════════════════════════════════════════════════════
if ($action === 'inscripciones') {
    $idEvento = intGet('evento');
    $idCat    = intGet('categoria');
    $limit    = intGet('limit', 200);

    $buscar   = strGet('buscar');

    $where = ["i.estado <> 'bloqueado'", "CAST(i.ci AS UNSIGNED) < CAST(i.ci_dupla AS UNSIGNED)"];
    if ($idEvento) $where[] = "i.id_evento = $idEvento";
    if ($idCat)    $where[] = "i.id_categoria = $idCat";
    if ($buscar) {
        $b = $mysqli2->real_escape_string($buscar);
        $where[] = "(u1.nombre LIKE '%$b%' OR u1.apellido LIKE '%$b%' OR u2.nombre LIKE '%$b%' OR u2.apellido LIKE '%$b%' OR i.ci LIKE '%$b%' OR i.ci_dupla LIKE '%$b%')";
    }
    $whereStr = 'WHERE ' . implode(' AND ', $where);

    $sql = "SELECT i.id, i.id_evento, i.id_categoria, i.ci as ci1_a, i.ci_dupla as ci1_b,
                   i.estado, i.obs,
                   u1.nombre as nombre1, u1.apellido as apellido1,
                   u2.nombre as nombre2, u2.apellido as apellido2,
                   c.categoria as cat_nombre,
                   ev.evento as evento_nombre
            FROM _p_incripciones i
            LEFT JOIN _p_usuarios u1 ON u1.ci = i.ci
            LEFT JOIN _p_usuarios u2 ON u2.ci = i.ci_dupla
            LEFT JOIN _p_categorias c ON c.id = i.id_categoria
            LEFT JOIN _p_eventos ev ON ev.id = i.id_evento
            $whereStr
            ORDER BY i.id DESC
            LIMIT $limit";
    $r = $mysqli2->query($sql);
    $inscr = [];
    if ($r) while ($row = $r->fetch_assoc()) $inscr[] = $row;

    resp(['success' => true, 'inscripciones' => $inscr, 'total' => count($inscr)]);
}

// ══════════════════════════════════════════════════════════════════
// ACTION: resultados — Partidos de _todosvstodos
// ══════════════════════════════════════════════════════════════════
if ($action === 'resultados') {
    $idEvento = intGet('evento');
    $idCat    = intGet('categoria');
    $grupoParam = isset($_GET['grupo']) ? (int)$_GET['grupo'] : -1;

    if (!$idEvento || !$idCat) respErr('Falta evento o categoria');

    $where = "WHERE t.evento=$idEvento AND t.categoria=$idCat";
    if ($grupoParam >= 0) $where .= " AND t.grupo=$grupoParam";

    $sql = "SELECT t.id, t.grupo, t.partido_nro, t.ci1_a, t.ci1_b, t.ci2_a, t.ci2_b,
                   t.rusultado_equipo1, t.resultado_equipo2,
                   t.resultado2_equipo1, t.resultado2_equipo2,
                   t.resultado3_equipo1, t.resultado3_equipo2,
                   t.tipo_referencia,
                   t.ref_etiqueta1, t.ref_etiqueta2,
                   t.ref_tipo_regustado1, t.ref_tipo_regustado2,
                   t.fecha_resultado,
                   t.en_juego,
                   g.id_etiqueta as grupo_etiqueta
            FROM _todosvstodos t
            LEFT JOIN _p_grupos g ON g.id = t.grupo
            $where
            ORDER BY t.grupo ASC, t.partido_nro ASC
            LIMIT 200";
    $r = $mysqli2->query($sql);
    if (!$r) respErr('Error SQL: ' . $mysqli2->error . ' | SQL: ' . $sql);
    $partidos = [];
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            // Buscar nombres de jugadores
            if ($row['ci1_a'] > 0) {
                $ru = $mysqli2->query("SELECT nombre, apellido FROM _p_usuarios WHERE ci='{$row['ci1_a']}' LIMIT 1");
                $u = $ru ? $ru->fetch_assoc() : null;
                $row['eq1_j1'] = $u ? trim($u['nombre'] . ' ' . $u['apellido']) : $row['ci1_a'];
            } else { $row['eq1_j1'] = ''; }
            if ($row['ci1_b'] > 0) {
                $ru = $mysqli2->query("SELECT nombre, apellido FROM _p_usuarios WHERE ci='{$row['ci1_b']}' LIMIT 1");
                $u = $ru ? $ru->fetch_assoc() : null;
                $row['eq1_j2'] = $u ? trim($u['nombre'] . ' ' . $u['apellido']) : $row['ci1_b'];
            } else { $row['eq1_j2'] = ''; }
            if ($row['ci2_a'] > 0) {
                $ru = $mysqli2->query("SELECT nombre, apellido FROM _p_usuarios WHERE ci='{$row['ci2_a']}' LIMIT 1");
                $u = $ru ? $ru->fetch_assoc() : null;
                $row['eq2_j1'] = $u ? trim($u['nombre'] . ' ' . $u['apellido']) : $row['ci2_a'];
            } else { $row['eq2_j1'] = ''; }
            if ($row['ci2_b'] > 0) {
                $ru = $mysqli2->query("SELECT nombre, apellido FROM _p_usuarios WHERE ci='{$row['ci2_b']}' LIMIT 1");
                $u = $ru ? $ru->fetch_assoc() : null;
                $row['eq2_j2'] = $u ? trim($u['nombre'] . ' ' . $u['apellido']) : $row['ci2_b'];
            } else { $row['eq2_j2'] = ''; }

            // Etiquetas para eliminatorias
            if ($row['tipo_referencia'] === 'no' && $row['ref_etiqueta1'] > 0) {
                $rg = $mysqli2->query("SELECT grupo AS nombre FROM _p_grupos WHERE id={$row['ref_etiqueta1']} LIMIT 1");
                $row['ref_label1'] = $rg ? $rg->fetch_assoc()['nombre'] : '';
            }
            if ($row['tipo_referencia'] === 'no' && $row['ref_etiqueta2'] > 0) {
                $rg = $mysqli2->query("SELECT grupo AS nombre FROM _p_grupos WHERE id={$row['ref_etiqueta2']} LIMIT 1");
                $row['ref_label2'] = $rg ? $rg->fetch_assoc()['nombre'] : '';
            }

            $partidos[] = $row;
        }
    }

    // Agrupar por grupo
    $grupos = [];
    foreach ($partidos as $p) {
        $g = (int)$p['grupo'];
        if (!isset($grupos[$g])) $grupos[$g] = [];
        $grupos[$g][] = $p;
    }

    resp(['success' => true, 'partidos' => $partidos, 'grupos' => $grupos, 'total' => count($partidos)]);
}

// ══════════════════════════════════════════════════════════════════
// ACTION: guardar_resultado — Guardar resultado de un partido
// ══════════════════════════════════════════════════════════════════
if ($action === 'guardar_resultado') {
    $id   = intGet('id');
    $s1a  = intGet('s1a');
    $s1b  = intGet('s1b');
    $s2a  = intGet('s2a');
    $s2b  = intGet('s2b');
    $s3a  = intGet('s3a');
    $s3b  = intGet('s3b');

    if (!$id) respErr('Falta id del partido');

    $sql = "UPDATE _todosvstodos SET
              rusultado_equipo1 = $s1a, resultado_equipo2 = $s1b,
              resultado2_equipo1 = $s2a, resultado2_equipo2 = $s2b,
              resultado3_equipo1 = $s3a, resultado3_equipo2 = $s3b,
              fecha_resultado = CURDATE(),
              en_juego = 'no'
            WHERE id = $id";
    if (!$mysqli2->query($sql)) {
        respErr('Error BD: ' . $mysqli2->error);
    }

    // PROPAGACION AUTOMATICA DEL GANADOR AL SIGUIENTE PARTIDO
    $resP = $mysqli2->query("SELECT id, evento, categoria, grupo, partido_nro, ci1_a, ci1_b, ci2_a, ci2_b,
        rusultado_equipo1 as r11, resultado_equipo2 as r12,
        resultado2_equipo1 as r21, resultado2_equipo2 as r22,
        resultado3_equipo1 as r31, resultado3_equipo2 as r32,
        tipo_referencia FROM _todosvstodos WHERE id={$id}");
    $partido = $resP ? $resP->fetch_assoc() : null;
    $propagado = false;

    if ($partido) {
        $ev  = $partido['evento'];
        $cat = $partido['categoria'];
        $grp = $partido['grupo'];
        $nroP = $partido['partido_nro'];

        $idVirtual = getIdVirtualGrupo($mysqli2, $grp, $nroP);
        $idParaBuscar = ($idVirtual > 0) ? $idVirtual : $grp;

        resetarSlotsDestino($mysqli2, $ev, $cat, $idParaBuscar);
        if ($idVirtual > 0) resetarSlotsDestino($mysqli2, $ev, $cat, $grp);

        $sA = 0; $sB = 0;
        if ($partido['r11'] > 0 || $partido['r12'] > 0) { $partido['r11'] > $partido['r12'] ? $sA++ : $sB++; }
        if ($partido['r21'] > 0 || $partido['r22'] > 0) { $partido['r21'] > $partido['r22'] ? $sA++ : $sB++; }
        if ($partido['r31'] > 0 || $partido['r32'] > 0) { $partido['r31'] > $partido['r32'] ? $sA++ : $sB++; }

        if ($sA != $sB) {
            $ci_g_a = ($sA > $sB) ? $partido['ci1_a'] : $partido['ci2_a'];
            $ci_g_b = ($sA > $sB) ? $partido['ci1_b'] : $partido['ci2_b'];
            $ci_p_a = ($sA > $sB) ? $partido['ci2_a'] : $partido['ci1_a'];
            $ci_p_b = ($sA > $sB) ? $partido['ci2_b'] : $partido['ci1_b'];

            // Ganador -> slot ci1
            $r = $mysqli2->query("SELECT id FROM _todosvstodos WHERE evento={$ev} AND categoria={$cat} AND ref_etiqueta1={$idParaBuscar} AND ref_tipo_regustado1=1 AND (tipo_referencia='si' OR (tipo_referencia='no' AND ci1_a=0)) LIMIT 1");
            if ($r && $r->num_rows > 0) { $n = $r->fetch_assoc(); propagarJugador($mysqli2, $n['id'], 'ci1', $ci_g_a, $ci_g_b); $propagado = true; }

            // Ganador -> slot ci2
            $r = $mysqli2->query("SELECT id FROM _todosvstodos WHERE evento={$ev} AND categoria={$cat} AND ref_etiqueta2={$idParaBuscar} AND ref_tipo_regustado2=1 AND (tipo_referencia='si' OR (tipo_referencia='no' AND ci2_a=0)) LIMIT 1");
            if ($r && $r->num_rows > 0) { $n = $r->fetch_assoc(); propagarJugador($mysqli2, $n['id'], 'ci2', $ci_g_a, $ci_g_b); $propagado = true; }

            // Perdedor -> slot ci1 (repechaje)
            $r = $mysqli2->query("SELECT id FROM _todosvstodos WHERE evento={$ev} AND categoria={$cat} AND ref_etiqueta1={$idParaBuscar} AND ref_tipo_regustado1=2 AND (tipo_referencia='si' OR (tipo_referencia='no' AND ci1_a=0)) LIMIT 1");
            if ($r && $r->num_rows > 0) { $n = $r->fetch_assoc(); propagarJugador($mysqli2, $n['id'], 'ci1', $ci_p_a, $ci_p_b); $propagado = true; }

            // Perdedor -> slot ci2 (repechaje)
            $r = $mysqli2->query("SELECT id FROM _todosvstodos WHERE evento={$ev} AND categoria={$cat} AND ref_etiqueta2={$idParaBuscar} AND ref_tipo_regustado2=2 AND (tipo_referencia='si' OR (tipo_referencia='no' AND ci2_a=0)) LIMIT 1");
            if ($r && $r->num_rows > 0) { $n = $r->fetch_assoc(); propagarJugador($mysqli2, $n['id'], 'ci2', $ci_p_a, $ci_p_b); $propagado = true; }
        }
    }

    // Recalcular clasificacion y propagar grupo->eliminatoria
    if ($partido && $partido["grupo"] < 13) {
        @file_get_contents("http://bt.com.py/logica/cargar.auxiliar.v2-parte2.php?evento=" . (int)$ev);
    }

    resp(['success' => true, 'mensaje' => 'Resultado guardado' . ($propagado ? ' y propagado' : '')]);
}

// ══════════════════════════════════════════════════════════════════
// ==================================================================
// ACTION: toggle_en_juego - Marcar/desmarcar partido en juego
// ==================================================================
if ($action === 'toggle_en_juego') {
    $id = intGet('id');
    if (!$id) respErr('Falta id del partido');
    $r = $mysqli2->query("SELECT en_juego FROM _todosvstodos WHERE id=$id");
    if (!$r || $r->num_rows == 0) respErr('Partido no encontrado');
    $row = $r->fetch_assoc();
    $nuevo = ($row['en_juego'] === 'si') ? 'no' : 'si';
    if ($mysqli2->query("UPDATE _todosvstodos SET en_juego='{$nuevo}' WHERE id=$id")) {
        resp(['success' => true, 'en_juego' => $nuevo, 'mensaje' => $nuevo === 'si' ? 'Partido EN JUEGO' : 'Partido detenido']);
    } else {
        respErr('Error BD: ' . $mysqli2->error);
    }
}

// ACTION: ranking_count — Contar registros en _ranking por evento
// ══════════════════════════════════════════════════════════════════
if ($action === 'ranking_count') {
    $idEvento = intGet('evento');
    if (!$idEvento) respErr('Falta evento');
    $r = $mysqli2->query("SELECT COUNT(*) as c FROM _ranking WHERE evento=$idEvento");
    $total = $r ? (int)$r->fetch_assoc()['c'] : 0;
    resp(['success' => true, 'total' => $total]);
}

// ══════════════════════════════════════════════════════════════════
// ACTION: ranking — Ranking de jugadores desde _ranking
// Replica la lógica EXACTA de mostrar-ranking.php:
// Para cada jugador, recorre evento por evento y calcula:
//   - ptosMixto: puntos del padre (categoría mixta)
//   - ptosHijo:  si es Grupo Único → phAcum completo
//                si NO es GU       → max(0, phAcum - ppEvt)
//   - total = ptosMixto + ptosHijo
// ══════════════════════════════════════════════════════════════════
if ($action === 'ranking') {
    $search   = strGet('q');
    $idCat    = intGet('categoria', 0);
    $limit    = intGet('limit', 50);

    // Obtener circuito (default 1 = Circuito Hernandariense)
    $idcircuito = intGet('circuito', 1);

    // Cargar mapa de categorías (id → padre, nombre)
    $catMap = [];
    $rCats = $mysqli2->query("SELECT id_categoria, id_categoria_padre, categoria FROM v_p_categorias");
    if ($rCats) while ($rc = $rCats->fetch_assoc()) {
        $catMap[(int)$rc['id_categoria']] = [
            'padre' => (int)$rc['id_categoria_padre'],
            'nombre' => $rc['categoria']
        ];
    }

    // Determinar categorías hijo a procesar
    $catsHijo = [];
    foreach ($catMap as $cid => $info) {
        if ($info['padre'] > 0) {
            if ($idCat && $cid != $idCat) continue; // filtro de categoría
            $catsHijo[$cid] = $info['padre'];
        }
    }

    // Filtro de búsqueda
    $whereBusq = '';
    if ($search) {
        $q = $mysqli2->real_escape_string($search);
        $whereBusq = " AND CONCAT(u.nombre,' ',u.apellido) LIKE '%$q%'";
    }

    // Para cada categoría hijo, obtener jugadores únicos y calcular puntos evento por evento
    $jugadoresGlobal = []; // ci => { nombre, apellido, categorias[], ptosMixto, ptosHijo, total }

    foreach ($catsHijo as $acategoria => $padreCat) {
        // Obtener jugadores únicos de esta categoría en _ranking
        $sqlJug = "SELECT DISTINCT r.ci, u.nombre, u.apellido
                   FROM _ranking r
                   LEFT JOIN _p_usuarios u ON u.ci = r.ci
                   WHERE r.circuito = $idcircuito 
                     AND r.categoria = $acategoria
                     AND r.puntos > 0
                     $whereBusq";
        $rJug = $mysqli2->query($sqlJug);
        if (!$rJug) continue;

        while ($jug = $rJug->fetch_assoc()) {
            $ci = $jug['ci'];
            if (empty(trim($ci))) continue;

            $ptosMixtoReal = 0;
            $ptosHijoReal  = 0;

            // Recorrer eventos donde este jugador participó en esta categoría o su padre
            $sqlEvts = "SELECT DISTINCT i.id_evento
                        FROM _p_incripciones i
                        JOIN _p_eventos ev ON ev.id = i.id_evento
                        WHERE (i.ci = '$ci' OR i.ci_dupla = '$ci')
                          AND (i.id_categoria = $acategoria OR i.id_categoria = $padreCat)
                          AND ev.id_circuito = $idcircuito";
            $rEvts = $mysqli2->query($sqlEvts);
            if (!$rEvts) continue;

            while ($rowEvt = $rEvts->fetch_assoc()) {
                $evId = (int)$rowEvt['id_evento'];

                // ¿Es Grupo Único? (tiene etiquetas POS1-POS4)
                $resGU = $mysqli2->query("SELECT COUNT(*) as total 
                    FROM _relacion_etiquetas_eventos r
                    JOIN _p_etiquetas e ON r.id_etiqueta = e.id
                    WHERE r.id_evento = $evId 
                      AND r.id_categoria IN ($acategoria, $padreCat)
                      AND e.value IN ('POS1','POS2','POS3','POS4')");
                $esGU = ($resGU && $resGU->fetch_assoc()['total'] > 0);

                // Puntos del padre en este evento
                $resPP = $mysqli2->query("SELECT puntos FROM _ranking 
                    WHERE evento = $evId AND circuito = $idcircuito 
                    AND ci = '$ci' AND categoria = $padreCat");
                $ppEvt = ($r2 = ($resPP ? $resPP->fetch_assoc() : null)) ? abs($r2['puntos']) : 0;

                // Puntos del hijo en este evento
                $resPH = $mysqli2->query("SELECT puntos FROM _ranking 
                    WHERE evento = $evId AND circuito = $idcircuito 
                    AND ci = '$ci' AND categoria = $acategoria");
                $phAcum = ($r2 = ($resPH ? $resPH->fetch_assoc() : null)) ? abs($r2['puntos']) : 0;

                $ptosMixtoReal += $ppEvt;
                $ptosHijoReal  += $esGU ? $phAcum : max(0, $phAcum - $ppEvt);
            }

            $totalReal = $ptosMixtoReal + $ptosHijoReal;
            $catNombre = $catMap[$acategoria]['nombre'] ?? "Cat. $acategoria";

            // Acumular: si un jugador está en múltiples categorías hijo, 
            // cada una se suma por separado (como en el ranking público)
            if (!isset($jugadoresGlobal[$ci . '_' . $acategoria])) {
                $jugadoresGlobal[$ci . '_' . $acategoria] = [
                    'ci'        => $ci,
                    'nombre'    => $jug['nombre'] ?? '',
                    'apellido'  => $jug['apellido'] ?? '',
                    'categoria' => $catNombre,
                    'id_cat'    => $acategoria,
                    'ptosMixto' => $ptosMixtoReal,
                    'ptosHijo'  => $ptosHijoReal,
                    'puntos'    => $totalReal,
                ];
            }
        }
    }

    // Si NO hay filtro de categoría, agrupar por CI sumando todas las categorías
    $ranking = [];
    if ($idCat) {
        // Con filtro: una fila por jugador en esa categoría
        $ranking = array_values($jugadoresGlobal);
    } else {
        // Sin filtro: sumar puntos de todas las categorías por jugador
        $agrupado = [];
        foreach ($jugadoresGlobal as $j) {
            $ci = $j['ci'];
            if (!isset($agrupado[$ci])) {
                $agrupado[$ci] = [
                    'ci'        => $ci,
                    'nombre'    => $j['nombre'],
                    'apellido'  => $j['apellido'],
                    'categoria' => $j['categoria'],
                    'puntos'    => 0,
                ];
            } else {
                // Concatenar nombres de categorías
                if (strpos($agrupado[$ci]['categoria'], $j['categoria']) === false) {
                    $agrupado[$ci]['categoria'] .= ', ' . $j['categoria'];
                }
            }
            $agrupado[$ci]['puntos'] += $j['puntos'];
        }
        $ranking = array_values($agrupado);
    }

    // Ordenar por puntos desc
    usort($ranking, function($a, $b) { return $b['puntos'] <=> $a['puntos']; });

    // Aplicar limit
    $ranking = array_slice($ranking, 0, $limit);

    resp(['success' => true, 'ranking' => $ranking, 'total' => count($ranking)]);
}

// ══════════════════════════════════════════════════════════════════
// ACTION: plantillas — Lista de plantillas TVT
// ══════════════════════════════════════════════════════════════════
if ($action === 'plantillas') {
    $sql = "SELECT id, nombre, min_parejas, max_parejas, cantidad_grupos, grupos_de_3, grupos_de_4,
                   tiene_16vos, tiene_8vos, tiene_cuartos, tiene_semis, tiene_final, tiene_3er_puesto,
                   activo
            FROM _tvt_plantillas ORDER BY min_parejas ASC, cantidad_grupos ASC";
    $r = $mysqli2->query($sql);
    $plantillas = [];
    if ($r) while ($row = $r->fetch_assoc()) $plantillas[] = $row;

    resp(['success' => true, 'plantillas' => $plantillas]);
}

// ══════════════════════════════════════════════════════════════════
// ACTION: resetear — Eliminar partidos de una categoría
// ══════════════════════════════════════════════════════════════════
if ($action === 'resetear') {
    $idEvento = intGet('evento');
    $idCat    = intGet('categoria');
    if (!$idEvento || !$idCat) respErr('Falta evento o categoria');

    if ($mysqli2->query("DELETE FROM _todosvstodos WHERE evento=$idEvento AND categoria=$idCat")) {
        resp(['success' => true, 'eliminados' => $mysqli2->affected_rows]);
    } else {
        respErr('Error BD: ' . $mysqli2->error);
    }
}

// ══════════════════════════════════════════════════════════════════
// ACTION: chart_inscripciones — Datos para gráfico de inscripciones por evento
// ══════════════════════════════════════════════════════════════════
if ($action === 'chart_inscripciones') {
    $sql = "SELECT ev.evento as nombre, COUNT(e.id) as total
            FROM _equipos e
            JOIN _p_eventos ev ON ev.id = e.id_evento
            GROUP BY e.id_evento
            ORDER BY total DESC
            LIMIT 10";
    $r = $mysqli2->query($sql);
    $labels = []; $data = [];
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $labels[] = mb_substr($row['nombre'], 0, 25);
            $data[] = (int)$row['total'];
        }
    }
    resp(['success' => true, 'labels' => $labels, 'data' => $data]);
}

// ══════════════════════════════════════════════════════════════════
// ACTION: chart_categorias — Distribución por categoría
// ══════════════════════════════════════════════════════════════════
if ($action === 'chart_categorias') {
    $sql = "SELECT c.categoria as nombre, COUNT(e.id) as total
            FROM _equipos e
            JOIN _p_categorias c ON c.id = e.id_categoria
            GROUP BY e.id_categoria
            ORDER BY total DESC
            LIMIT 8";
    $r = $mysqli2->query($sql);
    $labels = []; $data = [];
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $labels[] = $row['nombre'] ?: 'Sin nombre';
            $data[] = (int)$row['total'];
        }
    }
    resp(['success' => true, 'labels' => $labels, 'data' => $data]);
}

// ══════════════════════════════════════════════════════════════════
// ACTION: plantilla — Buscar plantilla por cantidad de grupos + parejas
// Usada por tvt_admin_v2 para mostrar los cruces al seleccionar grupos
// ══════════════════════════════════════════════════════════════════
if ($action === 'plantilla') {
    $nGrupos  = intGet('grupos');
    $nParejas = intGet('parejas');
    if (!$nGrupos) respErr('Falta parámetro grupos');

    // Priorizar plantilla que encaje exactamente con las parejas;
    // si no hay exacta, tomar cualquiera con ese número de grupos
    $sql = "SELECT * FROM _tvt_plantillas
            WHERE activo = 1
              AND cantidad_grupos = {$nGrupos}
            ORDER BY
              CASE WHEN min_parejas <= {$nParejas} AND max_parejas >= {$nParejas} THEN 0 ELSE 1 END ASC,
              min_parejas ASC
            LIMIT 1";

    $r = $mysqli2->query($sql);
    if (!$r || $r->num_rows === 0) {
        resp(['success' => false, 'mensaje' => "No hay plantilla activa para {$nGrupos} grupos."]);
    }

    $p = $r->fetch_assoc();
    $cruces = json_decode($p['cruces_eliminatoria'], true) ?: [];

    resp([
        'success'   => true,
        'plantilla' => [
            'id'               => (int)$p['id'],
            'nombre'           => $p['nombre'],
            'min_parejas'      => (int)$p['min_parejas'],
            'max_parejas'      => (int)$p['max_parejas'],
            'cantidad_grupos'  => (int)$p['cantidad_grupos'],
            'grupos_de_3'      => (int)$p['grupos_de_3'],
            'grupos_de_4'      => (int)$p['grupos_de_4'],
            'tiene_16vos'      => (bool)$p['tiene_16vos'],
            'tiene_8vos'       => (bool)$p['tiene_8vos'],
            'tiene_cuartos'    => (bool)$p['tiene_cuartos'],
            'tiene_semis'      => (bool)$p['tiene_semis'],
            'tiene_final'      => (bool)$p['tiene_final'],
            'tiene_3er_puesto' => (bool)$p['tiene_3er_puesto'],
        ],
        'cruces' => $cruces,
    ]);
}

// ══════════════════════════════════════════════════════════════════
// ACTION: ciudades — Lista de ciudades para el formulario de evento
// ══════════════════════════════════════════════════════════════════
if ($action === 'ciudades') {
    $r = $mysqli2->query("SELECT id, nombre FROM ciudadespy ORDER BY nombre LIMIT 300");
    $ciudades = [];
    if ($r) while ($row = $r->fetch_assoc()) $ciudades[] = $row;
    resp(['success' => true, 'ciudades' => $ciudades]);
}

// ══════════════════════════════════════════════════════════════════
// ACTION: crear_evento — Insertar nuevo evento
// ══════════════════════════════════════════════════════════════════
if ($action === 'crear_evento') {
    $nombre    = $mysqli2->real_escape_string(strGet('evento'));
    $url       = $mysqli2->real_escape_string(strGet('url_amigable'));
    $estado    = $mysqli2->real_escape_string(strGet('estado', 'inactivo'));
    $prioridad = intGet('prioridad', 1);
    $circuito  = intGet('id_circuito', 1);
    $organizador = intGet('id_organizador', 1);
    $ciudad    = intGet('id_ciudad', 0);
    $tipo      = intGet('id_tipo_evento', 0);
    $descripcion    = $mysqli2->real_escape_string(strGet('descripcion'));
    $reglamentacion = $mysqli2->real_escape_string(strGet('reglamentacion'));
    $versionForm    = $mysqli2->real_escape_string(strGet('version_formulario', 'v2'));
    $urlFixture     = $mysqli2->real_escape_string(strGet('url_fixture', 'grafico-llaves'));
    $btnFixture     = $mysqli2->real_escape_string(strGet('boton_fixture', 'visible'));
    $btnInscr       = $mysqli2->real_escape_string(strGet('boton_inscripcion', 'si'));
    $cantInscr      = $mysqli2->real_escape_string(strGet('cantidad_inscriptos', 'si'));
    $btnLlaves      = $mysqli2->real_escape_string(strGet('boton_llaves', 'oculto'));
    $basesCond      = $mysqli2->real_escape_string(strGet('base_condiciones'));
    $fixturePub     = $mysqli2->real_escape_string(strGet('fixture_publicado', 'si'));
    $fecha          = $mysqli2->real_escape_string(strGet('fecha'));
    $fechaFin       = $mysqli2->real_escape_string(strGet('fecha_fin'));
    $fechaFinInscr  = $mysqli2->real_escape_string(strGet('fecha_fin_inscripcion'));
    $fechaFinPago   = $mysqli2->real_escape_string(strGet('fecha_fin_pago'));
    $costo1         = intGet('costo1', 0);
    $costo2         = intGet('costo2', 0);
    $emailInscr     = $mysqli2->real_escape_string(strGet('email_inscipcion', 'no'));
    $asuntoEmail    = $mysqli2->real_escape_string(strGet('asunto_email_inscripcion'));
    $textoEmail     = $mysqli2->real_escape_string(strGet('texto_email_inscipcion'));
    $flyer          = $mysqli2->real_escape_string(strGet('flyer'));

    if (!$nombre) respErr('El nombre del evento es obligatorio');
    if (!$url)    respErr('La URL amigable es obligatoria');
    if (!$fecha)  respErr('La fecha del evento es obligatoria');

    // Verificar URL amigable única
    $rUrl = $mysqli2->query("SELECT id FROM _p_eventos WHERE url_amigable='$url' LIMIT 1");
    if ($rUrl && $rUrl->num_rows > 0) respErr("La URL amigable '$url' ya existe. Usá otra.");

    $ciudadVal    = $ciudad > 0 ? $ciudad : 'NULL';
    $tipoVal      = $tipo > 0 ? $tipo : 'NULL';
    $fechaFinVal  = $fechaFin      ? "'$fechaFin'"      : 'NULL';
    $fechaFinInVal= $fechaFinInscr ? "'$fechaFinInscr'" : 'NULL';
    $fechaFinPVal = $fechaFinPago  ? "'$fechaFinPago'"  : 'NULL';
    $baseVal      = $basesCond     ? "'$basesCond'"     : 'NULL';

    $sql = "INSERT INTO _p_eventos 
        (evento, url_amigable, estado, prioridad, id_circuito, id_organizador, id_ciudad,
         id_tipo_evento, descripcion, reglamentacion, version_formulario, url_fixture,
         boton_fixture, boton_inscripcion, cantidad_inscriptos, boton_llaves,
         base_condiciones, fixture_publicado, fecha, fecha_fin,
         fecha_fin_inscripcion, fecha_fin_pago, costo1, costo2,
         email_inscipcion, asunto_email_inscripcion, texto_email_inscipcion,
         flyer, con_resultado, lista, pos_gral)
        VALUES
        ('$nombre','$url','$estado',$prioridad,$circuito,$organizador,$ciudadVal,
         $tipoVal,'$descripcion','$reglamentacion','$versionForm','$urlFixture',
         '$btnFixture','$btnInscr','$cantInscr','$btnLlaves',
         $baseVal,'$fixturePub','$fecha',$fechaFinVal,
         $fechaFinInVal,$fechaFinPVal,$costo1,$costo2,
         '$emailInscr','$asuntoEmail','$textoEmail',
         '$flyer','no','si','no')";

    if ($mysqli2->query($sql)) {
        $newId = $mysqli2->insert_id;
        resp(['success' => true, 'id' => $newId, 'mensaje' => "Evento '$nombre' creado con ID $newId"]);
    } else {
        respErr('Error BD: ' . $mysqli2->error);
    }
}

// ══════════════════════════════════════════════════════════════════
// ACTION: get_evento — Traer datos de un evento para edición
// ══════════════════════════════════════════════════════════════════
if ($action === 'get_evento') {
    $id = intGet('id');
    if (!$id) respErr('Falta id');
    $r = $mysqli2->query("SELECT * FROM _p_eventos WHERE id=$id LIMIT 1");
    if (!$r || $r->num_rows === 0) respErr("Evento $id no encontrado");
    $ev = $r->fetch_assoc();
    resp(['success' => true, 'evento' => $ev]);
}

// ══════════════════════════════════════════════════════════════════
// ACTION: editar_evento — Actualizar evento existente
// ══════════════════════════════════════════════════════════════════
if ($action === 'editar_evento') {
    $id        = intGet('id');
    if (!$id) respErr('Falta id del evento');

    $nombre    = $mysqli2->real_escape_string(strGet('evento'));
    $url       = $mysqli2->real_escape_string(strGet('url_amigable'));
    $estado    = $mysqli2->real_escape_string(strGet('estado', 'inactivo'));
    $prioridad = intGet('prioridad', 1);
    $circuito  = intGet('id_circuito', 1);
    $organizador = intGet('id_organizador', 1);
    $ciudad    = intGet('id_ciudad', 0);
    $tipo      = intGet('id_tipo_evento', 0);
    $descripcion    = $mysqli2->real_escape_string(strGet('descripcion'));
    $reglamentacion = $mysqli2->real_escape_string(strGet('reglamentacion'));
    $versionForm    = $mysqli2->real_escape_string(strGet('version_formulario', 'v2'));
    $urlFixture     = $mysqli2->real_escape_string(strGet('url_fixture', 'grafico-llaves'));
    $btnFixture     = $mysqli2->real_escape_string(strGet('boton_fixture', 'visible'));
    $btnInscr       = $mysqli2->real_escape_string(strGet('boton_inscripcion', 'si'));
    $cantInscr      = $mysqli2->real_escape_string(strGet('cantidad_inscriptos', 'si'));
    $btnLlaves      = $mysqli2->real_escape_string(strGet('boton_llaves', 'oculto'));
    $basesCond      = $mysqli2->real_escape_string(strGet('base_condiciones'));
    $fixturePub     = $mysqli2->real_escape_string(strGet('fixture_publicado', 'si'));
    $fecha          = $mysqli2->real_escape_string(strGet('fecha'));
    $fechaFin       = $mysqli2->real_escape_string(strGet('fecha_fin'));
    $fechaFinInscr  = $mysqli2->real_escape_string(strGet('fecha_fin_inscripcion'));
    $fechaFinPago   = $mysqli2->real_escape_string(strGet('fecha_fin_pago'));
    $costo1         = intGet('costo1', 0);
    $costo2         = intGet('costo2', 0);
    $emailInscr     = $mysqli2->real_escape_string(strGet('email_inscipcion', 'no'));
    $asuntoEmail    = $mysqli2->real_escape_string(strGet('asunto_email_inscripcion'));
    $textoEmail     = $mysqli2->real_escape_string(strGet('texto_email_inscipcion'));
    $flyer          = $mysqli2->real_escape_string(strGet('flyer'));
    $flyer          = $mysqli2->real_escape_string(strGet('flyer'));

    if (!$nombre) respErr('El nombre del evento es obligatorio');
    if (!$url)    respErr('La URL amigable es obligatoria');
    if (!$fecha)  respErr('La fecha del evento es obligatoria');

    // Verificar URL amigable única (excluyendo el evento actual)
    $rUrl = $mysqli2->query("SELECT id FROM _p_eventos WHERE url_amigable='$url' AND id<>$id LIMIT 1");
    if ($rUrl && $rUrl->num_rows > 0) respErr("La URL amigable '$url' ya existe en otro evento.");

    $ciudadVal    = $ciudad > 0 ? $ciudad : 'NULL';
    $tipoVal      = $tipo > 0 ? $tipo : 'NULL';
    $fechaFinVal  = $fechaFin      ? "'$fechaFin'"      : 'NULL';
    $fechaFinInVal= $fechaFinInscr ? "'$fechaFinInscr'" : 'NULL';
    $fechaFinPVal = $fechaFinPago  ? "'$fechaFinPago'"  : 'NULL';
    $baseVal      = $basesCond     ? "'$basesCond'"     : 'NULL';

    $sql = "UPDATE _p_eventos SET
        evento='$nombre', url_amigable='$url', estado='$estado', prioridad=$prioridad,
        id_circuito=$circuito, id_organizador=$organizador, id_ciudad=$ciudadVal,
        id_tipo_evento=$tipoVal, descripcion='$descripcion', reglamentacion='$reglamentacion',
        version_formulario='$versionForm', url_fixture='$urlFixture',
        boton_fixture='$btnFixture', boton_inscripcion='$btnInscr',
        cantidad_inscriptos='$cantInscr', boton_llaves='$btnLlaves',
        base_condiciones=$baseVal, fixture_publicado='$fixturePub',
        fecha='$fecha', fecha_fin=$fechaFinVal,
        fecha_fin_inscripcion=$fechaFinInVal, fecha_fin_pago=$fechaFinPVal,
        costo1=$costo1, costo2=$costo2,
        email_inscipcion='$emailInscr', asunto_email_inscripcion='$asuntoEmail',
        texto_email_inscipcion='$textoEmail',
        flyer='$flyer'
        WHERE id=$id";

    if ($mysqli2->query($sql)) {
        resp(['success' => true, 'id' => $id, 'mensaje' => "Evento '$nombre' actualizado correctamente"]);
    } else {
        respErr('Error BD: ' . $mysqli2->error);
    }
}

// ══════════════════════════════════════════════════════════════════
// ACTION: editar_inscripcion — Actualizar pareja en _equipos y _p_incripciones
// ══════════════════════════════════════════════════════════════════
if ($action === 'editar_inscripcion') {
    $id      = intGet('id');
    $ci1     = $mysqli2->real_escape_string(strGet('ci1'));
    $ci2     = $mysqli2->real_escape_string(strGet('ci2'));
    $estado  = $mysqli2->real_escape_string(strGet('estado', 'preinscripcion'));
    $idCat   = intGet('id_categoria');
    $obs     = $mysqli2->real_escape_string(strGet('obs'));
    // CIs originales enviados desde el modal
    $origCI1 = $mysqli2->real_escape_string(strGet('orig_ci1'));
    $origCI2 = $mysqli2->real_escape_string(strGet('orig_ci2'));

    if (!$id)  respErr('Falta id de inscripción');
    if (!$ci1) respErr('Falta CI jugador 1');
    if (!$ci2) respErr('Falta CI jugador 2');

    // ── Paso 0: Determinar evento, categoría y recopilar TODOS los CIs viejos posibles ──
    $idEvento = 0; $oldCat = 0;
    $oldCIs = []; // Pares de CIs viejos a buscar en _todosvstodos

    // Desde _equipos por id
    $rEq = $mysqli2->query("SELECT * FROM _equipos WHERE id=$id LIMIT 1");
    if ($rEq && $rEq->num_rows > 0) {
        $eq = $rEq->fetch_assoc();
        $idEvento = $eq['id_evento'];
        $oldCat   = $eq['id_categoria'];
        $oldCIs[] = [$eq['ci1_a'], $eq['ci1_b']];
    }

    // Desde _p_incripciones por id
    $rInsc = $mysqli2->query("SELECT * FROM _p_incripciones WHERE id=$id LIMIT 1");
    if ($rInsc && $rInsc->num_rows > 0) {
        $insc = $rInsc->fetch_assoc();
        if (!$idEvento) { $idEvento = $insc['id_evento']; $oldCat = $insc['id_categoria']; }
        $oldCIs[] = [$insc['ci'], $insc['ci_dupla']];
    }

    // orig_ci del frontend
    if ($origCI1 && $origCI2) {
        $oldCIs[] = [$origCI1, $origCI2];
    }

    if (!$idEvento) respErr("No se encontró inscripción con id=$id");
    $newCat = $idCat > 0 ? $idCat : $oldCat;

    // También buscar en _equipos por CIs originales (puede haber otro id)
    foreach ($oldCIs as $par) {
        $a = $par[0]; $b = $par[1];
        $rEqCi = $mysqli2->query("SELECT ci1_a, ci1_b FROM _equipos 
            WHERE id_evento=$idEvento AND id_categoria=$oldCat 
            AND ((ci1_a='$a' AND ci1_b='$b') OR (ci1_a='$b' AND ci1_b='$a')) LIMIT 1");
        if ($rEqCi && $rEqCi->num_rows > 0) {
            $eqCi = $rEqCi->fetch_assoc();
            $oldCIs[] = [$eqCi['ci1_a'], $eqCi['ci1_b']];
        }
    }

    // Deduplicar pares
    $uniquePairs = [];
    foreach ($oldCIs as $par) {
        $key = min($par[0],$par[1]).'_'.max($par[0],$par[1]);
        if (!isset($uniquePairs[$key])) $uniquePairs[$key] = $par;
    }

    $msgs = [];

    // ── Paso 1: Actualizar _p_incripciones ──
    $updatedInsc = false;
    foreach ($uniquePairs as $par) {
        $a = $par[0]; $b = $par[1];
        // A→B
        $mysqli2->query("UPDATE _p_incripciones SET 
            ci='$ci1', ci_dupla='$ci2', id_categoria=$newCat, estado='$estado', obs='$obs'
            WHERE id_evento=$idEvento AND ci='$a' AND ci_dupla='$b' AND id_categoria=$oldCat LIMIT 1");
        if ($mysqli2->affected_rows > 0) $updatedInsc = true;
        // B→A
        $mysqli2->query("UPDATE _p_incripciones SET 
            ci='$ci2', ci_dupla='$ci1', id_categoria=$newCat, estado='$estado', obs='$obs'
            WHERE id_evento=$idEvento AND ci='$b' AND ci_dupla='$a' AND id_categoria=$oldCat LIMIT 1");
        if ($mysqli2->affected_rows > 0) $updatedInsc = true;
    }
    // Fallback por id directo
    if (!$updatedInsc) {
        $mysqli2->query("UPDATE _p_incripciones SET 
            ci='$ci1', ci_dupla='$ci2', id_categoria=$newCat, estado='$estado', obs='$obs' WHERE id=$id");
    }
    $msgs[] = '_p_incripciones';

    // ── Paso 2: Actualizar _equipos ──
    $updatedEq = false;
    foreach ($uniquePairs as $par) {
        $a = $par[0]; $b = $par[1];
        $mysqli2->query("UPDATE _equipos SET ci1_a='$ci1', ci1_b='$ci2', id_categoria=$newCat 
            WHERE id_evento=$idEvento AND id_categoria=$oldCat 
            AND ((ci1_a='$a' AND ci1_b='$b') OR (ci1_a='$b' AND ci1_b='$a')) LIMIT 1");
        if ($mysqli2->affected_rows > 0) { $updatedEq = true; break; }
    }
    if (!$updatedEq) {
        $mysqli2->query("UPDATE _equipos SET ci1_a='$ci1', ci1_b='$ci2', id_categoria=$newCat WHERE id=$id");
        if ($mysqli2->affected_rows > 0) $updatedEq = true;
    }
    if ($updatedEq) $msgs[] = '_equipos';

    // ── Paso 3: Actualizar _todosvstodos ──
    $tvtUpdated = 0;
    foreach ($uniquePairs as $par) {
        $a = $par[0]; $b = $par[1];
        // No actualizar si los CIs ya son los nuevos
        if (($a == $ci1 && $b == $ci2) || ($a == $ci2 && $b == $ci1)) continue;

        // Pareja como equipo local (ci1_a, ci1_b)
        $mysqli2->query("UPDATE _todosvstodos SET ci1_a='$ci1', ci1_b='$ci2' 
            WHERE evento=$idEvento AND categoria=$oldCat AND ci1_a='$a' AND ci1_b='$b'");
        $tvtUpdated += $mysqli2->affected_rows;
        $mysqli2->query("UPDATE _todosvstodos SET ci1_a='$ci1', ci1_b='$ci2' 
            WHERE evento=$idEvento AND categoria=$oldCat AND ci1_a='$b' AND ci1_b='$a'");
        $tvtUpdated += $mysqli2->affected_rows;
        // Pareja como equipo visitante (ci2_a, ci2_b)
        $mysqli2->query("UPDATE _todosvstodos SET ci2_a='$ci1', ci2_b='$ci2' 
            WHERE evento=$idEvento AND categoria=$oldCat AND ci2_a='$a' AND ci2_b='$b'");
        $tvtUpdated += $mysqli2->affected_rows;
        $mysqli2->query("UPDATE _todosvstodos SET ci2_a='$ci1', ci2_b='$ci2' 
            WHERE evento=$idEvento AND categoria=$oldCat AND ci2_a='$b' AND ci2_b='$a'");
        $tvtUpdated += $mysqli2->affected_rows;
    }
    if ($tvtUpdated > 0) $msgs[] = "_todosvstodos($tvtUpdated partidos)";

    // ── Paso 4: Actualizar tabla_auxiliar (eliminatorias) ──
    foreach ($uniquePairs as $par) {
        $a = $par[0]; $b = $par[1];
        if (($a == $ci1 && $b == $ci2) || ($a == $ci2 && $b == $ci1)) continue;
        $mysqli2->query("UPDATE tabla_auxiliar SET ci1_a='$ci1' WHERE id_evento=$idEvento AND id_categoria=$oldCat AND ci1_a='$a'");
        $mysqli2->query("UPDATE tabla_auxiliar SET ci1_b='$ci1' WHERE id_evento=$idEvento AND id_categoria=$oldCat AND ci1_b='$a'");
        $mysqli2->query("UPDATE tabla_auxiliar SET ci1_a='$ci2' WHERE id_evento=$idEvento AND id_categoria=$oldCat AND ci1_a='$b'");
        $mysqli2->query("UPDATE tabla_auxiliar SET ci1_b='$ci2' WHERE id_evento=$idEvento AND id_categoria=$oldCat AND ci1_b='$b'");
    }

    resp(['success' => true, 'mensaje' => 'Actualizado: ' . implode(', ', $msgs)]);
}

// ══════════════════════════════════════════════════════════════════
// ACTION: eliminar_inscripcion
// ══════════════════════════════════════════════════════════════════
if ($action === 'eliminar_inscripcion') {
    $id = intGet('id');
    if (!$id) respErr('Falta id de inscripcion');

    $check = $mysqli2->query("SELECT id_evento, id_categoria, ci, ci_dupla FROM _p_incripciones WHERE id=$id");
    if (!$check || $check->num_rows === 0) respErr('Inscripcion no encontrada');
    $row = $check->fetch_assoc();
    $ev = $row['id_evento']; $cat = $row['id_categoria'];
    $c1 = $mysqli2->real_escape_string($row['ci']);
    $c2 = $mysqli2->real_escape_string($row['ci_dupla']);

    // Borrar inscripción y su espejo (dupla invertida)
    $mysqli2->query("DELETE FROM _p_incripciones WHERE id=$id");
    $mysqli2->query("DELETE FROM _p_incripciones WHERE id_evento=$ev AND id_categoria=$cat AND ci='$c2' AND ci_dupla='$c1'");

    // Limpiar tabla_auxiliar
    $mysqli2->query("DELETE FROM tabla_auxiliar WHERE id_evento=$ev AND id_categoria=$cat AND (ci1_a='$c1' OR ci1_b='$c1' OR ci1_a='$c2' OR ci1_b='$c2')");

    resp(['success' => true, 'msg' => 'Inscripcion eliminada']);
}

// ══════════════════════════════════════════════════════════════════
// ACTION: cats_evento — Categorías asignadas a un evento
// ══════════════════════════════════════════════════════════════════
if ($action === 'cats_evento') {
    $idEvento = intGet('evento');
    if (!$idEvento) respErr('Falta evento');
    $r = $mysqli2->query("SELECT rec.id as id_relacion, rec.id_categoria, rec.estado,
                   rec.cupo, rec.visualizar_en_llaves, rec.sexo,
                   rec.costo, rec.orden_visualizacion, rec.link_grupos,
                   c.categoria
            FROM _relacion_evento_categoria rec
            LEFT JOIN _p_categorias c ON c.id = rec.id_categoria
            WHERE rec.id_evento = $idEvento
            ORDER BY rec.orden_visualizacion ASC, c.categoria ASC");
    $cats = [];
    if ($r) while ($row = $r->fetch_assoc()) $cats[] = $row;
    resp(['success' => true, 'categorias' => $cats]);
}

// ══════════════════════════════════════════════════════════════════
// ACTION: todas_cats — Todas las categorías disponibles
// ══════════════════════════════════════════════════════════════════
if ($action === 'todas_cats') {
    $r = $mysqli2->query("SELECT id, categoria FROM _p_categorias 
                          WHERE id IN (3,4,5,8,9,10,18,19,20,21,22,23)
                          ORDER BY categoria ASC");
    $cats = [];
    if ($r) while ($row = $r->fetch_assoc()) $cats[] = $row;
    resp(['success' => true, 'categorias' => $cats]);
}

// ══════════════════════════════════════════════════════════════════
// ACTION: agregar_cat_evento — Asignar categoría a un evento
// ══════════════════════════════════════════════════════════════════
if ($action === 'agregar_cat_evento') {
    $idEvento = intGet('evento');
    $idCat    = intGet('categoria');
    if (!$idEvento || !$idCat) respErr('Faltan parámetros');
    $rChk = $mysqli2->query("SELECT id FROM _relacion_evento_categoria WHERE id_evento=$idEvento AND id_categoria=$idCat LIMIT 1");
    if ($rChk && $rChk->num_rows > 0) respErr('Esta categoría ya está asignada al evento');
    $estado  = $mysqli2->real_escape_string(strGet('estado', 'activo'));
    $cupo    = $mysqli2->real_escape_string(strGet('cupo', 'disponible'));
    $llaves  = $mysqli2->real_escape_string(strGet('visualizar_en_llaves', 'si'));
    $sexo    = $mysqli2->real_escape_string(strGet('sexo', ''));
    $costo   = intGet('costo', 0);
    $orden   = intGet('orden_visualizacion', 1);
    $link    = $mysqli2->real_escape_string(strGet('link_grupos', ''));
    $sexoVal = $sexo ? "'$sexo'" : 'NULL';
    $mysqli2->query("INSERT INTO _relacion_evento_categoria 
        (id_evento, id_categoria, estado, cupo, visualizar_en_llaves, sexo, costo, orden_visualizacion, link_grupos)
        VALUES ($idEvento, $idCat, '$estado', '$cupo', '$llaves', $sexoVal, $costo, $orden, '$link')");
    resp(['success' => true, 'mensaje' => 'Categoría agregada correctamente']);
}

// ══════════════════════════════════════════════════════════════════
// ACTION: actualizar_cat_evento — Actualizar campo de categoría en evento
// ══════════════════════════════════════════════════════════════════
if ($action === 'actualizar_cat_evento') {
    $idRel = intGet('id_relacion');
    $campo = strGet('campo');
    $valor = $mysqli2->real_escape_string(strGet('valor'));
    if (!$idRel || !$campo) respErr('Faltan parámetros');
    // Whitelist de campos permitidos
    $camposPermitidos = ['estado','cupo','visualizar_en_llaves','sexo','costo','orden_visualizacion','link_grupos'];
    if (!in_array($campo, $camposPermitidos)) respErr("Campo '$campo' no permitido");
    $mysqli2->query("UPDATE _relacion_evento_categoria SET `$campo`='$valor' WHERE id=$idRel LIMIT 1");
    resp(['success' => true]);
}

if ($action === 'quitar_cat_evento') {
    $idRel = intGet('id_relacion');
    if (!$idRel) respErr('Falta id_relacion');
    $mysqli2->query("DELETE FROM _relacion_evento_categoria WHERE id=$idRel LIMIT 1");
    resp(['success' => true, 'mensaje' => 'Categoría quitada correctamente']);
}

// ══════════════════════════════════════════════════════════════════
// ACTION: cats_con_inscriptos — Categorías con inscripciones reales
// ══════════════════════════════════════════════════════════════════
if ($action === 'cats_con_inscriptos') {
    $idEvento = intGet('evento');
    if (!$idEvento) respErr('Falta evento');
    $r = $mysqli2->query("SELECT i.id_categoria, c.categoria, FLOOR(COUNT(*)/2) as total
            FROM _p_incripciones i
            LEFT JOIN _p_categorias c ON c.id = i.id_categoria
            WHERE i.id_evento=$idEvento AND i.estado <> 'bloqueado'
            AND i.id_categoria > 0
            GROUP BY i.id_categoria
            HAVING total > 0
            ORDER BY c.categoria ASC");
    $cats = [];
    if ($r) while ($row = $r->fetch_assoc()) $cats[] = $row;
    resp(['success' => true, 'categorias' => $cats]);
}

// ══════════════════════════════════════════════════════════════════
// ACTION: jugadores — Listar jugadores con búsqueda y paginación
// ══════════════════════════════════════════════════════════════════
if ($action === 'jugadores') {
    $buscar = strGet('buscar');
    $page = intGet('page', 1);
    if ($page < 1) $page = 1;
    $limit = 20;
    $offset = ($page - 1) * $limit;
    $filtroEstado = strGet('estado');
    $filtroTipo = strGet('tipo');

    $where = "WHERE 1=1";
    $params = [];

    if ($buscar !== '') {
        $b = $mysqli2->real_escape_string($buscar);
        $where .= " AND (nombre LIKE '%{$b}%' OR apellido LIKE '%{$b}%' OR ci LIKE '%{$b}%' OR email LIKE '%{$b}%' OR cel LIKE '%{$b}%')";
    }
    if ($filtroEstado !== '') {
        $fe = $mysqli2->real_escape_string($filtroEstado);
        $where .= " AND estado = '{$fe}'";
    }
    if ($filtroTipo !== '') {
        $ft = $mysqli2->real_escape_string($filtroTipo);
        $where .= " AND tipo = '{$ft}'";
    }

    // Total para paginación
    $rCount = $mysqli2->query("SELECT COUNT(*) as total FROM _p_usuarios {$where}");
    $total = $rCount ? (int)$rCount->fetch_assoc()['total'] : 0;

    $sql = "SELECT id, ci, nombre, apellido, email, cel, sexo, estado, tipo, fecha_nacimiento, ciudad, imagen_usuario, created
            FROM _p_usuarios {$where}
            ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}";
    $r = $mysqli2->query($sql);
    $jugadores = [];
    if ($r) while ($row = $r->fetch_assoc()) $jugadores[] = $row;

    resp([
        'success' => true,
        'jugadores' => $jugadores,
        'total' => $total,
        'page' => $page,
        'pages' => ceil($total / $limit),
        'limit' => $limit
    ]);
}

// ══════════════════════════════════════════════════════════════════
// ACTION: get_jugador — Obtener un jugador por ID
// ══════════════════════════════════════════════════════════════════
if ($action === 'get_jugador') {
    $id = intGet('id');
    if (!$id) respErr('Falta ID');
    $r = $mysqli2->query("SELECT * FROM _p_usuarios WHERE id = {$id}");
    if (!$r || $r->num_rows == 0) respErr('Jugador no encontrado');
    resp(['success' => true, 'jugador' => $r->fetch_assoc()]);
}

// ══════════════════════════════════════════════════════════════════
// ACTION: editar_jugador — Actualizar datos de un jugador
// ══════════════════════════════════════════════════════════════════
if ($action === 'editar_jugador') {
    $id = intGet('id');
    if (!$id) respErr('Falta ID');

    $campos = ['nombre','apellido','ci','email','cel','sexo','estado','tipo','fecha_nacimiento','ciudad','nacionalidad','whatsapp','observacion'];
    $sets = [];
    foreach ($campos as $c) {
        if (isset($_GET[$c])) {
            $v = $mysqli2->real_escape_string(trim($_GET[$c]));
            $sets[] = "{$c} = '{$v}'";
        }
    }
    if (empty($sets)) respErr('No hay campos para actualizar');

    $sql = "UPDATE _p_usuarios SET " . implode(', ', $sets) . " WHERE id = {$id}";
    if ($mysqli2->query($sql)) {
        resp(['success' => true, 'message' => 'Jugador actualizado']);
    } else {
        respErr('Error al actualizar: ' . $mysqli2->error);
    }
}

// ══════════════════════════════════════════════════════════════════
// ACTION: crear_jugador — Registrar un nuevo jugador
// ══════════════════════════════════════════════════════════════════
if ($action === 'crear_jugador') {
    $nombre = $mysqli2->real_escape_string(trim(strGet('nombre')));
    $apellido = $mysqli2->real_escape_string(trim(strGet('apellido')));
    $ci = $mysqli2->real_escape_string(trim(strGet('ci')));
    if (!$nombre || !$apellido || !$ci) respErr('Nombre, Apellido y CI son obligatorios');

    $dup = $mysqli2->query("SELECT id FROM _p_usuarios WHERE ci = '{$ci}' LIMIT 1");
    if ($dup && $dup->num_rows > 0) respErr('Ya existe un jugador con CI ' . $ci);

    $campos = ['nombre','apellido','ci','email','cel','sexo','estado','tipo','fecha_nacimiento','ciudad','nacionalidad','whatsapp','observacion'];
    $cols = []; $vals = [];
    foreach ($campos as $c) {
        $v = trim(strGet($c));
        if ($v !== '') {
            $cols[] = $c;
            $vals[] = "'" . $mysqli2->real_escape_string($v) . "'";
        }
    }
    $sql = "INSERT INTO _p_usuarios (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")";
    if ($mysqli2->query($sql)) {
        resp(['success' => true, 'message' => 'Jugador creado', 'id' => $mysqli2->insert_id]);
    } else {
        respErr('Error al crear: ' . $mysqli2->error);
    }
}

// ══════════════════════════════════════════════════════════════════
// ACTION: buscar_jugador — Busca jugadores por nombre o CI (autocomplete)
// ══════════════════════════════════════════════════════════════════
if ($action === 'buscar_jugador') {
    $q = $mysqli2->real_escape_string(trim(strGet('q')));
    if (strlen($q) < 2) respErr('Mínimo 2 caracteres para buscar');

    $sql = "SELECT ci, nombre, apellido, cel, ciudad 
            FROM _p_usuarios 
            WHERE ci LIKE '%$q%' 
               OR nombre LIKE '%$q%' 
               OR apellido LIKE '%$q%'
               OR CONCAT(nombre,' ',apellido) LIKE '%$q%'
            ORDER BY apellido, nombre 
            LIMIT 15";
    $r = $mysqli2->query($sql);
    $jugadores = [];
    if ($r) while ($row = $r->fetch_assoc()) $jugadores[] = $row;

    resp(['success' => true, 'jugadores' => $jugadores, 'total' => count($jugadores)]);
}

// ══════════════════════════════════════════════════════════════════
// ACTION: inscriptos_categoria — Lista parejas de una categoría en un evento
// ══════════════════════════════════════════════════════════════════
if ($action === 'inscriptos_categoria') {
    $idEvento = intGet('evento');
    $idCat    = intGet('categoria');
    if (!$idEvento || !$idCat) respErr('Falta evento o categoria');

    // Traer de _p_incripciones (filtrando duplicados: ci < ci_dupla)
    // Incluye CIs de _equipos para saber qué hay realmente en el sorteo
    $sql = "SELECT i.id, i.ci as ci1, i.ci_dupla as ci2, i.estado, i.obs,
                   u1.nombre as nombre1, u1.apellido as apellido1,
                   u2.nombre as nombre2, u2.apellido as apellido2,
                   e.id as equipo_id, e.ci1_a as eq_ci1, e.ci1_b as eq_ci2
            FROM _p_incripciones i
            LEFT JOIN _p_usuarios u1 ON u1.ci = i.ci
            LEFT JOIN _p_usuarios u2 ON u2.ci = i.ci_dupla
            LEFT JOIN _equipos e ON e.id_evento = i.id_evento 
                AND e.id_categoria = i.id_categoria
                AND ((e.ci1_a = i.ci AND e.ci1_b = i.ci_dupla) OR (e.ci1_a = i.ci_dupla AND e.ci1_b = i.ci))
            WHERE i.id_evento = $idEvento 
              AND i.id_categoria = $idCat
              AND i.estado <> 'bloqueado'
              AND CAST(i.ci AS UNSIGNED) < CAST(i.ci_dupla AS UNSIGNED)
            ORDER BY u1.apellido, u1.nombre";
    $r = $mysqli2->query($sql);
    $parejas = [];
    if ($r) while ($row = $r->fetch_assoc()) $parejas[] = $row;

    resp(['success' => true, 'parejas' => $parejas, 'total' => count($parejas)]);
}

// ══════════════════════════════════════════════════════════════════
// ACTION: intercambiar_parejas — Swap 2 parejas entre grupos en _todosvstodos
// ══════════════════════════════════════════════════════════════════
if ($action === 'intercambiar_parejas') {
    $idEvento = intGet('evento');
    $idCat    = intGet('categoria');
    // Pareja A: ci1 + ci2
    $a1 = $mysqli2->real_escape_string(strGet('a_ci1'));
    $a2 = $mysqli2->real_escape_string(strGet('a_ci2'));
    // Pareja B: ci1 + ci2
    $b1 = $mysqli2->real_escape_string(strGet('b_ci1'));
    $b2 = $mysqli2->real_escape_string(strGet('b_ci2'));

    if (!$idEvento || !$idCat || !$a1 || !$a2 || !$b1 || !$b2) respErr('Faltan parámetros');

    // Usar un placeholder temporal para evitar colisiones durante el swap
    $tmp1 = '9999990001';
    $tmp2 = '9999990002';

    // Paso 1: Pareja A → temporal
    $mysqli2->query("UPDATE _todosvstodos SET ci1_a='$tmp1', ci1_b='$tmp2' WHERE evento=$idEvento AND categoria=$idCat AND ci1_a='$a1' AND ci1_b='$a2'");
    $mysqli2->query("UPDATE _todosvstodos SET ci2_a='$tmp1', ci2_b='$tmp2' WHERE evento=$idEvento AND categoria=$idCat AND ci2_a='$a1' AND ci2_b='$a2'");
    // Invertido
    $mysqli2->query("UPDATE _todosvstodos SET ci1_a='$tmp1', ci1_b='$tmp2' WHERE evento=$idEvento AND categoria=$idCat AND ci1_a='$a2' AND ci1_b='$a1'");
    $mysqli2->query("UPDATE _todosvstodos SET ci2_a='$tmp1', ci2_b='$tmp2' WHERE evento=$idEvento AND categoria=$idCat AND ci2_a='$a2' AND ci2_b='$a1'");

    // Paso 2: Pareja B → posición de A
    $mysqli2->query("UPDATE _todosvstodos SET ci1_a='$a1', ci1_b='$a2' WHERE evento=$idEvento AND categoria=$idCat AND ci1_a='$b1' AND ci1_b='$b2'");
    $mysqli2->query("UPDATE _todosvstodos SET ci2_a='$a1', ci2_b='$a2' WHERE evento=$idEvento AND categoria=$idCat AND ci2_a='$b1' AND ci2_b='$b2'");
    $mysqli2->query("UPDATE _todosvstodos SET ci1_a='$a1', ci1_b='$a2' WHERE evento=$idEvento AND categoria=$idCat AND ci1_a='$b2' AND ci1_b='$b1'");
    $mysqli2->query("UPDATE _todosvstodos SET ci2_a='$a1', ci2_b='$a2' WHERE evento=$idEvento AND categoria=$idCat AND ci2_a='$b2' AND ci2_b='$b1'");

    // Paso 3: Temporal → posición de B
    $mysqli2->query("UPDATE _todosvstodos SET ci1_a='$b1', ci1_b='$b2' WHERE evento=$idEvento AND categoria=$idCat AND ci1_a='$tmp1' AND ci1_b='$tmp2'");
    $mysqli2->query("UPDATE _todosvstodos SET ci2_a='$b1', ci2_b='$b2' WHERE evento=$idEvento AND categoria=$idCat AND ci2_a='$tmp1' AND ci2_b='$tmp2'");

    // También swap en _equipos (posicion/orden si existe)
    // No es estrictamente necesario pero mantiene consistencia

    resp(['success' => true, 'mensaje' => 'Parejas intercambiadas correctamente']);
}

// ═══ ADMIN CRUD ═══
if ($action === 'list_admins') {
    $r = $mysqli2->query("SELECT id, usuario, pase, tipo FROM _usuario_admin ORDER BY id ASC");
    $admins = [];
    while ($row = $r->fetch_assoc()) {
        $aid = $row['id'];
        $rEv = $mysqli2->query("SELECT ae.id_evento, e.evento FROM _admin_evento ae LEFT JOIN _p_eventos e ON e.id=ae.id_evento WHERE ae.id_admin=$aid ORDER BY e.evento");
        $row['eventos'] = [];
        while ($ev = $rEv->fetch_assoc()) $row['eventos'][] = $ev;
        $admins[] = $row;
    }
    resp(['success' => true, 'admins' => $admins]);
}

if ($action === 'get_admin') {
    $id = abs((int)strGet('id'));
    $r = $mysqli2->query("SELECT id, usuario, pase, tipo FROM _usuario_admin WHERE id=$id");
    $admin = $r ? $r->fetch_assoc() : null;
    if (!$admin) respErr('Admin no encontrado');
    resp(['success' => true, 'admin' => $admin]);
}

if ($action === 'create_admin') {
    $usuario = $mysqli2->real_escape_string(trim(strGet('usuario')));
    $pase = $mysqli2->real_escape_string(trim(strGet('pase')));
    $tipo = strGet('tipo') === 'superadmin' ? 'superadmin' : 'cliente';
    $menu = $tipo === 'superadmin' ? 'menu_bt' : 'menu_cliente';
    if (!$usuario || !$pase) respErr('Usuario y contraseña requeridos');
    $check = $mysqli2->query("SELECT id FROM _usuario_admin WHERE usuario='$usuario'");
    if ($check && $check->num_rows > 0) respErr('El usuario ya existe');
    $mysqli2->query("INSERT INTO _usuario_admin (usuario, pase, menu, tipo) VALUES ('$usuario','$pase','$menu','$tipo')");
    resp(['success' => true, 'id' => $mysqli2->insert_id]);
}

if ($action === 'update_admin') {
    $id = abs((int)strGet('id'));
    $usuario = $mysqli2->real_escape_string(trim(strGet('usuario')));
    $pase = $mysqli2->real_escape_string(trim(strGet('pase')));
    $tipo = strGet('tipo') === 'superadmin' ? 'superadmin' : 'cliente';
    $menu = $tipo === 'superadmin' ? 'menu_bt' : 'menu_cliente';
    if (!$usuario || !$pase) respErr('Usuario y contraseña requeridos');
    $mysqli2->query("UPDATE _usuario_admin SET usuario='$usuario', pase='$pase', menu='$menu', tipo='$tipo' WHERE id=$id");
    resp(['success' => true]);
}

if ($action === 'delete_admin') {
    $id = abs((int)strGet('id'));
    $mysqli2->query("DELETE FROM _admin_evento WHERE id_admin=$id");
    $mysqli2->query("DELETE FROM _usuario_admin WHERE id=$id");
    resp(['success' => true]);
}

if ($action === 'admin_eventos') {
    $adminId = abs((int)strGet('admin_id'));
    $rEv = $mysqli2->query("SELECT id, evento, fecha, estado FROM _p_eventos ORDER BY id DESC");
    $rAs = $mysqli2->query("SELECT id_evento FROM _admin_evento WHERE id_admin=$adminId");
    $asignados = [];
    while ($a = $rAs->fetch_assoc()) $asignados[] = (int)$a['id_evento'];
    $eventos = [];
    while ($ev = $rEv->fetch_assoc()) {
        $ev['asignado'] = in_array((int)$ev['id'], $asignados);
        $eventos[] = $ev;
    }
    resp(['success' => true, 'eventos' => $eventos]);
}

if ($action === 'asignar_evento') {
    $adminId = abs((int)strGet('admin_id'));
    $eventoId = abs((int)strGet('evento_id'));
    $mysqli2->query("INSERT IGNORE INTO _admin_evento (id_admin, id_evento) VALUES ($adminId, $eventoId)");
    resp(['success' => true]);
}

if ($action === 'desasignar_evento') {
    $adminId = abs((int)strGet('admin_id'));
    $eventoId = abs((int)strGet('evento_id'));
    $mysqli2->query("DELETE FROM _admin_evento WHERE id_admin=$adminId AND id_evento=$eventoId");
    resp(['success' => true]);
}

// ── PUNTAJES CRUD ──
if ($action === 'puntajes_evento') {
    $idEvento = abs((int)strGet('id_evento'));
    if (!$idEvento) respErr('Falta id_evento');

    $cats = [];
    $rc = $mysqli2->query("SELECT rec.id_categoria, c.categoria
        FROM _relacion_evento_categoria rec
        JOIN _p_categorias c ON c.id=rec.id_categoria
        WHERE rec.id_evento=$idEvento ORDER BY c.categoria");
    while ($r = $rc->fetch_assoc()) {
        $catId = (int)$r['id_categoria'];
        $cats[$catId] = ['id' => $catId, 'categoria' => $r['categoria'], 'puntajes' => []];
    }

    $rp = $mysqli2->query("SELECT id, id_etiqueta, id_categoria, puntos
        FROM _relacion_etiquetas_eventos
        WHERE id_evento=$idEvento ORDER BY id_categoria, id_etiqueta");
    while ($r = $rp->fetch_assoc()) {
        $catId = (int)$r['id_categoria'];
        if (isset($cats[$catId])) {
            $cats[$catId]['puntajes'][] = [
                'id' => (int)$r['id'],
                'id_etiqueta' => (int)$r['id_etiqueta'],
                'puntos' => (int)$r['puntos']
            ];
        }
    }

    $etiqs = [];
    $re = $mysqli2->query("SELECT id, etiqueta FROM _p_etiquetas ORDER BY id");
    while ($r = $re->fetch_assoc()) {
        $etiqs[] = ['id' => (int)$r['id'], 'etiqueta' => $r['etiqueta']];
    }

    resp(['categorias' => array_values($cats), 'etiquetas' => $etiqs]);
}

if ($action === 'guardar_puntajes_categoria') {
    $idEvento = abs((int)strGet('id_evento'));
    $idCategoria = abs((int)strGet('id_categoria'));
    $puntajes = json_decode(strGet('puntajes'), true);

    if (!$idEvento || !$idCategoria) respErr('Faltan parámetros');
    if (!is_array($puntajes)) respErr('Puntajes inválidos');

    $mysqli2->query("DELETE FROM _relacion_etiquetas_eventos
        WHERE id_evento=$idEvento AND id_categoria=$idCategoria");

    $count = 0;
    foreach ($puntajes as $p) {
        $etiq = abs((int)$p['id_etiqueta']);
        $pts = abs((int)$p['puntos']);
        if ($etiq > 0) {
            $mysqli2->query("INSERT INTO _relacion_etiquetas_eventos
                (id_etiqueta, id_evento, puntos, id_categoria)
                VALUES ($etiq, $idEvento, $pts, $idCategoria)");
            $count++;
        }
    }

    resp(['success' => true, 'count' => $count]);
}

if ($action === 'copiar_puntajes') {
    $origen = abs((int)strGet('evento_origen'));
    $destino = abs((int)strGet('evento_destino'));

    if (!$origen || !$destino) respErr('Faltan eventos origen/destino');
    if ($origen === $destino) respErr('Origen y destino son el mismo evento');

    $catsDest = [];
    $rc = $mysqli2->query("SELECT id_categoria FROM _relacion_evento_categoria WHERE id_evento=$destino");
    while ($r = $rc->fetch_assoc()) $catsDest[] = (int)$r['id_categoria'];

    $mysqli2->query("DELETE FROM _relacion_etiquetas_eventos WHERE id_evento=$destino");

    $count = 0;
    $ro = $mysqli2->query("SELECT id_etiqueta, puntos, id_categoria
        FROM _relacion_etiquetas_eventos WHERE id_evento=$origen");
    while ($r = $ro->fetch_assoc()) {
        $catId = (int)$r['id_categoria'];
        if (in_array($catId, $catsDest)) {
            $etiq = (int)$r['id_etiqueta'];
            $pts = (int)$r['puntos'];
            $mysqli2->query("INSERT INTO _relacion_etiquetas_eventos
                (id_etiqueta, id_evento, puntos, id_categoria)
                VALUES ($etiq, $destino, $pts, $catId)");
            $count++;
        }
    }

    resp(['success' => true, 'count' => $count]);
}

// ── Si ninguna acción coincidió ──
respErr("Acción '$action' no reconocida.");