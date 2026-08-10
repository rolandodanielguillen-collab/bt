<?php
/**
 * interclubes.functions.php — Cálculo de partidos, series y posiciones
 * Usado por interclubes_resultados.php (admin) y logica/grafico-interclubes.inc.php (público).
 *
 * Modelo (audios del 2026-08-01):
 * - Enfrentamiento (serie) club A vs club B por categoría: Partido 1 = dupla 1 vs dupla 1,
 *   Partido 2 = dupla 2 vs dupla 2 (tantos como min(duplas A, duplas B)).
 * - Cada partido: 2 sets + 3er set de desempate si empatan.
 * - Serie empatada tras los partidos regulares → partido de DESEMPATE con dupla mezclada.
 * - Posiciones: 1 pt por SERIE ganada en ambos criterios. Lo que cambia es el desempate
 *   (ver ic_criterio_viejo):
 *   · VIEJO (categorías que ya jugaron): saldo de games con el 3er partido valiendo ±1;
 *     confronto directo; último recurso sorteo.
 *   · NUEVO (2026-08-07): saldo de games COMPLETOS (el 3er partido suma su marcador
 *     real); luego partidos ganados y dif. de sets; último recurso sorteo.
 *     SIN confronto directo.
 */

/**
 * Categorías que arrancaron con el criterio viejo y lo mantienen hasta terminar
 * el torneo — cambiarlas a mitad de camino reescribiría partidos ya jugados.
 * [id_evento => [id_categoria, ...]]. Evento 15: D MASC(4), E MASC(5), D FEM(9), E FEM(10).
 * ponytail: lista explícita y no "la categoría tiene partidos" — así el criterio de una
 * categoría no cambia solo el día que se carga su primer resultado.
 */
const IC_CATS_CRITERIO_VIEJO = [15 => [4, 5, 9, 10]];

/**
 * Partidos regulares de una serie (+ desempate si quedan 1-1). Fijo en 2 porque el
 * plantel del reglamento es 2 parejas + 1 suplente por club y categoría (5 jugadores).
 * NO depende de cuántas parejas cargó cada club: un club con 1 pareja juega los 2
 * partidos igual, repitiendo jugadores si hace falta (2026-08-07).
 * ponytail: constante, no max_parejas — si alguna vez se juegan 3, es esta línea.
 */
const IC_SLOTS_SERIE = 2;

function ic_criterio_viejo(int $idEvento, int $idCat): bool {
    return in_array($idCat, IC_CATS_CRITERIO_VIEJO[$idEvento] ?? [], true);
}

/**
 * Ranking de clubes (2026-08-10): puntos por posición final en cada categoría.
 * [posición => puntos]; posición 0 = eliminado en fase de grupos = PARTICIPACIÓN.
 * Un evento puede pisar estos valores cargando su matriz en el admin
 * ("Puntajes por evento") — ver ic_puntos_evento().
 */
const IC_PUNTOS_POSICION = [1 => 100, 2 => 75, 3 => 60, 4 => 50, 0 => 30];

/** Etiqueta de _p_etiquetas equivalente a cada posición, para leer la matriz del admin. */
const IC_ETIQUETA_POSICION = [1 => 8, 2 => 6, 3 => 10, 4 => 4, 0 => 1]; // CAMPEONES/VICE/TERCER/SEMI/GRUPOS

/**
 * Posición final de cada club en UNA categoría de interclubes.
 * $clubes: [id_club => nombre] del sorteo · $llaves: [fase => ['clubA'=>,'clubB'=>]]
 * $partidos: filas de _ic_partidos de esa categoría (todas las fases).
 * Devuelve [id_club => posición] con 1..4 y 0 = participación, o [] si la final
 * todavía no está definida (la categoría no puntúa hasta que se decide).
 */
