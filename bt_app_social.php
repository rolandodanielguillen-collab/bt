<?php
/**
 * bt_app_social.php — Comunidad de la app móvil (muro, duplas, chats).
 * ================================================================
 * Archivo NUEVO y aislado. Todo pasa por token de jugador
 * (`bt_app_auth.php`). Ni una consulta toca las tablas del sitio salvo
 * `_p_usuarios`: lectura de nombre/ciudad del autor, y desde el 18-ago-2026
 * los MISMOS updates que ya hacen actualizar-perfil.php / subir-foto.php /
 * cambiar-password.php (con sesión web) — acá con token, para la app.
 *
 * Tablas: ver `sql/2026-08-10_social.sql`.
 *
 * Acciones de lectura : perfil · perfil_evento · muro · likes · duplas · chats · mensajes · buscar
 * Acciones de escritura: publicar · comentar · like · publicar_dupla ·
 *                        abrir_chat · enviar · actualizar_perfil · subir_foto ·
 *                        cambiar_password
 * ================================================================
 */

require_once __DIR__ . '/bt_app_auth.inc.php';
require_once __DIR__ . '/bt_app_moderacion.inc.php';

/** Techo de caracteres. Un muro sin límite se llena de basura pegada. */
const MAX_TEXTO = 1000;
const PAGINA = 20;

$yo = jugadorActual($mysqli2);
$miCi = $yo['ci'];

$action = inp('action');
if (!$action) respErr('Falta parámetro action');

$esEscritura = in_array($action, ['publicar', 'comentar', 'like', 'publicar_dupla', 'abrir_chat', 'enviar', 'actualizar_perfil', 'subir_foto', 'cambiar_password',
                                    'aceptar_terminos', 'bloquear', 'desbloquear', 'denunciar', 'marcar_leidas', 'eliminar_cuenta', 'registrar_dispositivo'], true);
if ($esEscritura && ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    respErr('Esta acción requiere POST', 405);
}

// ── Helpers locales ──────────────────────────────────────────────

/** Autor listo para la app: nombre armado + línea de contexto. */
function autorDe(array $row, string $prefijo = ''): array {
    return [
        'ci'     => $row[$prefijo . 'ci'] ?? '',
        'nombre' => trim(($row[$prefijo . 'nombre'] ?? '') . ' ' . ($row[$prefijo . 'apellido'] ?? '')),
        'meta'   => $row[$prefijo . 'ciudad'] ?? '',
        'fotoUrl' => fotoUrl($row[$prefijo . 'imagen_usuario'] ?? null),
    ];
}

/** Valida y recorta el texto de cualquier contenido. */
function textoValido(string $campo = 'texto'): string {
    $t = trim(inp($campo));
    if ($t === '') respErr('El texto no puede estar vacío');
    if (mb_strlen($t) > MAX_TEXTO) respErr('El texto es demasiado largo (máx. ' . MAX_TEXTO . ')');
    if (textoProhibido($t)) respErr('Ese texto no cumple las normas de la comunidad.');
    return $t;
}

/**
 * Guardia de las escrituras de Comunidad (Google Play UGC / App Store 1.2):
 * términos aceptados y sin suspensión. Devuelve `codigo` para que la app sepa
 * qué mostrar (`terminos` → pantalla de normas; `suspendido` → aviso con fecha).
 */
function exigirComunidad(mysqli $db, string $ci): void {
    $j = jugadorApp($db, $ci);
    if (!terminosAceptados($j)) {
        http_response_code(403);
        resp(['success' => false, 'codigo' => 'terminos', 'error' => 'Antes tenés que aceptar las normas de la comunidad.', 'version' => TERMINOS_VERSION]);
    }
    if ($susp = suspension($j)) {
        http_response_code(403);
        $hasta = $susp['hasta'] === '9999-12-31 00:00:00' ? 'de forma permanente' : 'hasta el ' . date('d/m/Y H:i', strtotime($susp['hasta']));
        resp(['success' => false, 'codigo' => 'suspendido', 'error' => "Tu cuenta está suspendida en la comunidad {$hasta}. Motivo: {$susp['motivo']}", 'hasta' => $susp['hasta']]);
    }
}

/** Anti-flood: la última fila de `$tabla` del jugador no puede tener menos de FLOOD_SEGUNDOS. */
function exigirCalma(mysqli $db, string $tabla, string $ci): void {
    $st = $db->prepare("SELECT TIMESTAMPDIFF(SECOND, MAX(creado), NOW()) AS s FROM `$tabla` WHERE ci_autor = ?");
    $st->bind_param('s', $ci); $st->execute();
    $r = $st->get_result()->fetch_assoc(); $st->close();
    if ($r && $r['s'] !== null && (int)$r['s'] < FLOOD_SEGUNDOS) respErr('Esperá unos segundos antes de publicar de nuevo.');
}

/**
 * Guarda las @menciones de un texto contra el padrón.
 * El handle es nombre+apellido sin espacios, que es como los escribe la app.
 */
function guardarMenciones(mysqli $db, string $origen, int $idOrigen, string $texto): void {
    if (!preg_match_all('/@([A-Za-zÁÉÍÓÚÑáéíóúñ0-9_]+)/u', $texto, $m)) return;

    $st = $db->prepare(
        "SELECT ci FROM _p_usuarios
         WHERE REPLACE(CONCAT(nombre, apellido), ' ', '') = ? LIMIT 1"
    );
    $ins = $db->prepare(
        "INSERT IGNORE INTO _app_menciones (origen, id_origen, ci) VALUES (?, ?, ?)"
    );

    foreach (array_unique($m[1]) as $handle) {
        $st->bind_param('s', $handle);
        $st->execute();
        $u = $st->get_result()->fetch_assoc();
        if ($u) {
            $ins->bind_param('sis', $origen, $idOrigen, $u['ci']);
            $ins->execute();
            if ($ins->affected_rows > 0) {
                $destino = $origen === 'post' ? "/comunidad?post=$idOrigen" : ($origen === 'mensaje' ? null : "/comunidad");
                notificar($db, (string)$u['ci'], 'mencion', 'Te mencionaron en la comunidad', mb_substr($texto, 0, 120), $destino);
            }
        }
    }
    $st->close();
    $ins->close();
}

