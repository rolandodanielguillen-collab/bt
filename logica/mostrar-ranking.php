<?php

if(isset($pagina))
include_once "db/conection.inc.php";

if(!isset($pagina))
include_once "../db/conection.inc.php";

if(isset($_GET['url'])):
	$url=filter_var($_GET['url'], FILTER_SANITIZE_STRING);
	$stmtU=$mysqli2->prepare("SELECT  id,
	upper( (CAST(CONVERT(nombre USING latin1) AS BINARY) )) as nombre,
	descripcion,
	url,
	url_amigable
	FROM
	  _circuitos WHERE
	url_amigable=?");
	$stmtU->bind_param('s',$url);
	$stmtU->execute();
	$rowU = $stmtU->get_result()->fetch_assoc();
	$stmtU->close();
	$idcircuito=abs($rowU['id'] ?? 0);
	$h1="Ranking";
endif;
?>
<?php include "nocode-mostrar-ranking.php"; ?>

<?php
$buscado='';
$filterCis=null; // null = sin búsqueda activa
if(isset($_POST['q'])):
    $q=filter_var($_POST['q'], FILTER_SANITIZE_STRING);
    $q=trim($q);
    $qLike=str_replace(" ","%",$q);
    $qLike=strtolower($qLike);
    // Busca contra _p_usuarios (fuente canónica de nombres; _ranking.nombre ya no se rellena)
    $filterCis=array();
    $stmtQ=$mysqli2->prepare("SELECT ci FROM _p_usuarios WHERE concat(lower(nombre),lower(apellido)) like ? OR ci=?");
    $likeQ="%{$qLike}%";
    $stmtQ->bind_param('ss',$likeQ,$q);
    $stmtQ->execute();
    $resQ=$stmtQ->get_result();
    while($rQ=$resQ->fetch_assoc()) $filterCis[$rQ['ci']]=true;
    $stmtQ->close();
    // Reconstruir URL de retorno manteniendo parámetros GET actuales
    $urlRetorno='?';
    if(isset($_GET['url']))    $urlRetorno.='url='.urlencode($_GET['url']).'&';
    if(isset($_GET['v3']))     $urlRetorno.='v3&';
    if(isset($_GET['debug']))  $urlRetorno.='debug&';
    $urlRetorno=rtrim($urlRetorno,'&');
    $buscado="<div><a href='{$urlRetorno}' style='text-decoration:none;text-transform:capitalize;'><img src='img/remove.png' style='width:15px'> {$q}</a></div>";
endif;

$sqlCat="SELECT * FROM v_p_categorias";
$resultadoCat=$mysqli2->query($sqlCat); 
$CatDisponibles=array();
while($rowCat = $resultadoCat->fetch_assoc()):
	$CatDisponibles[$rowCat['id_categoria']][0]=$rowCat['id_categoria'];
	$CatDisponibles[$rowCat['id_categoria']][1]=$rowCat['id_categoria_padre'];
	$CatDisponibles[$rowCat['id_categoria']][2]=$rowCat['categoria'];
endwhile;

// ═══ PRECARGA: todo el dato de la página en 4 queries; el resto son lookups en memoria ═══
$usuariosIdx=array();
$resPre=$mysqli2->query("SELECT ci, nombre, apellido FROM _p_usuarios");
// primera fila por ci (hay CIs duplicados en _p_usuarios; fetch_assoc tomaba la primera)
while($rPre=$resPre->fetch_assoc()) if(!isset($usuariosIdx[$rPre['ci']])) $usuariosIdx[$rPre['ci']]=$rPre;

$catNombreIdx=array();
$resPre=$mysqli2->query("SELECT id, categoria FROM _p_categorias");
while($rPre=$resPre->fetch_assoc()) $catNombreIdx[$rPre['id']]=$rPre['categoria'];

// _ranking completo del circuito: filas + índice evento→categoria→ci = puntos
// (se conserva la primera fila por clave, igual que el fetch_assoc de las queries puntuales)
$rankRows=array();
$rankIdx=array();
$resPre=$mysqli2->query("SELECT * FROM _ranking WHERE circuito={$idcircuito}");
while($rPre=$resPre->fetch_assoc()){
	$rankRows[]=$rPre;
	if(!isset($rankIdx[$rPre['evento']][$rPre['categoria']][$rPre['ci']]))
		$rankIdx[$rPre['evento']][$rPre['categoria']][$rPre['ci']]=abs($rPre['puntos']);
}

// inscripciones del circuito: ci→categoria→evento (titular y dupla) + nombres de evento
$inscIdx=array();
$eventoNombre=array();
$resPre=$mysqli2->query("SELECT i.ci, i.ci_dupla, i.id_categoria, i.id_evento, e.evento
	FROM _p_incripciones i JOIN _p_eventos e ON e.id=i.id_evento
	WHERE e.id_circuito={$idcircuito}");
while($rPre=$resPre->fetch_assoc()){
	$eventoNombre[$rPre['id_evento']]=$rPre['evento'];
	if($rPre['ci']!==null && $rPre['ci']!=='')             $inscIdx[$rPre['ci']][$rPre['id_categoria']][$rPre['id_evento']]=true;
	if($rPre['ci_dupla']!==null && $rPre['ci_dupla']!=='') $inscIdx[$rPre['ci_dupla']][$rPre['id_categoria']][$rPre['id_evento']]=true;
}

// eventos DISTINCT de un jugador en la categoría o su padre, dentro del circuito
function rk_eventos($ci,$catHijo,$catPadre){
	global $inscIdx;
	$evts=array();
	if(isset($inscIdx[$ci][$catHijo]))  $evts += $inscIdx[$ci][$catHijo];
	if(isset($inscIdx[$ci][$catPadre])) $evts += $inscIdx[$ci][$catPadre];
	ksort($evts);
	return array_keys($evts);
}
function rk_puntos($evento,$categoria,$ci){
	global $rankIdx;
	return isset($rankIdx[$evento][$categoria][$ci]) ? $rankIdx[$evento][$categoria][$ci] : 0;
}

if(isset($_GET['debug'])): echo __LINE__." precarga: ".count($rankRows)." ranking / ".count($inscIdx)." cis / ".count($usuariosIdx)." usuarios"; echo "<pre>"; print_r($CatDisponibles); echo "</pre>"; endif;

$mostrado=array();
foreach($rankRows as $row){
	if($filterCis!==null && !isset($filterCis[$row['ci']]) && (string)$row['ci']!==(string)$q) continue;
	$ACategorias[$row['categoria']]=$row['categoria'];
	if(!isset($puntosxJugador[$row['categoria']][$row['ci']])) $puntosxJugador[$row['categoria']][$row['ci']][0]=0;
	$puntosxJugador[$row['categoria']][$row['ci']][0]=abs($puntosxJugador[$row['categoria']][$row['ci']][0]) + $row['puntos'];
	if(isset($_GET['debug'])) echo __LINE__."<div>{$row['puntos']}</div>";
	$lospuntos=abs($puntosxJugador[$row['categoria']][$row['ci']][0]);
	$puntosAll[$lospuntos][$row['categoria']][$row['ci']][0]=$row['ci'];
	if(strlen(trim($row['ci']))==0 && isset($_GET['debug'])) echo "<div> alert ".$row['id']."</div>";
	$puntosAll[$lospuntos][$row['categoria']][$row['ci']][1]=$lospuntos;
	$puntosAll[$lospuntos][$row['categoria']][$row['ci']][2]=$row['id'];
	$puntosAll[$lospuntos][$row['categoria']][$row['ci']][3]=$row['pos'];
	if(!isset($mostrado[$row['categoria']][$row['ci']])):
		$mostrado[$row['categoria']][$row['ci']]=true;
		if(!isset($cantidadPxCat[$row['categoria']])) $cantidadPxCat[$row['categoria']]=0;
		$cantidadPxCat[$row['categoria']]=abs($cantidadPxCat[$row['categoria']])+1;
	endif;
}
krsort($puntosAll);
if(isset($_GET['debug'])): echo "-".__LINE__; echo "<pre>"; print_r($puntosAll); print_r($puntosxJugador); print_r($cantidadPxCat); echo "</pre>"; endif;

// ═══ TOP 10 GLOBAL: calcular usando la misma lógica evento-por-evento ═══
$top10Global = [];
$top10Visto  = [];

foreach($ACategorias as $acategoria):
    if(!isset($CatDisponibles[$acategoria]) || $CatDisponibles[$acategoria][1] <= 0) continue;
    $padreCatT = $CatDisponibles[$acategoria][1];

    foreach($puntosAll as $cadaPunto):
        if(!isset($cadaPunto[$acategoria])) continue;
        foreach($cadaPunto[$acategoria] as $cadaParticipante):
            $ciT = $cadaParticipante[0];
            if(empty(trim($ciT))) continue;
            $keyT = $ciT.'_'.$acategoria;
            if(isset($top10Visto[$keyT])) continue;
            $top10Visto[$keyT] = true;

            $ptosMixtoT = 0; $ptosHijoT = 0;

            foreach(rk_eventos($ciT,$acategoria,$padreCatT) as $evIdT):
                // FIX 02/06/2026: puntos puros, sin restar padre
                $ptosMixtoT += rk_puntos($evIdT,$padreCatT,$ciT);
                $ptosHijoT  += rk_puntos($evIdT,$acategoria,$ciT);
            endforeach;

            $totalT = $ptosMixtoT + $ptosHijoT;
            if($totalT > 0):
                if(!isset($top10Global[$ciT])):
                    $rowNomT=isset($usuariosIdx[$ciT]) ? $usuariosIdx[$ciT] : null;
                    $top10Global[$ciT] = [
                        'ci' => $ciT,
                        'nombre' => $rowNomT ? strtoupper(explode(' ',trim($rowNomT['nombre']))[0]) : '',
                        'apellido' => $rowNomT ? strtoupper(explode(' ',trim($rowNomT['apellido']))[0]) : '',
                        'total' => $totalT,
                    ];
                else:
                    $top10Global[$ciT]['total'] += $totalT;
                endif;
            endif;
        endforeach;
    endforeach;
endforeach;

usort($top10Global, function($a,$b){ return $b['total'] <=> $a['total']; });
$top10Global = array_slice($top10Global, 0, 10);
$top10Max = !empty($top10Global) ? $top10Global[0]['total'] : 1;
?>

<?php if(!isset($_GET['debugv3'])):?>
<!-- Modal original sin cambios -->
<div id='modal' class="adp-popup adp-popup-type-content adp-popup-location-center adp-preview-image-left adp-preview-image-no adp-popup-open animate__animated animate__fadeInUp" data-limit-display="1" data-overlay-close="false" data-esc-close="true" data-f4-close="false" data-id="9110" style="width:1050px;">
	<div class='cerrar_'><button type="button" class="adp-popup-closes" fdprocessedid="0mf6k" onClick="$('#modal').toggle();"></button></div>
	<div class="adp-popup-wrap"><div class="adp-popup-container"><div class="adp-popup-outer"><div class="adp-popup-content"><div class="adp-popup-inner">
	<div class="sc_layouts sc_layouts_default sc_layouts_7074"><div class="mc4wp-form-fields" style='padding:30px 20px 50px 20px'><span id='modald'></span></div></div>
	</div></div></div></div></div>
</div>
<script>function modal(id){ $('#modald').html($('#c'+id).html()); $('#modal').toggle(); }</script>
<?php endif; ?>

<!-- ============================================================
  ESTILOS: scope con prefijo rk_ para no chocar con WordPress
  Se usa todo con !important en propiedades críticas
============================================================ -->
<style type="text/css">
/* Reset total del contenedor */
#rk-container, #rk-container * {
  box-sizing: border-box !important;
  font-family: 'DM Sans', Arial, sans-serif !important;
  line-height: normal !important;
}

/* Título */
#rk-container .rk-titulo {
  text-align: center !important;
  font-size: 1.25em !important;
  font-weight: 800 !important;
  color: #1e3a5f !important;
  margin: 20px 0 18px !important;
  padding: 0 !important;
  background: none !important;
  border: none !important;
}

