<?php
/**
 * Export CSV del ranking por categorías (se abre en Excel).
 * SOLO ADMIN: el CSV lista cédulas de todos los jugadores, no va público.
 * Se entra desde tvt_admin_v2.php → Ranking.
 *
 * Reusa el cálculo de logica/mostrar-ranking.php: ahí adentro, con $csvExport
 * seteado, emite el CSV y hace exit antes de tocar una sola línea de HTML.
 *
 * Uso: /ranking_export.php?url=ranking-circuito-hernandariense
 */

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Acceso denegado. Entrar por el panel de administracion.\n";
    exit;
}

$pagina    = 'export'; // mostrar-ranking.php usa esto para resolver db/conection.inc.php desde la raíz
$csvExport = true;

ob_start(); // nada puede salir antes de los headers del CSV

include "logica/mostrar-ranking.php";

// Si llegamos acá el branch de export no corrió: no mandar HTML disfrazado de planilla.
ob_end_clean();
http_response_code(500);
header('Content-Type: text/plain; charset=UTF-8');
echo "No se pudo generar el export. Verificar el parametro ?url=\n";