// ══════════════════════════════════════════════════════════════════
// perfil — mismo contenido que perfil.php de la web
//
// Réplica EXACTA de `perfil_jugador.inc.php`: los partidos ganados y perdidos
// se cuentan en tiempo real sobre `_todosvstodos` contando sets por partido,
// no hay tabla de estadísticas. Si se cambia el criterio allá, cambiarlo acá.
// ══════════════════════════════════════════════════════════════════
if ($action === 'perfil') {
    // Sólo el propio perfil: los datos de contacto y las inscripciones de un
    // jugador no son públicos.
    $ci = $miCi;

    $partidos = $victorias = $derrotas = 0;

    $st = $mysqli2->prepare(
        "SELECT ci1_a, ci1_b, ci2_a, ci2_b,
                rusultado_equipo1  AS s1e1, resultado_equipo2  AS s1e2,
                resultado2_equipo1 AS s2e1, resultado2_equipo2 AS s2e2,
                resultado3_equipo1 AS s3e1, resultado3_equipo2 AS s3e2
         FROM _todosvstodos
         WHERE (ci1_a = ? OR ci1_b = ? OR ci2_a = ? OR ci2_b = ?)
           AND (rusultado_equipo1 > 0 OR resultado_equipo2 > 0)"
    );
    $st->bind_param('ssss', $ci, $ci, $ci, $ci);
    $st->execute();
    $res = $st->get_result();

    while ($row = $res->fetch_assoc()) {
        $enEquipo1 = ((int)$row['ci1_a'] === (int)$ci || (int)$row['ci1_b'] === (int)$ci);
        $setsE1 = $setsE2 = 0;
        foreach ([['s1e1', 's1e2'], ['s2e1', 's2e2'], ['s3e1', 's3e2']] as $par) {
            $a = (int)$row[$par[0]];
            $b = (int)$row[$par[1]];
            if ($a === 0 && $b === 0) continue;
            if ($a > $b) $setsE1++; else $setsE2++;
        }
        if ($setsE1 === 0 && $setsE2 === 0) continue;

        $partidos++;
        $ganoE1 = $setsE1 > $setsE2;
        if (($enEquipo1 && $ganoE1) || (!$enEquipo1 && !$ganoE1)) $victorias++;
        else $derrotas++;
    }
    $st->close();

    $efectividad = $partidos > 0 ? round($victorias * 100 / $partidos, 1) : 0;

    // Trofeos: réplica de perfil_jugador.inc.php — finales jugadas (grupo=18),
    // ganada = campeón, perdida = finalista. Mismo conteo de sets.
    $campeon = $finalista = 0;
    $st = $mysqli2->prepare(
        "SELECT ci1_a, ci1_b, ci2_a, ci2_b,
                rusultado_equipo1  AS s1e1, resultado_equipo2  AS s1e2,
                resultado2_equipo1 AS s2e1, resultado2_equipo2 AS s2e2,
                resultado3_equipo1 AS s3e1, resultado3_equipo2 AS s3e2
         FROM _todosvstodos
         WHERE grupo = 18
           AND (ci1_a = ? OR ci1_b = ? OR ci2_a = ? OR ci2_b = ?)
           AND (rusultado_equipo1 > 0 OR resultado_equipo2 > 0)"
    );
    $st->bind_param('ssss', $ci, $ci, $ci, $ci);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) {
        $enEquipo1 = ((int)$row['ci1_a'] === (int)$ci || (int)$row['ci1_b'] === (int)$ci);
        $setsE1 = $setsE2 = 0;
        foreach ([['s1e1', 's1e2'], ['s2e1', 's2e2'], ['s3e1', 's3e2']] as $par) {
            $a = (int)$row[$par[0]];
            $b = (int)$row[$par[1]];
            if ($a === 0 && $b === 0) continue;
            if ($a > $b) $setsE1++; else $setsE2++;
        }
        if ($setsE1 === 0 && $setsE2 === 0) continue;
        $ganoE1 = $setsE1 > $setsE2;
        if (($enEquipo1 && $ganoE1) || (!$enEquipo1 && !$ganoE1)) $campeon++;
        else $finalista++;
    }
    $st->close();

    // Inscripciones: mismo SELECT que la web. LIMIT amplio: la app agrupa por
    // evento y pagina de a 10 eventos del lado del cliente.
    $st = $mysqli2->prepare(
        "SELECT i.id_evento, i.id_categoria, i.ci_dupla, i.estado, i.fecha_inscripcion,
                e.evento AS nombre_evento,
                DATE_FORMAT(e.fecha, '%d-%m-%Y') AS fecha_evento,
                c.categoria,
                d.nombre AS dupla_nombre, d.apellido AS dupla_apellido
         FROM _p_incripciones i
         LEFT JOIN _p_eventos e ON e.id = i.id_evento
         LEFT JOIN _p_categorias c ON c.id = i.id_categoria
         LEFT JOIN _p_usuarios d ON d.ci = i.ci_dupla
         WHERE i.ci = ?
         ORDER BY e.fecha DESC, i.fecha_inscripcion DESC
         LIMIT 200"
    );
    $st->bind_param('s', $ci);
    $st->execute();
    $res = $st->get_result();

    $ESTADOS = [
        'pagado'         => 'Pagado',
        'inscripto'      => 'Inscripto',
        'preinscripcion' => 'Preinscripción',
        'bloqueado'      => 'Bloqueado',
    ];

    $inscripciones = [];
    while ($row = $res->fetch_assoc()) {
        $estado = $row['estado'] ?? '';
        $inscripciones[] = [
            'eventoId'    => (int)$row['id_evento'],
            'categoriaId' => (int)$row['id_categoria'],
            'evento'      => $row['nombre_evento'] ?: ('Torneo #' . $row['id_evento']),
            'fecha'     => $row['fecha_evento'] ?: substr($row['fecha_inscripcion'] ?? '', 0, 10),
            'categoria' => $row['categoria'] ?: ('Cat. ' . (int)$row['id_categoria']),
            'companero' => trim(mb_strtoupper(($row['dupla_nombre'] ?? '') . ' ' . ($row['dupla_apellido'] ?? ''), 'UTF-8')),
            'estado'    => $ESTADOS[$estado] ?? $estado,
            'estadoRaw' => $estado,
        ];
    }
    $st->close();

    resp([
        'success' => true,
        'jugador' => [
            'ci'     => $yo['ci'],
            'nombre' => nombreJugador($yo),
            'ciudad' => $yo['ciudad'] ?? '',
            'email'  => $yo['email'] ?? '',
            'cel'    => $yo['cel'] ?? '',
            'foto'   => fotoUrl($yo['imagen_usuario'] ?? null),
            /** Y-m-d o null */
            'fechaNacimiento' => (!empty($yo['fecha_nacimiento']) && $yo['fecha_nacimiento'] !== '0000-00-00') ? $yo['fecha_nacimiento'] : null,
        ],
        'stats' => [
            'partidos'    => $partidos,
            'victorias'   => $victorias,
            'derrotas'    => $derrotas,
            'efectividad' => $efectividad,
        ],
        'trofeos' => [
            'campeon'   => $campeon,
            'finalista' => $finalista,
        ],
        'inscripciones' => $inscripciones,
    ]);
}