/* Buscador */
#rk-container .rk-form {
  display: flex !important;
  gap: 8px !important;
  margin-bottom: 20px !important;
}
#rk-container .rk-input {
  flex: 1 !important;
  padding: 10px 14px !important;
  border: 1.5px solid #d1d5db !important;
  border-radius: 8px !important;
  font-size: 14px !important;
  outline: none !important;
  background: #fff !important;
  color: #111 !important;
  height: auto !important;
  box-shadow: none !important;
}
#rk-container .rk-btn {
  padding: 10px 22px !important;
  background: #2563eb !important;
  color: #fff !important;
  border: none !important;
  border-radius: 8px !important;
  font-weight: 700 !important;
  font-size: 14px !important;
  cursor: pointer !important;
  white-space: nowrap !important;
  height: auto !important;
  line-height: normal !important;
}

/* Card por categoría */
#rk-container .rk-card {
  background: #fff !important;
  border-radius: 10px !important;
  box-shadow: 0 2px 8px rgba(0,0,0,.10) !important;
  overflow: hidden !important;
  margin-bottom: 14px !important;
  border: none !important;
}

/* Header categoría */
#rk-container .rk-cat-header {
  background: #1e3a5f !important;
  color: #fff !important;
  padding: 12px 18px !important;
  font-weight: 700 !important;
  font-size: 13px !important;
  display: flex !important;
  justify-content: space-between !important;
  align-items: center !important;
  cursor: pointer !important;
  user-select: none !important;
  letter-spacing: .04em !important;
  border: none !important;
  border-radius: 0 !important;
  margin: 0 !important;
}
#rk-container .rk-cat-header:hover { background: #162d4a !important; }
#rk-container .rk-cat-count { font-size: 11px !important; font-weight: 400 !important; opacity: .7 !important; }
#rk-container .rk-hchev { font-size: 16px !important; display: inline-block !important; transition: transform .25s !important; }

