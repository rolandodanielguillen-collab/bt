<?php
/**
 * Check del ranking de clubes (posición por categoría → puntos). Sin DB:
 * `php test_interclubes_ranking.php`. Datos reales del evento 15.
 */
require __DIR__ . '/interclubes.functions.php';

$ARENA = 3; $CHIQUI = 8; $VISTA = 9; $MOES = 10; $LUJINI = 13; $CSA4 = 107;
$nombres = [$ARENA => 'ARENA BAR', $CHIQUI => 'En lo de chiqui Beach', $VISTA => 'Vista Bar',
            $MOES => 'Moes - Yoyi', $LUJINI => 'Lujini Beach Tennis', $CSA4 => 'CSA4'];

$fallos = 0;
$check = function (string $qué, $esperado, $obtenido) use (&$fallos) {
    if ($esperado === $obtenido) { echo "  ok   $qué\n"; return; }
    $fallos++;
    echo "  FALLA $qué: esperaba " . json_encode($esperado) . ", obtuvo " . json_encode($obtenido) . "\n";
};

// Serie ganada 2-0 por el club que se pasa como ganador (2 partidos, 1 set cada uno)
$serie = fn(string $fase, int $ganador, int $perdedor) => [
    ['fase' => $fase, 'club1' => $ganador, 'club2' => $perdedor, 's1c1' => 6, 's1c2' => 3,
     's2c1' => 0, 's2c2' => 0, 's3c1' => 0, 's3c2' => 0, 'es_desempate' => 0],
    ['fase' => $fase, 'club1' => $ganador, 'club2' => $perdedor, 's1c1' => 6, 's1c2' => 4,
     's2c1' => 0, 's2c2' => 0, 's3c1' => 0, 's3c2' => 0, 'es_desempate' => 0],
];

// ── Cat. C MASC del ev15: final Lujini-Arena, 3er puesto Vista-CSA4 ──────────
// (llaves reales: final 3 vs 13, tercer 107 vs 9)
$llaves = [
    'semi1' => ['clubA' => $CSA4,   'clubB' => $ARENA],
    'semi2' => ['clubA' => $LUJINI, 'clubB' => $VISTA],
    'final' => ['clubA' => $ARENA,  'clubB' => $LUJINI],
    'tercer' => ['clubA' => $CSA4,  'clubB' => $VISTA],
];
$partidos = array_merge($serie('final', $LUJINI, $ARENA), $serie('tercer', $VISTA, $CSA4));

echo "Cat. C MASC ev15 — campeón Lujini, 3ro Vista Bar\n";
$pos = ic_posiciones_categoria($nombres, $llaves, $partidos);
$check('campeón',        1, $pos[$LUJINI]);
$check('finalista',      2, $pos[$ARENA]);
$check('tercer puesto',  3, $pos[$VISTA]);
$check('cuarto',         4, $pos[$CSA4]);
$check('participación (eliminados en grupos)', [0, 0], [$pos[$CHIQUI], $pos[$MOES]]);
$check('todos los clubes tienen posición', 6, count($pos));

echo "Puntos por posición (100/75/60/50/30) y suma de la categoría\n";
$pts = array_map(fn($p) => ic_puntos_pos([], 15, 3, $p), $pos);
$check('Lujini 100', 100, $pts[$LUJINI]);
$check('Arena 75',    75, $pts[$ARENA]);
$check('Vista 60',    60, $pts[$VISTA]);
$check('CSA4 50',     50, $pts[$CSA4]);
$check('Chiqui 30',   30, $pts[$CHIQUI]);
$check('la categoría reparte 345', 345, array_sum($pts));

echo "La matriz del admin pisa la constante cuando el evento la tiene cargada\n";
$matriz = [15 => [3 => [1 => 200, 0 => 10]]];
$check('campeón con matriz',     200, ic_puntos_pos($matriz, 15, 3, 1));
$check('finalista sin matriz',    75, ic_puntos_pos($matriz, 15, 3, 2));
$check('otra categoría intacta', 100, ic_puntos_pos($matriz, 15, 4, 1));

echo "Final sin definir: la categoría todavía no puntúa\n";
$check('sin final', [], ic_posiciones_categoria($nombres, ['semi1' => $llaves['semi1']], []));
$check('final cargada a medias', [],
    ic_posiciones_categoria($nombres, $llaves, array_slice($serie('final', $LUJINI, $ARENA), 0, 1)));

echo "3er puesto sin jugar: los perdedores de semis son 4tos, no participación\n";
$sinTercer = $llaves; unset($sinTercer['tercer']);
$pos2 = ic_posiciones_categoria($nombres, $sinTercer, $serie('final', $LUJINI, $ARENA));
$check('perdedores de semis', [4, 4], [$pos2[$CSA4], $pos2[$VISTA]]);
$check('el resto participación', [0, 0], [$pos2[$CHIQUI], $pos2[$MOES]]);

echo $fallos ? "\n{$fallos} FALLAS\n" : "\nTodo ok\n";
exit($fallos ? 1 : 0);