// ══════════════════════════════════════════════════════════════════
// actualizar_perfil — POST JSON {fecha_nacimiento?, ciudad?, cel?, email?}
// Réplica de actualizar-perfil.php (mismas validaciones y campos), con token.
// Sólo actualiza lo que llega; '' en fecha_nacimiento la borra.
// ══════════════════════════════════════════════════════════════════
if ($action === 'actualizar_perfil') {
    $raw = json_decode(file_get_contents('php://input'), true) ?: [];
    $sets = []; $tipos = ''; $vals = [];

    if (array_key_exists('fecha_nacimiento', $raw)) {
        $f = trim((string)$raw['fecha_nacimiento']);
        if ($f === '') { $sets[] = 'fecha_nacimiento = NULL'; }
        else {
            $d = DateTime::createFromFormat('Y-m-d', $f);
            if (!$d || $d->format('Y-m-d') !== $f) respErr('Fecha de nacimiento no válida');
            $edad = (new DateTime())->diff($d)->y;
            if ($edad < 5 || $edad > 99) respErr('Fecha de nacimiento fuera de rango');
            $sets[] = 'fecha_nacimiento = ?'; $tipos .= 's'; $vals[] = $f;
        }
    }
    if (array_key_exists('ciudad', $raw)) {
        $c = trim((string)$raw['ciudad']);
        if (mb_strlen($c) > 255) respErr('Ciudad demasiado larga');
        $sets[] = 'ciudad = ?'; $tipos .= 's'; $vals[] = $c;
    }
    if (array_key_exists('cel', $raw)) {
        $c = trim((string)$raw['cel']);
        if ($c !== '' && !preg_match('/^[0-9+\-\s()]{6,20}$/', $c)) respErr('Número de celular no válido');
        $sets[] = 'cel = ?'; $tipos .= 's'; $vals[] = $c;
    }
    if (array_key_exists('email', $raw)) {
        $e = strtolower(trim((string)$raw['email']));
        if ($e !== '' && !filter_var($e, FILTER_VALIDATE_EMAIL)) respErr('Email no válido');
        $sets[] = 'email = ?'; $tipos .= 's'; $vals[] = $e;
    }
    if (!$sets) respErr('No se enviaron datos para actualizar');

    $tipos .= 's'; $vals[] = $miCi;
    $st = $mysqli2->prepare("UPDATE _p_usuarios SET " . implode(', ', $sets) . " WHERE TRIM(ci) = ?");
    $st->bind_param($tipos, ...$vals);
    $st->execute();
    $st->close();
    resp(['success' => true]);
}

// ══════════════════════════════════════════════════════════════════
// subir_foto — POST JSON {foto: "data:image/jpeg;base64,..." | base64}
// Réplica de subir-foto.php con token: mismos tipos (jpg/png/gif/webp), mismo
// tope (3 MB), misma carpeta y nombre (`img/usuarios/foto_<ci>.<ext>`) y el
// mismo UPDATE de `imagen_usuario`, así la web ve la foto que sube la app.
// La app ya la manda achicada (≤ 800 px, JPEG); acá se valida igual por si acaso.
// ══════════════════════════════════════════════════════════════════
if ($action === 'subir_foto') {
    $raw = json_decode(file_get_contents('php://input'), true) ?: [];
    $b64 = (string)($raw['foto'] ?? '');
    if ($b64 === '') respErr('No se recibió la foto');
    if (preg_match('#^data:image/[a-z]+;base64,#i', $b64)) $b64 = substr($b64, strpos($b64, ',') + 1);
    $bin = base64_decode($b64, true);
    if ($bin === false || strlen($bin) < 100) respErr('La foto no se pudo leer');
    if (strlen($bin) > 3 * 1024 * 1024) respErr('La imagen no puede superar 3MB');

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->buffer($bin);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'][$mime] ?? null;
    if (!$ext) respErr('Formato no permitido. Usá JPG, PNG o WEBP');
    if (@getimagesizefromstring($bin) === false) respErr('El archivo no es una imagen válida');

    $docroot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? __DIR__, '/');
    $carpeta = $docroot . '/img/usuarios/';
    if (!is_dir($carpeta) && !mkdir($carpeta, 0755, true)) respErr('No se pudo preparar la carpeta de fotos', 500);
    $nombre = 'foto_' . preg_replace('/[^0-9]/', '', $miCi) . '.' . $ext;
    // Si cambia la extensión, la foto anterior queda huérfana: se borra.
    foreach (glob($carpeta . 'foto_' . preg_replace('/[^0-9]/', '', $miCi) . '.*') ?: [] as $vieja) {
        if (basename($vieja) !== $nombre) @unlink($vieja);
    }
    if (file_put_contents($carpeta . $nombre, $bin) === false) respErr('No se pudo guardar la foto', 500);
    @chmod($carpeta . $nombre, 0644);

    $rel = 'img/usuarios/' . $nombre;
    $st = $mysqli2->prepare("UPDATE _p_usuarios SET imagen_usuario = ? WHERE TRIM(ci) = ?");
    $st->bind_param('ss', $rel, $miCi);
    $st->execute();
    $st->close();
    resp(['success' => true, 'foto' => fotoUrl($rel)]);
}

// ══════════════════════════════════════════════════════════════════
// cambiar_password — POST JSON {pwd_actual, pwd_nueva}
// Réplica de cambiar-password.php con token. `pase` es texto plano en el
// sistema existente (deuda conocida): se compara y guarda igual que la web.
// ══════════════════════════════════════════════════════════════════
if ($action === 'cambiar_password') {
    $raw = json_decode(file_get_contents('php://input'), true) ?: [];
    $actual = trim((string)($raw['pwd_actual'] ?? ''));
    $nueva  = trim((string)($raw['pwd_nueva'] ?? ''));
    if ($actual === '') respErr('Ingresá tu contraseña actual');
    if (strlen($nueva) < 8) respErr('La nueva contraseña debe tener mínimo 8 caracteres');

    $st = $mysqli2->prepare("SELECT pase FROM _p_usuarios WHERE TRIM(ci) = ? LIMIT 1");
    $st->bind_param('s', $miCi);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$row) respErr('Usuario no encontrado', 404);
    if (trim((string)$row['pase']) !== $actual) respErr('La contraseña actual es incorrecta');

    $st = $mysqli2->prepare("UPDATE _p_usuarios SET pase = ? WHERE TRIM(ci) = ?");
    $st->bind_param('ss', $nueva, $miCi);
    $st->execute();
    $st->close();
    resp(['success' => true]);
}