/* Encabezado de columnas (flex row) */
#rk-container .rk-thead {
  display: flex !important;
  background: #f0f4f8 !important;
  border-bottom: 2px solid #dde3ea !important;
  padding: 0 !important;
  margin: 0 !important;
}
#rk-container .rk-th {
  padding: 9px 10px !important;
  font-size: 11px !important;
  text-transform: uppercase !important;
  color: #6b7280 !important;
  letter-spacing: .05em !important;
  font-weight: 700 !important;
  white-space: nowrap !important;
  border: none !important;
  background: none !important;
  text-align: center !important;
  margin: 0 !important;
}
#rk-container .rk-th-left { text-align: left !important; }
#rk-container .rk-col-pos   { width: 50px !important; flex-shrink: 0 !important; }
#rk-container .rk-col-jug   { flex: 1 !important; text-align: left !important; }
#rk-container .rk-col-mix   { width: 80px !important; flex-shrink: 0 !important; }
#rk-container .rk-col-pts   { width: 80px !important; flex-shrink: 0 !important; }
#rk-container .rk-col-tot   { width: 90px !important; flex-shrink: 0 !important; font-weight: 900 !important; }
#rk-container .rk-col-chev  { width: 40px !important; flex-shrink: 0 !important; }

/* Fila jugador (flex row) */
#rk-container .rk-row {
  display: flex !important;
  align-items: center !important;
  border-bottom: 1px solid #eee !important;
  cursor: pointer !important;
  padding: 0 !important;
  margin: 0 !important;
}
#rk-container .rk-row:hover { background: #eef2ff !important; }
#rk-container .rk-row.rk-even { background: #f8fafc !important; }
#rk-container .rk-row.rk-odd  { background: #ffffff !important; }
#rk-container .rk-row:hover   { background: #eef2ff !important; }

