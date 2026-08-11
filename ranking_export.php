<?php
/**
 * Export CSV del ranking por categorías (se abre en Excel).
 * Reusa el cálculo de logica/mostrar-ranking.php: ahí adentro, con $csvExport
 * seteado, emite el CSV y hace exit antes de tocar una sola línea de HTML.
 *
 * Uso: /ranking_export.php?url=ranking-circuito-hernandariense
 */

$pagina    = 'export'; // mostrar-ranking.php usa esto para resolver db/conection.inc.php desde la raíz
$csvExport = true;

ob_start(); // nada puede salir antes de los headers del CSV

include "logica/mostrar-ranking.php";

// Si llegamos acá el branch de export no corrió: no mandar HTML disfrazado de planilla.
ob_end_clean();
http_response_code(500);
header('Content-Type: text/plain; charset=UTF-8');
echo "No se pudo generar el export. Verificar el parametro ?url=\n";