// ══════════════════════════════════════════════════════════════════
// perfil_evento — estadísticas del jugador en un evento+categoría
// (acordeón de Inscripciones en el Perfil de la app, pedido 17-ago-2026).
// Mismo criterio de sets que `perfil`; los nombres de fase salen del mismo
// mapa de `_p_grupos` que usa bt_app_api.php `resultados`.
// ══════════════════════════════════════════════════════════════════
if ($action === 'perfil_evento') {
    $idEvento = inpInt('evento');
    $idCat    = inpInt('categoria');
    if (!$idEvento || !$idCat) respErr('Falta evento o categoria');
    $ci = $miCi;

    $FASES = [
        32 => ['nombre' => '16vos de final',   'orden' => 1],
        26 => ['nombre' => '8vos de final',    'orden' => 2],
        13 => ['nombre' => 'Cuartos de final', 'orden' => 3],
        15 => ['nombre' => 'Semifinal',        'orden' => 4],
        18 => ['nombre' => 'Final',            'orden' => 5],
        19 => ['nombre' => '3er puesto',       'orden' => 6],
    ];

    $st = $mysqli2->prepare(
        "SELECT t.id, t.grupo, t.partido_nro,
                t.ci1_a, t.ci1_b, t.ci2_a, t.ci2_b,
                t.rusultado_equipo1  AS s1e1, t.resultado_equipo2  AS s1e2,
                t.resultado2_equipo1 AS s2e1, t.resultado2_equipo2 AS s2e2,
                t.resultado3_equipo1 AS s3e1, t.resultado3_equipo2 AS s3e2,
                t.en_juego,
                g.grupo AS grupo_nombre, g.orden AS grupo_orden
         FROM _todosvstodos t
         LEFT JOIN _p_grupos g ON g.id = t.grupo
         WHERE t.evento = ? AND t.categoria = ?
           AND (t.ci1_a = ? OR t.ci1_b = ? OR t.ci2_a = ? OR t.ci2_b = ?)
         ORDER BY g.orden ASC, t.partido_nro ASC
         LIMIT 100"
    );
    $st->bind_param('iissss', $idEvento, $idCat, $ci, $ci, $ci, $ci);
    $st->execute();
    $res = $st->get_result();

    $filas = [];
    $cis = [];
    while ($row = $res->fetch_assoc()) {
        foreach (['ci1_a', 'ci1_b', 'ci2_a', 'ci2_b'] as $k) {
            $c = (string)$row[$k];
            if ($c !== '' && $c !== '0') $cis[$c] = true;
        }
        $filas[] = $row;
    }
    $st->close();

    // Un solo SELECT de nombres para todos los CI (apellido solo: es lo que
    // entra en una fila del acordeón).
    $nombres = [];
    if ($cis) {
        $lista = implode(',', array_map(fn($c) => "'" . $mysqli2->real_escape_string($c) . "'", array_keys($cis)));
        $rn = $mysqli2->query("SELECT ci, nombre, apellido FROM _p_usuarios WHERE ci IN ($lista)");
        if ($rn) while ($u = $rn->fetch_assoc()) {
            $nombres[(string)$u['ci']] = trim($u['apellido'] ?: $u['nombre']);
        }
    }
    $nom = fn($c) => $nombres[(string)$c] ?? '';

    $partidos = $victorias = $derrotas = 0;
    $grupo = null;
    $mejorFase = null;   // ['orden' => n, 'nombre' => s]
    $lista = [];

    foreach ($filas as $row) {
        $enEquipo1 = ((int)$row['ci1_a'] === (int)$ci || (int)$row['ci1_b'] === (int)$ci);
        $setsE1 = $setsE2 = 0;
        $sets = [];
        foreach ([['s1e1', 's1e2'], ['s2e1', 's2e2'], ['s3e1', 's3e2']] as $par) {
            $a = (int)$row[$par[0]];
            $b = (int)$row[$par[1]];
            if ($a === 0 && $b === 0) continue;
            if ($a > $b) $setsE1++; else $setsE2++;
            // Los sets se muestran desde el lado del jugador.
            $sets[] = $enEquipo1 ? "$a-$b" : "$b-$a";
        }
        $jugado = ($setsE1 + $setsE2) > 0;
        $gano = null;
        if ($jugado) {
            $ganoE1 = $setsE1 > $setsE2;
            $gano = ($enEquipo1 && $ganoE1) || (!$enEquipo1 && !$ganoE1);
            $partidos++;
            if ($gano) $victorias++; else $derrotas++;
        }

        $idGrupo = (int)$row['grupo'];
        $esEliminatoria = isset($FASES[$idGrupo]);
        $fase = $esEliminatoria ? $FASES[$idGrupo]['nombre'] : (string)($row['grupo_nombre'] ?? '');
        if (!$esEliminatoria && $grupo === null && $fase !== '') $grupo = $fase;
        if ($esEliminatoria) {
            $orden = $FASES[$idGrupo]['orden'];
            // El 3er puesto no es "más lejos" que la final; se cuenta como semi jugada.
            if ($idGrupo === 19) $orden = 4;
            $nombreFase = $FASES[$idGrupo]['nombre'];
            if ($idGrupo === 18 && $gano === true) { $orden = 7; $nombreFase = 'Campeón'; }
            if ($mejorFase === null || $orden > $mejorFase['orden']) $mejorFase = ['orden' => $orden, 'nombre' => $nombreFase];
        }

        $rivalA = $enEquipo1 ? $row['ci2_a'] : $row['ci1_a'];
        $rivalB = $enEquipo1 ? $row['ci2_b'] : $row['ci1_b'];
        $compCi = $enEquipo1
            ? ((int)$row['ci1_a'] === (int)$ci ? $row['ci1_b'] : $row['ci1_a'])
            : ((int)$row['ci2_a'] === (int)$ci ? $row['ci2_b'] : $row['ci2_a']);

        $lista[] = [
            'id'        => (int)$row['id'],
            'rival'     => trim($nom($rivalA) . ' / ' . $nom($rivalB), ' /'),
            'companero' => $nom($compCi),
            'sets'      => $sets,
            'gano'      => $gano,
            'fase'      => $fase,
            'enJuego'   => ($row['en_juego'] ?? 'no') === 'si',
        ];
    }

    // Posición en el grupo: ya calculada en tabla_auxiliar (sólo lectura).
    $posicion = null;
    $st = $mysqli2->prepare(
        "SELECT la_posicion FROM tabla_auxiliar
         WHERE id_evento = ? AND id_categoria = ? AND (ci1_a = ? OR ci1_b = ?)
         ORDER BY la_posicion ASC LIMIT 1"
    );
    $st->bind_param('iiss', $idEvento, $idCat, $ci, $ci);
    $st->execute();
    $rp = $st->get_result()->fetch_assoc();
    $st->close();
    if ($rp && (int)$rp['la_posicion'] > 0) $posicion = (int)$rp['la_posicion'];

    resp([
        'success'       => true,
        'partidos'      => $partidos,
        'victorias'     => $victorias,
        'derrotas'      => $derrotas,
        'grupo'         => $grupo,
        'posicion'      => $posicion,
        'faseAlcanzada' => $mejorFase ? $mejorFase['nombre'] : ($grupo ?: null),
        'lista'         => $lista,
    ]);
}