/* Celdas de fila */
#rk-container .rk-td {
  padding: 11px 10px !important;
  border: none !important;
  background: none !important;
  margin: 0 !important;
  text-align: center !important;
  font-size: 13px !important;
  color: #111827 !important;
}
#rk-container .rk-td-left { text-align: left !important; }

/* Valores */
#rk-container .rk-val-pos  { font-weight: 800 !important; color: #2563eb !important; font-size: 14px !important; }
#rk-container .rk-val-mix  { font-weight: 700 !important; color: #7c3aed !important; font-size: 14px !important; }
#rk-container .rk-val-pts  { font-weight: 700 !important; color: #2563eb !important; font-size: 14px !important; }
#rk-container .rk-val-tot  { font-weight: 900 !important; color: #16a34a !important; font-size: 15px !important; }

/* Jugador */
#rk-container .rk-ci   { font-size: 10px !important; color: #9ca3af !important; font-family: monospace !important; display: block !important; line-height: 1.3 !important; }
#rk-container .rk-name { font-size: 13px !important; font-weight: 700 !important; color: #111827 !important; display: block !important; line-height: 1.4 !important; }
/* Nombre y apellido en líneas separadas */
#rk-container .rk-nombre-line  { display: block !important; font-size: 13px !important; font-weight: 400 !important; color: #374151 !important; line-height: 1.3 !important; }
#rk-container .rk-apellido-line { display: block !important; font-size: 13px !important; font-weight: 700 !important; color: #111827 !important; line-height: 1.3 !important; }

/* Chevron fila */
#rk-container .rk-chevron { font-size: 13px !important; color: #9ca3af !important; display: inline-block !important; transition: transform .2s !important; }

/* Fila detalle */
#rk-container .rk-detail { display: none !important; border-bottom: 1px solid #eee !important; }
#rk-container .rk-detail.rk-open { display: block !important; }
#rk-container .rk-detail-inner { padding: 4px 16px 10px 60px !important; background: #f3f4f6 !important; }

/* Filas del detalle */
#rk-container .rk-det-row {
  display: flex !important;
  border-top: 1px solid #e5e7eb !important;
  padding: 0 !important;
  margin: 0 !important;
  background: #f3f4f6 !important;
}
#rk-container .rk-det-head { font-size: 11px !important; text-transform: uppercase !important; color: #9ca3af !important; font-weight: 700 !important; letter-spacing: .04em !important; border: none !important; }
#rk-container .rk-det-label { flex: 1 !important; padding: 5px 8px !important; font-size: 12px !important; color: #374151 !important; border: none !important; }
#rk-container .rk-det-val   { width: 80px !important; padding: 5px 8px !important; font-size: 12px !important; font-weight: 700 !important; color: #2563eb !important; text-align: center !important; border: none !important; }
#rk-container .rk-det-val-mix { color: #7c3aed !important; }

