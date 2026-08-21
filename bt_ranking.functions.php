<?php
/**
 * bt_ranking.functions.php — el criterio de puntos del ranking, en UN solo lugar.
 *
 * Antes esta cuenta estaba copiada en cuatro archivos y dos se habían quedado con
 * la fórmula vieja: el admin y la siembra de llaves mostraban otros números que el
 * sitio y la app (164 jugadores, 12.460 puntos de diferencia).
 * Detalle: INFORME_RANKING_DEUDA_20260821.md
 *
 * CRITERIO VIGENTE (FIX 02/06/2026): por cada evento en el que el jugador estuvo
 * inscripto en la categoría hijo o en su padre, se suman los puntos de las dos
 * categorías TAL CUAL están en `_ranking`. No se resta el padre y no hay caso
 * especial de "grupo único".
 *
 * Esto NO calcula puntos: sólo lee `_ranking`. Quien escribe esa tabla es
 * logica/calculo.ranking.php.
 *
 * Uso:
 *   require_once __DIR__ . '/bt_ranking.functions.php';
 *   $rk = bt_rank_cargar($mysqli2, $idcircuito);     // 4 queries, después todo en memoria
 *   $t  = bt_rank_total($ci, $catHijo, $catPadre);   // ptosMixto / ptosHijo / total
 *   foreach (bt_rank_jugadores_cat($catHijo, $catPadre) as $j) { ... }
 */

/** Estado de la precarga. Por referencia para que las funciones lo lean sin copiarlo. */
function &bt_rank_estado()
{
    static $estado = null;
    return $estado;
}

/**
 * Precarga todo lo que necesita el ranking de un circuito: 4 queries y después
 * puros lookups en memoria (antes cada jugador disparaba 3 queries por evento).
 *
 * @param mysqli     $mysqli
 * @param int        $circuito
 * @param array|null $filterCis  null = sin búsqueda; array ci => true para filtrar.
 * @return array claves: rankRows, categorias, puntosAll, cantidadPxCat, usuarios, eventoNombre
 */