// ══════════════════════════════════════════════════════════════════
// muro — posts con sus comentarios y respuestas
// ══════════════════════════════════════════════════════════════════
if ($action === 'muro') {
    $desde = inpInt('desde', 0);
    $sinBloq = sqlNoEn($mysqli2, 'p.ci_autor', bloqueadosPor($mysqli2, $miCi));

    $st = $mysqli2->prepare(
        "SELECT p.id, p.texto, p.imagen, p.creado, p.likes, p.comentarios,
                u.ci, u.nombre, u.apellido, u.ciudad, u.imagen_usuario,
                (SELECT COUNT(*) FROM _app_likes l WHERE l.id_post = p.id AND l.ci = ?) AS mi_like
         FROM _app_posts p
         LEFT JOIN _p_usuarios u ON u.ci = p.ci_autor
         WHERE p.estado = 'publicado' $sinBloq
         ORDER BY p.creado DESC
         LIMIT ? OFFSET ?"
    );
    $lim = PAGINA;
    $st->bind_param('sii', $miCi, $lim, $desde);
    $st->execute();
    $res = $st->get_result();

    $posts = [];
    $ids = [];
    while ($row = $res->fetch_assoc()) {
        $ids[] = (int)$row['id'];
        $posts[(int)$row['id']] = [
            'id'         => (int)$row['id'],
            'autor'      => autorDe($row),
            'texto'      => $row['texto'],
            'fecha'      => $row['creado'],
            'imagenUrl'  => $row['imagen'],
            'likes'      => (int)$row['likes'],
            'meGusta'    => (int)$row['mi_like'] > 0,
            'mio'        => (string)$row['ci'] === $miCi,
            'comentarios' => [],
        ];
    }
    $st->close();

    // Comentarios de todos los posts en UNA query, no una por post.
    if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $sinBloqC = sqlNoEn($mysqli2, 'c.ci_autor', bloqueadosPor($mysqli2, $miCi));
        $sql = "SELECT c.id, c.id_post, c.id_padre, c.texto, c.creado,
                       u.ci, u.nombre, u.apellido, u.ciudad, u.imagen_usuario
                FROM _app_comentarios c
                LEFT JOIN _p_usuarios u ON u.ci = c.ci_autor
                WHERE c.estado = 'publicado' AND c.id_post IN ($in) $sinBloqC
                ORDER BY c.creado ASC";
        $stc = $mysqli2->prepare($sql);
        $stc->bind_param(str_repeat('i', count($ids)), ...$ids);
        $stc->execute();
        $rc = $stc->get_result();

        // Dos pasadas, sin referencias: primero se juntan raíces y respuestas
        // por separado, después se arma el árbol. Aburrido y predecible.
        $raices = [];      // id_post => [comentario, ...]
        $respuestas = [];  // id_padre => [respuesta, ...]

        while ($c = $rc->fetch_assoc()) {
            $item = [
                'id'    => (int)$c['id'],
                'autor' => autorDe($c),
                'texto' => $c['texto'],
                'fecha' => $c['creado'],
            ];
            if ($c['id_padre']) {
                $respuestas[(int)$c['id_padre']][] = $item;
            } else {
                $item['respuestas'] = [];
                $raices[(int)$c['id_post']][] = $item;
            }
        }
        $stc->close();

        foreach ($raices as $idPost => $lista) {
            foreach ($lista as $i => $com) {
                $lista[$i]['respuestas'] = $respuestas[$com['id']] ?? [];
            }
            $posts[$idPost]['comentarios'] = $lista;
        }
    }

    resp(['success' => true, 'posts' => array_values($posts)]);
}

// ══════════════════════════════════════════════════════════════════
// publicar — nuevo post en el muro
// ══════════════════════════════════════════════════════════════════
if ($action === 'publicar') {
    exigirComunidad($mysqli2, $miCi);
    exigirCalma($mysqli2, '_app_posts', $miCi);
    $texto = textoValido();
    $circ  = inpInt('circuito', 0) ?: null;

    $st = $mysqli2->prepare("INSERT INTO _app_posts (ci_autor, texto, id_circuito) VALUES (?, ?, ?)");
    $st->bind_param('ssi', $miCi, $texto, $circ);
    $st->execute();
    $id = (int)$mysqli2->insert_id;
    $st->close();

    guardarMenciones($mysqli2, 'post', $id, $texto);
    resp(['success' => true, 'id' => $id]);
}

// ══════════════════════════════════════════════════════════════════
// comentar — comentario o respuesta (un solo nivel)
// ══════════════════════════════════════════════════════════════════
if ($action === 'comentar') {
    exigirComunidad($mysqli2, $miCi);
    exigirCalma($mysqli2, '_app_comentarios', $miCi);
    $idPost = inpInt('post');
    $idPadre = inpInt('padre', 0) ?: null;
    $texto = textoValido();
    if (!$idPost) respErr('Falta post');

    $st = $mysqli2->prepare("SELECT id, ci_autor FROM _app_posts WHERE id = ? AND estado = 'publicado' LIMIT 1");
    $st->bind_param('i', $idPost);
    $st->execute();
    $post = $st->get_result()->fetch_assoc();
    if (!$post) respErr('El post no existe', 404);
    $st->close();

    // Una respuesta a una respuesta se aplana al comentario raíz: el diseño
    // sólo dibuja un nivel, y sin este corte el hilo se vuelve infinito.
    if ($idPadre) {
        $st = $mysqli2->prepare("SELECT id_padre FROM _app_comentarios WHERE id = ? LIMIT 1");
        $st->bind_param('i', $idPadre);
        $st->execute();
        $p = $st->get_result()->fetch_assoc();
        $st->close();
        if ($p && $p['id_padre']) $idPadre = (int)$p['id_padre'];
    }

    $st = $mysqli2->prepare("INSERT INTO _app_comentarios (id_post, id_padre, ci_autor, texto) VALUES (?, ?, ?, ?)");
    $st->bind_param('iiss', $idPost, $idPadre, $miCi, $texto);
    $st->execute();
    $id = (int)$mysqli2->insert_id;
    $st->close();

    $st = $mysqli2->prepare("UPDATE _app_posts SET comentarios = comentarios + 1 WHERE id = ?");
    $st->bind_param('i', $idPost);
    $st->execute();
    $st->close();

    guardarMenciones($mysqli2, 'comentario', $id, $texto);
    if ((string)$post['ci_autor'] !== $miCi) {
        notificar($mysqli2, (string)$post['ci_autor'], 'comentario', 'Comentaron tu publicación', mb_substr($texto, 0, 120), "/comunidad?post=$idPost");
    }
    resp(['success' => true, 'id' => $id]);
}

// ══════════════════════════════════════════════════════════════════
// likes — quiénes dieron me gusta a un post (19-ago-2026). Sin bloqueados.
// ══════════════════════════════════════════════════════════════════
if ($action === 'likes') {
    $idPost = inpInt('post');
    if (!$idPost) respErr('Falta post');
    $sinBloq = sqlNoEn($mysqli2, 'u.ci', bloqueadosPor($mysqli2, $miCi));
    $st = $mysqli2->prepare(
        "SELECT u.ci, u.nombre, u.apellido, u.ciudad, u.imagen_usuario
         FROM _app_likes l
         JOIN _p_usuarios u ON u.ci = l.ci
         WHERE l.id_post = ? $sinBloq
         ORDER BY l.creado DESC
         LIMIT 100"
    );
    $st->bind_param('i', $idPost);
    $st->execute();
    $res = $st->get_result();
    $jugadores = [];
    while ($row = $res->fetch_assoc()) $jugadores[] = autorDe($row);
    $st->close();
    resp(['success' => true, 'jugadores' => $jugadores]);
}

// ══════════════════════════════════════════════════════════════════
// like — alterna el me gusta. La UNIQUE de la tabla evita duplicados.
// ══════════════════════════════════════════════════════════════════
if ($action === 'like') {
    exigirComunidad($mysqli2, $miCi);
    $idPost = inpInt('post');
    if (!$idPost) respErr('Falta post');

    $st = $mysqli2->prepare("DELETE FROM _app_likes WHERE id_post = ? AND ci = ?");
    $st->bind_param('is', $idPost, $miCi);
    $st->execute();
    $quitado = $st->affected_rows > 0;
    $st->close();

    if (!$quitado) {
        $st = $mysqli2->prepare("INSERT IGNORE INTO _app_likes (id_post, ci) VALUES (?, ?)");
        $st->bind_param('is', $idPost, $miCi);
        $st->execute();
        $st->close();
    }

    // El contador se recalcula desde la tabla: si dos requests se cruzan, no
    // queda desfasado como pasaría con `likes = likes ± 1`.
    $st = $mysqli2->prepare(
        "UPDATE _app_posts SET likes = (SELECT COUNT(*) FROM _app_likes WHERE id_post = ?) WHERE id = ?"
    );
    $st->bind_param('ii', $idPost, $idPost);
    $st->execute();
    $st->close();

    resp(['success' => true, 'meGusta' => !$quitado]);
}