/* ============================================================
   RESPONSIVE MÓVIL (max 480px)
   ============================================================ */
@media (max-width: 480px) {

  #rk-container {
    padding: 0 4px 40px !important;
  }

  #rk-container .rk-cat-header {
    padding: 9px 10px !important;
    font-size: 11px !important;
  }
  #rk-container .rk-cat-count {
    font-size: 10px !important;
  }

  #rk-container .rk-col-pos  { width: 30px !important; }
  #rk-container .rk-col-mix  { width: 58px !important; }
  #rk-container .rk-col-pts  { width: 58px !important; }
  #rk-container .rk-col-tot  { width: 62px !important; }
  #rk-container .rk-col-chev { width: 22px !important; }

  #rk-container .rk-th {
    padding: 6px 3px !important;
    font-size: 9px !important;
    letter-spacing: 0 !important;
  }

  #rk-container .rk-td {
    padding: 7px 3px !important;
    font-size: 11px !important;
  }

  /* Ocultar cédula en móvil para ganar espacio */
  #rk-container .rk-ci {
    display: none !important;
  }

  /* Nombre en dos líneas (nombre arriba, apellido abajo) sin cortar */
  #rk-container .rk-name {
    font-size: 11px !important;
    white-space: normal !important;
    overflow: visible !important;
    text-overflow: unset !important;
    max-width: 110px !important;
    display: block !important;
    line-height: 1.3 !important;
    word-break: break-word !important;
  }

  #rk-container .rk-val-pos { font-size: 12px !important; }
  #rk-container .rk-val-mix { font-size: 12px !important; }
  #rk-container .rk-val-pts { font-size: 12px !important; }
  #rk-container .rk-val-tot { font-size: 13px !important; }

  #rk-container .rk-detail-inner {
    padding: 4px 8px 8px 34px !important;
  }
  #rk-container .rk-det-label {
    font-size: 10px !important;
    padding: 4px 4px !important;
  }
  #rk-container .rk-det-val {
    width: 58px !important;
    font-size: 10px !important;
    padding: 4px 4px !important;
  }

  #rk-container .rk-input {
    padding: 8px 10px !important;
    font-size: 13px !important;
  }
  #rk-container .rk-btn {
    padding: 8px 14px !important;
    font-size: 13px !important;
  }
}

/* ═══ TOP 10 SECTION ═══ */
#rk-container .rk-top10 {
  background: linear-gradient(135deg, #1e3a5f 0%, #0f2744 100%) !important;
  border-radius: 12px !important;
  padding: 24px 20px 20px !important;
  margin-bottom: 24px !important;
  box-shadow: 0 4px 20px rgba(0,0,0,.25) !important;
  border: none !important;
}
#rk-container .rk-top10-title {
  font-size: 15px !important;
  font-weight: 800 !important;
  color: #fff !important;
  margin: 0 0 4px !important;
  padding: 0 !important;
  text-transform: uppercase !important;
  letter-spacing: .06em !important;
}
#rk-container .rk-top10-sub {
  font-size: 11px !important;
  color: rgba(255,255,255,.5) !important;
  margin: 0 0 18px !important;
  padding: 0 !important;
}
#rk-container .rk-t10-row {
  display: flex !important;
  align-items: center !important;
  gap: 10px !important;
  margin-bottom: 8px !important;
  padding: 0 !important;
}
#rk-container .rk-t10-pos {
  width: 26px !important;
  height: 26px !important;
  border-radius: 50% !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  font-size: 11px !important;
  font-weight: 800 !important;
  color: #fff !important;
  flex-shrink: 0 !important;
  border: none !important;
}
#rk-container .rk-t10-pos.rk-t10-g { background: linear-gradient(135deg, #f59e0b, #d97706) !important; }
#rk-container .rk-t10-pos.rk-t10-s { background: linear-gradient(135deg, #94a3b8, #64748b) !important; }
#rk-container .rk-t10-pos.rk-t10-b { background: linear-gradient(135deg, #d97706, #92400e) !important; }
#rk-container .rk-t10-pos.rk-t10-n { background: rgba(255,255,255,.12) !important; }