function bt_rank_cargar($mysqli, $circuito, $filterCis = null)
{
    $circuito = (int)$circuito;

    $estado = [
        'rankIdx'       => [],   // evento => categoria => ci => puntos (abs, primera fila)
        'inscIdx'       => [],   // ci => categoria => evento => true
        'eventoNombre'  => [],
        'usuarios'      => [],   // ci => fila (la primera: hay CIs repetidos en _p_usuarios)
        'rankRows'      => [],
        'categorias'    => [],   // categorías presentes en _ranking, en orden de aparición
        'puntosAll'     => [],   // puntos acumulados => categoria => ci => [ci, puntos, idRanking, pos]
        'cantidadPxCat' => [],
    ];

    $res = $mysqli->query("SELECT ci, nombre, apellido FROM _p_usuarios");
    while ($r = $res->fetch_assoc()) {
        if (!isset($estado['usuarios'][$r['ci']])) $estado['usuarios'][$r['ci']] = $r;
    }

    $res = $mysqli->query("SELECT * FROM _ranking WHERE circuito={$circuito}");
    while ($r = $res->fetch_assoc()) {
        $estado['rankRows'][] = $r;
        if (!isset($estado['rankIdx'][$r['evento']][$r['categoria']][$r['ci']])) {
            $estado['rankIdx'][$r['evento']][$r['categoria']][$r['ci']] = abs($r['puntos']);
        }
    }

    $res = $mysqli->query("SELECT i.ci, i.ci_dupla, i.id_categoria, i.id_evento, e.evento
        FROM _p_incripciones i JOIN _p_eventos e ON e.id=i.id_evento
        WHERE e.id_circuito={$circuito}");
    while ($r = $res->fetch_assoc()) {
        $estado['eventoNombre'][$r['id_evento']] = $r['evento'];
        if ($r['ci'] !== null && $r['ci'] !== '')             $estado['inscIdx'][$r['ci']][$r['id_categoria']][$r['id_evento']] = true;
        if ($r['ci_dupla'] !== null && $r['ci_dupla'] !== '') $estado['inscIdx'][$r['ci_dupla']][$r['id_categoria']][$r['id_evento']] = true;
    }

    // Nombres de eventos que no aparecieron por inscripciones (fecha sin inscriptos).
    $res = $mysqli->query("SELECT id, evento FROM _p_eventos WHERE id_circuito={$circuito}");
    while ($r = $res->fetch_assoc()) {
        if (!isset($estado['eventoNombre'][$r['id']])) $estado['eventoNombre'][$r['id']] = $r['evento'];
    }

    // Índice por puntos acumulados: de acá salen el orden de las tarjetas, qué
    // jugadores tiene cada categoría y el id de fila que usa el HTML del detalle.
    $puntosxJugador = [];
    $mostrado = [];
    foreach ($estado['rankRows'] as $row) {
        if ($filterCis !== null && !isset($filterCis[$row['ci']])) continue;
        $cat = $row['categoria'];
        $ci  = $row['ci'];
        $estado['categorias'][$cat] = $cat;
        if (!isset($puntosxJugador[$cat][$ci])) $puntosxJugador[$cat][$ci] = 0;
        $puntosxJugador[$cat][$ci] = abs($puntosxJugador[$cat][$ci]) + $row['puntos'];
        $lospuntos = abs($puntosxJugador[$cat][$ci]);
        $estado['puntosAll'][$lospuntos][$cat][$ci] = [$ci, $lospuntos, $row['id'], $row['pos']];
        if (!isset($mostrado[$cat][$ci])) {
            $mostrado[$cat][$ci] = true;
            if (!isset($estado['cantidadPxCat'][$cat])) $estado['cantidadPxCat'][$cat] = 0;
            $estado['cantidadPxCat'][$cat]++;
        }
    }
    krsort($estado['puntosAll']);

    $ref = &bt_rank_estado();
    $ref = $estado;
    return $estado;
}

/** Eventos (ids, ascendente) donde el jugador estuvo inscripto en la categoría o en su padre. */
function bt_rank_eventos($ci, $catHijo, $catPadre)
{
    $e = &bt_rank_estado();
    $evts = [];
    if (isset($e['inscIdx'][$ci][$catHijo]))  $evts += $e['inscIdx'][$ci][$catHijo];
    if (isset($e['inscIdx'][$ci][$catPadre])) $evts += $e['inscIdx'][$ci][$catPadre];
    ksort($evts);
    return array_keys($evts);
}

/** Puntos del jugador en un evento y categoría (0 si no jugó o no puntuó). */
function bt_rank_puntos($evento, $categoria, $ci)
{
    $e = &bt_rank_estado();
    return isset($e['rankIdx'][$evento][$categoria][$ci]) ? $e['rankIdx'][$evento][$categoria][$ci] : 0;
}

/** Nombre del evento, para el desglose por fecha. */
function bt_rank_evento_nombre($evento)
{
    $e = &bt_rank_estado();
    return isset($e['eventoNombre'][$evento]) ? $e['eventoNombre'][$evento] : "Fecha #{$evento}";
}

/** Nombre y apellido del jugador, con la misma regla que el sitio (primera fila del CI). */
function bt_rank_usuario($ci)
{
    $e = &bt_rank_estado();
    return isset($e['usuarios'][$ci]) ? $e['usuarios'][$ci] : ['nombre' => '', 'apellido' => ''];
}

/** EL CRITERIO. Total del jugador en una categoría: padre + hijo, puros. */
function bt_rank_total($ci, $catHijo, $catPadre)
{
    $ptosMixto = 0;
    $ptosHijo  = 0;
    foreach (bt_rank_eventos($ci, $catHijo, $catPadre) as $evId) {
        $ptosMixto += bt_rank_puntos($evId, $catPadre, $ci);
        $ptosHijo  += bt_rank_puntos($evId, $catHijo, $ci);
    }
    return ['ptosMixto' => $ptosMixto, 'ptosHijo' => $ptosHijo, 'total' => $ptosMixto + $ptosHijo];
}

/** Desglose por fecha. Sólo las fechas donde sumó algo, como en el sitio. */
function bt_rank_detalle($ci, $catHijo, $catPadre)
{
    $out = [];
    foreach (bt_rank_eventos($ci, $catHijo, $catPadre) as $evId) {
        $ptsPadre = bt_rank_puntos($evId, $catPadre, $ci);
        $ptsHijo  = bt_rank_puntos($evId, $catHijo, $ci);
        if ($ptsPadre > 0 || $ptsHijo > 0) {
            $out[] = [
                'eventoId'     => (int)$evId,
                'evento'       => bt_rank_evento_nombre($evId),
                'ptsPadre'     => $ptsPadre,
                'ptsCategoria' => $ptsHijo,
            ];
        }
    }
    return $out;
}

/** Jugadores de una categoría con su total real, ordenados de mayor a menor. */
function bt_rank_jugadores_cat($catHijo, $catPadre)
{
    $e = &bt_rank_estado();
    $jugadores = [];
    $yaVisto = [];
    foreach ($e['puntosAll'] as $cadaPunto) {
        if (!isset($cadaPunto[$catHijo])) continue;
        foreach ($cadaPunto[$catHijo] as $cadaParticipante) {
            $ci = $cadaParticipante[0];
            if (isset($yaVisto[$ci])) continue;
            $yaVisto[$ci] = true;
            $t = bt_rank_total($ci, $catHijo, $catPadre);
            $jugadores[] = [
                'ci'        => $ci,
                'idRanking' => $cadaParticipante[2],
                'ptosMixto' => $t['ptosMixto'],
                'ptosHijo'  => $t['ptosHijo'],
                'total'     => $t['total'],
            ];
        }
    }
    usort($jugadores, fn($a, $b) => $b['total'] <=> $a['total']);
    return $jugadores;
}
