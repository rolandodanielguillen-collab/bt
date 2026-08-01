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

// Ganador del partido: 1 (club1), 2 (club2), 0 (sin definir)
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
 * Posiciones de un grupo.
 * $clubes: [id_club => nombre] en el grupo. $slotsPorCruce: [clave "menor-mayor" => slots].
 * $partidos: filas _ic_partidos del grupo. Devuelve lista ordenada de stats por club.
 */
function ic_posiciones(array $clubes, array $partidos, array $slotsPorCruce): array {
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
    $lista = array_values($st);
    usort($lista, function ($x, $y) {
        return [$y['pts'], $y['pg'] - $y['pp'], $y['setsF'] - $y['setsC'], $y['gamesF'] - $y['gamesC']]
           <=> [$x['pts'], $x['pg'] - $x['pp'], $x['setsF'] - $x['setsC'], $x['gamesF'] - $x['gamesC']];
    });
    return $lista;
}
