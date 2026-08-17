<?php
/**
 * bt_app_inscribir.php — Inscripción de una pareja desde la app móvil.
 * ================================================================
 * Archivo NUEVO y aislado. Réplica del POST de `inscripcion.php` (la web),
 * en JSON y con sentencias preparadas:
 *   - los dos jugadores deben existir en `_p_usuarios` (la app los busca por
 *     CI con `bt_app_api.php?action=buscar_jugador`; si alguno no está, se
 *     registra en la web primero — acá no se crean usuarios);
 *   - la categoría debe pertenecer al evento, estar activa y con cupo;
 *   - se insertan las DOS filas de `_p_incripciones` (A→B y B→A), como la web,
 *     con `medio='app'`.
 * Sin token: la web tampoco pide login para inscribirse.
 *
 * POST JSON: { evento, categoria, ci1, ci2 }
 * → { success:true, codigo, categoria, evento }
 * → { success:false, error, codigo? }   (400)
 * ================================================================
 */

require_once __DIR__ . '/bt_app_auth.inc.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') respErr('Esta acción requiere POST', 405);

$idEvento    = inpInt('evento');
$idCategoria = inpInt('categoria');
$ci1 = normalizarCi(inp('ci1'));
$ci2 = normalizarCi(inp('ci2'));

if (!$idEvento || !$idCategoria) respErr('Falta evento o categoria');
if (strlen($ci1) < 6 || strlen($ci2) < 6) respErr('Cédula inválida. Verificá los datos.');
if ($ci1 === $ci2) respErr('Ambos jugadores tienen la misma cédula.');

// ── Evento abierto a inscripción ─────────────────────────────────
$st = $mysqli2->prepare(
    "SELECT id, evento, estado, fecha_fin_inscripcion, hora_fin_inscripcion, boton_inscripcion, id_tipo_evento
     FROM _p_eventos WHERE id = ? LIMIT 1"
);
$st->bind_param('i', $idEvento);
$st->execute();
$ev = $st->get_result()->fetch_assoc();
$st->close();
if (!$ev) respErr('Evento no encontrado', 404);
if (!in_array($ev['estado'], ['registro', 'activo'], true)) respErr('Las inscripciones de este torneo no están abiertas.');
if (($ev['boton_inscripcion'] ?? 'si') === 'no') respErr('Las inscripciones de este torneo no están abiertas.');
if ((int)$ev['id_tipo_evento'] === 5) respErr('Los interclubes se inscriben por club, desde el link del evento.');
if ($ev['fecha_fin_inscripcion']) {
    $limite = $ev['fecha_fin_inscripcion'] . ' ' . ($ev['hora_fin_inscripcion'] ?: '23:59:59');
    if (strtotime($limite) !== false && strtotime($limite) < time()) respErr('Las inscripciones cerraron el ' . date('d-m-Y', strtotime($ev['fecha_fin_inscripcion'])) . '.');
}

// ── Categoría del evento, activa y con cupo ──────────────────────
$st = $mysqli2->prepare(
    "SELECT rc.cupo, rc.sexo, c.categoria
     FROM _relacion_evento_categoria rc
     JOIN _p_categorias c ON c.id = rc.id_categoria
     WHERE rc.id_evento = ? AND rc.id_categoria = ? AND rc.estado = 'activo' LIMIT 1"
);
$st->bind_param('ii', $idEvento, $idCategoria);
$st->execute();
$cat = $st->get_result()->fetch_assoc();
$st->close();
if (!$cat) respErr('Categoría inválida para este torneo.');
if (($cat['cupo'] ?? 'disponible') === 'lleno') respErr('La categoría ' . $cat['categoria'] . ' ya no tiene cupo.');

// ── Los dos jugadores registrados ────────────────────────────────
$st = $mysqli2->prepare("SELECT ci, nombre, apellido FROM _p_usuarios WHERE TRIM(ci) IN (?, ?)");
$st->bind_param('ss', $ci1, $ci2);
$st->execute();
$rs = $st->get_result();
$jug = [];
while ($row = $rs->fetch_assoc()) $jug[normalizarCi($row['ci'])] = $row;
$st->close();
foreach ([$ci1, $ci2] as $ci) {
    if (!isset($jug[$ci])) respErr("El jugador con CI $ci no está registrado en bt.com.py. Registrate en la web primero.");
}

// ── Sin inscripción previa en la misma categoría ─────────────────
$st = $mysqli2->prepare(
    "SELECT id FROM _p_incripciones
     WHERE (ci = ? OR ci = ?) AND id_categoria = ? AND id_evento = ? AND estado <> 'bloqueado' LIMIT 1"
);
$st->bind_param('ssii', $ci1, $ci2, $idCategoria, $idEvento);
$st->execute();
$previa = $st->get_result()->fetch_assoc();
$st->close();
if ($previa) {
    http_response_code(400);
    resp(['success' => false, 'error' => 'Alguno de los jugadores ya tiene inscripción en esta categoría.', 'codigo' => (int)$previa['id']]);
}

// ── Insertar las dos filas (A→B y B→A), como la web ──────────────
$mysqli2->begin_transaction();
try {
    $st = $mysqli2->prepare(
        "INSERT INTO _p_incripciones (id_evento, ci, ci_dupla, id_categoria, phorario, comentario, medio)
         VALUES (?, ?, ?, ?, 'no', '', 'app')"
    );
    $st->bind_param('issi', $idEvento, $ci1, $ci2, $idCategoria);
    $st->execute();
    $codigo = (int)$mysqli2->insert_id;
    $st->bind_param('issi', $idEvento, $ci2, $ci1, $idCategoria);
    $st->execute();
    $st->close();
    $mysqli2->commit();
} catch (Throwable $e) {
    $mysqli2->rollback();
    error_log('bt_app_inscribir: ' . $e->getMessage());
    respErr('No se pudo registrar la inscripción. Probá de nuevo.', 500);
}

resp([
    'success'   => true,
    'codigo'    => $codigo,
    'evento'    => $ev['evento'],
    'categoria' => $cat['categoria'],
    'jugador1'  => nombreJugador($jug[$ci1]),
    'jugador2'  => nombreJugador($jug[$ci2]),
]);
