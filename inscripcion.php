<?php
/**
 * inscripcion.php — Formulario de inscripción Beach Tennis
 * Diseño moderno con wizard 4 pasos + lógica completa de BD
 */

// ── Obtener url/id del evento ─────────────────────────────────────────────────
// El .htaccess reescribe:  /torneo-SLUG&5  →  inscripcion.php?url=SLUG&5=
// PHP parsea: $_GET['url']='SLUG'  y  $_GET['5']=''
// El slug llega limpio. Capturamos también el id numérico suelto si existe.
$dato    = '';
$idExtra = 0;

if (isset($_GET['id'])) {
    $dato = abs((int) base64_decode(filter_var($_GET['id'], FILTER_SANITIZE_STRING)));
} elseif (isset($_GET['url'])) {
    $raw  = filter_var($_GET['url'], FILTER_SANITIZE_STRING);
    $raw  = preg_replace('/&\d+$/', '', $raw); // por si acaso llega junto
    $dato = addslashes(trim($raw));

    // Capturar id numérico suelto del &5 en la URL
    foreach ($_GET as $k => $v) {
        if (is_numeric($k) && $v === '') { $idExtra = (int)$k; break; }
    }
}

include_once "db/conection.inc.php";
include_once "funciones.php";
include_once "function.sanear.php";

if (!isset($objeto)) $objeto = new principal($mysqli2);

// ── Cargar datos del evento ───────────────────────────────────────────────────
$sqlEvento = "SELECT id, codigo_evento, evento, nombre_evento2, fecha, fecha_fin,
              costo1, boton_inscripcion, estado, descripcion, reglamentacion,
              flyer, flyer2, mapa, id_organizador, base_condiciones, version_formulario,
              id_tipo_evento
              FROM _p_eventos
              WHERE (estado='activo' OR estado='registro')
              AND (url_amigable='{$dato}' OR id='{$dato}' OR ($idExtra > 0 AND id={$idExtra}))
              LIMIT 1";
$resEvento = $mysqli2->query($sqlEvento);
$evento    = $resEvento ? $resEvento->fetch_assoc() : null;

if (!$evento) {
    // Evento no encontrado
    $idEvento           = 0;
    $nombreEvento       = 'Torneo';
    $subtituloEvento    = '';
    $base_condiciones   = 'b';
    $boton_inscripcion  = 'si';
    $descripcion = $reglamentacion = $mapa = $flyerFile = $flyerUrl = $flyer2File = $flyer2Url = '';
    $fechaEvento = $fechaFin = $costo = '';
    $urlAmigable = $dato;
    $urlVolver   = '#';
} else {
    $idEvento           = (int) $evento['id'];
    $nombreEvento       = $evento['evento'];
    $subtituloEvento    = $evento['nombre_evento2'];
    $base_condiciones   = $evento['base_condiciones'];
    $boton_inscripcion  = $evento['boton_inscripcion'];
    $descripcion        = $evento['descripcion'];
    $reglamentacion     = $evento['reglamentacion'];
    $mapa               = $evento['mapa'];
    $flyerFile          = $evento['flyer'];
    $flyerUrl           = '/img/flyers/' . $flyerFile;
    $flyer2File         = $evento['flyer2'] ?? '';
    $flyer2Url          = $flyer2File ? '/img/flyers/' . $flyer2File : '';
    $fechaEvento        = $evento['fecha'];
    $fechaFin           = $evento['fecha_fin'];
    $costo              = $evento['costo1'];
    // URL para el botón "volver" — reconstruye la URL amigable
    $urlAmigable = $evento['url_amigable'] ?? $dato;
    $urlVolver   = 'torneo-' . $urlAmigable . ($idExtra ? '&' . $idExtra : '');
}

// ── Evento Interclubes: acá no hay inscripción individual ────────────────────
// La inscripción la hace el dueño de cada club desde su link con token
// (interclubes.php?token=...). Esta página muestra solo un aviso.
if ($evento && (int)$evento['id_tipo_evento'] === 5) {
    $flyerTag = $flyerFile
        ? '<img src="' . htmlspecialchars($flyerUrl) . '" alt="" style="max-width:100%;border-radius:12px;margin-bottom:18px;">'
        : '';
    $sub = $subtituloEvento ? ' · ' . htmlspecialchars($subtituloEvento) : '';
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">'
       . '<title>' . htmlspecialchars($nombreEvento) . ' · Interclubes</title>'
       . '<link rel="shortcut icon" href="/favicon.ico">'
       . '<style>body{font-family:\'Segoe UI\',system-ui,sans-serif;background:#f6f1e7;color:#22303c;margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;box-sizing:border-box;}'
       . '.card{max-width:520px;text-align:center;}'
       . '.aviso{background:#fff;border:1px solid #e2e6eb;border-radius:14px;padding:26px 22px;}'
       . '.aviso h1{font-size:21px;margin:0 0 6px;}'
       . '.aviso .badge{display:inline-block;background:#0b6aa8;color:#fff;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:1px;padding:5px 14px;border-radius:20px;margin-bottom:14px;}'
       . '.aviso p{font-size:14px;line-height:1.6;color:#5a6570;margin:10px 0 0;}'
       . '.volver{display:inline-block;margin-top:18px;font-size:13px;color:#0b6aa8;text-decoration:none;font-weight:600;}</style></head>'
       . '<body><div class="card">' . $flyerTag
       . '<div class="aviso"><div class="badge">Torneo Interclubes</div>'
       . '<h1>' . htmlspecialchars($nombreEvento) . $sub . '</h1>'
       . '<p>En este torneo la inscripción se realiza <strong>por club</strong>: el responsable de cada club inscribe a sus parejas desde el link exclusivo que le envió la organización.</p>'
       . '<p>Si sos jugador, hablá con el responsable de tu club.<br>Si sos responsable y no tenés tu link, contactá con la organización.</p>'
       . '<a class="volver" href="https://bt.com.py/">&larr; Volver a bt.com.py</a>'
       . '</div></div></body></html>';
    exit;
}

// PDFs de términos según base_condiciones
$ccfp = ($base_condiciones === 'a')
    ? 'bases-y-condiciones/certificacion-de-condiciones-fisicas-para-participar-en-el-torneo-de-padel.pdf'
    : 'bases-y-condiciones/certificacion-de-condiciones-fisicas-para-participar-en-el-torneo-de-padel-general.pdf';
$dbyc = ($base_condiciones === 'a')
    ? 'bases-y-condiciones/aceptacion-de-bases-y-condiciones-del-torneo-de-padel.pdf'
    : 'bases-y-condiciones/aceptacion-de-bases-y-condiciones-del-torneo-de-padel-general.pdf';
$privacidad = 'bases-y-condiciones/politica-de-privacidad-y-tratamiento-de-datos-para-PADELSYS.pdf';

// ── Funciones de enmascarado para AJAX ───────────────────────────────────────
function maskText($str) {
    $str = trim($str);
    if (strlen($str) === 0) return '';
    $words = explode(' ', $str);
    $masked = [];
    foreach ($words as $w) {
        if (strlen($w) <= 2) { $masked[] = $w; continue; }
        $masked[] = mb_substr($w, 0, 1) . str_repeat('*', mb_strlen($w) - 1);
    }
    return implode(' ', $masked);
}
function maskPhone($str) {
    $str = preg_replace('/[^0-9]/', '', $str);
    if (strlen($str) <= 4) return $str;
    return substr($str, 0, 4) . str_repeat('*', strlen($str) - 4);
}
function maskEmail($str) {
    $str = trim($str);
    if (strpos($str, '@') === false) return $str ? str_repeat('*', strlen($str)) : '';
    [$local, $domain] = explode('@', $str, 2);
    return mb_substr($local, 0, 2) . str_repeat('*', max(0, mb_strlen($local) - 2)) . '@' . $domain;
}

// ── AJAX: buscar CI ───────────────────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'buscar_ci') {
    header('Content-Type: application/json; charset=utf-8');
    $ci_raw = isset($_GET['ci']) ? $_GET['ci'] : '';
    $ci_num = ltrim(preg_replace('/[^0-9]/', '', $ci_raw), '0');
    if (strlen($ci_num) < 5) {
        echo json_encode(['found' => false, 'error' => 'ci_corta']);
        exit;
    }
    $ci_esc = $mysqli2->real_escape_string($ci_num);

    // 1) _p_usuarios
    $sqlU = "SELECT nombre, apellido, cel, email, ciudad FROM _p_usuarios WHERE TRIM(ci)=TRIM('{$ci_esc}') LIMIT 1";
    $resU = $mysqli2->query($sqlU);
    if ($resU && $resU->num_rows > 0) {
        $row = $resU->fetch_assoc();
        echo json_encode([
            'found'         => true,  'source'        => 'usuarios',
            'nombre'        => maskText($row['nombre']),   'apellido' => maskText($row['apellido']),
            'cel'           => maskPhone($row['cel']),     'email'    => maskEmail($row['email']),
            'ciudad'        => maskText($row['ciudad']),
            'nombre_real'   => $row['nombre'],  'apellido_real' => $row['apellido'],
            'cel_real'      => $row['cel'],     'email_real'    => $row['email'],
            'ciudad_real'   => $row['ciudad'],
        ]);
        exit;
    }

    // 2) _ci_py
    $sqlC = "SELECT nombre, apellido FROM _ci_py WHERE ci LIKE '{$ci_esc}' LIMIT 1";
    $resC = $mysqli2->query($sqlC);
    if ($resC && $resC->num_rows > 0) {
        $row = $resC->fetch_assoc();
        echo json_encode([
            'found'         => true,  'source'        => 'ci_py',
            'nombre'        => maskText($row['nombre']),   'apellido' => maskText($row['apellido']),
            'cel'           => '',    'email'          => '',  'ciudad' => '',
            'nombre_real'   => $row['nombre'],  'apellido_real' => $row['apellido'],
            'cel_real'      => '',   'email_real'     => '',  'ciudad_real' => '',
        ]);
        exit;
    }

    echo json_encode(['found' => false]);
    exit;
}

