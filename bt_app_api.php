<?php
/**
 * bt_app_api.php — API PÚBLICA de sólo lectura para la app móvil (Expo).
 * ================================================================
 * Archivo NUEVO y AISLADO. No modifica ni depende de tvt_api.php:
 * el admin sigue funcionando exactamente igual si esto se borra.
 *
 * Reglas de este archivo:
 *   - SÓLO LECTURA. Ninguna acción escribe en la base. Nunca agregar una.
 *   - Sin sesión: lo consume la app, que no tiene login de admin.
 *   - Sólo expone datos que YA son públicos en bt.com.py (fixture, ranking,
 *     inscriptos). Nada de emails, teléfonos completos ni datos de admin.
 *
 * Uso: bt_app_api.php?action=eventos
 * ================================================================
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Cache-Control: public, max-age=30');
ini_set('display_errors', 0);
error_reporting(E_ALL);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') { http_response_code(204); exit; }

// Sólo GET: si alguna vez llega un POST acá, es un error de quien llama.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Sólo GET']);
    exit;
}

set_exception_handler(function (Throwable $e) {
    error_log('bt_app_api ' . ($_GET['action'] ?? '?') . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    // No devolvemos el mensaje real: es un endpoint público.
    echo json_encode(['success' => false, 'error' => 'Error interno']);
    exit;
});

// ── Conexión BD (mismo patrón que tvt_api.php) ───────────────────
if (file_exists("db/conection.inc.php")) {
    include_once "db/conection.inc.php";
    @include_once "funciones.php";
} else {
    include_once $_SERVER['DOCUMENT_ROOT'] . "/db/conection.inc.php";
    @include_once $_SERVER['DOCUMENT_ROOT'] . "/funciones.php";
}

// ── Helpers ──────────────────────────────────────────────────────
function resp($data) { echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
function respErr($msg, $code = 400) { http_response_code($code); resp(['success' => false, 'error' => $msg]); }

/** Entero positivo desde GET. Ignora arrays (`?evento[]=1`). */
function intGet($k, $d = 0) {
    if (!isset($_GET[$k]) || is_array($_GET[$k])) return $d;
    return abs((int)$_GET[$k]);
}
/** String desde GET, siempre escalar. */
function strGet($k, $d = '') {
    if (!isset($_GET[$k]) || is_array($_GET[$k])) return $d;
    return trim((string)$_GET[$k]);
}

/**
 * Nombre de cancha. `cancha_id()` vive en funciones.php del sitio; si no está,
 * devolvemos el id crudo en vez de romper. NO asumir que existe.
 */
function nombre_cancha($valor) {
    if ($valor === null || $valor === '') return null;
    return function_exists('cancha_id') ? cancha_id($valor) : (string)$valor;
}

/**
 * Prefijo del header de un partido, misma regla que la web
 * (logica/todos.vs.todos.php:1356-1366).
 */
function prefijo_partido(string $nombreGrupo, int $nro): string {
    $g = mb_strtoupper($nombreGrupo, 'UTF-8');
    if (mb_strpos($g, '16VOS') !== false)   return '16.' . $nro;
    if (mb_strpos($g, '8VOS') !== false)    return '8.' . $nro;
    if (mb_strpos($g, 'CUARTOS') !== false) return 'C' . $nro;
    if (mb_strpos($g, 'SEMI') !== false)    return 'S' . $nro;
    if (mb_strpos($g, 'FINAL') !== false)   return 'F';
    if (mb_strpos($g, 'TERCER') !== false)  return '3P';
    if (mb_strpos($g, 'GRUPO') !== false || mb_strpos($g, 'RONDA') !== false) return 'R' . $nro;
    return mb_substr($nombreGrupo, 0, 2, 'UTF-8') . $nro;
}

// ── Whitelist: cualquier cosa fuera de acá se rechaza ────────────
const ACCIONES = ['eventos', 'evento', 'categorias', 'parejas', 'resultados', 'resultados_ic', 'ranking', 'ranking_interclubes', 'buscar_jugador', 'en_juego'];

$action = strGet('action');
if (!$action) respErr('Falta parámetro action');
if (!in_array($action, ACCIONES, true)) respErr('Acción no disponible', 404);

// ══════════════════════════════════════════════════════════════════
// eventos — lista de fechas del circuito
// ══════════════════════════════════════════════════════════════════
if ($action === 'eventos') {
    // `previsualizacion` es un borrador del admin: no se muestra en la app.
    // id_tipo_evento 5 = Interclubes (clubes, sorteo, series): la app lo abre
    // en grafico-interclubes.php como hace la web, no en el detalle de parejas.
    $sql = "SELECT e.id, e.evento, e.url_amigable, e.fecha, e.fecha_fin, e.estado,
                   e.flyer, e.id_circuito, e.fecha_fin_inscripcion, e.id_tipo_evento,
                   c.nombre AS circuito
            FROM _p_eventos e
            LEFT JOIN _circuitos c ON c.id = e.id_circuito
            WHERE e.estado NOT IN ('inactivo','previsualizacion')
            ORDER BY e.id DESC LIMIT 50";
    $r = $mysqli2->query($sql);
    $eventos = [];
    if ($r) while ($row = $r->fetch_assoc()) {
        $id = (int)$row['id'];
        // Inscripciones reales: filas de _p_incripciones / 2 (una por jugador).
        $ri = $mysqli2->query("SELECT COUNT(*) as c FROM _p_incripciones WHERE id_evento='$id' AND estado<>'bloqueado'");
        $row['inscriptos'] = $ri ? (int)floor((int)$ri->fetch_assoc()['c'] / 2) : 0;
        // Los flyers viven en /img/flyers/. Si está vacío, la app pinta el fondo sólido.
        $row['flyer_url'] = !empty($row['flyer']) ? '/img/flyers/' . $row['flyer'] : null;
        $eventos[] = $row;
    }
    resp(['success' => true, 'eventos' => $eventos]);
}