// ══════════════════════════════════════════════════════════════════
// duplas — avisos de "busco compañero/a" abiertos
// ══════════════════════════════════════════════════════════════════
if ($action === 'duplas') {
    $sinBloq = sqlNoEn($mysqli2, 'd.ci_autor', bloqueadosPor($mysqli2, $miCi));
    $st = $mysqli2->prepare(
        "SELECT d.id, d.texto, d.disponibilidad, d.creado, c.categoria,
                u.ci, u.nombre, u.apellido, u.ciudad, u.imagen_usuario
         FROM _app_busca_dupla d
         LEFT JOIN _p_usuarios u ON u.ci = d.ci_autor
         LEFT JOIN _p_categorias c ON c.id = d.id_categoria
         WHERE d.estado = 'abierta' $sinBloq
         ORDER BY d.creado DESC
         LIMIT 50"
    );
    $st->execute();
    $res = $st->get_result();
    $duplas = [];
    while ($row = $res->fetch_assoc()) {
        $duplas[] = [
            'id'    => (int)$row['id'],
            'autor' => autorDe($row),
            'categoria' => $row['categoria'] ?? '',
            'texto' => $row['texto'],
            'disponibilidad' => $row['disponibilidad'] ?? '',
            'fecha' => $row['creado'],
            'mio'   => (string)$row['ci'] === $miCi,
        ];
    }
    $st->close();
    resp(['success' => true, 'duplas' => $duplas]);
}

if ($action === 'publicar_dupla') {
    exigirComunidad($mysqli2, $miCi);
    exigirCalma($mysqli2, '_app_busca_dupla', $miCi);
    $texto = textoValido();
    $cat = inpInt('categoria', 0) ?: null;
    $disp = substr(inp('disponibilidad', ''), 0, 160);

    $st = $mysqli2->prepare("INSERT INTO _app_busca_dupla (ci_autor, id_categoria, texto, disponibilidad) VALUES (?, ?, ?, ?)");
    $st->bind_param('siss', $miCi, $cat, $texto, $disp);
    $st->execute();
    $id = (int)$mysqli2->insert_id;
    $st->close();
    resp(['success' => true, 'id' => $id]);
}

// ══════════════════════════════════════════════════════════════════
// chats — conversaciones del jugador logueado
// ══════════════════════════════════════════════════════════════════
if ($action === 'chats') {
    // Bloqueo en cualquiera de los dos sentidos: el chat desaparece de la lista.
    $sinBloq = sqlNoEn($mysqli2, 'IF(ch.ci_a = \'' . $mysqli2->real_escape_string($miCi) . '\', ch.ci_b, ch.ci_a)', bloqueosDe($mysqli2, $miCi));
    $st = $mysqli2->prepare(
        "SELECT ch.id, ch.ci_a, ch.ci_b, ch.ultimo_msg,
                u.ci, u.nombre, u.apellido, u.ciudad, u.imagen_usuario,
                (SELECT texto FROM _app_mensajes m WHERE m.id_chat = ch.id ORDER BY m.creado DESC LIMIT 1) AS ultimo,
                (SELECT COUNT(*) FROM _app_mensajes m WHERE m.id_chat = ch.id AND m.leido IS NULL AND m.ci_autor <> ?) AS sin_leer
         FROM _app_chats ch
         LEFT JOIN _p_usuarios u ON u.ci = IF(ch.ci_a = ?, ch.ci_b, ch.ci_a)
         WHERE (ch.ci_a = ? OR ch.ci_b = ?) $sinBloq
         ORDER BY ch.ultimo_msg DESC
         LIMIT 50"
    );
    $st->bind_param('ssss', $miCi, $miCi, $miCi, $miCi);
    $st->execute();
    $res = $st->get_result();
    $chats = [];
    while ($row = $res->fetch_assoc()) {
        $chats[] = [
            'id'  => (int)$row['id'],
            'con' => autorDe($row),
            'ultimoMensaje' => $row['ultimo'] ?? '',
            'ultimaFecha'   => $row['ultimo_msg'],
            'sinLeer'       => (int)$row['sin_leer'],
        ];
    }
    $st->close();
    resp(['success' => true, 'chats' => $chats]);
}

/** Corta si el chat no es del jugador logueado. Nadie lee chats ajenos. */
function chatMio(mysqli $db, int $idChat, string $miCi): array {
    $st = $db->prepare("SELECT id, ci_a, ci_b FROM _app_chats WHERE id = ? LIMIT 1");
    $st->bind_param('i', $idChat);
    $st->execute();
    $chat = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$chat) respErr('El chat no existe', 404);
    if ($chat['ci_a'] !== $miCi && $chat['ci_b'] !== $miCi) respErr('Ese chat no es tuyo', 403);
    return $chat;
}

if ($action === 'mensajes') {
    $idChat = inpInt('chat');
    if (!$idChat) respErr('Falta chat');
    chatMio($mysqli2, $idChat, $miCi);

    $st = $mysqli2->prepare(
        "SELECT id, ci_autor, texto, creado FROM _app_mensajes
         WHERE id_chat = ? ORDER BY creado ASC LIMIT 200"
    );
    $st->bind_param('i', $idChat);
    $st->execute();
    $res = $st->get_result();
    $mensajes = [];
    while ($row = $res->fetch_assoc()) {
        $mensajes[] = [
            'id'    => (int)$row['id'],
            'texto' => $row['texto'],
            'fecha' => $row['creado'],
            'mio'   => $row['ci_autor'] === $miCi,
        ];
    }
    $st->close();

    // Al abrir el chat, lo del otro queda leído.
    $st = $mysqli2->prepare("UPDATE _app_mensajes SET leido = NOW() WHERE id_chat = ? AND ci_autor <> ? AND leido IS NULL");
    $st->bind_param('is', $idChat, $miCi);
    $st->execute();
    $st->close();

    resp(['success' => true, 'mensajes' => $mensajes]);
}

// ══════════════════════════════════════════════════════════════════
// buscar — jugadores del padrón por nombre / apellido / CI, para NUEVO CHAT
// (19-ago-2026). Saca al propio jugador, a los bloqueados en cualquier
// sentido y a las cuentas eliminadas desde la app.
// ══════════════════════════════════════════════════════════════════
if ($action === 'buscar') {
    $q = trim(inp('q', ''));
    if (mb_strlen($q) < 3) respErr('Mínimo 3 caracteres para buscar');
    $like = '%' . $q . '%';
    $pref = $q . '%';
    $sinBloq = sqlNoEn($mysqli2, 'u.ci', array_merge([$miCi], bloqueosDe($mysqli2, $miCi)));
    $st = $mysqli2->prepare(
        "SELECT u.ci, u.nombre, u.apellido, u.ciudad, u.imagen_usuario
         FROM _p_usuarios u
         LEFT JOIN _app_jugadores j ON j.ci = u.ci AND j.eliminado_en IS NOT NULL
         WHERE j.ci IS NULL
           AND (u.nombre LIKE ? OR u.apellido LIKE ?
                OR CONCAT(u.nombre, ' ', u.apellido) LIKE ?
                OR CONCAT(u.apellido, ' ', u.nombre) LIKE ?
                OR u.ci LIKE ?) $sinBloq
         ORDER BY u.apellido, u.nombre
         LIMIT 15"
    );
    $st->bind_param('sssss', $pref, $pref, $like, $like, $pref);
    $st->execute();
    $res = $st->get_result();
    $jugadores = [];
    while ($row = $res->fetch_assoc()) $jugadores[] = autorDe($row);
    $st->close();
    resp(['success' => true, 'jugadores' => $jugadores]);
}