#rk-container .rk-t10-name {
  width: 160px !important;
  flex-shrink: 0 !important;
  font-size: 12px !important;
  font-weight: 600 !important;
  color: #e2e8f0 !important;
  white-space: nowrap !important;
  overflow: hidden !important;
  text-overflow: ellipsis !important;
  text-align: left !important;
  border: none !important;
  background: none !important;
}
#rk-container .rk-t10-barwrap {
  flex: 1 !important;
  height: 24px !important;
  background: rgba(255,255,255,.08) !important;
  border-radius: 6px !important;
  overflow: hidden !important;
  position: relative !important;
  border: none !important;
  min-width: 0 !important;
}
#rk-container .rk-t10-bar {
  height: 100% !important;
  border-radius: 6px !important;
  display: flex !important;
  align-items: center !important;
  justify-content: flex-end !important;
  padding-right: 8px !important;
  font-size: 11px !important;
  font-weight: 800 !important;
  color: #fff !important;
  transition: width .6s cubic-bezier(.25,.8,.25,1) !important;
  min-width: 36px !important;
  border: none !important;
}

/* Colores de barras — especificidad alta para ganar a WordPress */
#rk-container .rk-t10-barwrap .rk-t10-bar.rk-bar-0  { background: linear-gradient(90deg, #6366f1, #818cf8) !important; }
#rk-container .rk-t10-barwrap .rk-t10-bar.rk-bar-1  { background: linear-gradient(90deg, #3b82f6, #60a5fa) !important; }
#rk-container .rk-t10-barwrap .rk-t10-bar.rk-bar-2  { background: linear-gradient(90deg, #22c55e, #4ade80) !important; }
#rk-container .rk-t10-barwrap .rk-t10-bar.rk-bar-3  { background: linear-gradient(90deg, #f59e0b, #fbbf24) !important; }
#rk-container .rk-t10-barwrap .rk-t10-bar.rk-bar-4  { background: linear-gradient(90deg, #ef4444, #f87171) !important; }
#rk-container .rk-t10-barwrap .rk-t10-bar.rk-bar-5  { background: linear-gradient(90deg, #ec4899, #f472b6) !important; }
#rk-container .rk-t10-barwrap .rk-t10-bar.rk-bar-6  { background: linear-gradient(90deg, #8b5cf6, #a78bfa) !important; }
#rk-container .rk-t10-barwrap .rk-t10-bar.rk-bar-7  { background: linear-gradient(90deg, #14b8a6, #2dd4bf) !important; }
#rk-container .rk-t10-barwrap .rk-t10-bar.rk-bar-8  { background: linear-gradient(90deg, #f97316, #fb923c) !important; }
#rk-container .rk-t10-barwrap .rk-t10-bar.rk-bar-9  { background: linear-gradient(90deg, #06b6d4, #22d3ee) !important; }

@media (max-width: 480px) {
  #rk-container .rk-top10 {
    padding: 16px 12px 14px !important;
    margin-bottom: 18px !important;
    border-radius: 10px !important;
  }
  #rk-container .rk-top10-title {
    font-size: 13px !important;
  }
  #rk-container .rk-t10-row {
    gap: 6px !important;
    margin-bottom: 6px !important;
  }
  #rk-container .rk-t10-pos {
    width: 22px !important;
    height: 22px !important;
    font-size: 10px !important;
  }
  #rk-container .rk-t10-name {
    width: 100px !important;
    font-size: 10px !important;
    white-space: normal !important;
    overflow: visible !important;
    text-overflow: unset !important;
    line-height: 1.3 !important;
    word-break: break-word !important;
  }
  #rk-container .rk-t10-barwrap {
    height: 20px !important;
  }
  #rk-container .rk-t10-bar {
    font-size: 9px !important;
    padding-right: 5px !important;
    min-width: 28px !important;
  }
}
</style>

<!-- ============================================================
  CONTENEDOR PRINCIPAL
============================================================ -->
<div id="rk-container" style="max-width:860px; margin:0 auto; padding:0 12px 50px;">

  <h3 class="rk-titulo"><?php echo $h1; ?></h3>

  <?php if($buscado<>''): ?>
    <div style="margin-bottom:16px; font-size:13px;"><?php echo str_replace("%"," ",$buscado); ?></div>
  <?php else: ?>
    <form method='post' class="rk-form">
      <input name='q' type='text' autofocus placeholder='Buscar jugador o CI...' class='rk-input buscador'>
      <button type='submit' class="rk-btn">Buscar</button>
    </form>
  <?php endif; ?>

  <!-- ═══ TOP 10 GLOBAL ═══ -->
  <?php if(!empty($top10Global) && $buscado === ''): ?>
  <div class="rk-top10">
    <div class="rk-top10-title">🏆 Top 10 — Puntos Generales</div>
    <div class="rk-top10-sub">Ranking acumulado de todas las categorías</div>
    <?php foreach($top10Global as $idx => $t10):
        $pctW = round(($t10['total'] / $top10Max) * 100);
        if($pctW < 8) $pctW = 8;
        $posClass = $idx === 0 ? 'rk-t10-g' : ($idx === 1 ? 'rk-t10-s' : ($idx === 2 ? 'rk-t10-b' : 'rk-t10-n'));
    ?>
    <div class="rk-t10-row">
      <div class="rk-t10-pos <?php echo $posClass; ?>"><?php echo $idx+1; ?></div>
      <div class="rk-t10-name"><?php echo $t10['nombre'].' '.$t10['apellido']; ?></div>
      <div class="rk-t10-barwrap">
        <div class="rk-t10-bar rk-bar-<?php echo $idx; ?>" style="width:<?php echo $pctW; ?>%;">
          <?php echo $t10['total']; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