function ic_posiciones_categoria(array $clubes, array $llaves, array $partidos): array {
    $ganPerd = function (string $fase) use ($llaves, $partidos): array {
        if (!isset($llaves[$fase])) return [0, 0];
        $a = (int)$llaves[$fase]['clubA']; $b = (int)$llaves[$fase]['clubB'];
        $ms = array_values(array_filter($partidos, fn($m) => $m['fase'] === $fase));
        [, , $definida, $ganador] = ic_estado_serie($ms, $a, $b, IC_SLOTS_SERIE);
        return $definida ? [$ganador, $ganador === $a ? $b : $a] : [0, 0];
    };
    [$campeon, $vice] = $ganPerd('final');
    if (!$campeon) return [];
    $pos = [$campeon => 1, $vice => 2];
    [$tercero, $cuarto] = $ganPerd('tercer');
    if ($tercero) {
        $pos[$tercero] = 3; $pos[$cuarto] = 4;
    } else {
        // 3er puesto sin jugar: los dos perdedores de semis son 4tos, no participación
        foreach (['semi1', 'semi2'] as $f) foreach (['clubA', 'clubB'] as $k)
            if (isset($llaves[$f]) && !isset($pos[(int)$llaves[$f][$k]])) $pos[(int)$llaves[$f][$k]] = 4;
    }
    foreach (array_keys($clubes) as $idClub) if (!isset($pos[$idClub])) $pos[$idClub] = 0;
    return $pos;
}

/**
 * Matriz de puntos cargada en el admin para estos eventos, traducida a posiciones:
 * [id_evento][id_categoria][posición] => puntos. Lo que no esté cargado cae en
 * IC_PUNTOS_POSICION (ver ic_puntos_pos).
 */