// ══════════════════════════════════════════════════════════════════
// en_juego — partidos que la organización marcó como jugándose ahora,
// de TODOS los eventos vivos. Es el flag `_todosvstodos.en_juego`, nada más.
// ══════════════════════════════════════════════════════════════════
if ($action === 'en_juego') {
    $sql = "SELECT t.id, t.evento, t.categoria, t.grupo, t.partido_nro,
                   t.ci1_a, t.ci1_b, t.ci2_a, t.ci2_b,
                   t.rusultado_equipo1 AS s1a, t.resultado_equipo2  AS s1b,
                   t.resultado2_equipo1 AS s2a, t.resultado2_equipo2 AS s2b,
                   t.resultado3_equipo1 AS s3a, t.resultado3_equipo2 AS s3b,
                   t.cancha, t.hora,
                   c.categoria AS cat_nombre,
                   g.grupo AS grupo_nombre,
                   e.evento AS evento_nombre
            FROM _todosvstodos t
            LEFT JOIN _p_categorias c ON c.id = t.categoria
            LEFT JOIN _p_grupos g ON g.id = t.grupo
            LEFT JOIN _p_eventos e ON e.id = t.evento
            WHERE t.en_juego = 'si'
            ORDER BY t.evento DESC, t.categoria ASC, t.partido_nro ASC
            LIMIT 40";
    $r = $mysqli2->query($sql);
    if (!$r) respErr('Error consultando partidos en juego', 500);

    $filas = [];
    $cis = [];
    while ($row = $r->fetch_assoc()) {
        foreach (['ci1_a', 'ci1_b', 'ci2_a', 'ci2_b'] as $k) {
            $ci = (string)$row[$k];
            if ($ci !== '' && $ci !== '0') $cis[$ci] = true;
        }
        $filas[] = $row;
    }

    $nombres = [];
    if ($cis) {
        $lista = implode(',', array_map(fn($ci) => "'" . $mysqli2->real_escape_string($ci) . "'", array_keys($cis)));
        $rn = $mysqli2->query("SELECT ci, nombre, apellido FROM _p_usuarios WHERE ci IN ($lista)");
        if ($rn) while ($u = $rn->fetch_assoc()) {
            $nombres[(string)$u['ci']] = trim($u['nombre'] . ' ' . $u['apellido']);
        }
    }
    $nom = fn($ci) => $nombres[(string)$ci] ?? '';

    $partidos = [];
    foreach ($filas as $row) {
        $partidos[] = [
            'id'        => (int)$row['id'],
            'eventoId'  => (int)$row['evento'],
            'evento'    => $row['evento_nombre'] ?? '',
            'categoriaId' => (int)$row['categoria'],
            'categoria' => $row['cat_nombre'] ?? '',
            'grupo'     => (int)$row['grupo'],
            'grupoNombre' => $row['grupo_nombre'] ?: '',
            'nro'       => (int)$row['partido_nro'],
            'pareja_a'  => trim($nom($row['ci1_a']) . ' / ' . $nom($row['ci1_b']), ' /'),
            'pareja_b'  => trim($nom($row['ci2_a']) . ' / ' . $nom($row['ci2_b']), ' /'),
            'sets'      => [
                'a' => [(int)$row['s1a'], (int)$row['s2a'], (int)$row['s3a']],
                'b' => [(int)$row['s1b'], (int)$row['s2b'], (int)$row['s3b']],
            ],
            'cancha'    => nombre_cancha($row['cancha']),
            'hora'      => $row['hora'],
        ];
    }

    resp(['success' => true, 'partidos' => $partidos]);
}

// ══════════════════════════════════════════════════════════════════
// evento — ficha completa de una fecha (pantalla Detalle del torneo)
// Lo que la web muestra en inscripcion.php + grafico-llaves-v2 DETALLES:
// fechas, ciudad, sede (complejo), cierre de inscripción, costo, premios,
// bases, y las categorías del evento con sexo, cupo y parejas inscriptas.
// ══════════════════════════════════════════════════════════════════
if ($action === 'evento') {
    $id = intGet('id');
    if (!$id) respErr('Falta id');

    $st = $mysqli2->prepare(
        "SELECT e.id, e.evento, e.nombre_evento2, e.url_amigable, e.fecha, e.fecha_fin, e.estado,
                e.flyer, e.id_circuito, e.id_tipo_evento, e.fecha_fin_inscripcion, e.hora_fin_inscripcion,
                e.costo1, e.costo2, e.premios, e.base_condiciones, e.reglamentacion, e.detalle,
                e.descripcion, e.flyer2, e.boton_inscripcion, e.cupos,
                c.nombre AS circuito, ci.nombre AS ciudad
         FROM _p_eventos e
         LEFT JOIN _circuitos c ON c.id = e.id_circuito
         LEFT JOIN ciudadespy ci ON ci.id = e.id_ciudad
         WHERE e.id = ? AND e.estado NOT IN ('inactivo','previsualizacion')
         LIMIT 1"
    );
    $st->bind_param('i', $id);
    $st->execute();
    $ev = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$ev) respErr('Evento no encontrado', 404);

    // Sede: complejo(s) del evento (puede no haber ninguno cargado).
    $sedes = [];
    $st = $mysqli2->prepare(
        "SELECT p.nombre, p.gmaps FROM _relacion_evento_complejo r
         JOIN _p_complejos p ON p.id = r.id_complejo
         WHERE r.id_evento = ? AND p.estado = 'activo'"
    );
    $st->bind_param('i', $id);
    $st->execute();
    $rs = $st->get_result();
    while ($row = $rs->fetch_assoc()) $sedes[] = ['nombre' => trim($row['nombre']), 'mapa' => $row['gmaps'] ?: null];
    $st->close();

    // Categorías del evento (activas) con parejas inscriptas.
    $cats = [];
    $st = $mysqli2->prepare(
        "SELECT rc.id_categoria, c.categoria, rc.sexo, rc.cupo, rc.costo, rc.max_parejas, rc.orden_visualizacion,
                (SELECT FLOOR(COUNT(*)/2) FROM _p_incripciones i
                  WHERE i.id_evento = rc.id_evento AND i.id_categoria = rc.id_categoria AND i.estado <> 'bloqueado') AS parejas
         FROM _relacion_evento_categoria rc
         JOIN _p_categorias c ON c.id = rc.id_categoria
         WHERE rc.id_evento = ? AND rc.estado = 'activo'
         ORDER BY rc.orden_visualizacion ASC, c.categoria ASC"
    );
    $st->bind_param('i', $id);
    $st->execute();
    $rs = $st->get_result();
    while ($row = $rs->fetch_assoc()) {
        $cats[] = [
            'id'      => (int)$row['id_categoria'],
            'nombre'  => $row['categoria'],
            'sexo'    => $row['sexo'] ?: null,           // hombre | mujer | mixto
            'cupo'    => $row['cupo'] ?: 'disponible',   // disponible | lleno
            'costo'   => (int)$row['costo'],
            'parejas' => (int)$row['parejas'],
        ];
    }
    $st->close();

    $ri = $mysqli2->query("SELECT COUNT(*) as c FROM _p_incripciones WHERE id_evento='$id' AND estado<>'bloqueado'");
    $inscriptos = $ri ? (int)floor((int)$ri->fetch_assoc()['c'] / 2) : 0;

    // Cronograma: la web muestra `descripcion` (un <img> con la programación) en
    // el acordeón "Cronograma"; flyer2 es la alternativa cargada como archivo.
    $cronogramaImg = null;
    if (!empty($ev['flyer2'])) {
        $cronogramaImg = '/img/flyers/' . $ev['flyer2'];
    } elseif (!empty($ev['descripcion']) && preg_match('/<img[^>]+src="([^"]+)"/i', $ev['descripcion'], $m)) {
        $cronogramaImg = $m[1];
    }

    // base_condiciones: 'b' = usa el PDF genérico del sitio (así lo hace inscripcion.php).
    $basesUrl = ($ev['base_condiciones'] && $ev['base_condiciones'] !== 'b')
        ? $ev['base_condiciones']
        : '/bases-y-condiciones/aceptacion-de-bases-y-condiciones-del-torneo-de-padel-general.pdf';

    resp([
        'success' => true,
        'evento' => [
            'id'                  => (int)$ev['id'],
            'evento'              => $ev['evento'],
            'subtitulo'           => $ev['nombre_evento2'] ?: null,
            'url_amigable'        => $ev['url_amigable'],
            'fecha'               => $ev['fecha'],
            'fecha_fin'           => $ev['fecha_fin'],
            'estado'              => $ev['estado'],
            'flyer_url'           => !empty($ev['flyer']) ? '/img/flyers/' . $ev['flyer'] : null,
            'id_circuito'         => $ev['id_circuito'],
            'circuito'            => $ev['circuito'],
            'id_tipo_evento'      => $ev['id_tipo_evento'],
            'ciudad'              => $ev['ciudad'] ?: null,
            'sedes'               => $sedes,
            'fecha_fin_inscripcion' => $ev['fecha_fin_inscripcion'] ?: null,
            'hora_fin_inscripcion'  => $ev['hora_fin_inscripcion'] ?: null,
            'costo'               => (int)$ev['costo1'],
            'costo_tardio'        => (int)$ev['costo2'],
            'premios'             => $ev['premios'] ?: null,
            'bases_url'           => $basesUrl,
            'reglamento_html'     => $ev['reglamentacion'] ?: null,
            'cronograma_html'     => $ev['descripcion'] ?: ($ev['detalle'] ?: null),
            'cronograma_img'      => $cronogramaImg,
            'boton_inscripcion'   => $ev['boton_inscripcion'] ?: 'si',
            'inscriptos'          => $inscriptos,
            'categorias'          => $cats,
        ],
    ]);
}