<?php
$mostrado=array();
foreach($ACategorias as $acategoria):
	if($CatDisponibles[$acategoria][1]>0):
	$pos=0;
	$padreCat    = $CatDisponibles[$acategoria][1];
	$nombrePadre = $CatDisponibles[$padreCat][2];
	$catNombre   = categoria_id($acategoria);
	$catCorto    = preg_replace('/^CAT\.\s*/i', '', $catNombre); // quita "CAT. " del inicio
	$catCount    = isset($cantidadPxCat[$acategoria]) ? $cantidadPxCat[$acategoria] : 0;
?>

  <div class="rk-card">

    <!-- Header -->
    <div class="rk-cat-header" onclick="rkToggleCat(<?php echo $acategoria; ?>)">
      <span><?php echo $catNombre; ?></span>
      <span style="display:flex;align-items:center;gap:10px;">
        <span class="rk-cat-count"><?php echo $catCount; ?> jugadores</span>
        <span id="hchev-<?php echo $acategoria; ?>" class="rk-hchev">&#8964;</span>
      </span>
    </div>

    <!-- Cuerpo (oculto por defecto) -->
    <div id="rktbl-<?php echo $acategoria; ?>" style="display:none; overflow-x:auto;">

      <!-- Encabezado columnas -->
      <div class="rk-thead">
        <div class="rk-th rk-col-pos">Pos</div>
        <div class="rk-th rk-th-left rk-col-jug">Jugador</div>
        <div class="rk-th rk-col-mix"><?php echo htmlspecialchars($nombrePadre); ?></div>
        <div class="rk-th rk-col-pts"><?php echo $catCorto; ?></div>
        <div class="rk-th rk-col-tot">Total Pts</div>
        <div class="rk-th rk-col-chev"></div>
      </div>

<?php
// ═══ FASE 1: recolectar todos los jugadores de esta categoría
//     y calcular su total real evento por evento ═══════════════
$jugadoresCat = [];
$yaVisto      = [];
foreach($puntosAll as $cadaPunto):
	if(!isset($cadaPunto[$acategoria])) continue;
	foreach($cadaPunto[$acategoria] as $cadaParticipante):
		$ci = $cadaParticipante[0];
		if(isset($yaVisto[$ci])) continue;
		$yaVisto[$ci] = true;

		$ptosMixtoReal = 0;
		$ptosHijoReal  = 0;

		foreach(rk_eventos($ci,$acategoria,$padreCat) as $evId):
			// FIX 02/06/2026: puntos puros, sin restar padre
			$ptosMixtoReal += rk_puntos($evId,$padreCat,$ci);
			$ptosHijoReal  += rk_puntos($evId,$acategoria,$ci);
		endforeach;

		$jugadoresCat[] = [
			'ci'        => $ci,
			'idRanking' => $cadaParticipante[2],
			'ptosMixto' => $ptosMixtoReal,
			'ptosHijo'  => $ptosHijoReal,
			'total'     => $ptosMixtoReal + $ptosHijoReal,
		];
	endforeach;
endforeach;

// ═══ Ordenar por total real desc ════════════════════════════════
usort($jugadoresCat, fn($a,$b) => $b['total'] <=> $a['total']);