// ══════════════════════════════════════════════════════════════════
// abrir_chat — devuelve el chat con alguien, creándolo si no existe
// ══════════════════════════════════════════════════════════════════
if ($action === 'abrir_chat') {
    exigirComunidad($mysqli2, $miCi);
    $otro = normalizarCi(inp('ci'));
    if ($otro === '' || $otro === $miCi) respErr('Cédula inválida');
    if (in_array($otro, bloqueosDe($mysqli2, $miCi), true)) respErr('No podés chatear con este jugador.', 403);

    $st = $mysqli2->prepare("SELECT ci FROM _p_usuarios WHERE ci = ? LIMIT 1");
    $st->bind_param('s', $otro);
    $st->execute();
    if (!$st->get_result()->fetch_assoc()) respErr('Ese jugador no existe', 404);
    $st->close();

    // Orden fijo: así la UNIQUE impide dos chats para el mismo par.
    $a = strcmp($miCi, $otro) < 0 ? $miCi : $otro;
    $b = strcmp($miCi, $otro) < 0 ? $otro : $miCi;

    $st = $mysqli2->prepare("INSERT IGNORE INTO _app_chats (ci_a, ci_b, ultimo_msg) VALUES (?, ?, NOW())");
    $st->bind_param('ss', $a, $b);
    $st->execute();
    $st->close();

    $st = $mysqli2->prepare("SELECT id FROM _app_chats WHERE ci_a = ? AND ci_b = ? LIMIT 1");
    $st->bind_param('ss', $a, $b);
    $st->execute();
    $chat = $st->get_result()->fetch_assoc();
    $st->close();

    resp(['success' => true, 'id' => (int)$chat['id']]);
}

if ($action === 'enviar') {
    exigirComunidad($mysqli2, $miCi);
    $idChat = inpInt('chat');
    $texto = textoValido();
    if (!$idChat) respErr('Falta chat');
    $chat = chatMio($mysqli2, $idChat, $miCi);
    $otro = $chat['ci_a'] === $miCi ? (string)$chat['ci_b'] : (string)$chat['ci_a'];
    if (in_array($otro, bloqueosDe($mysqli2, $miCi), true)) respErr('No podés chatear con este jugador.', 403);

    $st = $mysqli2->prepare("INSERT INTO _app_mensajes (id_chat, ci_autor, texto) VALUES (?, ?, ?)");
    $st->bind_param('iss', $idChat, $miCi, $texto);
    $st->execute();
    $id = (int)$mysqli2->insert_id;
    $st->close();

    $st = $mysqli2->prepare("UPDATE _app_chats SET ultimo_msg = NOW() WHERE id = ?");
    $st->bind_param('i', $idChat);
    $st->execute();
    $st->close();

    guardarMenciones($mysqli2, 'mensaje', $id, $texto);
    notificar($mysqli2, $otro, 'mensaje', 'Te escribieron por chat', mb_substr($texto, 0, 120), "/chat/$idChat");
    resp(['success' => true, 'id' => $id]);
}

// ══════════════════════════════════════════════════════════════════
// COMUNIDAD — normas, bloqueos, denuncias (18-ago-2026)
// ══════════════════════════════════════════════════════════════════

/** Estado del jugador en la comunidad: lo pide la app al entrar a Comunidad. */
if ($action === 'estado_comunidad') {
    $j = jugadorApp($mysqli2, $miCi);
    $susp = suspension($j);
    resp([
        'success'           => true,
        'terminosVersion'   => TERMINOS_VERSION,
        'terminosAceptados' => terminosAceptados($j),
        'suspendidoHasta'   => $susp['hasta'] ?? null,
        'suspendidoMotivo'  => $susp['motivo'] ?? null,
        'bloqueados'        => bloqueadosPor($mysqli2, $miCi),
    ]);
}