// ── AJAX: categorias por género ───────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'categorias') {
    header('Content-Type: application/json; charset=utf-8');
    $sexo_raw = strtolower(filter_var($_GET['sexo'] ?? '', FILTER_SANITIZE_STRING));
    // Mapear género del frontend al campo sexo de la BD
    $sexo_map = ['caballeros' => 'hombre', 'damas' => 'mujer', 'mixto' => 'mixto'];
    $sexo_bd  = $sexo_map[$sexo_raw] ?? 'hombre';
    $sexo_esc = $mysqli2->real_escape_string($sexo_bd);
    // El id_evento llega como parámetro GET en la petición AJAX
    $ev_id = isset($_GET['id_evento']) ? (int)$_GET['id_evento'] : (int)($idEvento ?? 0);

    // JOIN con _relacion_evento_categoria para respetar el estado que configura el admin
    // estado='activo' = habilitado por admin
    // cupo='lleno' o cupo='no disponible' = cerrado para inscripción
    // disponible='no' = cerrado para inscripción
    $sql = "SELECT v.id_categoria, v.categoria, rec.estado, rec.cupo, rec.visualizar_en_llaves as disponible
            FROM v_p_categorias v
            INNER JOIN _relacion_evento_categoria rec
              ON rec.id_categoria = v.id_categoria
             AND rec.id_evento    = {$ev_id}
            WHERE v.sexo='{$sexo_esc}'
              AND v.id_evento={$ev_id}
            ORDER BY v.categoria";
    $res  = $mysqli2->query($sql);
    $cats = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $cats[] = [
                'id'          => (int) $row['id_categoria'],
                'nombre'      => $row['categoria'],
                'disponible'  => ($row['estado'] === 'activo' && $row['cupo'] !== 'lleno'),
                'estado'      => $row['estado'],
            ];
        }
    }
    echo json_encode(['categorias' => $cats]);
    exit;
}