// ═══ FASE 2: renderizar en el orden correcto ════════════════════
$x1 = 0;
foreach($jugadoresCat as $jug):
	$x1++;
	$ci        = $jug['ci'];
	$idRanking = $jug['idRanking'];
	$ptosMixto = $jug['ptosMixto'];
	$ptosHijo  = $jug['ptosHijo'];
	$total     = $jug['total'];
	$posShow   = $x1;

	$detailId = 'rkd-'.$idRanking;
	$chevId   = 'rkchev-'.$idRanking;
	$rowClass = ($x1 % 2 == 0) ? 'rk-even' : 'rk-odd';

	// Detalle por evento
	$detailHtml='';
	if(!isset($_GET['debugv3'])):
		foreach(rk_eventos($ci,$acategoria,$padreCat) as $evIdD):
			$ptsPadre = rk_puntos($evIdD,$padreCat,$ci);
			// FIX 02/06/2026: puntos puros, sin restar padre
			$ptsHijaD = rk_puntos($evIdD,$acategoria,$ci);

			if($ptsHijaD > 0 || $ptsPadre > 0):
				$nombreEvD = isset($eventoNombre[$evIdD]) ? $eventoNombre[$evIdD] : '';
				$detailHtml.="
				<div class='rk-det-row'>
					<div class='rk-det-label'>{$nombreEvD}</div>
					<div class='rk-det-val rk-det-val-mix'>{$ptsPadre}</div>
					<div class='rk-det-val'>{$ptsHijaD}</div>
				</div>";
			endif;
		endforeach;
	endif;
?>

      <!-- Fila jugador -->
      <div class="rk-row <?php echo $rowClass; ?>" onclick="rkToggleDetail('<?php echo $detailId; ?>','<?php echo $chevId; ?>')">

        <div class="rk-td rk-col-pos rk-val-pos"><?php echo $posShow; ?></div>

        <div class="rk-td rk-td-left rk-col-jug">
          <span class="rk-ci"><?php echo $ci; ?></span>
          <span class="rk-name"><?php echo user_ci($ci); ?></span>
        </div>

        <div class="rk-td rk-col-mix rk-val-mix"><?php echo $ptosMixto; ?></div>

        <div class="rk-td rk-col-pts rk-val-pts" onclick="event.stopPropagation(); modal(<?php echo $idRanking; ?>)" title="Ver detalle">
          <?php echo $ptosHijo; ?>&nbsp;<span class='fa fa-eye' style='font-size:10px;opacity:.35;'></span>
        </div>

        <div class="rk-td rk-col-tot rk-val-tot"><?php echo $total; ?></div>

        <div class="rk-td rk-col-chev">
          <span id="<?php echo $chevId; ?>" class="rk-chevron">&#8964;</span>
        </div>
      </div>

      <!-- Fila detalle -->
      <div id="<?php echo $detailId; ?>" class="rk-detail">
        <div id='c<?php echo $idRanking; ?>' style='display:none;'><?php echo $detailHtml; ?></div>
        <?php if($detailHtml): ?>
        <div class="rk-detail-inner">
          <div class="rk-det-row rk-det-head">
            <div class="rk-det-label">Fecha</div>
            <div class="rk-det-val rk-det-val-mix"><?php echo htmlspecialchars($nombrePadre); ?></div>
            <div class="rk-det-val"><?php echo $catCorto; ?></div>
          </div>
          <?php echo $detailHtml; ?>
        </div>
        <?php endif; ?>
      </div>

<?php
endforeach; // jugadoresCat
?>

    </div><!-- /rktbl -->
  </div><!-- /rk-card -->

<?php
	endif; // id_categoria_padre > 0
endforeach; // ACategorias
?>

</div><!-- /rk-container -->

<script>
function rkToggleCat(catId) {
  var tbl  = document.getElementById('rktbl-' + catId);
  var chev = document.getElementById('hchev-' + catId);
  var open = tbl.style.display !== 'none';
  tbl.style.display    = open ? 'none' : 'block';
  chev.style.transform = open ? '' : 'rotate(180deg)';
}
function rkToggleDetail(detailId, chevId) {
  var row  = document.getElementById(detailId);
  var chev = document.getElementById(chevId);
  var isOpen = row.classList.contains('rk-open');
  if(isOpen){
    row.classList.remove('rk-open');
    chev.style.transform = '';
  } else {
    row.classList.add('rk-open');
    chev.style.transform = 'rotate(180deg)';
  }
}
</script>

<?php

function categoria_id($id){
	if($id==0) return '';
	global $catNombreIdx;
	return strtoupper(isset($catNombreIdx[$id]) ? $catNombreIdx[$id] : '');
}

function user_ci($ci){
	if($ci==0) return true;
	global $usuariosIdx;
	$row=isset($usuariosIdx[$ci]) ? $usuariosIdx[$ci] : array('nombre'=>'','apellido'=>'');
	// Solo primer nombre y primer apellido, en dos líneas separadas
	$primerNombre   = strtoupper(explode(' ', trim($row['nombre']))[0]);
	$primerApellido = strtoupper(explode(' ', trim($row['apellido']))[0]);
	return "<span class='rk-nombre-line'>".$primerNombre."</span><strong class='rk-apellido-line'>".$primerApellido."</strong>";
}

?>