// ══════════════════════════════════════════════════════════════════
// categorias — categorías con inscriptos de un evento
// (misma query que tvt_api.php `cats_con_inscriptos`)
// ══════════════════════════════════════════════════════════════════
if ($action === 'categorias') {
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
// parejas — inscriptos de una categoría, agrupados por dupla
// (misma query que tvt_api.php `inscriptos_categoria`, sin campos de admin)
// ══════════════════════════════════════════════════════════════════
if ($action === 'parejas') {
    $idEvento = intGet('evento');
    $idCat    = intGet('categoria');
    if (!$idEvento || !$idCat) respErr('Falta evento o categoria');

    // CAST(...) < CAST(...) evita traer la dupla dos veces (una por jugador).
    $sql = "SELECT u1.nombre as nombre1, u1.apellido as apellido1,
                   u2.nombre as nombre2, u2.apellido as apellido2
            FROM _p_incripciones i
            LEFT JOIN _p_usuarios u1 ON u1.ci = i.ci
            LEFT JOIN _p_usuarios u2 ON u2.ci = i.ci_dupla
            WHERE i.id_evento = $idEvento
              AND i.id_categoria = $idCat
              AND i.estado <> 'bloqueado'
              AND CAST(i.ci AS UNSIGNED) < CAST(i.ci_dupla AS UNSIGNED)
            ORDER BY u1.apellido, u1.nombre";
    $r = $mysqli2->query($sql);
    $parejas = [];
    $n = 0;
    if ($r) while ($row = $r->fetch_assoc()) {
        $n++;
        $parejas[] = [
            'nro' => $n,
            'j1'  => trim(($row['nombre1'] ?? '') . ' ' . ($row['apellido1'] ?? '')),
            'j2'  => trim(($row['nombre2'] ?? '') . ' ' . ($row['apellido2'] ?? '')),
        ];
    }
    resp(['success' => true, 'parejas' => $parejas, 'total' => count($parejas)]);
}

// ══════════════════════════════════════════════════════════════════
// resultados — partidos de una categoría, agrupados por grupo
// Igual que tvt_api.php `resultados`, MÁS hora/fecha/cancha (esas columnas
// ya existen en _todosvstodos y el admin no las devolvía).
// `en_juego` es lo único que marca "en juego" — no hay marcador en vivo.
// ══════════════════════════════════════════════════════════════════
if ($action === 'resultados') {
    $idEvento = intGet('evento');
    $idCat    = intGet('categoria');
    if (!$idEvento || !$idCat) respErr('Falta evento o categoria');

    $sql = "SELECT t.id, t.grupo, t.partido_nro,
                   t.ci1_a, t.ci1_b, t.ci2_a, t.ci2_b,
                   t.rusultado_equipo1 AS s1a, t.resultado_equipo2  AS s1b,
                   t.resultado2_equipo1 AS s2a, t.resultado2_equipo2 AS s2b,
                   t.resultado3_equipo1 AS s3a, t.resultado3_equipo2 AS s3b,
                   t.en_juego, t.fecha, t.hora, t.cancha, t.complejo,
                   t.fecha_resultado,
                   t.ref_tipo_regustado1, t.ref_tipo_regustado2,
                   t.ref_etiqueta1, t.ref_etiqueta2,
                   g.grupo AS grupo_nombre, g.orden AS grupo_orden
            FROM _todosvstodos t
            LEFT JOIN _p_grupos g ON g.id = t.grupo
            WHERE t.evento = $idEvento AND t.categoria = $idCat
            ORDER BY g.orden ASC, t.partido_nro ASC
            LIMIT 300";
    $r = $mysqli2->query($sql);
    if (!$r) respErr('Error consultando resultados', 500);

    // Un solo SELECT de nombres para todos los CI del fixture: el admin hacía
    // 4 queries por partido (N+1). Acá es 1 sola para toda la categoría.
    $filas = [];
    $cis = [];
    while ($row = $r->fetch_assoc()) {
        foreach (['ci1_a', 'ci1_b', 'ci2_a', 'ci2_b'] as $k) {
            $ci = (string)$row[$k];
            if ($ci !== '' && $ci !== '0') $cis[$ci] = true;
        }
        $filas[] = $row;
    }

    $nombres = [];
    if ($cis) {
        $lista = implode(',', array_map(fn($ci) => "'" . $mysqli2->real_escape_string($ci) . "'", array_keys($cis)));
        $rn = $mysqli2->query("SELECT ci, nombre, apellido FROM _p_usuarios WHERE ci IN ($lista)");
        if ($rn) while ($u = $rn->fetch_assoc()) {
            $nombres[(string)$u['ci']] = trim($u['nombre'] . ' ' . $u['apellido']);
        }
    }
    $nom = fn($ci) => $nombres[(string)$ci] ?? '';

    // Clasificación: ya está calculada en `tabla_auxiliar` (la escribe
    // logica/calcular_clasificacion.php desde el admin). Acá SÓLO se lee.
    $tablas = [];
    $rt = $mysqli2->query("SELECT id_grupo, ci1_a, ci1_b, jugados, sg, puntos, la_posicion
                           FROM tabla_auxiliar
                           WHERE id_evento = $idEvento AND id_categoria = $idCat
                           ORDER BY id_grupo ASC, la_posicion ASC");
    if ($rt) while ($tr = $rt->fetch_assoc()) {
        $g = (int)$tr['id_grupo'];
        $tablas[$g][] = [
            'pareja'   => trim($nom($tr['ci1_a']) . ' / ' . $nom($tr['ci1_b']), ' /'),
            'pj'       => (int)$tr['jugados'],
            'sg'       => (int)$tr['sg'],
            'pts'      => (int)$tr['puntos'],
            'posicion' => (int)$tr['la_posicion'],
        ];
    }

    // Nombres y ORDEN VISUAL de las fases, calcados de
    // logica/todos.vs.todos.php:1996. Ojo: el 3er puesto va ÚLTIMO, después de
    // la Final — no en el orden de `_p_grupos.orden`, donde comparte el 14 con
    // Cuartos y quedaría en el medio del bracket.
    $FASES = [
        32 => ['nombre' => '16vos de final',   'orden' => 1],
        26 => ['nombre' => '8vos de final',    'orden' => 2],
        13 => ['nombre' => 'Cuartos de final', 'orden' => 3],
        15 => ['nombre' => 'Semifinal',        'orden' => 4],
        18 => ['nombre' => 'Final',            'orden' => 5],
        19 => ['nombre' => '3er puesto',       'orden' => 6],
    ];
    $ELIMINATORIAS = array_keys($FASES);

    // Etiquetas de cruce sin definir: "Primero GRUPO 1", "Ganador SEMI FINAL".
    $referencias = [];
    $rr = $mysqli2->query("SELECT id, referencia FROM _referencia_etiquetas");
    if ($rr) while ($x = $rr->fetch_assoc()) $referencias[(int)$x['id']] = $x['referencia'];

    $nombresGrupo = [];
    $rg = $mysqli2->query("SELECT id, grupo FROM _p_grupos");
    if ($rg) while ($x = $rg->fetch_assoc()) $nombresGrupo[(int)$x['id']] = $x['grupo'];

    /** Nombre a mostrar de un lado del cruce: jugador real, Bye Bye o referencia. */
    $ladoBracket = function ($ci, $refTipo, $refEtiqueta) use ($nom, $referencias, $nombresGrupo) {
        if ((int)$ci > 0) return $nom($ci);
        if ((int)$refTipo === 3) return 'Bye Bye';
        if ((int)$refEtiqueta > 0) {
            $ref = $referencias[(int)$refTipo] ?? '';
            $grp = $nombresGrupo[(int)$refEtiqueta] ?? '';
            return trim("$ref $grp");
        }
        return '';
    };

    $grupos = [];
    $fases   = [];
    foreach ($filas as $row) {
        $g = (int)$row['grupo'];
        $esEliminatoria = in_array($g, $ELIMINATORIAS, true);
        $destino = $esEliminatoria ? 'fases' : 'grupos';

        if ($destino === 'fases') {
            if (!isset($fases[$g])) {
                $fases[$g] = [
                    'id'       => $g,
                    'nombre'   => $FASES[$g]['nombre'] ?? ($row['grupo_nombre'] ?: "FASE $g"),
                    'orden'    => $FASES[$g]['orden'] ?? 99,
                    'partidos' => [],
                ];
            }
        } elseif (!isset($grupos[$g])) {
            $grupos[$g] = [
                'grupo'    => $g,
                'nombre'   => $row['grupo_nombre'] ?: "GRUPO $g",
                'tabla'    => $tablas[$g] ?? [],
                'partidos' => [],
            ];
        }
        $enJuego = ($row['en_juego'] === 'si');
        $jugado  = !empty($row['fecha_resultado']) || (int)$row['s1a'] > 0 || (int)$row['s1b'] > 0;

        $partido = [
            'id'        => (int)$row['id'],
            'nro'       => (int)$row['partido_nro'],
            'prefijo'   => prefijo_partido($row['grupo_nombre'] ?? '', (int)$row['partido_nro']),
            'pareja_a'  => trim($nom($row['ci1_a']) . ' / ' . $nom($row['ci1_b']), ' /'),
            'pareja_b'  => trim($nom($row['ci2_a']) . ' / ' . $nom($row['ci2_b']), ' /'),
            // Por jugador: la web los muestra en dos líneas, no concatenados.
            'j1a'       => $nom($row['ci1_a']),
            'j1b'       => $nom($row['ci1_b']),
            'j2a'       => $nom($row['ci2_a']),
            'j2b'       => $nom($row['ci2_b']),
            'sets'      => [
                'a' => [(int)$row['s1a'], (int)$row['s2a'], (int)$row['s3a']],
                'b' => [(int)$row['s1b'], (int)$row['s2b'], (int)$row['s3b']],
            ],
            // El estado que consume la app: espeja en_juego, no inventa nada.
            'estado'    => $enJuego ? 'en_juego' : ($jugado ? 'finalizado' : 'por_jugar'),
            'fecha'     => $row['fecha'],
            'hora'      => $row['hora'],
            'cancha'    => nombre_cancha($row['cancha']),
            'complejo'  => $row['complejo'],
        ];

        if ($destino === 'fases') {
            // El bracket muestra el cruce aunque todavía no haya jugadores.
            $partido['j1a'] = $ladoBracket($row['ci1_a'], $row['ref_tipo_regustado1'], $row['ref_etiqueta1']);
            $partido['j1b'] = (int)$row['ci1_b'] > 0 ? $nom($row['ci1_b']) : '';
            $partido['j2a'] = $ladoBracket($row['ci2_a'], $row['ref_tipo_regustado2'], $row['ref_etiqueta2']);
            $partido['j2b'] = (int)$row['ci2_b'] > 0 ? $nom($row['ci2_b']) : '';

            // Ganador por sets; si un lado es Bye Bye, el rival pasa 1-0.
            $sa = $sb = 0;
            foreach ([['s1a','s1b'], ['s2a','s2b'], ['s3a','s3b']] as $par) {
                $x = (int)$row[$par[0]]; $y = (int)$row[$par[1]];
                if ($x === 0 && $y === 0) continue;
                if ($x > $y) $sa++; else $sb++;
            }
            $hayResultado = ($sa + $sb) > 0;
            $ganador = ($hayResultado && $sa !== $sb) ? ($sa > $sb ? 'a' : 'b') : null;
            $bye = false;
            if (!$ganador) {
                if ((int)$row['ref_tipo_regustado1'] === 3 && (int)$row['ci2_a'] > 0) { $ganador = 'b'; $bye = true; }
                elseif ((int)$row['ref_tipo_regustado2'] === 3 && (int)$row['ci1_a'] > 0) { $ganador = 'a'; $bye = true; }
            }

            $partido['ganador'] = $ganador;
            $partido['bye']     = $bye;
            // Cruce sin definir: la web lo pinta en itálica y gris (.bracket-pend-nm).
            $partido['pendA'] = ((int)$row['ci1_a'] === 0 && (int)$row['ref_tipo_regustado1'] !== 3);
            $partido['pendB'] = ((int)$row['ci2_a'] === 0 && (int)$row['ref_tipo_regustado2'] !== 3);
            // La web pone "-" cuando no hay marcador, no vacío.
            $partido['scoreA']  = $hayResultado ? implode(' ', array_filter([
                ((int)$row['s1a'] || (int)$row['s1b']) ? (int)$row['s1a'] : null,
                ((int)$row['s2a'] || (int)$row['s2b']) ? (int)$row['s2a'] : null,
                ((int)$row['s3a'] || (int)$row['s3b']) ? (int)$row['s3a'] : null,
            ], fn($v) => $v !== null)) : '-';
            $partido['scoreB']  = $hayResultado ? implode(' ', array_filter([
                ((int)$row['s1a'] || (int)$row['s1b']) ? (int)$row['s1b'] : null,
                ((int)$row['s2a'] || (int)$row['s2b']) ? (int)$row['s2b'] : null,
                ((int)$row['s3a'] || (int)$row['s3b']) ? (int)$row['s3b'] : null,
            ], fn($v) => $v !== null)) : '-';

            $fases[$g]['partidos'][] = $partido;
        } else {
            $grupos[$g]['partidos'][] = $partido;
        }
    }

    $listaFases = array_values($fases);
    usort($listaFases, fn($a, $b) => $a['orden'] <=> $b['orden']);

    resp([
        'success' => true,
        'grupos'  => array_values($grupos),
        'fases'   => $listaFases,
    ]);
}

// ══════════════════════════════════════════════════════════════════
// resultados_ic — fixture de un evento INTERCLUBES (id_tipo_evento 5)
// Misma fuente y mismo criterio que logica/interclubes.llaves.inc.php:
// categorías con sorteo, grupos de clubes, series club vs club (partidos
// dupla vs dupla + desempate), posiciones (ic_posiciones) y llaves.
// ══════════════════════════════════════════════════════════════════
if ($action === 'resultados_ic') {
    $idEvento = intGet('evento');
    if (!$idEvento) respErr('Falta evento');
    $fnIc = file_exists(__DIR__ . '/interclubes.functions.php') ? __DIR__ . '/interclubes.functions.php' : $_SERVER['DOCUMENT_ROOT'] . '/interclubes.functions.php';
    if (!file_exists($fnIc)) respErr('Interclubes no disponible', 500);
    require_once $fnIc;

    // Categorías con sorteo, visibles en llaves (igual que la web).
    $st = $mysqli2->prepare(
        "SELECT s.id_categoria, COALESCE(cat.categoria,'?') AS categoria
           FROM _ic_sorteo s LEFT JOIN _p_categorias cat ON cat.id = s.id_categoria
           JOIN _relacion_evento_categoria rec
             ON rec.id_evento = s.id_evento AND rec.id_categoria = s.id_categoria
            AND rec.estado = 'activo' AND rec.visualizar_en_llaves = 'si'
          WHERE s.id_evento = ? GROUP BY s.id_categoria ORDER BY cat.categoria ASC");
    $st->bind_param('i', $idEvento);
    $st->execute();
    $rs = $st->get_result();
    $cats = [];
    while ($r = $rs->fetch_assoc()) $cats[] = ['id' => (int)$r['id_categoria'], 'nombre' => $r['categoria']];
    $st->close();

    $idCat = intGet('categoria');
    $idsCat = array_column($cats, 'id');
    if (!in_array($idCat, $idsCat, true)) $idCat = $idsCat[0] ?? 0;
    if (!$idCat) resp(['success' => true, 'categorias' => [], 'categoria' => 0, 'grupos' => [], 'llaves' => [], 'podio' => null]);

    // Clubes por grupo (orden de sorteo)
    $st = $mysqli2->prepare(
        "SELECT s.grupo, s.id_club, cl.nombre FROM _ic_sorteo s JOIN _p_clubes cl ON cl.id = s.id_club
          WHERE s.id_evento = ? AND s.id_categoria = ? ORDER BY s.grupo ASC, s.posicion ASC");
    $st->bind_param('ii', $idEvento, $idCat);
    $st->execute();
    $rs = $st->get_result();
    $grupos = []; $mapa = [];
    while ($r = $rs->fetch_assoc()) {
        $grupos[(int)$r['grupo']][(int)$r['id_club']] = trim($r['nombre']);
        $mapa[(int)$r['id_club']] = trim($r['nombre']);
    }
    $st->close();
    $nom = fn($id) => $mapa[(int)$id] ?? ('Club #' . (int)$id);

    // Partidos de la categoría + nombres cortos de jugadores
    $st = $mysqli2->prepare("SELECT * FROM _ic_partidos WHERE id_evento = ? AND id_categoria = ? ORDER BY es_desempate ASC, id ASC");
    $st->bind_param('ii', $idEvento, $idCat);
    $st->execute();
    $rs = $st->get_result();
    $partidos = []; $cis = [];
    while ($r = $rs->fetch_assoc()) { $partidos[] = $r; foreach (['ci1_a','ci1_b','ci2_a','ci2_b'] as $k) if (trim((string)$r[$k]) !== '') $cis[trim($r[$k])] = 1; }
    $st->close();
    $nombres = [];
    if ($cis) {
        $in = "'" . implode("','", array_map(fn($c) => $mysqli2->real_escape_string($c), array_keys($cis))) . "'";
        $r2 = $mysqli2->query("SELECT ci, " . ic_sql_nombre_corto() . " n FROM _p_usuarios WHERE TRIM(ci) IN ($in)");
        if ($r2) while ($x = $r2->fetch_assoc()) $nombres[trim($x['ci'])] = $x['n'];
    }
    $dupla = fn($a, $b) => trim(implode(' / ', array_filter([$nombres[trim((string)$a)] ?? '', $nombres[trim((string)$b)] ?? ''])));

    $viejo = ic_criterio_viejo($idEvento, $idCat);
    $slots = IC_SLOTS_SERIE;

    /** Serie club A vs club B como la ve la app. */
    $serie = function (string $fase, int $a, int $b, array $ms) use ($nom, $dupla, $slots) {
        [$wA, $wB, $definida, $ganador, $necesitaDes] = ic_estado_serie($ms, $a, $b, $slots);
        $enJuego = false;
        $lista = [];
        $n = 0;
        foreach ($ms as $m) {
            if (($m['en_juego'] ?? 'no') === 'si') $enJuego = true;
            [$s1, $s2] = ic_sets_partido($m);
            $gan = ic_ganador_partido($m);
            $sets = [];
            foreach ([['s1c1','s1c2'],['s2c1','s2c2'],['s3c1','s3c2']] as [$ka,$kb]) {
                $va = (int)$m[$ka]; $vb = (int)$m[$kb];
                if ($va === 0 && $vb === 0) continue;
                // Desde el lado del club A de la serie
                $sets[] = ((int)$m['club1'] === $a) ? [$va, $vb] : [$vb, $va];
            }
            $ladoA = ((int)$m['club1'] === $a);
            $n += (int)$m['es_desempate'] === 1 ? 0 : 1;
            $lista[] = [
                'id'        => (int)$m['id'],
                'nro'       => (int)$m['es_desempate'] === 1 ? 0 : $n,
                'desempate' => (int)$m['es_desempate'] === 1,
                'duplaA'    => $ladoA ? $dupla($m['ci1_a'], $m['ci1_b']) : $dupla($m['ci2_a'], $m['ci2_b']),
                'duplaB'    => $ladoA ? $dupla($m['ci2_a'], $m['ci2_b']) : $dupla($m['ci1_a'], $m['ci1_b']),
                'sets'      => $sets,
                // 'A' | 'B' | null (0 = sin definir / empatado)
                'ganador'   => $gan === 0 ? null : ((($gan === 1) === $ladoA) ? 'A' : 'B'),
                'enJuego'   => ($m['en_juego'] ?? 'no') === 'si',
            ];
        }
        return [
            'fase'      => $fase,
            'clubA'     => ['id' => $a, 'nombre' => $nom($a)],
            'clubB'     => ['id' => $b, 'nombre' => $nom($b)],
            'ganadas'   => [$wA, $wB],
            'definida'  => $definida,
            'ganador'   => $ganador ? ($ganador === $a ? 'A' : 'B') : null,
            'desempate' => $necesitaDes,
            'enJuego'   => $enJuego,
            'slots'     => $slots,
            'partidos'  => $lista,
        ];
    };

    // Grupos: series (todos vs todos) + posiciones
    $out = [];
    foreach ($grupos as $g => $clubes) {
        $ids = array_keys($clubes);
        $msGrupo = array_values(array_filter($partidos, fn($m) => $m['fase'] === 'grupo' && isset($clubes[(int)$m['club1']]) && isset($clubes[(int)$m['club2']])));
        $series = [];
        for ($i = 0; $i < count($ids); $i++) for ($j = $i + 1; $j < count($ids); $j++) {
            $a = $ids[$i]; $b = $ids[$j];
            $ms = array_values(array_filter($msGrupo, fn($m) => (min((int)$m['club1'], (int)$m['club2']) === min($a, $b)) && (max((int)$m['club1'], (int)$m['club2']) === max($a, $b))));
            $series[] = $serie('grupo', $a, $b, $ms);
        }
        $pos = ic_posiciones($clubes, $msGrupo, [], $viejo);
        $tabla = [];
        $p = 0;
        foreach ($pos as $c) {
            $tabla[] = [
                'posicion' => ++$p,
                'club'     => $c['club'],
                'sj'       => (int)$c['sj'],
                'sg'       => (int)$c['sg'],
                'sp'       => (int)$c['sp'],
                'games'    => (int)$c['gamesF'] - (int)$c['gamesC'],
                'pts'      => (int)$c['pts'],
            ];
        }
        $out[] = ['nombre' => 'GRUPO ' . $g, 'clubes' => array_map(fn($id) => ['id' => $id, 'nombre' => $clubes[$id]], $ids), 'series' => $series, 'tabla' => $tabla];
    }

    // Llaves: semi1/semi2/final/tercer
    $st = $mysqli2->prepare("SELECT fase, clubA, clubB FROM _ic_llaves WHERE id_evento=? AND id_categoria=?");
    $st->bind_param('ii', $idEvento, $idCat);
    $st->execute();
    $rs = $st->get_result();
    $llaves = [];
    while ($r = $rs->fetch_assoc()) {
        $fase = $r['fase'];
        $ms = array_values(array_filter($partidos, fn($m) => $m['fase'] === $fase));
        $llaves[$fase] = $serie($fase, (int)$r['clubA'], (int)$r['clubB'], $ms);
    }
    $st->close();
    $podio = null;
    if (isset($llaves['final']) && $llaves['final']['definida']) {
        $f = $llaves['final'];
        $podio = [
            'campeon' => $f['ganador'] === 'A' ? $f['clubA']['nombre'] : $f['clubB']['nombre'],
            'vice'    => $f['ganador'] === 'A' ? $f['clubB']['nombre'] : $f['clubA']['nombre'],
            'tercero' => (isset($llaves['tercer']) && $llaves['tercer']['definida'])
                ? ($llaves['tercer']['ganador'] === 'A' ? $llaves['tercer']['clubA']['nombre'] : $llaves['tercer']['clubB']['nombre'])
                : null,
        ];
    }

    resp(['success' => true, 'categorias' => $cats, 'categoria' => $idCat, 'criterioViejo' => $viejo, 'grupos' => $out, 'llaves' => $llaves, 'podio' => $podio]);
}

// ══════════════════════════════════════════════════════════════════
// ranking — MISMA estructura que la página web (logica/mostrar-ranking.php):
// una tarjeta por categoría, y dentro cada jugador con el desglose por fecha.
//
// ⚠️ DEUDA: el criterio de puntos está duplicado (acá, en tvt_api.php ~490 y
// en logica/mostrar-ranking.php). Si cambia allá, cambiar acá. El arreglo real
// es extraerlo a un bt_ranking.functions.php compartido.
//
// Es N+1 (una consulta por jugador por evento) y el endpoint es público:
// por eso va con caché en disco.
// ══════════════════════════════════════════════════════════════════
if ($action === 'ranking') {
    $circ  = intGet('circuito', 1);
    $q     = strGet('q');

    $cacheFile = sys_get_temp_dir() . "/bt_rank_cat_{$circ}.json";
    $usarCache = $q === '' && is_readable($cacheFile) && (time() - filemtime($cacheFile)) < 300;
    if ($usarCache) {
        header('X-BT-Cache: hit');
        echo file_get_contents($cacheFile);
        exit;
    }

    // Mapa de categorías: id → padre + nombre.
    $catMap = [];
    $rCats = $mysqli2->query("SELECT id_categoria, id_categoria_padre, categoria FROM v_p_categorias");
    if ($rCats) while ($rc = $rCats->fetch_assoc()) {
        $catMap[(int)$rc['id_categoria']] = [
            'padre'  => (int)$rc['id_categoria_padre'],
            'nombre' => $rc['categoria'],
        ];
    }

    // Nombres de evento, para el desglose por fecha.
    $eventoNombre = [];
    $rEv = $mysqli2->query("SELECT id, evento FROM _p_eventos");
    if ($rEv) while ($e = $rEv->fetch_assoc()) $eventoNombre[(int)$e['id']] = $e['evento'];

    $categorias = [];

    foreach ($catMap as $idCat => $info) {
        $padreCat = $info['padre'];
        if ($padreCat <= 0) continue; // sólo categorías hijo, como la web

        $rJug = $mysqli2->query("SELECT DISTINCT r.ci, u.nombre, u.apellido
                   FROM _ranking r
                   LEFT JOIN _p_usuarios u ON u.ci = r.ci
                   WHERE r.circuito = $circ AND r.categoria = $idCat AND r.puntos > 0");
        if (!$rJug) continue;

        $jugadores = [];
        while ($jug = $rJug->fetch_assoc()) {
            $ciRaw = (string)$jug['ci'];
            $ci = $mysqli2->real_escape_string($ciRaw);
            if (trim($ci) === '') continue;

            $nombre   = $jug['nombre'] ?? '';
            $apellido = $jug['apellido'] ?? '';

            if ($q !== '' && stripos("$nombre $apellido $ciRaw", $q) === false) continue;

            $totalPadre = 0;
            $totalCat   = 0;
            $detalle    = [];

            $rEvts = $mysqli2->query("SELECT DISTINCT i.id_evento
                        FROM _p_incripciones i
                        JOIN _p_eventos ev ON ev.id = i.id_evento
                        WHERE (i.ci = '$ci' OR i.ci_dupla = '$ci')
                          AND (i.id_categoria = $idCat OR i.id_categoria = $padreCat)
                          AND ev.id_circuito = $circ");
            if (!$rEvts) continue;

            while ($rowEvt = $rEvts->fetch_assoc()) {
                $evId = (int)$rowEvt['id_evento'];

                $resPP = $mysqli2->query("SELECT puntos FROM _ranking
                    WHERE evento = $evId AND circuito = $circ AND ci = '$ci' AND categoria = $padreCat");
                $ptsPadre = ($x = ($resPP ? $resPP->fetch_assoc() : null)) ? abs($x['puntos']) : 0;

                $resPH = $mysqli2->query("SELECT puntos FROM _ranking
                    WHERE evento = $evId AND circuito = $circ AND ci = '$ci' AND categoria = $idCat");
                $ptsCat = ($x = ($resPH ? $resPH->fetch_assoc() : null)) ? abs($x['puntos']) : 0;

                $totalPadre += $ptsPadre;
                $totalCat   += $ptsCat;

                // La web sólo lista la fecha si sumó algo en alguna de las dos.
                if ($ptsPadre > 0 || $ptsCat > 0) {
                    $detalle[] = [
                        'evento'       => $eventoNombre[$evId] ?? "Fecha #$evId",
                        'ptsPadre'     => $ptsPadre,
                        'ptsCategoria' => $ptsCat,
                    ];
                }
            }

            $jugadores[] = [
                'ci'           => $ciRaw,
                'nombre'       => $nombre,
                'apellido'     => $apellido,
                'ptsPadre'     => $totalPadre,
                'ptsCategoria' => $totalCat,
                'total'        => $totalPadre + $totalCat,
                'detalle'      => $detalle,
            ];
        }

        if (!$jugadores) continue;

        usort($jugadores, fn($a, $b) => $b['total'] <=> $a['total']);
        foreach ($jugadores as $i => &$j) { $j['pos'] = $i + 1; }
        unset($j);

        $categorias[] = [
            'id'           => $idCat,
            'nombre'       => $info['nombre'],
            'colPadre'     => $catMap[$padreCat]['nombre'] ?? 'Mixto',
            'colCategoria' => $info['nombre'],
            'jugadores'    => $jugadores,
        ];
    }

    // Mismo orden de tarjetas que la web: primera aparición de la categoría en
    // _ranking (la web itera las filas tal cual vienen; equivale a MIN(id)).
    $ordenCat = [];
    $rOrd = $mysqli2->query("SELECT categoria, MIN(id) m FROM _ranking WHERE circuito = $circ AND puntos > 0 GROUP BY categoria ORDER BY m");
    if ($rOrd) while ($o = $rOrd->fetch_assoc()) $ordenCat[(int)$o['categoria']] = count($ordenCat);
    usort($categorias, fn($a, $b) => ($ordenCat[$a['id']] ?? PHP_INT_MAX) <=> ($ordenCat[$b['id']] ?? PHP_INT_MAX));

    // TOP 10 — PUNTOS GENERALES, como en la web: por CI se suma el total de todas
    // las categorías; primer nombre + primer apellido en mayúsculas.
    $top10 = [];
    foreach ($categorias as $cat) {
        foreach ($cat['jugadores'] as $j) {
            $ciT = $j['ci'];
            if (!isset($top10[$ciT])) {
                $top10[$ciT] = [
                    'ci'       => $ciT,
                    'nombre'   => mb_strtoupper(explode(' ', trim($j['nombre']))[0] ?? '', 'UTF-8'),
                    'apellido' => mb_strtoupper(explode(' ', trim($j['apellido']))[0] ?? '', 'UTF-8'),
                    'total'    => 0,
                ];
            }
            $top10[$ciT]['total'] += $j['total'];
        }
    }
    usort($top10, fn($a, $b) => $b['total'] <=> $a['total']);
    $top10 = array_slice(array_values($top10), 0, 10);

    $json = json_encode(['success' => true, 'categorias' => $categorias, 'top10' => $top10], JSON_UNESCAPED_UNICODE);
    if ($q === '') @file_put_contents($cacheFile, $json);
    header('X-BT-Cache: miss');
    echo $json;
    exit;
}

// ══════════════════════════════════════════════════════════════════
// ranking_interclubes — ranking de CLUBES del circuito, MISMA lógica que
// logica/mostrar-ranking-interclubes.php (10-ago): cada categoría reparte
// puntos por posición final, el club suma sus categorías, el circuito acumula
// los eventos interclubes CULMINADOS. Cálculo en vivo (6 queries).
//
// ⚠️ DEUDA: la agregación está duplicada con la página web (~70 líneas). Las
// funciones ic_* sí son compartidas (interclubes.functions.php). El arreglo
// real es mover el bloque a una función ahí y que la página la llame.
// Params: circuito (id, default 1) · ev (id de una fecha; 0 = acumulado).
// ══════════════════════════════════════════════════════════════════
if ($action === 'ranking_interclubes') {
    $circ  = intGet('circuito', 1);
    $evSel = intGet('ev', 0);
    $fnIc = file_exists(__DIR__ . '/interclubes.functions.php') ? __DIR__ . '/interclubes.functions.php' : $_SERVER['DOCUMENT_ROOT'] . '/interclubes.functions.php';
    if (!file_exists($fnIc)) respErr('Interclubes no disponible', 500);
    require_once $fnIc;

    $txt = function ($v): string {
        $v = (string)$v;
        if (!mb_check_encoding($v, 'UTF-8')) $v = mb_convert_encoding($v, 'UTF-8', 'ISO-8859-1');
        return mb_strtoupper(trim($v), 'UTF-8');
    };

    // Circuito: fecha_fin cargada y pasada = cerrado (campeón); si no, líder.
    $rowC = $mysqli2->query("SELECT fecha_fin FROM _circuitos WHERE id = $circ")->fetch_assoc();
    $finC = $rowC['fecha_fin'] ?? null;
    $circuitoCerrado = $finC && $finC !== '0000-00-00' && date('Y-m-d') >= $finC;

    $eventosIC = [];   // id => nombre (sólo culminados, orden cronológico)
    $rEv = $mysqli2->query("SELECT id, evento FROM _p_eventos
        WHERE id_circuito = $circ AND id_tipo_evento = 5 AND estado = 'culminado'
        ORDER BY fecha ASC, id ASC");
    if ($rEv) while ($r = $rEv->fetch_assoc()) $eventosIC[(int)$r['id']] = $r['evento'];

    $fechasPendientes = (int)($mysqli2->query("SELECT COUNT(*) c FROM _p_eventos
        WHERE id_circuito = $circ AND id_tipo_evento = 5 AND estado IN ('activo','registro')")->fetch_assoc()['c'] ?? 0);
    if ($fechasPendientes > 0) $circuitoCerrado = false;

    if (!isset($eventosIC[$evSel])) $evSel = 0;
    $eventosVer = $evSel ? [$evSel => $eventosIC[$evSel]] : $eventosIC;

    $ranking = []; $catNombre = []; $catsEnCurso = []; $escalaEv = [];
    $etiquetaPos = [1 => 'Campeón', 2 => 'Finalista', 3 => 'Tercer puesto', 4 => 'Cuarto puesto', 0 => 'Participación'];

    if ($eventosVer) {
        $ids = implode(',', array_map('intval', array_keys($eventosVer)));

        $clubes = [];
        $res = $mysqli2->query("SELECT id, id_evento, nombre FROM _p_clubes WHERE id_evento IN ($ids)");
        while ($r = $res->fetch_assoc()) $clubes[(int)$r['id_evento']][(int)$r['id']] = $r['nombre'];

        $sorteo = [];
        $res = $mysqli2->query("SELECT id_evento, id_categoria, id_club FROM _ic_sorteo WHERE id_evento IN ($ids) ORDER BY posicion ASC");
        while ($r = $res->fetch_assoc()) {
            $ev = (int)$r['id_evento']; $cl = (int)$r['id_club'];
            if (isset($clubes[$ev][$cl])) $sorteo[$ev][(int)$r['id_categoria']][$cl] = $clubes[$ev][$cl];
        }

        $partidos = [];
        $res = $mysqli2->query("SELECT * FROM _ic_partidos WHERE id_evento IN ($ids)");
        while ($r = $res->fetch_assoc()) $partidos[(int)$r['id_evento']][(int)$r['id_categoria']][] = $r;

        $llaves = [];
        $res = $mysqli2->query("SELECT id_evento, id_categoria, fase, clubA, clubB FROM _ic_llaves WHERE id_evento IN ($ids)");
        while ($r = $res->fetch_assoc()) $llaves[(int)$r['id_evento']][(int)$r['id_categoria']][$r['fase']] = $r;

        $res = $mysqli2->query("SELECT id, categoria FROM _p_categorias");
        while ($r = $res->fetch_assoc()) $catNombre[(int)$r['id']] = $r['categoria'];

        $matriz = ic_puntos_matriz($mysqli2, array_keys($eventosVer));

        foreach ($eventosVer as $idEv => $nombreEv) {
            $catsEnCurso[$idEv] = 0;
            $catsEv = $sorteo[$idEv] ?? [];
            uksort($catsEv, fn($a, $b) => strcmp($catNombre[$a] ?? '', $catNombre[$b] ?? ''));
            $catRef = array_key_first($catsEv);
            if ($catRef !== null)
                foreach ([1, 2, 3, 4, 0] as $p) $escalaEv[$idEv][$p] = ic_puntos_pos($matriz, $idEv, $catRef, $p);
            foreach ($catsEv as $idCat => $clubesCat) {
                $posiciones = ic_posiciones_categoria($clubesCat, $llaves[$idEv][$idCat] ?? [], $partidos[$idEv][$idCat] ?? []);
                if (!$posiciones) { $catsEnCurso[$idEv]++; continue; }
                foreach ($posiciones as $idClub => $pos) {
                    $nombreClub = $clubesCat[$idClub] ?? '';
                    if ($nombreClub === '') continue;
                    $k = ic_clave_club($nombreClub);
                    if (!isset($ranking[$k]))
                        $ranking[$k] = ['nombre' => $nombreClub, 'total' => 0, 'conteo' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 0 => 0], 'eventos' => []];
                    $pts = ic_puntos_pos($matriz, $idEv, $idCat, $pos);
                    $ranking[$k]['total'] += $pts;
                    $ranking[$k]['conteo'][$pos]++;
                    if (!isset($ranking[$k]['eventos'][$idEv]))
                        $ranking[$k]['eventos'][$idEv] = ['pts' => 0, 'conteo' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 0 => 0], 'cats' => []];
                    $ranking[$k]['eventos'][$idEv]['pts'] += $pts;
                    $ranking[$k]['eventos'][$idEv]['conteo'][$pos]++;
                    $ranking[$k]['eventos'][$idEv]['cats'][] = ['cat' => $txt($catNombre[$idCat] ?? "Cat. $idCat"), 'pos' => $pos, 'label' => $etiquetaPos[$pos], 'pts' => $pts];
                }
            }
        }
    }

    // Orden: puntos → títulos → finales → terceros → cuartos → nombre
    $clave = fn($c) => [$c['total'], $c['conteo'][1], $c['conteo'][2], $c['conteo'][3], $c['conteo'][4]];
    uasort($ranking, function ($a, $b) use ($clave) {
        $cmp = $clave($b) <=> $clave($a);
        return $cmp !== 0 ? $cmp : strcmp($a['nombre'], $b['nombre']);
    });
    $ranking = array_values($ranking);

    $campeones = [];
    if ($ranking) {
        $campeones[] = $ranking[0];
        for ($i = 1; $i < count($ranking); $i++) {
            if ($clave($ranking[$i]) !== $clave($ranking[0])) break;
            $campeones[] = $ranking[$i];
        }
    }

    // Campeón de cada fecha (mismo desempate, dentro de la fecha)
    $campeonEvento = [];
    foreach (array_keys($eventosVer) as $idEv) {
        $mejor = null; $nombres = [];
        foreach ($ranking as $c) {
            if (!isset($c['eventos'][$idEv])) continue;
            $e = $c['eventos'][$idEv];
            $k = [$e['pts'], $e['conteo'][1], $e['conteo'][2], $e['conteo'][3], $e['conteo'][4]];
            if ($mejor === null || $k > $mejor) { $mejor = $k; $nombres = [$txt($c['nombre'])]; }
            elseif ($k === $mejor) $nombres[] = $txt($c['nombre']);
        }
        if ($nombres) $campeonEvento[] = ['evento' => $txt($eventosVer[$idEv]), 'clubes' => $nombres];
    }

    $conteoOut = fn($c) => ['participacion' => $c[0], 'cuarto' => $c[4], 'tercero' => $c[3], 'finalista' => $c[2], 'campeon' => $c[1]];
    $clubesOut = [];
    foreach ($ranking as $i => $c) {
        $evsOut = [];
        foreach ($c['eventos'] as $idEv => $e)
            $evsOut[] = ['id' => $idEv, 'nombre' => $txt($eventosIC[$idEv] ?? ''), 'pts' => $e['pts'], 'cats' => $e['cats'], 'enCurso' => $catsEnCurso[$idEv] ?? 0];
        $clubesOut[] = ['pos' => $i + 1, 'club' => $txt($c['nombre']), 'total' => $c['total'], 'conteo' => $conteoOut($c['conteo']), 'eventos' => $evsOut];
    }

    $escalasDistintas = count(array_unique(array_map('json_encode', $escalaEv))) > 1;
    $esc1 = $escalaEv ? reset($escalaEv) : IC_PUNTOS_POSICION;

    resp([
        'success'          => true,
        'circuitoCerrado'  => $circuitoCerrado,
        'fechasPendientes' => $fechasPendientes,
        'eventos'          => array_map(fn($id, $n) => ['id' => $id, 'nombre' => $txt($n)], array_keys($eventosIC), $eventosIC),
        'evSel'            => $evSel,
        'hero'             => $campeones ? [
            'tipo'    => $evSel ? 'campeonFecha' : ($circuitoCerrado ? 'campeonCircuito' : 'lider'),
            'clubes'  => array_map(fn($c) => $txt($c['nombre']), $campeones),
            'pts'     => $campeones[0]['total'],
            'titulos' => $campeones[0]['conteo'][1],
            'fechas'  => count($eventosVer),
            'empate'  => count($campeones) > 1,
        ] : null,
        'campeonesFecha'   => $campeonEvento,
        'clubes'           => $clubesOut,
        'escala'           => $escalasDistintas ? null : $conteoOut($esc1),
    ]);
}

// ══════════════════════════════════════════════════════════════════
// buscar_jugador — autocompletado del wizard de inscripción
// Devuelve SÓLO lo que la app muestra. Nada de cel completo ni email:
// esto es público y sin login.
// ══════════════════════════════════════════════════════════════════
if ($action === 'buscar_jugador') {
    $q = $mysqli2->real_escape_string(strGet('q'));
    if (strlen($q) < 3) respErr('Mínimo 3 caracteres para buscar');

    $sql = "SELECT ci, nombre, apellido, ciudad
            FROM _p_usuarios
            WHERE ci LIKE '%$q%'
               OR CONCAT(nombre,' ',apellido) LIKE '%$q%'
            ORDER BY apellido, nombre
            LIMIT 10";
    $r = $mysqli2->query($sql);
    $jugadores = [];
    if ($r) while ($row = $r->fetch_assoc()) $jugadores[] = $row;

    resp(['success' => true, 'jugadores' => $jugadores, 'total' => count($jugadores)]);
}