// ── POST: procesar inscripción ────────────────────────────────────────────────
$resultado_inscripcion = null; // null=no procesado, array=resultado

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['p1_ci'])) {

    // Sanitizar CI Jugador 1
    $ci1 = abs((int) preg_replace('/[^0-9]/', '', $_POST['p1_ci']));

    // Sanitizar CI Jugador 2
    $ci2 = abs((int) preg_replace('/[^0-9]/', '', $_POST['p2_ci']));

    // Sanitizar categoría — el frontend envía el id_categoria directamente
    $categoriaStr = ''; // se rellena luego con el nombre desde la BD

    // Sanitizar género / sexo
    $sexo_raw = strtolower(filter_var($_POST['genero'] ?? '', FILTER_SANITIZE_STRING));
    $sexo_map = ['caballeros' => 'hombre', 'damas' => 'mujer', 'mixto' => 'mixto'];
    $sexo     = $sexo_map[$sexo_raw] ?? 'hombre';

    // Función para limpiar celular
    function sanitizarCel($v) {
        return preg_replace('/[^0-9]/', '', $v);
    }

    // Función helper para limpiar texto — compatible PHP 8+
    function cleanStr($v) {
        return trim(strip_tags($v ?? ''));
    }

    // Datos Jugador 1
    // Para jugador NUEVO: los _real están vacíos → usa los campos editables del form
    // Para jugador ENCONTRADO: los _real tienen los datos originales de BD
    $p1_es_reg  = ((int) ($_POST['p1_es_registrado'] ?? 0)) === 1;
    $p1_nombre  = cleanStr($_POST['p1_nombre_real']   ?? '') ?: cleanStr($_POST['p1_nombres']   ?? '');
    $p1_apell   = cleanStr($_POST['p1_apellido_real'] ?? '') ?: cleanStr($_POST['p1_apellidos'] ?? '');
    $p1_cel     = sanitizarCel(($_POST['p1_cel_real'] ?? '') ?: ($_POST['p1_celular'] ?? ''));
    $p1_email   = filter_var(($_POST['p1_email_real'] ?? '') ?: ($_POST['p1_email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $p1_ciudad  = cleanStr($_POST['p1_ciudad_real']   ?? '') ?: cleanStr($_POST['p1_ciudad']    ?? '');

    // Datos Jugador 2
    $p2_es_reg  = ((int) ($_POST['p2_es_registrado'] ?? 0)) === 1;
    $p2_nombre  = cleanStr($_POST['p2_nombre_real']   ?? '') ?: cleanStr($_POST['p2_nombres']   ?? '');
    $p2_apell   = cleanStr($_POST['p2_apellido_real'] ?? '') ?: cleanStr($_POST['p2_apellidos'] ?? '');
    $p2_cel     = sanitizarCel(($_POST['p2_cel_real'] ?? '') ?: ($_POST['p2_celular'] ?? ''));
    $p2_email   = filter_var(($_POST['p2_email_real'] ?? '') ?: ($_POST['p2_email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $p2_ciudad  = cleanStr($_POST['p2_ciudad_real']   ?? '') ?: cleanStr($_POST['p2_ciudad']    ?? '');

    $phorario   = 'no'; // horario desactivado en el formulario

    // ── Validaciones básicas ──────────────────────────────────────────────────
    if ($ci1 < 100000 || $ci2 < 100000) {
        $resultado_inscripcion = ['error' => 'ci_invalida', 'msg' => 'Cédula inválida. Verificá los datos.'];
    } elseif ($ci1 === $ci2) {
        $resultado_inscripcion = ['error' => 'ci_iguales', 'msg' => 'Ambos jugadores tienen la misma cédula.'];
    } elseif (empty($_POST['categoria']) || (int)$_POST['categoria'] <= 0) {
        $resultado_inscripcion = ['error' => 'sin_categoria', 'msg' => 'Seleccioná una categoría.'];
    } else {
        // ── El frontend envía id_categoria directamente ──────────────────────
        $idCategoria = (int) filter_var($_POST['categoria'] ?? 0, FILTER_SANITIZE_NUMBER_INT);

        if ($idCategoria <= 0) {
            $resultado_inscripcion = ['error' => 'categoria_no_encontrada', 'msg' => 'Categoría inválida.'];
        } else {
            // Recuperar nombre legible para mostrar en el resultado
            $resCatNombre = $mysqli2->query("SELECT categoria FROM _p_categorias WHERE id={$idCategoria} LIMIT 1");
            if ($resCatNombre && $resCatNombre->num_rows > 0) {
                $categoriaStr = $resCatNombre->fetch_assoc()['categoria'];
            }
            // ── Verificar inscripción previa (mismo evento + categoría) ───────
            $sqlCheck = "SELECT id FROM _p_incripciones
                         WHERE (ci='{$ci1}' OR ci='{$ci2}')
                         AND id_categoria={$idCategoria}
                         AND id_evento={$idEvento} LIMIT 1";
            $resCheck = $mysqli2->query($sqlCheck);

            if ($resCheck && $resCheck->num_rows > 0) {
                $rowCheck = $resCheck->fetch_assoc();
                $resultado_inscripcion = [
                    'error'  => 'ya_inscripto',
                    'msg'    => 'Alguno de los jugadores ya tiene inscripción activa en esta categoría.',
                    'codigo' => $rowCheck['id'],
                ];
            } else {
                // ── Upsert Jugador 1 ──────────────────────────────────────────
                $ci1esc  = $mysqli2->real_escape_string($ci1);
                $sqlChk1 = "SELECT id FROM _p_usuarios WHERE TRIM(ci)=TRIM('{$ci1esc}') LIMIT 1";
                $resChk1 = $mysqli2->query($sqlChk1);

                if ($resChk1 && $resChk1->num_rows > 0) {
                    // UPDATE — solo sobreescribimos si el campo tiene valor
                    $sets = [];
                    if ($p1_nombre) $sets[] = "nombre='" . $mysqli2->real_escape_string($p1_nombre) . "'";
                    if ($p1_apell)  $sets[] = "apellido='" . $mysqli2->real_escape_string($p1_apell) . "'";
                    if ($p1_cel)    $sets[] = "cel='" . $mysqli2->real_escape_string($p1_cel) . "'";
                    if ($p1_email)  $sets[] = "email='" . $mysqli2->real_escape_string($p1_email) . "'";
                    if ($p1_ciudad) $sets[] = "ciudad='" . $mysqli2->real_escape_string($p1_ciudad) . "'";
                    $sets[] = "sexo='" . $mysqli2->real_escape_string($sexo) . "'";
                    $sets[] = "medio='web-inscripcion update'";
                    if (!empty($sets))
                        $mysqli2->query("UPDATE _p_usuarios SET " . implode(',', $sets) . " WHERE TRIM(ci)=TRIM('{$ci1esc}')");
                } else {
                    // INSERT nuevo usuario
                    if (!$p1_nombre || !$p1_apell || !$p1_cel || !$p1_ciudad) {
                        $resultado_inscripcion = ['error' => 'datos_incompletos_p1', 'msg' => 'Completá todos los datos del Jugador 1.'];
                        goto fin_post;
                    }
                    $mysqli2->query("INSERT INTO _p_usuarios
                        (nombre, apellido, email, cel, ci, sexo, tipo_documento, registro, tipo, ciudad, medio)
                        VALUES (
                        '" . $mysqli2->real_escape_string($p1_nombre) . "',
                        '" . $mysqli2->real_escape_string($p1_apell)  . "',
                        '" . $mysqli2->real_escape_string($p1_email)  . "',
                        '" . $mysqli2->real_escape_string($p1_cel)    . "',
                        '{$ci1esc}',
                        '" . $mysqli2->real_escape_string($sexo)      . "',
                        'CI','inscripcion','jugador',
                        '" . $mysqli2->real_escape_string($p1_ciudad) . "',
                        'web-inscripcion')");
                }

                // ── Upsert Jugador 2 ──────────────────────────────────────────
                $ci2esc  = $mysqli2->real_escape_string($ci2);
                $sqlChk2 = "SELECT id FROM _p_usuarios WHERE TRIM(ci)=TRIM('{$ci2esc}') LIMIT 1";
                $resChk2 = $mysqli2->query($sqlChk2);

                if ($resChk2 && $resChk2->num_rows > 0) {
                    $sets = [];
                    if ($p2_nombre) $sets[] = "nombre='" . $mysqli2->real_escape_string($p2_nombre) . "'";
                    if ($p2_apell)  $sets[] = "apellido='" . $mysqli2->real_escape_string($p2_apell) . "'";
                    if ($p2_cel)    $sets[] = "cel='" . $mysqli2->real_escape_string($p2_cel) . "'";
                    if ($p2_email)  $sets[] = "email='" . $mysqli2->real_escape_string($p2_email) . "'";
                    if ($p2_ciudad) $sets[] = "ciudad='" . $mysqli2->real_escape_string($p2_ciudad) . "'";
                    $sets[] = "sexo='" . $mysqli2->real_escape_string($sexo) . "'";
                    $sets[] = "medio='web-inscripcion update'";
                    if (!empty($sets))
                        $mysqli2->query("UPDATE _p_usuarios SET " . implode(',', $sets) . " WHERE TRIM(ci)=TRIM('{$ci2esc}')");
                } else {
                    if (!$p2_nombre || !$p2_apell || !$p2_cel || !$p2_ciudad) {
                        $resultado_inscripcion = ['error' => 'datos_incompletos_p2', 'msg' => 'Completá todos los datos del Jugador 2.'];
                        goto fin_post;
                    }
                    $mysqli2->query("INSERT INTO _p_usuarios
                        (nombre, apellido, email, cel, ci, sexo, tipo_documento, registro, tipo, ciudad, medio)
                        VALUES (
                        '" . $mysqli2->real_escape_string($p2_nombre) . "',
                        '" . $mysqli2->real_escape_string($p2_apell)  . "',
                        '" . $mysqli2->real_escape_string($p2_email)  . "',
                        '" . $mysqli2->real_escape_string($p2_cel)    . "',
                        '{$ci2esc}',
                        '" . $mysqli2->real_escape_string($sexo)      . "',
                        'CI','inscripcion','jugador',
                        '" . $mysqli2->real_escape_string($p2_ciudad) . "',
                        'web-inscripcion')");
                }

                // ── INSERT _p_incripciones (par A→B y B→A) ────────────────────
                $phorEsc = $mysqli2->real_escape_string($phorario);

                $mysqli2->query("INSERT INTO _p_incripciones
                    (id_evento, ci, ci_dupla, id_categoria, phorario, comentario, medio)
                    VALUES ({$idEvento}, '{$ci1esc}', '{$ci2esc}', {$idCategoria}, '{$phorEsc}', '', 'web-inscripcion')");

                $mysqli2->query("INSERT INTO _p_incripciones
                    (id_evento, ci, ci_dupla, id_categoria, phorario, comentario, medio)
                    VALUES ({$idEvento}, '{$ci2esc}', '{$ci1esc}', {$idCategoria}, '{$phorEsc}', '', 'web-inscripcion')");

                // Obtener el ID generado para el Jugador 1
                $sqlId = "SELECT id FROM _p_incripciones
                          WHERE ci='{$ci1esc}' AND id_evento={$idEvento} AND id_categoria={$idCategoria}
                          ORDER BY id DESC LIMIT 1";
                $resId = $mysqli2->query($sqlId);
                $codigoInscripcion = 0;
                if ($resId && $resId->num_rows > 0) {
                    $codigoInscripcion = (int) $resId->fetch_assoc()['id'];
                }

                $resultado_inscripcion = [
                    'ok'       => true,
                    'codigo'   => $codigoInscripcion,
                    'categoria'=> $categoriaStr,
                    'genero'   => ucfirst($sexo_raw),
                ];
            }
        }
    }
    fin_post:
}
?>
<?php
// ── Variables para el header de plantilla.php ─────────────────────────────────
$titulo   = 'Inscripción · ' . htmlspecialchars($nombreEvento) . ' ' . htmlspecialchars($subtituloEvento);
$laurl    = 'https://bt.com.py/' . htmlspecialchars($urlVolver ?? '');
if (!empty($flyerUrl)) {
    $ogImage = '<meta property="og:image" content="' . htmlspecialchars($flyerUrl) . '" />';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1"/>
  <title><?= $titulo ?></title>
  <link rel="shortcut icon" href="/favicon.ico" />
  <!-- Font Awesome requerido por el header global -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <!-- Open Graph -->
  <?php if(isset($laurl)): ?><meta property="og:url" content="<?= $laurl ?>" /><?php endif; ?>
  <?php if(isset($ogImage)): echo $ogImage; else: ?>
  <meta property="og:image" content="https://www.bt.com.py/logo-bt.com.png" />
  <?php endif; ?>
  <?php include_once "style.inc.php"; ?>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --bg:         #f0f4f8;
      --card:       #ffffff;
      --border:     #d6e0ea;
      --primary:    #2e86ab;
      --primary-10: #e8f4f9;
      --primary-fg: #ffffff;
      --fg:         #1a2636;
      --muted:      #6b7c93;
      --muted-bg:   #e8edf3;
      --success-10: #f0fdf4;
      --warning-10: #fffbeb;
      --error-10:   #fff1f2;
      --radius:     14px;
      --shadow:     0 2px 12px rgba(46,134,171,.12);
    }
    body { background:var(--bg); color:var(--fg); font-family:system-ui,-apple-system,sans-serif; min-height:100vh; display:flex; flex-direction:column; }

    /* ── Header ──────────────────────────────────── */
    .top-header { background:rgba(240,244,248,.95); backdrop-filter:blur(8px); border-bottom:1px solid var(--border); padding:12px 16px; display:flex; align-items:center; gap:12px; position:sticky; top:0; z-index:100; }
    .back-btn { height:32px; width:32px; border-radius:50%; border:1px solid var(--border); background:var(--card); display:flex; align-items:center; justify-content:center; text-decoration:none; color:var(--fg); font-size:18px; flex-shrink:0; cursor:pointer; }
    .back-btn:hover { background:var(--muted-bg); }
    .header-info { flex:1; }
    .header-info h1 { font-size:15px; font-weight:700; line-height:1.2; }
    .header-info p  { font-size:11px; color:var(--muted); margin-top:1px; }
    .step-counter { font-size:11px; font-weight:700; color:var(--muted); flex-shrink:0; }

    /* ── Stepper ─────────────────────────────────── */
    .stepper { display:flex; align-items:flex-start; padding:20px 16px 8px; max-width:520px; margin:0 auto; width:100%; }
    .step-item { display:flex; flex-direction:column; align-items:center; gap:4px; }
    .step-item + .step-connector { flex:1; height:2px; margin:0 4px; border-radius:2px; margin-bottom:20px; transition:background .4s; }
    .step-circle { height:36px; width:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; border:2px solid var(--border); background:var(--muted-bg); transition:all .3s; font-size:13px; }
    .step-label { font-size:10px; font-weight:700; color:var(--muted); white-space:nowrap; }
    .step-item.done   .step-circle { background:var(--primary); border-color:var(--primary); color:var(--primary-fg); }
    .step-item.active .step-circle { background:var(--primary-10); border-color:var(--primary); color:var(--primary); }
    .step-item.active .step-label  { color:var(--primary); }
    .step-connector.done-line { background:var(--primary); }
    .step-connector           { background:var(--border); }

    /* ── Content ─────────────────────────────────── */
    .content { flex:1; padding:8px 16px 24px; max-width:520px; margin:0 auto; width:100%; }

    /* ── Section head ────────────────────────────── */
    .section-head { display:flex; align-items:center; gap:12px; padding:8px 0 16px; }
    .section-badge { height:40px; width:40px; border-radius:50%; background:var(--primary); color:var(--primary-fg); display:flex; align-items:center; justify-content:center; font-weight:900; font-size:16px; flex-shrink:0; box-shadow:0 4px 12px rgba(46,134,171,.3); }
    .section-head h2 { font-size:18px; font-weight:700; }
    .section-head p  { font-size:12px; color:var(--muted); margin-top:2px; }

    /* ── Card ────────────────────────────────────── */
    .card { background:var(--card); border:1px solid var(--border); border-radius:var(--radius); box-shadow:var(--shadow); padding:20px; margin-bottom:12px; }
    .card-title { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.07em; color:var(--muted); margin-bottom:14px; }

    /* ── CI Search ───────────────────────────────── */
    .ci-search-wrap { display:flex; gap:8px; align-items:flex-end; }
    .ci-search-wrap .input-wrap { flex:1; }
    .btn-buscar { padding:13px 16px; border-radius:12px; border:none; background:var(--primary); color:var(--primary-fg); font-size:13px; font-weight:700; cursor:pointer; white-space:nowrap; flex-shrink:0; transition:opacity .2s; display:flex; align-items:center; gap:6px; }
    .btn-buscar:hover { opacity:.88; }
    .btn-buscar:disabled { background:var(--muted-bg); color:var(--muted); cursor:not-allowed; }

    /* ── Status ──────────────────────────────────── */
    .ci-status { border-radius:10px; padding:10px 14px; font-size:13px; font-weight:500; margin-top:10px; align-items:center; gap:8px; }
    .ci-status.hidden { display:none; }
    .ci-status.found { display:flex; background:var(--success-10); border:1px solid #bbf7d0; color:#15803d; }
    .ci-status.new   { display:flex; background:var(--warning-10); border:1px solid #fde68a; color:#92400e; }
    .ci-status svg   { flex-shrink:0; width:16px; height:16px; stroke:currentColor; fill:none; stroke-width:2; }

    /* ── Player fields ───────────────────────────── */
    .player-fields { overflow:hidden; transition:max-height .45s ease, opacity .35s ease; max-height:0; opacity:0; }
    .player-fields.visible { max-height:900px; opacity:1; }
    .player-fields-divider { border:none; border-top:1px solid var(--border); margin:16px 0 14px; }

    /* ── Fields ──────────────────────────────────── */
    .field { display:flex; flex-direction:column; gap:6px; margin-bottom:14px; }
    .field:last-child { margin-bottom:0; }
    .field label { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--muted); }
    .input-wrap { position:relative; }
    .input-wrap .field-icon { position:absolute; left:12px; top:50%; transform:translateY(-50%); width:16px; height:16px; stroke:var(--muted); fill:none; stroke-width:2; pointer-events:none; }
    .input-wrap input { width:100%; padding:13px 14px 13px 38px; border:1px solid var(--border); border-radius:12px; background:var(--bg); font-size:14px; color:var(--fg); outline:none; transition:border .2s, box-shadow .2s, background .2s; -webkit-appearance:none; }
    .input-wrap input:focus { border-color:var(--primary); box-shadow:0 0 0 3px rgba(46,134,171,.15); }
    .input-wrap input::placeholder { color:var(--muted); }
    .input-wrap input[readonly] { background:var(--muted-bg); color:var(--muted); cursor:not-allowed; letter-spacing:.04em; }
    .lock-badge { position:absolute; right:10px; top:50%; transform:translateY(-50%); width:16px; height:16px; opacity:.4; display:none; }
    .input-wrap input[readonly] ~ .lock-badge { display:block; }

    /* spinner */
    .spinner { width:15px; height:15px; border:2px solid #fff; border-top-color:transparent; border-radius:50%; animation:spin .6s linear infinite; display:inline-block; vertical-align:middle; }
    @keyframes spin { to { transform:rotate(360deg); } }

    /* ── Radio ───────────────────────────────────── */
    .radio-list { display:flex; flex-direction:column; gap:8px; }
    .radio-btn { display:flex; align-items:center; gap:12px; padding:14px 16px; border-radius:12px; border:1px solid var(--border); background:var(--bg); cursor:pointer; transition:all .2s; font-size:14px; font-weight:500; }
    .radio-btn:hover { background:var(--muted-bg); }
    .radio-btn.selected { border-color:var(--primary); background:var(--primary-10); color:var(--primary); font-weight:600; }
    .radio-dot { width:16px; height:16px; border-radius:50%; border:2px solid var(--muted); display:flex; align-items:center; justify-content:center; flex-shrink:0; transition:border .2s; }
    .radio-btn.selected .radio-dot { border-color:var(--primary); }
    .radio-dot-inner { width:8px; height:8px; border-radius:50%; background:var(--primary); display:none; }
    .radio-btn.selected .radio-dot-inner { display:block; }

    /* ── Cat grid ────────────────────────────────── */
    .cat-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
    .cat-btn { padding:13px 8px; border-radius:12px; border:1px solid var(--border); background:var(--bg); font-size:13px; font-weight:500; color:var(--fg); cursor:pointer; transition:all .2s; text-align:center; }
    .cat-btn:hover { background:var(--muted-bg); }
    .cat-btn.selected   { border-color:var(--primary); background:var(--primary-10); color:var(--primary); font-weight:700; }
    .cat-btn.cat-disabled { background:var(--muted-bg); color:var(--muted); cursor:not-allowed; opacity:.65; border-style:dashed; position:relative; }
    .cat-btn.cat-disabled:hover { background:var(--muted-bg); }
    .cat-badge-no { display:block; font-size:10px; font-weight:700; color:#b91c1c; margin-top:4px; letter-spacing:.03em; }

    /* ── Summary ─────────────────────────────────── */
    .summary-row { display:flex; justify-content:space-between; align-items:center; padding:9px 0; border-bottom:1px solid var(--border); }
    .summary-row:last-child { border-bottom:none; }
    .summary-row .s-label { font-size:12px; color:var(--muted); }
    .summary-row .s-value { font-size:13px; font-weight:600; text-align:right; max-width:60%; }

    /* ── Checkboxes ──────────────────────────────── */
    .check-item { display:flex; align-items:flex-start; gap:12px; cursor:pointer; margin-bottom:16px; }
    .check-item:last-child { margin-bottom:0; }
    .check-box { width:20px; height:20px; border-radius:6px; border:2px solid var(--border); display:flex; align-items:center; justify-content:center; flex-shrink:0; margin-top:1px; transition:all .2s; background:var(--bg); }
    .check-box.checked { background:var(--primary); border-color:var(--primary); }
    .check-box svg { width:12px; height:12px; stroke:white; fill:none; stroke-width:2.5; display:none; }
    .check-box.checked svg { display:block; }
    .check-label { font-size:13px; line-height:1.5; color:var(--fg); }
    .check-label a { color:var(--primary); text-decoration:underline; }
    .hint { font-size:13px; color:var(--muted); font-style:italic; }

    /* ── Bottom nav ──────────────────────────────── */
    .bottom-nav { position:sticky; bottom:0; z-index:100; background:rgba(240,244,248,.95); backdrop-filter:blur(8px); border-top:1px solid var(--border); padding:14px 16px; }
    .nav-row { display:flex; gap:10px; max-width:520px; margin:0 auto; }
    .btn-back { display:flex; align-items:center; gap:6px; padding:14px 18px; border-radius:12px; border:1px solid var(--border); background:var(--card); font-size:13px; font-weight:700; color:var(--fg); cursor:pointer; transition:background .2s; white-space:nowrap; }
    .btn-back:hover { background:var(--muted-bg); }
    .btn-next { flex:1; padding:14px; border-radius:12px; border:none; background:var(--primary); color:var(--primary-fg); font-size:14px; font-weight:700; cursor:pointer; box-shadow:0 4px 14px rgba(46,134,171,.3); transition:opacity .2s, transform .1s; display:flex; align-items:center; justify-content:center; gap:6px; }
    .btn-next:hover { opacity:.92; }
    .btn-next:active { transform:scale(.98); }
    .btn-next.disabled { background:var(--muted-bg); color:var(--muted); box-shadow:none; cursor:not-allowed; }

    /* ── Resultado inscripción ───────────────────── */
    .result-wrap { max-width:520px; margin:32px auto; padding:0 16px 48px; }
    .result-card { border-radius:var(--radius); padding:32px 24px; text-align:center; }
    .result-card.ok    { background:var(--success-10); border:1px solid #bbf7d0; }
    .result-card.error { background:var(--error-10);   border:1px solid #fecaca; }
    .result-icon { font-size:48px; margin-bottom:12px; }
    .result-card h2 { font-size:22px; font-weight:800; margin-bottom:8px; }
    .result-card.ok    h2 { color:#15803d; }
    .result-card.error h2 { color:#b91c1c; }
    .result-card p { font-size:14px; line-height:1.6; color:#374151; }
    .result-codigo { display:inline-block; margin-top:16px; padding:10px 24px; border-radius:24px; background:var(--primary); color:white; font-size:15px; font-weight:700; letter-spacing:.04em; }
    .btn-volver { display:inline-block; margin-top:20px; padding:12px 28px; border-radius:12px; background:var(--primary); color:white; font-size:14px; font-weight:700; text-decoration:none; border:none; cursor:pointer; }

    @media (max-width:400px) {
      .stepper { padding:16px 10px 6px; }
      .step-label { font-size:9px; }
    }

    /* ── Sección evento ──────────────────────────── */
    .event-section  { background:var(--bg); padding:16px 16px 0; }
    .event-inner    { max-width:520px; margin:0 auto; }
    .event-desc-main { font-size:14px; line-height:1.7; color:var(--fg); margin-bottom:14px; }

    /* ── Acordeón ────────────────────────────────── */
    .acordeon       { display:flex; flex-direction:column; gap:0; margin-bottom:16px; }
    .acc-item       { background:var(--card); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; margin-bottom:8px; box-shadow:var(--shadow); }
    .acc-head       { width:100%; display:flex; align-items:center; gap:12px; padding:16px; background:none; border:none; cursor:pointer; text-align:left; transition:background .15s; }
    .acc-head:hover { background:var(--primary-10); }
    .acc-icon       { width:40px; height:40px; border-radius:50%; background:var(--primary-10); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .acc-icon svg   { width:18px; height:18px; stroke:var(--primary); fill:none; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; }
    .acc-titles     { flex:1; display:flex; flex-direction:column; gap:2px; }
    .acc-title      { font-size:15px; font-weight:700; color:var(--fg); line-height:1.2; }
    .acc-sub        { font-size:11px; color:var(--muted); }
    .acc-arrow      { width:18px; height:18px; stroke:var(--muted); fill:none; stroke-width:2.5; stroke-linecap:round; stroke-linejoin:round; flex-shrink:0; transition:transform .3s; }
    .acc-item.open .acc-arrow { transform:rotate(180deg); }
    .acc-body       { max-height:0; overflow:hidden; transition:max-height .4s ease; }
    .acc-item.open .acc-body  { max-height:2000px; }
    .acc-body-inner { padding:0 16px 16px; }

    /* ── Contenido acordeón ──────────────────────── */
    .acc-reglamento { font-size:13px; line-height:1.7; color:var(--fg); }
    .acc-reglamento a { color:var(--primary); text-decoration:underline; }
    /* Ítems tipo lista dentro del reglamento */
    .acc-reg-item   { display:flex; gap:12px; padding:12px 0; border-bottom:1px solid var(--border); }
    .acc-reg-item:last-child { border-bottom:none; }
    .acc-reg-icon   { width:32px; height:32px; border-radius:50%; background:var(--primary-10); display:flex; align-items:center; justify-content:center; flex-shrink:0; margin-top:2px; }
    .acc-reg-icon svg { width:15px; height:15px; stroke:var(--primary); fill:none; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; }
    .acc-reg-content h5 { font-size:13px; font-weight:700; color:var(--fg); margin-bottom:3px; }
    .acc-reg-content p  { font-size:12px; color:var(--muted); line-height:1.5; margin:0; }
    .acc-reg-content a  { display:inline-flex; align-items:center; gap:4px; font-size:12px; font-weight:600; color:var(--primary); text-decoration:none; margin-top:6px; }
    .acc-reg-content a:hover { text-decoration:underline; }
    .acc-mapa iframe { width:100%; border:0; border-radius:10px; display:block; min-height:220px; }
    /* Imágenes dentro del campo descripcion (cronograma) */
    .acc-cronograma-body img { width:100%; height:auto; border-radius:10px; display:block; }
    .acc-cronograma-body p   { margin:0; }

  </style>
</head>
<body>

<!-- ════════════════════════════════════════════
     HEADER GLOBAL (igual que el resto del sitio)
════════════════════════════════════════════ -->
<?php include_once "script.inc.php"; ?>

<!-- Navbar Superior -->
<nav class="top-navbar" style="background-color:#1e293b;position:sticky;top:0;z-index:50;box-shadow:0 1px 3px rgba(0,0,0,.1);">
  <div style="max-width:1200px;margin:0 auto;padding:.75rem 1rem;display:flex;justify-content:space-between;align-items:center;">
    <div></div>
    <div style="display:flex;align-items:center;gap:1rem;">
      <a href="https://www.instagram.com/beach_tennispy/" target="_blank" style="color:#ec4899;font-size:1.5rem;text-decoration:none;">
        <i class="fa-brands fa-instagram"></i>
      </a>
      <?php if(isset($_SESSION['user'])): ?>
        <a href="perfil.php" style="background:#3b82f6;color:white;border-radius:50%;width:2rem;height:2rem;display:flex;align-items:center;justify-content:center;text-decoration:none;">
          <i class="fa-solid fa-user" style="font-size:.85rem;"></i>
        </a>
      <?php else: ?>
        <a href="login" style="background:#3b82f6;color:white;border-radius:50%;width:2rem;height:2rem;display:flex;align-items:center;justify-content:center;text-decoration:none;">
          <i class="fa-solid fa-user" style="font-size:.85rem;"></i>
        </a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<!-- Hero Header -->
<header style="background:linear-gradient(to bottom,#334155,#1e293b);padding:1.5rem 1rem 2rem;text-align:center;border-radius:0 0 1.5rem 1.5rem;box-shadow:0 4px 6px -1px rgba(0,0,0,.1);margin-bottom:.5rem;">
  <a href="/" style="text-decoration:none;display:block;">
    <div style="max-width:28rem;margin:0 auto;">
      <div style="width:8rem;height:8rem;border-radius:50%;margin:0 auto;border:4px solid white;box-shadow:0 4px 6px -1px rgba(0,0,0,.1);overflow:hidden;background-color:#fbbf24;">
        <img src="/img/logo-ASO.png" alt="Circuito Hernandariense" style="width:100%;height:100%;object-fit:cover;">
      </div>
      <h1 style="color:white;font-size:1.5rem;font-weight:800;margin-top:1rem;margin-bottom:.25rem;text-transform:uppercase;letter-spacing:.06em;">Circuito Hernandariense</h1>
      <p style="color:#cbd5e1;font-size:.8rem;margin:0 0 .6rem;text-transform:uppercase;letter-spacing:.07em;">Beach Tennis 2026</p>
      <?php if ($nombreEvento): ?>
      <p style="color:white;font-size:1rem;font-weight:700;margin:0;text-transform:uppercase;letter-spacing:.04em;"><?= htmlspecialchars($nombreEvento) ?></p>
      <?php endif; ?>
    </div>
  </a>
</header>

<?php if ($resultado_inscripcion !== null): ?>
<!-- ════════════════════════════════════════════
     RESULTADO DE LA INSCRIPCIÓN
════════════════════════════════════════════ -->
<div style="max-width:520px;margin:0 auto;padding:0 16px;">
  <a href="/<?= htmlspecialchars($urlVolver ?? '') ?>" class="back-btn" style="margin:16px 0;display:inline-flex;align-items:center;gap:6px;font-size:13px;color:var(--muted);text-decoration:none;">&#8249; Volver al torneo</a>
</div>

<div class="result-wrap">
  <?php if (!empty($resultado_inscripcion['ok'])): ?>
    <div class="result-card ok">
      <div class="result-icon">🎾</div>
      <h2>¡Inscripción exitosa!</h2>
      <p>Tu equipo quedó registrado correctamente.<br>
         <strong><?= htmlspecialchars($resultado_inscripcion['genero']) ?> · <?= htmlspecialchars($resultado_inscripcion['categoria']) ?></strong>
      </p>
      <?php if ($resultado_inscripcion['codigo']): ?>
        <div class="result-codigo">Código de inscripción: #<?= $resultado_inscripcion['codigo'] ?></div>
      <?php endif; ?>
      <br>
      <a href="/<?= htmlspecialchars($urlVolver) ?>" class="btn-volver">Volver al torneo</a>
    </div>

  <?php elseif (isset($resultado_inscripcion['error']) && $resultado_inscripcion['error'] === 'ya_inscripto'): ?>
    <div class="result-card error">
      <div class="result-icon">⚠️</div>
      <h2>Ya existe una inscripción</h2>
      <p><?= htmlspecialchars($resultado_inscripcion['msg']) ?></p>
      <?php if (!empty($resultado_inscripcion['codigo'])): ?>
        <div class="result-codigo">Código #<?= $resultado_inscripcion['codigo'] ?></div>
      <?php endif; ?>
      <br>
      <a href="/<?= htmlspecialchars($urlVolver) ?>" class="btn-volver">Volver al torneo</a>
    </div>

  <?php else: ?>
    <div class="result-card error">
      <div class="result-icon">❌</div>
      <h2>Error al inscribirse</h2>
      <p><?= htmlspecialchars($resultado_inscripcion['msg'] ?? 'Ocurrió un error inesperado.') ?></p>
      <br>
      <button class="btn-volver" onclick="history.back()">← Volver e intentar de nuevo</button>
    </div>
  <?php endif; ?>
</div>

<?php else: ?>
<!-- ════════════════════════════════════════════
     FORMULARIO WIZARD
════════════════════════════════════════════ -->

<div class="top-header">
  <a href="/<?= htmlspecialchars($urlVolver ?? '') ?>" class="back-btn" title="Volver al torneo">&#8249;</a>
  <div class="header-info">
    <h1>Inscripción al Torneo</h1>
    <p><?= htmlspecialchars($nombreEvento) ?> <?= $subtituloEvento ? '· ' . htmlspecialchars($subtituloEvento) : '' ?></p>
  </div>
  <span class="step-counter" id="stepCounter">1 / 4</span>
</div>


<!-- ════════════════════════════════════════════
     SECCIÓN INFO DEL EVENTO — Acordeón
════════════════════════════════════════════ -->
<?php if ($evento): ?>
<div class="event-section">
  <div class="event-inner">

    <div class="acordeon">

      <!-- ── Item 1: Cronograma — contenido del campo descripcion (HTML con <img>) ── -->
      <?php if ($descripcion): ?>
      <div class="acc-item" id="acc-cronograma">
        <button class="acc-head" type="button" onclick="toggleAcc('acc-cronograma')">
          <span class="acc-icon">
            <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          </span>
          <span class="acc-titles">
            <span class="acc-title">Cronograma</span>
            <span class="acc-sub">Programación del torneo</span>
          </span>
          <svg class="acc-arrow" viewBox="0 0 24 24"><polyline points="18 15 12 9 6 15"/></svg>
        </button>
        <div class="acc-body">
          <div class="acc-body-inner acc-cronograma-body">
            <?= $descripcion ?>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- ── Item 2: Bases y Condiciones (campo reglamentacion) ── -->
      <?php if ($reglamentacion): ?>
      <div class="acc-item" id="acc-bases">
        <button class="acc-head" type="button" onclick="toggleAcc('acc-bases')">
          <span class="acc-icon">
            <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
          </span>
          <span class="acc-titles">
            <span class="acc-title">Bases y Condiciones</span>
            <span class="acc-sub">Reglamento del torneo</span>
          </span>
          <svg class="acc-arrow" viewBox="0 0 24 24"><polyline points="18 15 12 9 6 15"/></svg>
        </button>
        <div class="acc-body">
          <div class="acc-body-inner">
            <div class="acc-reglamento">
              <?php
              // ── Ítem 1: Código de Conducta (del campo reglamentacion) ──────────────
              // Extraemos el link del campo reglamentacion si existe, sino usamos el PDF de términos
              $dom = new DOMDocument();
              libxml_use_internal_errors(true);
              $dom->loadHTML('<meta charset="utf-8">' . $reglamentacion, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
              libxml_clear_errors();
              $xpath   = new DOMXPath($dom);
              $anchors = $xpath->query('//a');
              $linkCodigo = '';
              foreach ($anchors as $a) {
                  $href = trim($a->getAttribute('href'));
                  if ($href) { $linkCodigo = $href; break; }
              }
              // Texto plano del campo (sin HTML)
              $textoReg = trim(strip_tags($reglamentacion));
              // Limpiar entidades y espacios extras
              $textoReg = html_entity_decode($textoReg, ENT_QUOTES | ENT_HTML5, 'UTF-8');
              $textoReg = preg_replace('/\s+/', ' ', $textoReg);
              $textoReg = trim($textoReg);
              ?>

              <!-- Ítem: Código de Conducta -->
              <div class="acc-reg-item">
                <div class="acc-reg-icon">
                  <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                </div>
                <div class="acc-reg-content">
                  <h5>Código de Conducta</h5>
                  <p>Las reglas de conductas serán consideradas de acuerdo al Código de Conducta del World Tour.</p>
                  <?php if ($linkCodigo): ?>
                  <a href="<?= htmlspecialchars($linkCodigo) ?>" target="_blank">
                    Ver Código de Conducta
                    <svg viewBox="0 0 24 24" style="width:11px;height:11px;stroke:currentColor;fill:none;stroke-width:2.5;vertical-align:middle;"><polyline points="15 3 21 3 21 9"/><path d="M10 14L21 3"/><path d="M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5"/></svg>
                  </a>
                  <?php endif; ?>
                </div>
              </div>

              <!-- Ítem: Condiciones Físicas (PDF $ccfp) -->
              <div class="acc-reg-item">
                <div class="acc-reg-icon">
                  <svg viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                </div>
                <div class="acc-reg-content">
                  <h5>Condiciones Físicas</h5>
                  <p>Todos los participantes deben certificar que se encuentran en condiciones físicas aptas para competir y sin impedimento médico alguno.</p>
                  <a href="https://bt.com.py/<?= htmlspecialchars($ccfp) ?>" target="_blank">
                    Ver Certificación
                    <svg viewBox="0 0 24 24" style="width:11px;height:11px;stroke:currentColor;fill:none;stroke-width:2.5;vertical-align:middle;"><polyline points="15 3 21 3 21 9"/><path d="M10 14L21 3"/><path d="M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5"/></svg>
                  </a>
                </div>
              </div>

              <!-- Ítem: Política de Privacidad (PDF $privacidad) -->
              <div class="acc-reg-item">
                <div class="acc-reg-icon">
                  <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg>
                </div>
                <div class="acc-reg-content">
                  <h5>Política de Privacidad</h5>
                  <p>Los datos recopilados serán utilizados exclusivamente para la gestión del torneo, conforme a nuestra política de privacidad.</p>
                  <a href="https://bt.com.py/<?= htmlspecialchars($privacidad) ?>" target="_blank">
                    Ver Política de Privacidad
                    <svg viewBox="0 0 24 24" style="width:11px;height:11px;stroke:currentColor;fill:none;stroke-width:2.5;vertical-align:middle;"><polyline points="15 3 21 3 21 9"/><path d="M10 14L21 3"/><path d="M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5"/></svg>
                  </a>
                </div>
              </div>

            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- ── Item 3: Mapa (campo mapa) ── -->
      <?php if ($mapa): ?>
      <div class="acc-item" id="acc-mapa">
        <button class="acc-head" type="button" onclick="toggleAcc('acc-mapa')">
          <span class="acc-icon">
            <svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
          </span>
          <span class="acc-titles">
            <span class="acc-title">Ubicación</span>
            <span class="acc-sub">Cómo llegar</span>
          </span>
          <svg class="acc-arrow" viewBox="0 0 24 24"><polyline points="18 15 12 9 6 15"/></svg>
        </button>
        <div class="acc-body">
          <div class="acc-body-inner">
            <div class="acc-mapa"><?= $mapa ?></div>
          </div>
        </div>
      </div>
      <?php endif; ?>

    </div><!-- /acordeon -->
  </div>
</div>
<?php endif; ?>

<div class="stepper" id="stepper">
  <div class="step-item active" id="si-0">
    <div class="step-circle">
      <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
    </div>
    <span class="step-label">Jugador 1</span>
  </div>
  <div class="step-connector" id="sc-0"></div>
  <div class="step-item" id="si-1">
    <div class="step-circle">
      <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
    </div>
    <span class="step-label">Jugador 2</span>
  </div>
  <div class="step-connector" id="sc-1"></div>
  <div class="step-item" id="si-2">
    <div class="step-circle">
      <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2"><path d="M12 2l3 6h6l-5 4 2 6-6-4-6 4 2-6L3 8h6z"/></svg>
    </div>
    <span class="step-label">Torneo</span>
  </div>
  <div class="step-connector" id="sc-2"></div>
  <div class="step-item" id="si-3">
    <div class="step-circle">
      <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
    </div>
    <span class="step-label">Confirmar</span>
  </div>
</div>

<form method="POST" id="inscForm" novalidate>
<!-- Hidden para pasar el url del evento en el POST -->
<input type="hidden" name="evento_url" value="<?= htmlspecialchars($dato) ?>">
<input type="hidden" id="idEvento" value="<?= (int)$idEvento ?>">
<div class="content">

  <!-- ═══ STEP 0: Jugador 1 ═══ -->
  <div class="step-panel" id="panel-0">
    <div class="section-head">
      <div class="section-badge">1</div>
      <div><h2>Jugador 1</h2><p>Ingresá la cédula para buscar los datos</p></div>
    </div>
    <div class="card">
      <div class="field">
        <label>Nro. de Documento *</label>
        <div class="ci-search-wrap">
          <div class="input-wrap">
            <svg class="field-icon" viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
            <input type="text" name="p1_ci" id="p1_ci" placeholder="Cédula de identidad" inputmode="numeric" autocomplete="off">
          </div>
          <button type="button" class="btn-buscar" id="btn-buscar-p1" onclick="buscarCI(1)" disabled>
            <svg viewBox="0 0 24 24" style="width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <span id="btn-buscar-p1-txt">Buscar</span>
          </button>
        </div>
      </div>
      <div class="ci-status hidden" id="status-p1"></div>
      <div class="player-fields" id="fields-p1">
        <hr class="player-fields-divider">
        <input type="hidden" name="p1_nombre_real"   id="p1_nombre_real">
        <input type="hidden" name="p1_apellido_real" id="p1_apellido_real">
        <input type="hidden" name="p1_cel_real"      id="p1_cel_real">
        <input type="hidden" name="p1_email_real"    id="p1_email_real">
        <input type="hidden" name="p1_ciudad_real"   id="p1_ciudad_real">
        <input type="hidden" name="p1_es_registrado" id="p1_es_registrado" value="0">
        <div class="field">
          <label>Nombre(s) *</label>
          <div class="input-wrap">
            <svg class="field-icon" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
            <input type="text" name="p1_nombres" id="p1_nombres" placeholder="Nombre(s)" autocomplete="off">
            <svg class="lock-badge" viewBox="0 0 24 24" stroke="#6b7c93" fill="none" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          </div>
        </div>
        <div class="field">
          <label>Apellido(s) *</label>
          <div class="input-wrap">
            <svg class="field-icon" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
            <input type="text" name="p1_apellidos" id="p1_apellidos" placeholder="Apellido(s)" autocomplete="off">
            <svg class="lock-badge" viewBox="0 0 24 24" stroke="#6b7c93" fill="none" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          </div>
        </div>
        <div class="field">
          <label>Celular *</label>
          <div class="input-wrap">
            <svg class="field-icon" viewBox="0 0 24 24"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
            <input type="tel" name="p1_celular" id="p1_celular" placeholder="Ej: 0981 123 456" inputmode="tel">
            <svg class="lock-badge" viewBox="0 0 24 24" stroke="#6b7c93" fill="none" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          </div>
        </div>
        <div class="field">
          <label>Email</label>
          <div class="input-wrap">
            <svg class="field-icon" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            <input type="email" name="p1_email" id="p1_email" placeholder="correo@ejemplo.com" inputmode="email">
            <svg class="lock-badge" viewBox="0 0 24 24" stroke="#6b7c93" fill="none" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          </div>
        </div>
        <div class="field">
          <label>Ciudad *</label>
          <div class="input-wrap">
            <svg class="field-icon" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <input type="text" name="p1_ciudad" id="p1_ciudad" placeholder="Ciudad">
            <svg class="lock-badge" viewBox="0 0 24 24" stroke="#6b7c93" fill="none" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ═══ STEP 1: Jugador 2 ═══ -->
  <div class="step-panel" id="panel-1" style="display:none">
    <div class="section-head">
      <div class="section-badge">2</div>
      <div><h2>Jugador 2</h2><p>Ingresá la cédula para buscar los datos</p></div>
    </div>
    <div class="card">
      <div class="field">
        <label>Nro. de Documento *</label>
        <div class="ci-search-wrap">
          <div class="input-wrap">
            <svg class="field-icon" viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
            <input type="text" name="p2_ci" id="p2_ci" placeholder="Cédula de identidad" inputmode="numeric" autocomplete="off">
          </div>
          <button type="button" class="btn-buscar" id="btn-buscar-p2" onclick="buscarCI(2)" disabled>
            <svg viewBox="0 0 24 24" style="width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <span id="btn-buscar-p2-txt">Buscar</span>
          </button>
        </div>
      </div>
      <div class="ci-status hidden" id="status-p2"></div>
      <div class="player-fields" id="fields-p2">
        <hr class="player-fields-divider">
        <input type="hidden" name="p2_nombre_real"   id="p2_nombre_real">
        <input type="hidden" name="p2_apellido_real" id="p2_apellido_real">
        <input type="hidden" name="p2_cel_real"      id="p2_cel_real">
        <input type="hidden" name="p2_email_real"    id="p2_email_real">
        <input type="hidden" name="p2_ciudad_real"   id="p2_ciudad_real">
        <input type="hidden" name="p2_es_registrado" id="p2_es_registrado" value="0">
        <div class="field">
          <label>Nombre(s) *</label>
          <div class="input-wrap">
            <svg class="field-icon" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
            <input type="text" name="p2_nombres" id="p2_nombres" placeholder="Nombre(s)" autocomplete="off">
            <svg class="lock-badge" viewBox="0 0 24 24" stroke="#6b7c93" fill="none" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          </div>
        </div>
        <div class="field">
          <label>Apellido(s) *</label>
          <div class="input-wrap">
            <svg class="field-icon" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
            <input type="text" name="p2_apellidos" id="p2_apellidos" placeholder="Apellido(s)" autocomplete="off">
            <svg class="lock-badge" viewBox="0 0 24 24" stroke="#6b7c93" fill="none" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          </div>
        </div>
        <div class="field">
          <label>Celular *</label>
          <div class="input-wrap">
            <svg class="field-icon" viewBox="0 0 24 24"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
            <input type="tel" name="p2_celular" id="p2_celular" placeholder="Ej: 0981 123 456" inputmode="tel">
            <svg class="lock-badge" viewBox="0 0 24 24" stroke="#6b7c93" fill="none" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          </div>
        </div>
        <div class="field">
          <label>Email</label>
          <div class="input-wrap">
            <svg class="field-icon" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            <input type="email" name="p2_email" id="p2_email" placeholder="correo@ejemplo.com" inputmode="email">
            <svg class="lock-badge" viewBox="0 0 24 24" stroke="#6b7c93" fill="none" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          </div>
        </div>
        <div class="field">
          <label>Ciudad *</label>
          <div class="input-wrap">
            <svg class="field-icon" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <input type="text" name="p2_ciudad" id="p2_ciudad" placeholder="Ciudad">
            <svg class="lock-badge" viewBox="0 0 24 24" stroke="#6b7c93" fill="none" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ═══ STEP 2: Torneo ═══ -->
  <div class="step-panel" id="panel-2" style="display:none">
    <div class="section-head">
      <div class="section-badge">
        <svg viewBox="0 0 24 24" style="width:20px;height:20px;stroke:white;fill:none;stroke-width:2"><path d="M12 2l3 6h6l-5 4 2 6-6-4-6 4 2-6L3 8h6z"/></svg>
      </div>
      <div><h2>Datos del Torneo</h2><p>Seleccioná género y categoría</p></div>
    </div>
    <div class="card">
      <p class="card-title">Género</p>
      <input type="hidden" name="genero" id="generoInput" value="">
      <div class="radio-list">
        <div class="radio-btn" onclick="selectGenero('Caballeros', this)">
          <div class="radio-dot"><div class="radio-dot-inner"></div></div>Caballeros
        </div>
        <div class="radio-btn" onclick="selectGenero('Damas', this)">
          <div class="radio-dot"><div class="radio-dot-inner"></div></div>Damas
        </div>
        <div class="radio-btn" onclick="selectGenero('Mixto', this)">
          <div class="radio-dot"><div class="radio-dot-inner"></div></div>Mixto
        </div>
      </div>
    </div>
    <div class="card">
      <p class="card-title">Categoría</p>
      <input type="hidden" name="categoria" id="categoriaInput" value="">
      <div id="catGrid" class="cat-grid">
        <p class="hint">Seleccioná primero el género</p>
      </div>
    </div>
    <!-- horario desactivado en el form — se envía 'no' como hidden -->
    <input type="hidden" name="horario" value="no">
  </div>

  <!-- ═══ STEP 3: Confirmar ═══ -->
  <div class="step-panel" id="panel-3" style="display:none">
    <div class="section-head">
      <div class="section-badge">
        <svg viewBox="0 0 24 24" style="width:20px;height:20px;stroke:white;fill:none;stroke-width:2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      </div>
      <div><h2>Confirmación</h2><p>Revisá los datos antes de registrar</p></div>
    </div>
    <div class="card">
      <p class="card-title">Jugador 1</p>
      <div class="summary-row"><span class="s-label">Nombre</span><span class="s-value" id="s-p1-nombre">—</span></div>
      <div class="summary-row"><span class="s-label">CI</span><span class="s-value" id="s-p1-ci">—</span></div>
      <div class="summary-row"><span class="s-label">Celular</span><span class="s-value" id="s-p1-cel">—</span></div>
      <div class="summary-row"><span class="s-label">Email</span><span class="s-value" id="s-p1-email">—</span></div>
    </div>
    <div class="card">
      <p class="card-title">Jugador 2</p>
      <div class="summary-row"><span class="s-label">Nombre</span><span class="s-value" id="s-p2-nombre">—</span></div>
      <div class="summary-row"><span class="s-label">CI</span><span class="s-value" id="s-p2-ci">—</span></div>
      <div class="summary-row"><span class="s-label">Celular</span><span class="s-value" id="s-p2-cel">—</span></div>
      <div class="summary-row"><span class="s-label">Email</span><span class="s-value" id="s-p2-email">—</span></div>
    </div>
    <div class="card">
      <p class="card-title">Torneo</p>
      <div class="summary-row"><span class="s-label">Evento</span><span class="s-value"><?= htmlspecialchars($nombreEvento) ?></span></div>
      <div class="summary-row"><span class="s-label">Género</span><span class="s-value" id="s-genero">—</span></div>
      <div class="summary-row"><span class="s-label">Categoría</span><span class="s-value" id="s-categoria">—</span></div>
    </div>
    <div class="card">
      <p class="card-title">Términos y Condiciones</p>
      <div class="check-item" onclick="toggleCheck('chkFisico','cb-fisico')">
        <div class="check-box" id="chkFisico"><svg viewBox="0 0 12 12"><path d="M2 6l3 3 5-5" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
        <span class="check-label">He leído y acepto <a href="<?= htmlspecialchars($ccfp) ?>" target="_blank" onclick="event.stopPropagation()">Certificación de Condiciones Físicas para Participar</a></span>
        <input type="checkbox" name="term_fisico" id="cb-fisico" style="display:none">
      </div>
      <div class="check-item" onclick="toggleCheck('chkPriv','cb-priv')">
        <div class="check-box" id="chkPriv"><svg viewBox="0 0 12 12"><path d="M2 6l3 3 5-5" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
        <span class="check-label">He leído y acepto <a href="<?= htmlspecialchars($privacidad) ?>" target="_blank" onclick="event.stopPropagation()">Política de Privacidad y Tratamiento de Datos</a></span>
        <input type="checkbox" name="term_priv" id="cb-priv" style="display:none">
      </div>
      <div class="check-item" onclick="toggleCheck('chkBases','cb-bases')">
        <div class="check-box" id="chkBases"><svg viewBox="0 0 12 12"><path d="M2 6l3 3 5-5" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
        <span class="check-label">He leído y acepto <a href="<?= htmlspecialchars($dbyc) ?>" target="_blank" onclick="event.stopPropagation()">Bases y Condiciones del Torneo</a></span>
        <input type="checkbox" name="term_bases" id="cb-bases" style="display:none">
      </div>
    </div>
  </div>

</div><!-- /content -->

<div class="bottom-nav">
  <div class="nav-row">
    <button type="button" class="btn-back" id="btnBack" onclick="goBack()" style="display:none">&#8249; Atrás</button>
    <button type="button" class="btn-next disabled" id="btnNext" onclick="goNext()">Siguiente ›</button>
  </div>
</div>
</form>

<?php endif; // fin formulario vs resultado ?>

<script>
// ─── Categorías — se cargan desde la BD via AJAX ─────────────────────────────
var CATEGORIAS_CACHE = {}; // { 'Caballeros': [{id,nombre},...], ... }

var currentStep  = 0;
var totalSteps   = 4;
var generoSel    = '';
var categoriaSel = '';
var playerState  = { 1: 'none', 2: 'none' };
var SELF_URL     = window.location.pathname;

// ─── Listeners CI ─────────────────────────────────────────────────────────────
['1','2'].forEach(function(n) {
  var ciEl = document.getElementById('p' + n + '_ci');
  if (!ciEl) return;
  ciEl.addEventListener('input', function() {
    var ok = this.value.replace(/[^0-9]/g, '').length >= 5;
    document.getElementById('btn-buscar-p' + n).disabled = !ok;
    if (!ok) resetPlayer(parseInt(n));
    validateStep();
  });
  ciEl.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') { e.preventDefault(); buscarCI(parseInt(n)); }
  });
});

// ─── Buscar CI ────────────────────────────────────────────────────────────────
function buscarCI(num) {
  var ciEl = document.getElementById('p' + num + '_ci');
  var btn  = document.getElementById('btn-buscar-p' + num);
  var txt  = document.getElementById('btn-buscar-p' + num + '-txt');
  var ci   = ciEl.value.replace(/[^0-9]/g, '');
  if (ci.length < 5) return;

  btn.disabled   = true;
  txt.innerHTML  = '<span class="spinner"></span>';

  fetch(SELF_URL + '?action=buscar_ci&ci=' + encodeURIComponent(ci))
    .then(function(r) { return r.json(); })
    .then(function(data) {
      txt.textContent = 'Buscar';
      btn.disabled    = false;
      if (data.found) {
        applyFoundPlayer(num, data);
      } else {
        applyNewPlayer(num);
      }
      validateStep();
    })
    .catch(function() {
      txt.textContent = 'Buscar';
      btn.disabled    = false;
      applyNewPlayer(num);
      validateStep();
    });
}

// ─── Aplicar jugador encontrado ───────────────────────────────────────────────
function applyFoundPlayer(num, data) {
  var p = 'p' + num;
  playerState[num] = 'found';
  setHidden(p+'_nombre_real',   data.nombre_real   || '');
  setHidden(p+'_apellido_real', data.apellido_real || '');
  setHidden(p+'_cel_real',      data.cel_real      || '');
  setHidden(p+'_email_real',    data.email_real    || '');
  setHidden(p+'_ciudad_real',   data.ciudad_real   || '');
  setHidden(p+'_es_registrado', '1');
  setFieldReadonly(p+'_nombres',   data.nombre   || '');
  setFieldReadonly(p+'_apellidos', data.apellido || '');
  setFieldReadonly(p+'_celular',   data.cel      || '');
  setFieldReadonly(p+'_email',     data.email    || '');
  setFieldReadonly(p+'_ciudad',    data.ciudad   || '');
  showStatus(num, 'found',
    '<svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>' +
    '<span>Jugador encontrado — datos precargados</span>');
  showFields(num);
}

// ─── Aplicar nuevo jugador ────────────────────────────────────────────────────
function applyNewPlayer(num) {
  var p = 'p' + num;
  playerState[num] = 'new';
  setHidden(p+'_es_registrado', '0');
  ['nombre','apellido','cel','email','ciudad'].forEach(function(f) { setHidden(p+'_'+f+'_real',''); });
  setFieldEditable(p+'_nombres',   '');
  setFieldEditable(p+'_apellidos', '');
  setFieldEditable(p+'_celular',   '');
  setFieldEditable(p+'_email',     '');
  setFieldEditable(p+'_ciudad',    '');
  showStatus(num, 'new',
    '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>' +
    '<span>Jugador no registrado — completá todos los datos</span>');
  showFields(num);
}

// ─── Reset jugador ────────────────────────────────────────────────────────────
function resetPlayer(num) {
  playerState[num] = 'none';
  hideStatus(num); hideFields(num);
  var p = 'p' + num;
  ['nombres','apellidos','celular','email','ciudad'].forEach(function(f) {
    var el = document.getElementById(p + '_' + f);
    if (el) { el.value = ''; el.removeAttribute('readonly'); }
  });
}

// ─── Helpers UI ───────────────────────────────────────────────────────────────
function setHidden(id, val) { var el = document.getElementById(id); if (el) el.value = val; }
function setFieldReadonly(id, v) { var el = document.getElementById(id); if (!el) return; el.value = v; el.setAttribute('readonly', true); el.removeAttribute('required'); }
function setFieldEditable(id, v) { var el = document.getElementById(id); if (!el) return; el.value = v; el.removeAttribute('readonly'); el.setAttribute('required', true); }
function showStatus(num, type, html) { var el = document.getElementById('status-p' + num); el.className = 'ci-status ' + type; el.innerHTML = html; }
function hideStatus(num) { document.getElementById('status-p' + num).className = 'ci-status hidden'; }
function showFields(num) { document.getElementById('fields-p' + num).classList.add('visible'); }
function hideFields(num) { document.getElementById('fields-p' + num).classList.remove('visible'); }

// ─── Validación ───────────────────────────────────────────────────────────────
function validateStep() {
  var ok = false;
  if (currentStep === 0) {
    ok = playerState[1] === 'found' ||
        (playerState[1] === 'new' && val('p1_ci') && val('p1_nombres') && val('p1_apellidos') && val('p1_celular') && val('p1_ciudad'));
  } else if (currentStep === 1) {
    ok = playerState[2] === 'found' ||
        (playerState[2] === 'new' && val('p2_ci') && val('p2_nombres') && val('p2_apellidos') && val('p2_celular') && val('p2_ciudad'));
  } else if (currentStep === 2) {
    ok = !!(generoSel && categoriaSel);
  } else if (currentStep === 3) {
    ok = document.getElementById('cb-fisico').checked &&
         document.getElementById('cb-priv').checked &&
         document.getElementById('cb-bases').checked;
  }
  document.getElementById('btnNext').className = 'btn-next' + (ok ? '' : ' disabled');
}
function val(id) { var el = document.getElementById(id); return el && el.value.trim().length > 0; }

// ─── Stepper ──────────────────────────────────────────────────────────────────
function updateStepper() {
  document.getElementById('stepCounter').textContent = (currentStep + 1) + ' / ' + totalSteps;
  for (var i = 0; i < totalSteps; i++) {
    var si = document.getElementById('si-' + i);
    if (!si) continue;
    si.className = 'step-item' + (i < currentStep ? ' done' : i === currentStep ? ' active' : '');
    if (i < currentStep)
      si.querySelector('.step-circle').innerHTML =
        '<svg viewBox="0 0 12 12" style="width:14px;height:14px;stroke:white;fill:none;stroke-width:2.5"><path d="M2 6l3 3 5-5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    if (i < totalSteps - 1)
      document.getElementById('sc-' + i).className = 'step-connector' + (i < currentStep ? ' done-line' : '');
  }
}

function showPanel(n) {
  for (var i = 0; i < totalSteps; i++)
    document.getElementById('panel-' + i).style.display = i === n ? 'block' : 'none';
  document.getElementById('btnBack').style.display = n > 0 ? 'flex' : 'none';
  document.getElementById('btnNext').textContent = n === totalSteps - 1 ? 'Registrar Equipo' : 'Siguiente ›';
  window.scrollTo(0, 0);
  validateStep();
}

// ─── Navegación ───────────────────────────────────────────────────────────────
function goNext() {
  if (document.getElementById('btnNext').classList.contains('disabled')) return;
  if (currentStep === totalSteps - 1) {
    document.getElementById('inscForm').submit();
    return;
  }
  if (currentStep === 2) fillSummary();
  currentStep++;
  showPanel(currentStep);
  updateStepper();
}
function goBack() {
  if (currentStep === 0) return;
  currentStep--;
  showPanel(currentStep);
  updateStepper();
}

// ─── Género / Categoría ───────────────────────────────────────────────────────
function selectGenero(v, el) {
  generoSel = v; categoriaSel = '';
  document.getElementById('generoInput').value = v;
  document.getElementById('categoriaInput').value = '';
  document.querySelectorAll('#panel-2 .radio-btn').forEach(function(b) { b.classList.remove('selected'); });
  el.classList.add('selected');
  buildCategories(v);
  validateStep();
}
function buildCategories(g) {
  var grid = document.getElementById('catGrid');
  // Si ya están en caché, renderizar directamente
  if (CATEGORIAS_CACHE[g]) {
    renderCatGrid(g, CATEGORIAS_CACHE[g]);
    return;
  }
  // Mostrar loader
  grid.innerHTML = '<p class="hint">Cargando categorías...</p>';
  var evId = document.getElementById('idEvento') ? document.getElementById('idEvento').value : '0';
  fetch(SELF_URL + '?action=categorias&sexo=' + encodeURIComponent(g.toLowerCase()) + '&id_evento=' + evId)
    .then(function(r) { return r.json(); })
    .then(function(data) {
      CATEGORIAS_CACHE[g] = data.categorias || [];
      renderCatGrid(g, CATEGORIAS_CACHE[g]);
    })
    .catch(function() {
      grid.innerHTML = '<p class="hint" style="color:red">Error al cargar categorías</p>';
    });
}
function renderCatGrid(g, cats) {
  var grid = document.getElementById('catGrid');
  grid.innerHTML = '';
  if (!cats || cats.length === 0) {
    grid.innerHTML = '<p class="hint">No hay categorías disponibles</p>';
    return;
  }
  cats.forEach(function(cat) {
    var btn = document.createElement('div');
    if (cat.disponible) {
      // Categoría activa — seleccionable
      btn.className = 'cat-btn';
      btn.dataset.id = cat.id;
      btn.onclick = function() { selectCategoria(cat.id, cat.nombre, btn); };
    } else {
      // Categoría cerrada por el admin — visible pero no seleccionable
      btn.className = 'cat-btn cat-disabled';
      btn.title     = 'Categoría no disponible';
    }
    btn.innerHTML = cat.nombre +
      (cat.disponible ? '' : '<span class="cat-badge-no">No disponible</span>');
    grid.appendChild(btn);
  });
}
function selectCategoria(id, nombre, el) {
  categoriaSel = nombre;
  document.getElementById('categoriaInput').value = id; // enviamos el id_categoria al backend
  document.querySelectorAll('#catGrid .cat-btn').forEach(function(b) { b.classList.remove('selected'); });
  el.classList.add('selected');
  validateStep();
}

// ─── Checkboxes ───────────────────────────────────────────────────────────────
function toggleCheck(boxId, cbId) {
  var box = document.getElementById(boxId);
  box.classList.toggle('checked');
  document.getElementById(cbId).checked = box.classList.contains('checked');
  validateStep();
}

// ─── Resumen ──────────────────────────────────────────────────────────────────
function fillSummary() {
  setText('s-p1-nombre', (g('p1_nombres')+' '+g('p1_apellidos')).trim());
  setText('s-p1-ci',    g('p1_ci'));
  setText('s-p1-cel',   g('p1_celular'));
  setText('s-p1-email', g('p1_email'));
  setText('s-p2-nombre', (g('p2_nombres')+' '+g('p2_apellidos')).trim());
  setText('s-p2-ci',    g('p2_ci'));
  setText('s-p2-cel',   g('p2_celular'));
  setText('s-p2-email', g('p2_email'));
  setText('s-genero',    generoSel    || '—');
  setText('s-categoria', categoriaSel || '—'); // categoriaSel = nombre legible
}
function g(id)        { var el = document.getElementById(id); return el ? el.value.trim() : ''; }
function setText(id, v) { var el = document.getElementById(id); if (el) el.textContent = v || '—'; }

// Live validation
['p1_nombres','p1_apellidos','p1_celular','p1_ciudad',
 'p2_nombres','p2_apellidos','p2_celular','p2_ciudad'].forEach(function(id) {
  var el = document.getElementById(id);
  if (el) el.addEventListener('input', validateStep);
});

// ─── Acordeón ─────────────────────────────────────────────────────────────────
function toggleAcc(id) {
  var item = document.getElementById(id);
  if (!item) return;
  item.classList.toggle('open');
}

// Init
updateStepper();
validateStep();
</script>
</body>
</html>