if ($action === 'aceptar_terminos') {
    $v = TERMINOS_VERSION;
    $st = $mysqli2->prepare("INSERT INTO _app_jugadores (ci, terminos_version, terminos_en) VALUES (?, ?, NOW())
                             ON DUPLICATE KEY UPDATE terminos_version = VALUES(terminos_version), terminos_en = NOW()");
    $st->bind_param('ss', $miCi, $v); $st->execute(); $st->close();
    resp(['success' => true, 'terminosVersion' => $v]);
}

if ($action === 'bloquear' || $action === 'desbloquear') {
    $otro = normalizarCi(inp('ci'));
    if ($otro === '' || $otro === $miCi) respErr('Cédula inválida');
    if ($action === 'bloquear') {
        $st = $mysqli2->prepare("INSERT IGNORE INTO _app_bloqueos (ci, ci_bloqueado) VALUES (?, ?)");
    } else {
        $st = $mysqli2->prepare("DELETE FROM _app_bloqueos WHERE ci = ? AND ci_bloqueado = ?");
    }
    $st->bind_param('ss', $miCi, $otro); $st->execute(); $st->close();
    resp(['success' => true, 'bloqueados' => bloqueadosPor($mysqli2, $miCi)]);
}

/**
 * denunciar — {tipo: post|comentario|mensaje|dupla|jugador, id?, ci?, motivo, detalle?}
 * Guarda copia del texto y avisa al moderador (mail + Telegram); el admin lo lista.
 */
if ($action === 'denunciar') {
    $tipo = inp('tipo');
    $motivo = mb_substr(inp('motivo'), 0, 40);
    $detalle = mb_substr(inp('detalle'), 0, 500);
    $refId = inpInt('id', 0) ?: null;
    $ciDen = normalizarCi(inp('ci')) ?: null;
    if (!in_array($tipo, ['post', 'comentario', 'mensaje', 'dupla', 'jugador'], true)) respErr('Tipo inválido');
    if ($motivo === '') respErr('Falta el motivo');
    if ($tipo !== 'jugador' && !$refId) respErr('Falta el id del contenido');

    // Copia del contenido y autor, según el tipo.
    $texto = null;
    $q = ['post' => "SELECT texto, ci_autor FROM _app_posts WHERE id = ?",
          'comentario' => "SELECT texto, ci_autor FROM _app_comentarios WHERE id = ?",
          'mensaje' => "SELECT texto, ci_autor FROM _app_mensajes WHERE id = ?",
          'dupla' => "SELECT texto, ci_autor FROM _app_busca_dupla WHERE id = ?"][$tipo] ?? null;
    if ($q) {
        $st = $mysqli2->prepare($q); $st->bind_param('i', $refId); $st->execute();
        $row = $st->get_result()->fetch_assoc(); $st->close();
        if (!$row) respErr('El contenido no existe', 404);
        $texto = $row['texto']; $ciDen = (string)$row['ci_autor'];
    }
    if (!$ciDen) respErr('Falta el jugador denunciado');

    $st = $mysqli2->prepare("INSERT INTO _app_denuncias (ci_denunciante, tipo, ref_id, ci_denunciado, motivo, detalle, texto) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $st->bind_param('ssissss', $miCi, $tipo, $refId, $ciDen, $motivo, $detalle, $texto);
    $st->execute(); $idDen = (int)$mysqli2->insert_id; $st->close();

    $st = $mysqli2->prepare("SELECT CONCAT(nombre, ' ', apellido) n FROM _p_usuarios WHERE ci = ? LIMIT 1");
    $st->bind_param('s', $ciDen); $st->execute(); $nd = $st->get_result()->fetch_assoc()['n'] ?? $ciDen; $st->close();
    avisarModerador("Denuncia #$idDen ($tipo · $motivo)", "Denunciado: $nd (CI $ciDen)\nDenunciante: " . nombreJugador($yo) . " (CI $miCi)\n" . ($detalle ? "Detalle: $detalle\n" : '') . ($texto ? "Texto: " . mb_substr($texto, 0, 300) : ''));
    resp(['success' => true, 'id' => $idDen]);
}

// ══════════════════════════════════════════════════════════════════
// NOTIFICACIONES in-app (campana). El push de fase 3 sale de la misma tabla.
// ══════════════════════════════════════════════════════════════════
if ($action === 'notificaciones') {
    $st = $mysqli2->prepare("SELECT id, tipo, titulo, cuerpo, destino, creado, leida FROM _app_notificaciones WHERE ci = ? ORDER BY creado DESC LIMIT 100");
    $st->bind_param('s', $miCi); $st->execute();
    $r = $st->get_result(); $out = []; $sinLeer = 0;
    while ($n = $r->fetch_assoc()) {
        if (!$n['leida']) $sinLeer++;
        $out[] = ['id' => (string)$n['id'], 'tipo' => $n['tipo'], 'titulo' => $n['titulo'], 'cuerpo' => $n['cuerpo'] ?? '', 'destino' => $n['destino'], 'fecha' => $n['creado'], 'leida' => !empty($n['leida'])];
    }
    $st->close();
    resp(['success' => true, 'notificaciones' => $out, 'sinLeer' => $sinLeer]);
}

if ($action === 'marcar_leidas') {
    $st = $mysqli2->prepare("UPDATE _app_notificaciones SET leida = NOW() WHERE ci = ? AND leida IS NULL");
    $st->bind_param('s', $miCi); $st->execute(); $st->close();
    resp(['success' => true]);
}

// ══════════════════════════════════════════════════════════════════
// registrar_dispositivo — POST {token, plataforma} (push, fase 3, 20-ago-2026)
// La app lo llama cada vez que arranca con sesión. Un token es de UN teléfono:
// si otro jugador inicia sesión en ese teléfono, el token pasa a ser suyo.
// El push llega sin sesión abierta (así lo pidió el dueño); leer la campana
// sigue exigiendo login.
// ══════════════════════════════════════════════════════════════════
if ($action === 'registrar_dispositivo') {
    $raw = json_decode(file_get_contents('php://input'), true) ?: [];
    $token = trim((string)($raw['token'] ?? ''));
    $plat  = (string)($raw['plataforma'] ?? '');
    if (!preg_match('/^Expo(nent)?PushToken\[[A-Za-z0-9_-]+\]$/', $token)) respErr('Token de push no válido');
    if (!in_array($plat, ['ios', 'android'], true)) respErr('Plataforma no válida');

    $st = $mysqli2->prepare(
        "INSERT INTO _app_dispositivos (ci, expo_token, plataforma, ultimo_uso) VALUES (?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE ci = VALUES(ci), plataforma = VALUES(plataforma), ultimo_uso = NOW()"
    );
    $st->bind_param('sss', $miCi, $token, $plat);
    $st->execute(); $st->close();
    resp(['success' => true]);
}

// ══════════════════════════════════════════════════════════════════
// eliminar_cuenta — POST {pase}. Exigido por Google Play y App Store.
// Anonimiza el padrón (el CI queda para el historial de resultados, que es de
// las duplas y del torneo, no del jugador) y borra todo lo de la app.
// ══════════════════════════════════════════════════════════════════
if ($action === 'eliminar_cuenta') {
    $pase = trim((string)((json_decode(file_get_contents('php://input'), true) ?: [])['pase'] ?? ''));
    if ($pase === '') respErr('Ingresá tu contraseña para confirmar');
    $st = $mysqli2->prepare("SELECT pase, imagen_usuario FROM _p_usuarios WHERE TRIM(ci) = ? LIMIT 1");
    $st->bind_param('s', $miCi); $st->execute(); $u = $st->get_result()->fetch_assoc(); $st->close();
    if (!$u) respErr('Usuario no encontrado', 404);
    if (trim((string)$u['pase']) !== $pase) respErr('La contraseña es incorrecta');

    // Contenido de la app del jugador
    foreach (['_app_posts' => 'ci_autor', '_app_comentarios' => 'ci_autor', '_app_busca_dupla' => 'ci_autor', '_app_likes' => 'ci',
              '_app_menciones' => 'ci', '_app_notificaciones' => 'ci', '_app_dispositivos' => 'ci', '_app_bloqueos' => 'ci'] as $tabla => $col) {
        $st = $mysqli2->prepare("DELETE FROM `$tabla` WHERE `$col` = ?"); $st->bind_param('s', $miCi); $st->execute(); $st->close();
    }
    // Mensajes: se anonimizan (el otro conserva su lado de la conversación).
    $st = $mysqli2->prepare("UPDATE _app_mensajes SET texto = '[mensaje de una cuenta eliminada]' WHERE ci_autor = ?"); $st->bind_param('s', $miCi); $st->execute(); $st->close();
    // Padrón: datos personales fuera, CI y nombre corto para el historial de resultados.
    $emailAnon = "eliminado+{$miCi}@bt.com.py"; $paseAnon = bin2hex(random_bytes(12));
    $st = $mysqli2->prepare("UPDATE _p_usuarios SET email = ?, pase = ?, cel = '', tel = '', whatsapp = '', imagen_usuario = '', fecha_nacimiento = NULL, estado = 'inactivo' WHERE TRIM(ci) = ?");
    $st->bind_param('sss', $emailAnon, $paseAnon, $miCi); $st->execute(); $st->close();
    if (!empty($u['imagen_usuario']) && str_contains($u['imagen_usuario'], '/')) @unlink(rtrim($_SERVER['DOCUMENT_ROOT'] ?? __DIR__, '/') . '/' . ltrim($u['imagen_usuario'], '/'));
    // Sesiones del sitio y de la app
    $st = $mysqli2->prepare("DELETE FROM _sesion_usuario WHERE ci = ?"); $st->bind_param('s', $miCi); $st->execute(); $st->close();
    $st = $mysqli2->prepare("INSERT INTO _app_jugadores (ci, eliminado_en) VALUES (?, NOW()) ON DUPLICATE KEY UPDATE eliminado_en = NOW()"); $st->bind_param('s', $miCi); $st->execute(); $st->close();
    avisarModerador("Cuenta eliminada (CI $miCi)", "El jugador eliminó su cuenta desde la app.");
    resp(['success' => true]);
}

respErr('Acción no disponible', 404);
