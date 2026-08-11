<?php
/**
 * bt_app_social.php — Comunidad de la app móvil (muro, duplas, chats).
 * ================================================================
 * Archivo NUEVO y aislado. Todo pasa por token de jugador
 * (`bt_app_auth.php`). Ni una consulta toca las tablas del sitio salvo
 * `_p_usuarios`, y sólo para leer nombre/ciudad del autor.
 *
 * Tablas: ver `sql/2026-08-10_social.sql`.
 *
 * Acciones de lectura : muro · duplas · chats · mensajes
 * Acciones de escritura: publicar · comentar · like · publicar_dupla ·
 *                        abrir_chat · enviar
 * ================================================================
 */

require_once __DIR__ . '/bt_app_auth.inc.php';

/** Techo de caracteres. Un muro sin límite se llena de basura pegada. */
const MAX_TEXTO = 1000;
const PAGINA = 20;

$yo = jugadorActual($mysqli2);
$miCi = $yo['ci'];

$action = inp('action');
if (!$action) respErr('Falta parámetro action');

$esEscritura = in_array($action, ['publicar', 'comentar', 'like', 'publicar_dupla', 'abrir_chat', 'enviar'], true);
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
        'fotoUrl' => null,
    ];
}

/** Valida y recorta el texto de cualquier contenido. */
function textoValido(string $campo = 'texto'): string {
    $t = trim(inp($campo));
    if ($t === '') respErr('El texto no puede estar vacío');
    if (mb_strlen($t) > MAX_TEXTO) respErr('El texto es demasiado largo (máx. ' . MAX_TEXTO . ')');
    return $t;
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

    // Inscripciones: mismo SELECT que la web.
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
         ORDER BY i.fecha_inscripcion DESC
         LIMIT 50"
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
            'eventoId'  => (int)$row['id_evento'],
            'evento'    => $row['nombre_evento'] ?: ('Torneo #' . $row['id_evento']),
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
        ],
        'stats' => [
            'partidos'    => $partidos,
            'victorias'   => $victorias,
            'derrotas'    => $derrotas,
            'efectividad' => $efectividad,
        ],
        'inscripciones' => $inscripciones,
    ]);
}

// ══════════════════════════════════════════════════════════════════
// muro — posts con sus comentarios y respuestas
// ══════════════════════════════════════════════════════════════════
if ($action === 'muro') {
    $desde = inpInt('desde', 0);

    $st = $mysqli2->prepare(
        "SELECT p.id, p.texto, p.imagen, p.creado, p.likes, p.comentarios,
                u.ci, u.nombre, u.apellido, u.ciudad,
                (SELECT COUNT(*) FROM _app_likes l WHERE l.id_post = p.id AND l.ci = ?) AS mi_like
         FROM _app_posts p
         LEFT JOIN _p_usuarios u ON u.ci = p.ci_autor
         WHERE p.estado = 'publicado'
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
            'comentarios' => [],
        ];
    }
    $st->close();

    // Comentarios de todos los posts en UNA query, no una por post.
    if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT c.id, c.id_post, c.id_padre, c.texto, c.creado,
                       u.ci, u.nombre, u.apellido, u.ciudad
                FROM _app_comentarios c
                LEFT JOIN _p_usuarios u ON u.ci = c.ci_autor
                WHERE c.estado = 'publicado' AND c.id_post IN ($in)
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
    $idPost = inpInt('post');
    $idPadre = inpInt('padre', 0) ?: null;
    $texto = textoValido();
    if (!$idPost) respErr('Falta post');

    $st = $mysqli2->prepare("SELECT id FROM _app_posts WHERE id = ? AND estado = 'publicado' LIMIT 1");
    $st->bind_param('i', $idPost);
    $st->execute();
    if (!$st->get_result()->fetch_assoc()) respErr('El post no existe', 404);
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
    resp(['success' => true, 'id' => $id]);
}

// ══════════════════════════════════════════════════════════════════
// like — alterna el me gusta. La UNIQUE de la tabla evita duplicados.
// ══════════════════════════════════════════════════════════════════
if ($action === 'like') {
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
    $st = $mysqli2->prepare(
        "SELECT d.id, d.texto, d.disponibilidad, d.creado, c.categoria,
                u.ci, u.nombre, u.apellido, u.ciudad
         FROM _app_busca_dupla d
         LEFT JOIN _p_usuarios u ON u.ci = d.ci_autor
         LEFT JOIN _p_categorias c ON c.id = d.id_categoria
         WHERE d.estado = 'abierta'
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
        ];
    }
    $st->close();
    resp(['success' => true, 'duplas' => $duplas]);
}

if ($action === 'publicar_dupla') {
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
    $st = $mysqli2->prepare(
        "SELECT ch.id, ch.ci_a, ch.ci_b, ch.ultimo_msg,
                u.ci, u.nombre, u.apellido, u.ciudad,
                (SELECT texto FROM _app_mensajes m WHERE m.id_chat = ch.id ORDER BY m.creado DESC LIMIT 1) AS ultimo,
                (SELECT COUNT(*) FROM _app_mensajes m WHERE m.id_chat = ch.id AND m.leido IS NULL AND m.ci_autor <> ?) AS sin_leer
         FROM _app_chats ch
         LEFT JOIN _p_usuarios u ON u.ci = IF(ch.ci_a = ?, ch.ci_b, ch.ci_a)
         WHERE ch.ci_a = ? OR ch.ci_b = ?
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
// abrir_chat — devuelve el chat con alguien, creándolo si no existe
// ══════════════════════════════════════════════════════════════════
if ($action === 'abrir_chat') {
    $otro = normalizarCi(inp('ci'));
    if ($otro === '' || $otro === $miCi) respErr('Cédula inválida');

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
    $idChat = inpInt('chat');
    $texto = textoValido();
    if (!$idChat) respErr('Falta chat');
    chatMio($mysqli2, $idChat, $miCi);

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
    resp(['success' => true, 'id' => $id]);
}

respErr('Acción no disponible', 404);
