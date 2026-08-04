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
 * - Posiciones: 1 punto por serie ganada; desempate por dif. de partidos, sets y games.
 */

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
                TRIM(CONCAT(COALESCE(u1.nombre,''),' ',COALESCE(u1.apellido,''))) AS j1,
                TRIM(CONCAT(COALESCE(u2.nombre,''),' ',COALESCE(u2.apellido,''))) AS j2
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

/**
 * Avance AUTOMÁTICO de llaves: si ambos grupos completaron sus series y no hay
 * semis → las crea (1°G1 vs 2°G2, 1°G2 vs 2°G1). Si las semis están definidas
 * y no hay final → crea final y 3er puesto. Nunca borra nada.
 */
function ic_autogenerar_llaves(mysqli $db, int $idEvento, int $idCat): void {
    $st = $db->prepare("SELECT fase, clubA, clubB FROM _ic_llaves WHERE id_evento=? AND id_categoria=?");
    $st->bind_param('ii', $idEvento, $idCat);
    $st->execute();
    $res = $st->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[$r['fase']] = $r;

    // Paso 1: semis desde posiciones de grupos completos
    if (!isset($rows['semi1']) || !isset($rows['semi2'])) {
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
                $slotsPorCruce[min($ids[$i], $ids[$j]) . '-' . max($ids[$i], $ids[$j])] =
                    max(1, min(count(ic_duplas($db, $idEvento, $idCat, $ids[$i])), count(ic_duplas($db, $idEvento, $idCat, $ids[$j]))));
            }
            $pos[$g] = ic_posiciones($mapa, $partG, $slotsPorCruce);
            foreach ($pos[$g] as $p) if ($p['sj'] < count($mapa) - 1) return; // grupo incompleto
        }
        $st = $db->prepare("INSERT IGNORE INTO _ic_llaves (id_evento, id_categoria, fase, clubA, clubB) VALUES (?,?,?,?,?)");
        foreach ([['semi1', $pos[1][0]['id_club'], $pos[2][1]['id_club']],
                  ['semi2', $pos[2][0]['id_club'], $pos[1][1]['id_club']]] as [$fase, $ca, $cb]) {
            $st->bind_param('iisii', $idEvento, $idCat, $fase, $ca, $cb);
            $st->execute();
        }
        $rows['semi1'] = ['clubA' => $pos[1][0]['id_club'], 'clubB' => $pos[2][1]['id_club']];
        $rows['semi2'] = ['clubA' => $pos[2][0]['id_club'], 'clubB' => $pos[1][1]['id_club']];
    }

    // Paso 2: final y 3er puesto desde semis definidas
    if (!isset($rows['final'])) {
        $ganadores = $perdedores = [];
        foreach (['semi1', 'semi2'] as $fase) {
            $a = (int)$rows[$fase]['clubA']; $b = (int)$rows[$fase]['clubB'];
            $slots = max(1, min(count(ic_duplas($db, $idEvento, $idCat, $a)), count(ic_duplas($db, $idEvento, $idCat, $b))));
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
        $st = $db->prepare("INSERT IGNORE INTO _ic_llaves (id_evento, id_categoria, fase, clubA, clubB) VALUES (?,?,?,?,?)");
        foreach ([['final', $ganadores[0], $ganadores[1]], ['tercer', $perdedores[0], $perdedores[1]]] as [$fase, $ca, $cb]) {
            $st->bind_param('iisii', $idEvento, $idCat, $fase, $ca, $cb);
            $st->execute();
        }
    }
}

/**
 * Acumula stats (series/partidos/sets/games/pts) para un conjunto de clubes.
 * Usada por las posiciones y por la mini-liga del confronto directo.
 */
function ic_stats(array $clubes, array $partidos, array $slotsPorCruce): array {
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
        $st[$c1]['setsF'] += $s1; $st[$c1]['setsC'] += $s2;
        $st[$c2]['setsF'] += $s2; $st[$c2]['setsC'] += $s1;
        $st[$c1]['gamesF'] += $g1; $st[$c1]['gamesC'] += $g2;
        $st[$c2]['gamesF'] += $g2; $st[$c2]['gamesC'] += $g1;
        if ($gan === 1) { $st[$c1]['pg']++; $st[$c2]['pp']++; }
        else            { $st[$c2]['pg']++; $st[$c1]['pp']++; }
        $k = min($c1, $c2) . '-' . max($c1, $c2);
        $porCruce[$k][] = $m;
    }
    // Series definidas
    foreach ($porCruce as $k => $ms) {
        [$a, $b] = array_map('intval', explode('-', $k));
        $slots = $slotsPorCruce[$k] ?? 2;
        [$wA, $wB, $definida, $ganador] = ic_estado_serie($ms, $a, $b, $slots);
        if ($definida) {
            $st[$a]['sj']++; $st[$b]['sj']++;
            if ($ganador === $a) { $st[$a]['sg']++; $st[$a]['pts']++; $st[$b]['sp']++; }
            else                 { $st[$b]['sg']++; $st[$b]['pts']++; $st[$a]['sp']++; }
        }
    }
    return $st;
}

/**
 * Posiciones de un grupo. $clubes: [id_club => nombre] EN ORDEN DE SORTEO.
 * Criterio: pts → CONFRONTO DIRECTO entre los empatados (mini-liga: pts,
 * dif. partidos, dif. sets, dif. games solo entre ellos) → diferencias
 * globales → posición del sorteo (último recurso en empates circulares
 * perfectos, ej. A>B, B>C, C>A con todos los marcadores iguales).
 */
function ic_posiciones(array $clubes, array $partidos, array $slotsPorCruce): array {
    $st = ic_stats($clubes, $partidos, $slotsPorCruce);
    $ordenSorteo = array_flip(array_keys($clubes));
    $lista = array_values($st);
    usort($lista, fn($x, $y) => $y['pts'] <=> $x['pts']);
    $out = [];
    for ($i = 0, $n = count($lista); $i < $n; ) {
        $j = $i;
        while ($j < $n && $lista[$j]['pts'] === $lista[$i]['pts']) $j++;
        $empatados = array_slice($lista, $i, $j - $i);
        if (count($empatados) > 1) {
            $ids = array_column($empatados, 'id_club');
            $mini = ic_stats(
                array_intersect_key($clubes, array_flip($ids)),
                array_values(array_filter($partidos, fn($m) =>
                    in_array((int)$m['club1'], $ids, true) && in_array((int)$m['club2'], $ids, true))),
                $slotsPorCruce);
            $key = function ($c) use ($mini, $ordenSorteo) {
                $m = $mini[$c['id_club']];
                return [$m['pts'], $m['pg'] - $m['pp'], $m['setsF'] - $m['setsC'], $m['gamesF'] - $m['gamesC'],
                        $c['pg'] - $c['pp'], $c['setsF'] - $c['setsC'], $c['gamesF'] - $c['gamesC'],
                        -($ordenSorteo[$c['id_club']] ?? 99)];
            };
            usort($empatados, fn($x, $y) => $key($y) <=> $key($x));
        }
        $out = array_merge($out, $empatados);
        $i = $j;
    }
    return $out;
}