function ic_puntos_matriz(mysqli $db, array $idEventos): array {
    if (!$idEventos) return [];
    $ids = implode(',', array_map('intval', $idEventos));
    $posDeEtiqueta = array_flip(IC_ETIQUETA_POSICION);
    $res = $db->query("SELECT id_evento, id_categoria, id_etiqueta, puntos
                         FROM _relacion_etiquetas_eventos WHERE id_evento IN ({$ids})");
    $out = [];
    while ($r = $res->fetch_assoc()) {
        $etq = (int)$r['id_etiqueta'];
        if (!isset($posDeEtiqueta[$etq])) continue;
        $out[(int)$r['id_evento']][(int)$r['id_categoria']][$posDeEtiqueta[$etq]] = (int)$r['puntos'];
    }
    return $out;
}

function ic_puntos_pos(array $matriz, int $idEvento, int $idCat, int $pos): int {
    return $matriz[$idEvento][$idCat][$pos] ?? (IC_PUNTOS_POSICION[$pos] ?? 0);
}

/**
 * Identidad de un club ENTRE eventos. `_p_clubes` es por evento, así que el mismo
 * club en el evento siguiente es otra fila: el ranking histórico los junta por
 * este nombre normalizado — mayúsculas, sin acentos, sin puntuación
 * ("MOES - YOYI" = "MOES-YOYI") y espacios colapsados.
 * ponytail: NO adivina abreviaciones ("LUJINI" ≠ "LUJINI BEACH TENNIS"); eso lo
 * evita la lista del formulario de registro, y si igual pasa, el superadmin renombra.
 */
function ic_clave_club($nombre): string {
    if (!mb_check_encoding((string)$nombre, 'UTF-8')) $nombre = mb_convert_encoding($nombre, 'UTF-8', 'ISO-8859-1');
    $n = mb_strtoupper(trim((string)$nombre), 'UTF-8');
    $n = strtr($n, ['Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U','Ñ'=>'N','À'=>'A','Ã'=>'A','Ê'=>'E','Ô'=>'O','Ç'=>'C']);
    $n = preg_replace('/[^\p{L}\p{N} ]+/u', ' ', $n);   // puntuación fuera
    return trim(preg_replace('/\s+/', ' ', $n));
}

/**
 * Clubes que ya jugaron OTROS interclubes: [clave => nombre canónico] (el más
 * reciente gana). Alimenta la lista del formulario de registro y el reuso de
 * nombre, para que el ranking histórico no se parta en dos filas por un tipeo.
 */
function ic_clubes_previos(mysqli $db, int $idEventoActual): array {
    $st = $db->prepare(
        "SELECT c.nombre FROM _p_clubes c
           JOIN _p_eventos e ON e.id = c.id_evento
          WHERE e.id_tipo_evento = 5 AND c.id_evento <> ?
            AND e.estado IN ('activo','registro','culminado')
          ORDER BY e.fecha ASC, c.id ASC");
    $st->bind_param('i', $idEventoActual);
    $st->execute();
    $res = $st->get_result();
    $out = [];
    while ($r = $res->fetch_assoc()) $out[ic_clave_club($r['nombre'])] = $r['nombre'];
    return $out;
}

/**
 * Nombre con el que este club ya está registrado en interclubes anteriores, o
 * null si es nuevo. Se guarda ESE nombre y no el tipeado: "lujini beach tennis"
 * y "Lujini Beach Tennis" son el mismo club en el ranking.
 * ponytail: solo unifica lo que normaliza igual — las abreviaciones ("LUJINI")
 * las evita la lista del formulario; si igual pasa una, el superadmin renombra.
 */
function ic_nombre_canonico(mysqli $db, int $idEventoActual, string $nombre): ?string {
    return ic_clubes_previos($db, $idEventoActual)[ic_clave_club($nombre)] ?? null;
}

// Nombre para mostrar: primer nombre + primer apellido (pedido 2026-08-05).
// $t: alias de tabla con punto ('u1.') o '' si la query no usa alias.
function ic_sql_nombre_corto(string $t = ''): string {
    return "TRIM(CONCAT(SUBSTRING_INDEX(TRIM(COALESCE({$t}nombre,'')),' ',1),' '," .
           "SUBSTRING_INDEX(TRIM(COALESCE({$t}apellido,'')),' ',1)))";
}

// Sets ganados por cada lado en un partido (ignora sets no jugados 0-0)
function ic_sets_partido(array $m): array {
    $s1 = $s2 = $g1 = $g2 = 0;
    foreach ([['s1c1', 's1c2'], ['s2c1', 's2c2'], ['s3c1', 's3c2']] as [$a, $b]) {
        $va = (int)$m[$a]; $vb = (int)$m[$b];
        if ($va === 0 && $vb === 0) continue;
        $g1 += $va; $g2 += $vb;
        if ($va > $vb) $s1++;
        elseif ($vb > $va) $s2++;
    }
    return [$s1, $s2, $g1, $g2];
}

// Ganador del partido: quien lidera en sets ganados. Cargar el 1er set ya
// define el partido (pedido 2026-08-04); solo queda "en curso" si los sets
// cargados están empatados (ej. 1-1 esperando el 3ro).
function ic_ganador_partido(array $m): int {
    [$s1, $s2] = ic_sets_partido($m);
    if ($s1 === $s2) return 0;
    return $s1 > $s2 ? 1 : 2;
}

// Duplas de un club en una categoría, en orden de inscripción (Dupla 1, 2, …)
function ic_duplas(mysqli $db, int $idEvento, int $idCat, int $idClub): array {
    $st = $db->prepare(
        "SELECT i.ci, i.ci_dupla,
                " . ic_sql_nombre_corto('u1.') . " AS j1,
                " . ic_sql_nombre_corto('u2.') . " AS j2
           FROM _p_incripciones i
           LEFT JOIN _p_usuarios u1 ON TRIM(u1.ci) = TRIM(i.ci)
           LEFT JOIN _p_usuarios u2 ON TRIM(u2.ci) = TRIM(i.ci_dupla)
          WHERE i.id_evento = ? AND i.id_categoria = ? AND i.id_club = ?
            AND i.estado <> 'bloqueado'
            AND CAST(i.ci AS UNSIGNED) < CAST(i.ci_dupla AS UNSIGNED)
          ORDER BY i.id ASC");
    $st->bind_param('iii', $idEvento, $idCat, $idClub);
    $st->execute();
    $res = $st->get_result();
    $duplas = [];
    while ($r = $res->fetch_assoc()) $duplas[] = $r;
    return $duplas;
}

/**
 * Estado de una serie (enfrentamiento entre 2 clubes).
 * $partidos: filas de _ic_partidos de ESTE cruce (ambos órdenes de clubes ya normalizados
 * por el caller con club1/club2 según la fila). $slots = cantidad de partidos regulares.
 * Devuelve [winsA, winsB, definida(bool), ganador(idClub|0), necesitaDesempate(bool)]
 */
function ic_estado_serie(array $partidos, int $clubA, int $clubB, int $slots): array {
    $winsA = $winsB = $regulares = 0;
    $hayDesempate = false;
    foreach ($partidos as $m) {
        $g = ic_ganador_partido($m);
        if ($g === 0) continue;
        $ganadorClub = $g === 1 ? (int)$m['club1'] : (int)$m['club2'];
        if ($ganadorClub === $clubA) $winsA++;
        elseif ($ganadorClub === $clubB) $winsB++;
        if ((int)$m['es_desempate'] === 1) $hayDesempate = true;
        else $regulares++;
    }
    $necesitaDesempate = ($regulares >= $slots && $slots > 0 && $winsA === $winsB && !$hayDesempate);
    $definida = ($regulares >= $slots && $slots > 0 && $winsA !== $winsB);
    $ganador  = $definida ? ($winsA > $winsB ? $clubA : $clubB) : 0;
    return [$winsA, $winsB, $definida, $ganador, $necesitaDesempate];
}

// ¿Esta fase de llaves ya tiene algún games cargado? Una fase en juego no se reescribe.
function ic_fase_con_juego(mysqli $db, int $ev, int $cat, string $fase): bool {
    $st = $db->prepare(
        "SELECT COUNT(*) c FROM _ic_partidos
          WHERE id_evento=? AND id_categoria=? AND fase=?
            AND (s1c1+s1c2+s2c1+s2c2+s3c1+s3c2) > 0");
    $st->bind_param('iis', $ev, $cat, $fase);
    $st->execute();
    return (int)$st->get_result()->fetch_assoc()['c'] > 0;
}

/**
 * Crea o RE-SINCRONIZA una llave, y devuelve el par [clubA, clubB] vigente.
 * El INSERT IGNORE solo no alcanza: si un resultado de la fase anterior se CORRIGE
 * después de generarla, la llave queda con los clubes viejos (pasó el 2026-08-08 en
 * ev15 cat 8 — se editaron 3 marcadores del grupo 2 minutos después de crearse las
 * semis y el bracket nunca se enteró). Reescribe solo mientras esa fase no tenga
 * games cargados; si ya se está jugando, la deja como está.
 */
function ic_upsert_llave(mysqli $db, int $ev, int $cat, string $fase, int $ca, int $cb, ?array $actual): array {
    if (!$actual) {
        $st = $db->prepare("INSERT IGNORE INTO _ic_llaves (id_evento, id_categoria, fase, clubA, clubB) VALUES (?,?,?,?,?)");
        $st->bind_param('iisii', $ev, $cat, $fase, $ca, $cb);
        $st->execute();
        return [$ca, $cb];
    }
    if ((int)$actual['clubA'] === $ca && (int)$actual['clubB'] === $cb) return [$ca, $cb];
    if (ic_fase_con_juego($db, $ev, $cat, $fase)) return [(int)$actual['clubA'], (int)$actual['clubB']];
    $st = $db->prepare("UPDATE _ic_llaves SET clubA=?, clubB=? WHERE id_evento=? AND id_categoria=? AND fase=?");
    $st->bind_param('iiiis', $ca, $cb, $ev, $cat, $fase);
    $st->execute();
    // Los partidos de esa fase quedaron con los clubes viejos y sin jugar: se rehacen.
    $st = $db->prepare("DELETE FROM _ic_partidos WHERE id_evento=? AND id_categoria=? AND fase=?");
    $st->bind_param('iis', $ev, $cat, $fase);
    $st->execute();
    return [$ca, $cb];
}

/**
 * Avance AUTOMÁTICO de llaves: si ambos grupos completaron sus series → semis
 * (1°G1 vs 2°G2, 1°G2 vs 2°G1). Si las semis están definidas → final y 3er puesto.
 * Se vuelve a calcular en cada guardado y RE-SINCRONIZA la fase siguiente mientras
 * esa fase no tenga games cargados, así una corrección de marcador se propaga.
 */
function ic_autogenerar_llaves(mysqli $db, int $idEvento, int $idCat): void {
    $st = $db->prepare("SELECT fase, clubA, clubB FROM _ic_llaves WHERE id_evento=? AND id_categoria=?");
    $st->bind_param('ii', $idEvento, $idCat);
    $st->execute();
    $res = $st->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[$r['fase']] = $r;

    // Paso 1: semis desde posiciones de grupos completos.
    // Si alguna semi ya se está jugando, el cruce quedó fijo: ni recalcular.
    $semisEnJuego = ic_fase_con_juego($db, $idEvento, $idCat, 'semi1')
                 || ic_fase_con_juego($db, $idEvento, $idCat, 'semi2');
    if (!isset($rows['semi1']) || !isset($rows['semi2']) || !$semisEnJuego) {
        $pos = [];
        foreach ([1, 2] as $g) {
            $st = $db->prepare(
                "SELECT s.id_club, cl.nombre FROM _ic_sorteo s JOIN _p_clubes cl ON cl.id = s.id_club
                  WHERE s.id_evento=? AND s.id_categoria=? AND s.grupo=? ORDER BY s.posicion ASC");
            $st->bind_param('iii', $idEvento, $idCat, $g);
            $st->execute();
            $resG = $st->get_result();
            $mapa = [];
            while ($r = $resG->fetch_assoc()) $mapa[(int)$r['id_club']] = $r['nombre'];
            if (count($mapa) < 2) return;
            $st = $db->prepare("SELECT * FROM _ic_partidos WHERE id_evento=? AND id_categoria=? AND grupo=? AND fase='grupo'");
            $st->bind_param('iii', $idEvento, $idCat, $g);
            $st->execute();
            $resP = $st->get_result();
            $partG = [];
            while ($r = $resP->fetch_assoc()) $partG[] = $r;
            $ids = array_keys($mapa);
            $slotsPorCruce = [];
            for ($i = 0; $i < count($ids); $i++) for ($j = $i + 1; $j < count($ids); $j++) {
                $slotsPorCruce[min($ids[$i], $ids[$j]) . '-' . max($ids[$i], $ids[$j])] = IC_SLOTS_SERIE;
            }
            $pos[$g] = ic_posiciones($mapa, $partG, $slotsPorCruce, ic_criterio_viejo($idEvento, $idCat));
            foreach ($pos[$g] as $p) if ($p['sj'] < count($mapa) - 1) return; // grupo incompleto
        }
        foreach ([['semi1', (int)$pos[1][0]['id_club'], (int)$pos[2][1]['id_club']],
                  ['semi2', (int)$pos[2][0]['id_club'], (int)$pos[1][1]['id_club']]] as [$fase, $ca, $cb]) {
            [$a, $b] = ic_upsert_llave($db, $idEvento, $idCat, $fase, $ca, $cb, $rows[$fase] ?? null);
            $rows[$fase] = ['clubA' => $a, 'clubB' => $b];
        }
    }

    // Paso 2: final y 3er puesto desde semis definidas (mismo re-sync que las semis)
    $finalEnJuego = ic_fase_con_juego($db, $idEvento, $idCat, 'final')
                 || ic_fase_con_juego($db, $idEvento, $idCat, 'tercer');
    if (!isset($rows['final']) || !$finalEnJuego) {
        $ganadores = $perdedores = [];
        foreach (['semi1', 'semi2'] as $fase) {
            $a = (int)$rows[$fase]['clubA']; $b = (int)$rows[$fase]['clubB'];
            $slots = IC_SLOTS_SERIE;
            $st = $db->prepare("SELECT * FROM _ic_partidos WHERE id_evento=? AND id_categoria=? AND fase=?");
            $st->bind_param('iis', $idEvento, $idCat, $fase);
            $st->execute();
            $resP = $st->get_result();
            $ms = [];
            while ($r = $resP->fetch_assoc()) $ms[] = $r;
            [, , $definida, $ganador] = ic_estado_serie($ms, $a, $b, $slots);
            if (!$definida) return;
            $ganadores[] = $ganador;
            $perdedores[] = $ganador === $a ? $b : $a;
        }
        foreach ([['final', (int)$ganadores[0], (int)$ganadores[1]],
                  ['tercer', (int)$perdedores[0], (int)$perdedores[1]]] as [$fase, $ca, $cb]) {
            ic_upsert_llave($db, $idEvento, $idCat, $fase, $ca, $cb, $rows[$fase] ?? null);
        }
    }
}

/**
 * Acumula stats (series/partidos/sets/games/pts) para un conjunto de clubes.
 * Usada por las posiciones y por la mini-liga del confronto directo.
 */
function ic_stats(array $clubes, array $partidos, array $slotsPorCruce, bool $viejo): array {
    $st = [];
    foreach ($clubes as $id => $nombre) {
        $st[$id] = ['id_club' => $id, 'club' => $nombre, 'sj' => 0, 'sg' => 0, 'sp' => 0,
                    'pg' => 0, 'pp' => 0, 'setsF' => 0, 'setsC' => 0, 'gamesF' => 0, 'gamesC' => 0, 'pts' => 0];
    }
    // Partidos: sets y games por club
    $porCruce = [];
    foreach ($partidos as $m) {
        $c1 = (int)$m['club1']; $c2 = (int)$m['club2'];
        if (!isset($st[$c1]) || !isset($st[$c2])) continue;
        [$s1, $s2, $g1, $g2] = ic_sets_partido($m);
        $gan = ic_ganador_partido($m);
        if ($gan === 0) continue;
        if ($viejo && (int)$m['es_desempate'] === 1) {
            // Criterio viejo: el partido de desempate vale ±1 al saldo (no games reales ni sets)
            $w = $gan === 1 ? $c1 : $c2; $l = $gan === 1 ? $c2 : $c1;
            $st[$w]['gamesF']++; $st[$l]['gamesC']++;
        } else {
            $st[$c1]['setsF'] += $s1; $st[$c1]['setsC'] += $s2;
            $st[$c2]['setsF'] += $s2; $st[$c2]['setsC'] += $s1;
            $st[$c1]['gamesF'] += $g1; $st[$c1]['gamesC'] += $g2;
            $st[$c2]['gamesF'] += $g2; $st[$c2]['gamesC'] += $g1;
        }
        if ($gan === 1) { $st[$c1]['pg']++; $st[$c2]['pp']++; }
        else            { $st[$c2]['pg']++; $st[$c1]['pp']++; }
        $k = min($c1, $c2) . '-' . max($c1, $c2);
        $porCruce[$k][] = $m;
    }
    // Series definidas
    foreach ($porCruce as $k => $ms) {
        [$a, $b] = array_map('intval', explode('-', $k));
        $slots = $slotsPorCruce[$k] ?? IC_SLOTS_SERIE;
        [$wA, $wB, $definida, $ganador] = ic_estado_serie($ms, $a, $b, $slots);
        if ($definida) {
            $st[$a]['sj']++; $st[$b]['sj']++;
            if ($ganador === $a) { $st[$a]['sg']++; $st[$b]['sp']++; }
            else                 { $st[$b]['sg']++; $st[$a]['sp']++; }
        }
    }
    // Puntos: 1 por SERIE ganada, en los dos criterios (confirmado 2026-08-07 noche).
    // El criterio decide el DESEMPATE, no los puntos.
    foreach ($st as $id => $c) $st[$id]['pts'] = $c['sg'];
    return $st;
}

/**
 * Posiciones de un grupo. $clubes: [id_club => nombre] EN ORDEN DE SORTEO.
 * $viejo = true (categorías ya jugadas, reglamento 2026-08-05): pts → SALDO DE GAMES
 * global (desempate vale ±1) → CONFRONTO DIRECTO entre los que sigan empatados
 * (mini-liga: pts y saldo solo entre ellos) → posición del sorteo.
 * $viejo = false (reglamento 2026-08-07): pts → saldo de games COMPLETOS → partidos
 * ganados → dif. de sets → posición del sorteo. Sin confronto directo.
 */
function ic_posiciones(array $clubes, array $partidos, array $slotsPorCruce, bool $viejo): array {
    $st = ic_stats($clubes, $partidos, $slotsPorCruce, $viejo);
    $ordenSorteo = array_flip(array_keys($clubes));
    $saldo = fn($c) => $c['gamesF'] - $c['gamesC'];
    $lista = array_values($st);
    if (!$viejo) {
        $key = fn($c) => [$c['pts'], $saldo($c), $c['pg'], $c['setsF'] - $c['setsC'],
                          -($ordenSorteo[$c['id_club']] ?? 99)];
        usort($lista, fn($x, $y) => $key($y) <=> $key($x));
        return $lista;
    }
    usort($lista, fn($x, $y) => [$y['pts'], $saldo($y)] <=> [$x['pts'], $saldo($x)]);
    $out = [];
    for ($i = 0, $n = count($lista); $i < $n; ) {
        $j = $i;
        while ($j < $n && $lista[$j]['pts'] === $lista[$i]['pts'] && $saldo($lista[$j]) === $saldo($lista[$i])) $j++;
        $empatados = array_slice($lista, $i, $j - $i);
        if (count($empatados) > 1) {
            $ids = array_column($empatados, 'id_club');
            $mini = ic_stats(
                array_intersect_key($clubes, array_flip($ids)),
                array_values(array_filter($partidos, fn($m) =>
                    in_array((int)$m['club1'], $ids, true) && in_array((int)$m['club2'], $ids, true))),
                $slotsPorCruce, true);
            $key = function ($c) use ($mini, $saldo, $ordenSorteo) {
                $m = $mini[$c['id_club']];
                return [$m['pts'], $saldo($m), -($ordenSorteo[$c['id_club']] ?? 99)];
            };
            usort($empatados, fn($x, $y) => $key($y) <=> $key($x));
        }
        $out = array_merge($out, $empatados);
        $i = $j;
    }
    return $out;
}
