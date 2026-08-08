<?php
/**
 * Check del criterio de clasificación de Interclubes. Sin DB: `php test_interclubes_criterio.php`.
 * Datos reales del evento 15, categoría 3 (C MASC), grupo 1 — el caso que destapó el bug
 * del 2026-08-07: VISTA BAR ganó 1 serie pero menos partidos que MOES, y con pts=partidos
 * quedaba 3ro. Con pts=SERIES entra 2do, que es el reglamento.
 */
require __DIR__ . '/interclubes.functions.php';

$VISTA = 9; $MOES = 10; $CSA4 = 107;
$clubes = [$VISTA => 'Vista Bar', $MOES => 'Moes - Yoyi', $CSA4 => 'CSA4']; // orden de sorteo

// [club1, club2, games1, games2, es_desempate] — 1 set por partido, como se cargan en la base
$crudos = [
    [$VISTA, $CSA4, 3, 6, 0],
    [$VISTA, $CSA4, 5, 7, 0],
    [$VISTA, $MOES,  2, 6, 0],
    [$VISTA, $MOES,  6, 4, 0],
    [$VISTA, $MOES,  6, 4, 1],
    [$MOES,  $CSA4, 6, 1, 0],
    [$MOES,  $CSA4, 3, 6, 0],
    [$MOES,  $CSA4, 2, 6, 1],
];
$partidos = array_map(fn($p) => [
    'club1' => $p[0], 'club2' => $p[1], 's1c1' => $p[2], 's1c2' => $p[3],
    's2c1' => 0, 's2c2' => 0, 's3c1' => 0, 's3c2' => 0, 'es_desempate' => $p[4],
], $crudos);

$slots = [];
foreach ([[$VISTA, $MOES], [$VISTA, $CSA4], [$MOES, $CSA4]] as [$a, $b])
    $slots[min($a, $b) . '-' . max($a, $b)] = IC_SLOTS_SERIE;

$fallos = 0;
$check = function (string $qué, $esperado, $obtenido) use (&$fallos) {
    if ($esperado === $obtenido) { echo "  ok   $qué\n"; return; }
    $fallos++;
    echo "  FALLA $qué: esperaba " . json_encode($esperado) . ", obtuvo " . json_encode($obtenido) . "\n";
};

echo "Criterio NUEVO (cat 3): pts = series -> saldo games completos -> partidos ganados\n";
$pos = ic_posiciones($clubes, $partidos, $slots, false);
$check('orden', ['CSA4', 'Vista Bar', 'Moes - Yoyi'], array_column($pos, 'club'));
$check('pts', [2, 1, 0], array_column($pos, 'pts'));
$check('series ganadas', [2, 1, 0], array_column($pos, 'sg'));
$check('partidos ganados', [4, 2, 2], array_column($pos, 'pg'));
$check('saldo de games completos', [7, -5, -2],
    array_map(fn($p) => $p['gamesF'] - $p['gamesC'], $pos));

echo "Criterio VIEJO (cats 4/5/9/10 congeladas): el desempate vale +-1, no sus games\n";
$posV = ic_posiciones($clubes, $partidos, $slots, true);
$check('orden', ['CSA4', 'Vista Bar', 'Moes - Yoyi'], array_column($posV, 'club'));
$check('pts (tambien series)', [2, 1, 0], array_column($posV, 'pts'));
$check('saldo con desempate +-1', [4, -6, 2],
    array_map(fn($p) => $p['gamesF'] - $p['gamesC'], $posV));

/**
 * Cruce de semis: 1°G1 vs 2°G2 y 1°G2 vs 2°G1. Datos reales del ev15 cat 8 (C FEM),
 * donde el grupo 2 terminó con los 3 clubes a 1 serie y se define por saldo de games
 * (ARENA +2, CHIQUI 0, MOES −2). El bracket que estaba cargado tenía ARENA y CHIQUI
 * cambiados de lugar: se generó antes de que se corrigieran 3 marcadores del grupo.
 */
echo "Cruce de semis (ev15 cat 8): 1G1 vs 2G2 / 1G2 vs 2G1\n";
$LUJINI = 13; $CSA4b = 107; $MOES2 = 10; $ARENA = 3; $CHIQUI = 8;
$grupos = [
    1 => [[$LUJINI => 'Lujini', $VISTA => 'Vista Bar', $CSA4b => 'CSA4'], [
        [$LUJINI, $VISTA, 6, 4, 0], [$LUJINI, $VISTA, 6, 2, 0],
        [$LUJINI, $CSA4b, 6, 0, 0], [$LUJINI, $CSA4b, 6, 0, 0],
        [$VISTA, $CSA4b, 6, 0, 0], [$VISTA, $CSA4b, 6, 0, 0],
    ]],
    2 => [[$MOES2 => 'Moes', $ARENA => 'Arena Bar', $CHIQUI => 'Chiqui'], [
        [$MOES2, $ARENA, 6, 3, 0], [$MOES2, $ARENA, 1, 6, 0], [$MOES2, $ARENA, 4, 6, 1],
        [$MOES2, $CHIQUI, 6, 7, 0], [$MOES2, $CHIQUI, 7, 6, 0], [$MOES2, $CHIQUI, 7, 5, 1],
        [$ARENA, $CHIQUI, 4, 6, 0], [$ARENA, $CHIQUI, 6, 4, 0], [$ARENA, $CHIQUI, 4, 6, 1],
    ]],
];
$posG = [];
foreach ($grupos as $g => [$cls, $crudosG]) {
    $ms = array_map(fn($p) => [
        'club1' => $p[0], 'club2' => $p[1], 's1c1' => $p[2], 's1c2' => $p[3],
        's2c1' => 0, 's2c2' => 0, 's3c1' => 0, 's3c2' => 0, 'es_desempate' => $p[4],
    ], $crudosG);
    $sl = [];
    $idsG = array_keys($cls);
    for ($i = 0; $i < count($idsG); $i++) for ($j = $i + 1; $j < count($idsG); $j++)
        $sl[min($idsG[$i], $idsG[$j]) . '-' . max($idsG[$i], $idsG[$j])] = IC_SLOTS_SERIE;
    $posG[$g] = ic_posiciones($cls, $ms, $sl, false);
    $check("orden G$g", $g === 1 ? ['Lujini', 'Vista Bar', 'CSA4'] : ['Arena Bar', 'Chiqui', 'Moes'],
        array_column($posG[$g], 'club'));
}
$check('semi1 = 1G1 vs 2G2', [$LUJINI, $CHIQUI], [$posG[1][0]['id_club'], $posG[2][1]['id_club']]);
$check('semi2 = 1G2 vs 2G1', [$ARENA, $VISTA],  [$posG[2][0]['id_club'], $posG[1][1]['id_club']]);

echo $fallos ? "\n$fallos FALLA(S)\n" : "\nTodo ok\n";
exit($fallos ? 1 : 0);
