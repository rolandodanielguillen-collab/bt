<?php
// MARCADOR_VERSION_FINAL_2026 - Si ves esto en el código fuente, el archivo correcto está en el servidor
if(isset($pagina)):
include_once "db/conection.inc.php";
include_once "funciones.php";
elseif(!isset($pagina)):
	include_once "../db/conection.inc.php";
	include_once "../funciones.php";
endif;

// ============================================
// 🔧 FUNCIONES DE PROPAGACIÓN (globales - usadas por POST y auto-bye)
// ============================================
function resetarSlotsDestino($mysqli2, $ev, $cat, $idParaBuscar) {
	$rDest = $mysqli2->query("SELECT id, ref_etiqueta1, ref_etiqueta2,
		ci1_a, ci2_a,
		rusultado_equipo1, resultado_equipo2
		FROM _todosvstodos
		WHERE evento={$ev} AND categoria={$cat}
		AND (ref_etiqueta1={$idParaBuscar} OR ref_etiqueta2={$idParaBuscar})");
	if(!$rDest) return;
	while($dest = $rDest->fetch_assoc()) {
		$idDest = $dest['id'];
		if($dest['rusultado_equipo1']>0 || $dest['resultado_equipo2']>0) continue;
		if($dest['ref_etiqueta1']==$idParaBuscar && $dest['ref_tipo_regustado1']!=3) {
			$otroVacio = ($dest['ci2_a']==0 && $dest['ref_tipo_regustado2']!=3);
			if($otroVacio)
				$mysqli2->query("UPDATE _todosvstodos SET ci1_a=0, ci1_b=0, tipo_referencia='si' WHERE id={$idDest}");
			else
				$mysqli2->query("UPDATE _todosvstodos SET ci1_a=0, ci1_b=0 WHERE id={$idDest}");
		}
		if($dest['ref_etiqueta2']==$idParaBuscar && $dest['ref_tipo_regustado2']!=3) {
			$otroVacio = ($dest['ci1_a']==0 && $dest['ref_tipo_regustado1']!=3);
			if($otroVacio)
				$mysqli2->query("UPDATE _todosvstodos SET ci2_a=0, ci2_b=0, tipo_referencia='si' WHERE id={$idDest}");
			else
				$mysqli2->query("UPDATE _todosvstodos SET ci2_a=0, ci2_b=0 WHERE id={$idDest}");
		}
	}
}

function propagarJugador($mysqli2, $idDestino, $slot, $ci_a, $ci_b) {
	$campo_a = $slot.'_a';
	$campo_b = $slot.'_b';
	$rCheck = $mysqli2->query("SELECT ci1_a, ci1_b, ci2_a, ci2_b, ref_tipo_regustado1, ref_tipo_regustado2,
		evento, categoria, grupo, partido_nro FROM _todosvstodos WHERE id={$idDestino}");
	if(!$rCheck || $rCheck->num_rows==0) return;
	$dest = $rCheck->fetch_assoc();
	if($slot=='ci1') {
		$otroOcupado  = ($dest['ci2_a']>0 || $dest['ci2_b']>0 || $dest['ref_tipo_regustado2']==3);
		$esPartidoByeBye = ($dest['ref_tipo_regustado2']==3);
	} else {
		$otroOcupado  = ($dest['ci1_a']>0 || $dest['ci1_b']>0 || $dest['ref_tipo_regustado1']==3);
		$esPartidoByeBye = ($dest['ref_tipo_regustado1']==3);
	}
	if($otroOcupado)
		$mysqli2->query("UPDATE _todosvstodos SET {$campo_a}={$ci_a}, {$campo_b}={$ci_b}, tipo_referencia='no' WHERE id={$idDestino}");
	else
		$mysqli2->query("UPDATE _todosvstodos SET {$campo_a}={$ci_a}, {$campo_b}={$ci_b} WHERE id={$idDestino}");

	if($esPartidoByeBye && $ci_a > 0) {
		$ev2  = $dest['evento'];
		$cat2 = $dest['categoria'];
		$grp2 = $dest['grupo'];
		$nro2 = $dest['partido_nro'];
		$idVirt2 = getIdVirtualGrupo($mysqli2, $grp2, $nro2);
		$idBuscar2 = ($idVirt2 > 0) ? $idVirt2 : $grp2;
		$r=$mysqli2->query("SELECT id FROM _todosvstodos
			WHERE evento={$ev2} AND categoria={$cat2}
			AND ref_etiqueta1={$idBuscar2} AND ref_tipo_regustado1=1
			AND (tipo_referencia='si' OR (tipo_referencia='no' AND ci1_a=0))
			LIMIT 1");
		if($r && $r->num_rows>0){ $n=$r->fetch_assoc(); propagarJugador($mysqli2, $n['id'], 'ci1', $ci_a, $ci_b); }
		$r=$mysqli2->query("SELECT id FROM _todosvstodos
			WHERE evento={$ev2} AND categoria={$cat2}
			AND ref_etiqueta2={$idBuscar2} AND ref_tipo_regustado2=1
			AND (tipo_referencia='si' OR (tipo_referencia='no' AND ci2_a=0))
			LIMIT 1");
		if($r && $r->num_rows>0){ $n=$r->fetch_assoc(); propagarJugador($mysqli2, $n['id'], 'ci2', $ci_a, $ci_b); }
	}
}

function getIdVirtualGrupo($mysqli2, $idGrupoReal, $partidoNro) {
	$rEt = $mysqli2->query("SELECT id_etiqueta FROM _p_grupos WHERE id={$idGrupoReal}");
	if(!$rEt || $rEt->num_rows==0) return 0;
	$rowEt = $rEt->fetch_assoc();
	$idEtiqueta = $rowEt['id_etiqueta'];
	if($idEtiqueta == 0) return 0;
	$rVirt = $mysqli2->query("SELECT id FROM _p_grupos 
		WHERE id_etiqueta={$idEtiqueta} AND orden=0 AND id<>{$idGrupoReal}
		ORDER BY id ASC");
	if(!$rVirt || $rVirt->num_rows==0) return 0;
	$pos = 0;
	while($rowV = $rVirt->fetch_assoc()) {
		$pos++;
		if($pos == $partidoNro) return $rowV['id'];
	}
	return 0;
}

// ============================================
// 💾 PROCESAMIENTO DE FORMULARIO - Guardar resultados desde backend
// ============================================
if($_SERVER["REQUEST_METHOD"] == "POST"):
	$idRes=abs($_POST['idRes']);
	$R="";
	if(isset($_POST['rusultado_equipo1']))
    	$R.="rusultado_equipo1=".abs($_POST['rusultado_equipo1']).",";
	if(isset($_POST['resultado_equipo2']))
    	$R.="resultado_equipo2=".abs($_POST['resultado_equipo2']).",";

	if(isset($_POST['resultado2_equipo1']) && strlen(trim($_POST['resultado2_equipo1']))>0)
    	$R.="resultado2_equipo1\t=".abs($_POST['resultado2_equipo1']).",";
	else
    	$R.="resultado2_equipo1\t=resultado2_equipo1,";
	
	if(isset($_POST['resultado2_equipo2']) && strlen(trim($_POST['resultado2_equipo2']))>0)
    	$R.="resultado2_equipo2\t=".abs($_POST['resultado2_equipo2']).",";
	else
    	$R.="resultado2_equipo2\t=resultado2_equipo2,";
	
	if(isset($_POST['resultado3_equipo1']) && strlen(trim($_POST['resultado3_equipo1']))>0)
    	$R.="resultado3_equipo1=".abs($_POST['resultado3_equipo1']).",";
	else
    	$R.="resultado3_equipo1=resultado3_equipo1,";
	
	if(isset($_POST['resultado3_equipo2']) && strlen(trim($_POST['resultado3_equipo2']))>0)
    	$R.="resultado3_equipo2=".abs($_POST['resultado3_equipo2'])."";
	else
    	$R.="resultado3_equipo2=resultado3_equipo2";

	$ahora=date("Y-m-d H:i:s");
	$sqlUPDATE="UPDATE _todosvstodos SET {$R}, carga_resultado='{$ahora}' WHERE id={$idRes}";
	$mysqli2->query($sqlUPDATE); 

	$sqlL="INSERT INTO `sc_log` ( `username`, `application`, `action`, `description`, `created`) 
	VALUES ( 'web', 'front', 'update','UPDATE id={$idRes}',current_timestamp())";
	$mysqli2->query($sqlL); 

	// ============================================================
	// 🔄 PROPAGACIÓN AUTOMÁTICA DEL GANADOR AL SIGUIENTE PARTIDO
	// Con reset previo para que correcciones de resultados funcionen correctamente
	// ============================================================

	// --- Leer el partido guardado con sus resultados actualizados ---
	$resP = $mysqli2->query("SELECT id, evento, categoria, grupo, partido_nro, ci1_a, ci1_b, ci2_a, ci2_b,
		rusultado_equipo1 as r11, resultado_equipo2 as r12,
		resultado2_equipo1 as r21, resultado2_equipo2 as r22,
		resultado3_equipo1 as r31, resultado3_equipo2 as r32,
		tipo_referencia FROM _todosvstodos WHERE id={$idRes}");
	$partido = $resP->fetch_assoc();

	if($partido):
		$ev      = $partido['evento'];
		$cat     = $partido['categoria'];
		$grp     = $partido['grupo'];
		$nroP    = $partido['partido_nro'];

		// Obtener el ID virtual que representa este partido en el árbol eliminatorio
		// Ej: grupo=15 partido_nro=1 → id_virtual=24 (SEMI FINAL 1)
		//     grupo=15 partido_nro=2 → id_virtual=25 (SEMI FINAL 2)
		$idVirtual = getIdVirtualGrupo($mysqli2, $grp, $nroP);
		// Si no tiene virtual, usar el grupo real (fase de grupos sin subdivisión)
		$idParaBuscar = ($idVirtual > 0) ? $idVirtual : $grp;

		// ⚡ RESET PREVIO: limpiar slots destino antes de propagar
		// Esto permite que correcciones de resultados funcionen correctamente
		resetarSlotsDestino($mysqli2, $ev, $cat, $idParaBuscar);
		// En fase de grupos también resetear por el grupo real
		if($idVirtual > 0) resetarSlotsDestino($mysqli2, $ev, $cat, $grp);

		// --- Calcular ganador por sets (mejor de 3) ---
		$sA=0; $sB=0;
		if($partido['r11']>0 || $partido['r12']>0){ $partido['r11']>$partido['r12'] ? $sA++ : $sB++; }
		if($partido['r21']>0 || $partido['r22']>0){ $partido['r21']>$partido['r22'] ? $sA++ : $sB++; }
		if($partido['r31']>0 || $partido['r32']>0){ $partido['r31']>$partido['r32'] ? $sA++ : $sB++; }

		// Solo propagar si hay ganador claro
		if($sA != $sB):
			$ci_g_a = ($sA>$sB) ? $partido['ci1_a'] : $partido['ci2_a'];
			$ci_g_b = ($sA>$sB) ? $partido['ci1_b'] : $partido['ci2_b'];
			$ci_p_a = ($sA>$sB) ? $partido['ci2_a'] : $partido['ci1_a'];
			$ci_p_b = ($sA>$sB) ? $partido['ci2_b'] : $partido['ci1_b'];

			// -------------------------------------------------------
			// FASE A: Ganador(1) y Perdedor(2) al siguiente partido
			// Usa $idParaBuscar que puede ser el ID virtual (ej:24) o el grupo real
			// -------------------------------------------------------

			// Ganador → slot ci1
			$r=$mysqli2->query("SELECT id FROM _todosvstodos 
				WHERE evento={$ev} AND categoria={$cat} 
				AND ref_etiqueta1={$idParaBuscar} AND ref_tipo_regustado1=1 
				AND (tipo_referencia='si' OR (tipo_referencia='no' AND ci1_a=0)) 
				LIMIT 1");
			if($r && $r->num_rows>0){ $n=$r->fetch_assoc(); propagarJugador($mysqli2, $n['id'], 'ci1', $ci_g_a, $ci_g_b); }

			// Ganador → slot ci2
			$r=$mysqli2->query("SELECT id FROM _todosvstodos 
				WHERE evento={$ev} AND categoria={$cat} 
				AND ref_etiqueta2={$idParaBuscar} AND ref_tipo_regustado2=1 
				AND (tipo_referencia='si' OR (tipo_referencia='no' AND ci2_a=0)) 
				LIMIT 1");
			if($r && $r->num_rows>0){ $n=$r->fetch_assoc(); propagarJugador($mysqli2, $n['id'], 'ci2', $ci_g_a, $ci_g_b); }

			// Perdedor → slot ci1 (repechaje)
			$r=$mysqli2->query("SELECT id FROM _todosvstodos 
				WHERE evento={$ev} AND categoria={$cat} 
				AND ref_etiqueta1={$idParaBuscar} AND ref_tipo_regustado1=2 
				AND (tipo_referencia='si' OR (tipo_referencia='no' AND ci1_a=0)) 
				LIMIT 1");
			if($r && $r->num_rows>0){ $n=$r->fetch_assoc(); propagarJugador($mysqli2, $n['id'], 'ci1', $ci_p_a, $ci_p_b); }

			// Perdedor → slot ci2 (repechaje)
			$r=$mysqli2->query("SELECT id FROM _todosvstodos 
				WHERE evento={$ev} AND categoria={$cat} 
				AND ref_etiqueta2={$idParaBuscar} AND ref_tipo_regustado2=2 
				AND (tipo_referencia='si' OR (tipo_referencia='no' AND ci2_a=0)) 
				LIMIT 1");
			if($r && $r->num_rows>0){ $n=$r->fetch_assoc(); propagarJugador($mysqli2, $n['id'], 'ci2', $ci_p_a, $ci_p_b); }

		endif; // fin ganador claro

		// -------------------------------------------------------
		// La propagación de fase de grupos (posiciones 1°,2°,3°...) la maneja exclusivamente
		// el cron cargar.auxiliar.v2.php + cargar.auxiliar.v2-parte2.php

	endif; // fin $partido
	// ============================================================

	// CALCULO TABLA AUXILIAR DIRECTO
	if($partido && $partido['grupo'] < 13):
		$ev_aux=(int)$partido['evento']; $cat_aux=(int)$partido['categoria'];
		$mysqli2->query("UPDATE tabla_auxiliar SET jugados=0,`g+`=0,`g-`=0,sg=0,ganados=0,puntos=0,la_posicion=0 WHERE id_evento={$ev_aux} AND id_categoria={$cat_aux}");
		$resAux=$mysqli2->query("SELECT id,ci1_a,ci1_b,ci2_a,ci2_b,grupo,rusultado_equipo1,resultado_equipo2,resultado3_equipo1,resultado3_equipo2 FROM _todosvstodos WHERE evento={$ev_aux} AND categoria={$cat_aux} AND grupo<13 AND (rusultado_equipo1>0 OR resultado_equipo2>0 OR resultado3_equipo1>0 OR resultado3_equipo2>0)");
		while($rowAux=$resAux->fetch_assoc()):
			$idGrpAux=(int)$rowAux['grupo'];
			$sA=0;$sB=0;
			$r1a=abs($rowAux['rusultado_equipo1']);$r1b=abs($rowAux['resultado_equipo2']);
			$r3a=abs($rowAux['resultado3_equipo1']);$r3b=abs($rowAux['resultado3_equipo2']);
			if($r1a>0||$r1b>0){$r1a>$r1b?$sA++:$sB++;}
			if($r3a>0||$r3b>0){$r3a>$r3b?$sA++:$sB++;}
			if($sA==$sB) continue;
			$gA=$r1a+$r3a;$gB=$r1b+$r3b;
			if($sA>$sB){$ganA=$rowAux['ci1_a'];$ganB=$rowAux['ci1_b'];$perA=$rowAux['ci2_a'];$perB=$rowAux['ci2_b'];$tAF=$gA;$tEC=$gB;}
			else{$ganA=$rowAux['ci2_a'];$ganB=$rowAux['ci2_b'];$perA=$rowAux['ci1_a'];$perB=$rowAux['ci1_b'];$tAF=$gB;$tEC=$gA;}
			$aFv=$tAF-$tEC;
			$rG=$mysqli2->query("SELECT id FROM tabla_auxiliar WHERE (ci1_a='{$ganA}' OR ci1_b='{$ganA}') AND id_grupo={$idGrpAux} AND id_categoria={$cat_aux} AND id_evento={$ev_aux}");
			if($rG->num_rows==0) $mysqli2->query("INSERT INTO tabla_auxiliar (id_grupo,ci1_a,ci1_b,id_categoria,id_evento,tipo_proceso) VALUES ({$idGrpAux},'{$ganA}','{$ganB}',{$cat_aux},{$ev_aux},'web')");
			else{$rowG=$rG->fetch_assoc();$mysqli2->query("UPDATE tabla_auxiliar SET jugados=jugados+1,ganados=ganados+1,`g+`=`g+`+{$tAF},`g-`=`g-`+{$tEC} WHERE id={$rowG['id']}");}
			$rP=$mysqli2->query("SELECT id FROM tabla_auxiliar WHERE (ci1_a='{$perA}' OR ci1_b='{$perA}') AND id_grupo={$idGrpAux} AND id_categoria={$cat_aux} AND id_evento={$ev_aux}");
			if($rP->num_rows==0) $mysqli2->query("INSERT INTO tabla_auxiliar (id_grupo,ci1_a,ci1_b,id_categoria,id_evento,tipo_proceso) VALUES ({$idGrpAux},'{$perA}','{$perB}',{$cat_aux},{$ev_aux},'web')");
			else{$rowP=$rP->fetch_assoc();$mysqli2->query("UPDATE tabla_auxiliar SET jugados=jugados+1,`g-`=`g-`+{$tAF},`g+`=`g+`+{$tEC} WHERE id={$rowP['id']}");}
		endwhile;
		// Recalcular sg y puntos
		$rFix=$mysqli2->query("SELECT id,`g+`,`g-`,ganados FROM tabla_auxiliar WHERE id_evento={$ev_aux} AND id_categoria={$cat_aux}");
		while($rF=$rFix->fetch_assoc()){$elSG=abs($rF['g+'])-abs($rF['g-']);$ptos=$rF['ganados']+$elSG;$mysqli2->query("UPDATE tabla_auxiliar SET sg={$elSG},puntos={$ptos} WHERE id={$rF['id']}");}
		// Posiciones con confronto directo
		$rPos=$mysqli2->query("SELECT * FROM tabla_auxiliar WHERE id_evento={$ev_aux} AND id_categoria={$cat_aux} ORDER BY id_grupo ASC");
		$grps_aux=[];
		while($rPr=$rPos->fetch_assoc()) $grps_aux[$rPr['id_grupo']][]=$rPr;
		foreach($grps_aux as $idGrp=>$eqs_aux){
			$conf_aux=[];
			$rCf=$mysqli2->query("SELECT ci1_a,ci1_b,ci2_a,ci2_b,rusultado_equipo1 as r11,resultado_equipo2 as r12,resultado2_equipo1 as r21,resultado2_equipo2 as r22,resultado3_equipo1 as r31,resultado3_equipo2 as r32 FROM _todosvstodos WHERE grupo={$idGrp} AND evento={$ev_aux} AND categoria={$cat_aux} AND tipo_referencia='no'");
			if($rCf) while($pC=$rCf->fetch_assoc()){if($pC['r11']==0&&$pC['r12']==0&&$pC['r21']==0&&$pC['r22']==0&&$pC['r31']==0&&$pC['r32']==0) continue;$sa2=0;$sb2=0;if($pC['r11']>0||$pC['r12']>0){$pC['r11']>$pC['r12']?$sa2++:$sb2++;}if($pC['r21']>0||$pC['r22']>0){$pC['r21']>$pC['r22']?$sa2++:$sb2++;}if($pC['r31']>0||$pC['r32']>0){$pC['r31']>$pC['r32']?$sa2++:$sb2++;}$kA2=$pC['ci1_a'].'-'.$pC['ci1_b'];$kB2=$pC['ci2_a'].'-'.$pC['ci2_b'];if($sa2!=$sb2){if($sa2>$sb2){$conf_aux[$kA2][$kB2]=1;$conf_aux[$kB2][$kA2]=0;}else{$conf_aux[$kB2][$kA2]=1;$conf_aux[$kA2][$kB2]=0;}}}
			foreach($eqs_aux as &$eq2){$eq2['_clave']=$eq2['ci1_a'].'-'.$eq2['ci1_b'];$eq2['_conf']=isset($conf_aux[$eq2['_clave']])?$conf_aux[$eq2['_clave']]:[];}unset($eq2);
			uasort($eqs_aux,function($a,$b){return $b['ganados']-$a['ganados'];});$eqs_aux=array_values($eqs_aux);
			$gpg2=[];foreach($eqs_aux as $i2=>$eq2){$gpg2[$eq2['ganados']][]=$i2;}$ord2=[];krsort($gpg2);
			foreach($gpg2 as $ixs2){
				if(count($ixs2)==1){
					$ord2[]=$eqs_aux[$ixs2[0]];
				}elseif(count($ixs2)==2){
					$e0=$eqs_aux[$ixs2[0]];$e1=$eqs_aux[$ixs2[1]];
					if(isset($e0['_conf'][$e1['_clave']])){
						if($e0['_conf'][$e1['_clave']]==1){$ord2[]=$e0;$ord2[]=$e1;}
						else{$ord2[]=$e1;$ord2[]=$e0;}
					}else{
						$sg0=$e0['g+']-$e0['g-'];$sg1=$e1['g+']-$e1['g-'];
						if($sg0>=$sg1){$ord2[]=$e0;$ord2[]=$e1;}
						else{$ord2[]=$e1;$ord2[]=$e0;}
					}
				}else{
					$sub2=array_map(fn($i)=>$eqs_aux[$i],$ixs2);
					usort($sub2,function($a,$b){
						$sgA=$a['g+']-$a['g-'];$sgB=$b['g+']-$b['g-'];
						if($sgB!=$sgA) return $sgB-$sgA;
						return $b['g+']-$a['g+'];
					});
					// Sub-desempatar por confronto directo entre los que tienen mismo SG
					$final_p=[];$ip=0;$tp=count($sub2);
					while($ip<$tp){
						$blq=[$sub2[$ip]];
						$sgR=(int)$sub2[$ip]['g+']-(int)$sub2[$ip]['g-'];
						$jp=$ip+1;
						while($jp<$tp){
							$sgN=(int)$sub2[$jp]['g+']-(int)$sub2[$jp]['g-'];
							if($sgN===$sgR){$blq[]=$sub2[$jp];$jp++;}
							else break;
						}
						if(count($blq)==2){
							$b0=$blq[0];$b1=$blq[1];
							if(isset($b0['_conf'][$b1['_clave']])){
								if($b0['_conf'][$b1['_clave']]==1){$final_p[]=$b0;$final_p[]=$b1;}
								else{$final_p[]=$b1;$final_p[]=$b0;}
							}else{foreach($blq as $bb)$final_p[]=$bb;}
						}else{foreach($blq as $bb)$final_p[]=$bb;}
						$ip=$jp;
					}
					foreach($final_p as $e)$ord2[]=$e;
				}
			}
			$posAux=1;foreach($ord2 as $eq2){$mysqli2->query("UPDATE tabla_auxiliar SET la_posicion={$posAux} WHERE id={$eq2['id']}");$posAux++;}
		}

		// -------------------------------------------------------
		// PROPAGACIÓN AUTOMÁTICA: después de recalcular posiciones,
		// verificar si TODOS los partidos del grupo de esta categoría terminaron.
		// Si sí, llamar a parte2 para propagar Primero/Segundo a eliminatoria.
		// -------------------------------------------------------
		$grpPartido = (int)$partido['grupo'];
		$rSinRes = $mysqli2->query("SELECT COUNT(*) as sinres FROM _todosvstodos 
			WHERE grupo={$grpPartido} AND evento={$ev_aux} AND categoria={$cat_aux}
			AND tipo_referencia='no'
			AND rusultado_equipo1=0 AND resultado_equipo2=0
			AND resultado3_equipo1=0 AND resultado3_equipo2=0");
		$sinRes = $rSinRes ? $rSinRes->fetch_assoc()['sinres'] : 1;
		if($sinRes == 0):
			// Grupo completo → propagar posiciones a eliminatoria
			@file_get_contents("http://bt.com.py/logica/cargar.auxiliar.v2-parte2.php?evento={$ev_aux}");
		endif;

	endif;

	echo "<script>alert('Guardado correctamente');</script>";
endif;


echo "<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Inter', system-ui, sans-serif; background: hsl(210, 20%, 96%); color: hsl(220, 20%, 15%); }

/* Override header solo para TVT mobile */
@media (max-width: 767px) {
  .top-navbar { position: relative !important; }
  .hero-header {
    padding: 1rem 1rem 1.2rem !important;
    margin-bottom: 0 !important;
    border-radius: 0 !important;
  }
  .hero-logo {
    width: 4rem !important;
    height: 4rem !important;
    border-width: 2px !important;
  }
  .hero-title {
    font-size: 1rem !important;
    margin-top: 0.5rem !important;
    margin-bottom: 0.1rem !important;
  }
  .hero-subtitle {
    font-size: 0.7rem !important;
  }
}

/* Estilos para modo edición backend */
.winner {
  color: #000;
  font-weight: bold;
  background-color: #0781b233;
}
.ic {
  width: 36px !important;
  height: 26px;
  padding: 2px 4px;
  border: 1px solid #ccc;
  border-radius: 3px;
  text-align: center;
  font-size: 13px;
  font-weight: 600;
  -moz-appearance: textfield;
}
.ic::-webkit-outer-spin-button,
.ic::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}
.ic:focus {
  outline: 2px solid hsl(200, 45%, 50%);
  border-color: hsl(200, 45%, 50%);
}
.enJuegosi {
  background: #EBA652 !important;
}
.tenJuegono {
  display: none;
}
.tenJuegosi {
  display: block;
  font-size: 10px;
}
.ten2Juegosi {
  display: none;
  font-size: 10px;
}
.ten2Juegono {
  display: block;
  font-size: 10px;
}

/* Título del evento - responsive */
h2 {
  font-size: 28px;
  margin: 20px 0;
  text-align: center;
  line-height: 1.3;
}

@media (max-width: 768px) {
  h2 {
    font-size: 18px;
    margin: 15px 0;
    padding: 0 10px;
  }
}

@media (max-width: 480px) {
  h2 {
    font-size: 16px;
    margin: 10px 0;
  }
}


/* Tabs */
.tabs { 
  display: flex; 
  gap: 4px; 
  background: hsl(210, 15%, 93%); 
  border-radius: 8px; 
  padding: 4px; 
  margin-bottom: 16px; 
  max-width: 400px;
}

.tab-btn { 
  flex: 1; 
  padding: 8px; 
  border: none; 
  border-radius: 6px; 
  font-size: 14px; 
  font-weight: 500; 
  cursor: pointer; 
  background: transparent; 
  color: hsl(215, 14%, 50%); 
  transition: all 0.2s; 
}

.tab-btn.active { 
  background: #fff; 
  color: hsl(220, 20%, 15%); 
  box-shadow: 0 1px 3px rgba(0,0,0,0.1); 
}

.tab-content { 
  display: none; 
}

.tab-content.active { 
  display: block; 
}

/* Match Cards - NUEVO DISEÑO */
.match-card { 
  background: #fff; 
  border-radius: 8px; 
  border: 1px solid hsl(214, 25%, 85%); 
  overflow: hidden; 
  margin-bottom: 12px; 
  box-shadow: 0 1px 2px rgba(0,0,0,0.05); 
}

/* Match card EN JUEGO - Color naranja */
.match-card.en-juego {
  border: 2px solid #EBA652;
  box-shadow: 0 2px 8px rgba(235, 166, 82, 0.3);
}

.match-card.en-juego .match-header {
  background: #EBA652 !important;
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.85; }
}

.match-header { 
  background: #374151;
  color: #fff; 
  display: flex; 
  align-items: center; 
  justify-content: space-between; 
  padding: 10px 16px; 
  cursor: pointer; 
  border: none; 
  width: 100%; 
  font-family: inherit; 
}

.match-header .round { 
  font-weight: 700; 
  font-size: 14px;
  padding-right: 12px;
  border-right: 1px solid rgba(255,255,255,0.2);
  margin-right: 4px;
}

.match-header .info { 
  font-size: 12px; 
  opacity: 0.85; 
  display: flex; 
  align-items: center; 
  justify-content: space-between;
  gap: 8px; 
  flex: 1;
}

.match-header .summary {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  text-align: left;
  flex: 1;
}

.match-header.open .summary {
  display: none;
}

.match-header .chevron { 
  transition: transform 0.2s; 
}

.match-header.open .chevron { 
  transform: rotate(180deg); 
}

.match-body { 
  display: none; 
}

.match-body.open { 
  display: block; 
}

.player-row { 
  display: grid; 
  grid-template-columns: 1fr 1fr auto auto; 
  align-items: center; 
  gap: 8px; 
  padding: 8px 16px; 
  border-bottom: 1px solid hsl(214, 25%, 85%); 
}

.player-row.winner-row {
  background-color: #e8f4f8;
}

.player-row:last-of-type { 
  border-bottom: none; 
}

.player-name { 
  font-size: 14px; 
}

.player-name.winner-name,
.team-name.winner-name { 
  font-weight: 800; 
  color: hsl(220, 25%, 8%);
}

.player-name.winner-name .a,
.team-name.winner-name .a {
  font-weight: 800;
  color: hsl(220, 25%, 8%);
}

.player-name.winner-name .b strong,
.team-name.winner-name .b strong {
  font-weight: 800;
  color: hsl(220, 20%, 20%);
}

.player-name.loser-name,
.team-name.loser-name { 
  color: hsl(215, 14%, 50%);
}

.player-last { 
  font-size: 10px; 
  text-transform: uppercase; 
  letter-spacing: 0.05em; 
}

/* Estilos para banderas e información de user_ci2() */
.player-name img {
  width: 16px;
  height: 12px;
  margin-right: 4px;
  vertical-align: middle;
}

.player-name .ab {
  display: inline-block;
  line-height: 1.2;
}

.player-name .a {
  display: block;
  font-size: 14px;
  line-height: 1.1;
  margin-bottom: 2px;
}

.player-name .b {
  display: block;
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: hsl(215, 14%, 50%);
  line-height: 1.1;
}

.player-name .b strong {
  font-weight: 500;
}

/* Dentro del nuevo layout horizontal: nombre+apellido en una sola línea */
.team-left .player-name .ab,
.team-right .player-name .ab,
.team-left .team-name .ab,
.team-right .team-name .ab {
  display: inline;
  white-space: nowrap;
}
.team-left .player-name .a,
.team-right .player-name .a,
.team-left .team-name .a,
.team-right .team-name .a {
  display: inline;
  font-size: 12px;
  font-weight: 600;
  margin-bottom: 0;
}
.team-left .player-name br,
.team-right .player-name br,
.team-left .team-name br,
.team-right .team-name br {
  display: none;
}
.team-left .player-name .b,
.team-right .player-name .b,
.team-left .team-name .b,
.team-right .team-name .b {
  display: inline;
  font-size: 11px;
  margin-left: 4px;
}
.team-left .player-name,
.team-right .player-name,
.team-left .team-name,
.team-right .team-name {
  font-size: 12px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 100%;
  display: block;
}

.player-right { 
  text-align: right; 
}

.scores { 
  display: flex; 
  gap: 4px; 
  margin-left: 8px; 
}

.score { 
  width: 28px; 
  height: 22px; 
  display: flex; 
  align-items: center; 
  justify-content: center; 
  border-radius: 3px; 
  font-size: 13px; 
  font-weight: 600; 
  background: hsl(210, 15%, 95%); 
}

.score.winner-score { 
  color: hsl(220, 20%, 12%);
  font-weight: 700; 
}

.score.loser-score { 
  color: hsl(215, 14%, 50%);
}

.venue { 
  background: hsl(200, 55%, 42%);
  color: #fff;
  text-align: center; 
  padding: 6px; 
  font-size: 10px; 
  font-weight: 500; 
  text-transform: uppercase; 
  letter-spacing: 0.1em; 
}

/* Winner class original */
.winner {
  color: #000;
  font-weight: bold;
  background-color: #0781b233;
}

/* Standings Table */
.standings { 
  background: #fff; 
  border-radius: 8px; 
  border: 1px solid hsl(214, 25%, 85%); 
  overflow: hidden; 
  box-shadow: 0 1px 2px rgba(0,0,0,0.05); 
  margin: 10px;
  padding: 10px;
}

.standings-header { 
  background: #374151;
  color: #fff; 
  padding: 10px 16px; 
  font-weight: 700; 
  font-size: 14px; 
  margin: -10px -10px 10px -10px;
}

table { 
  width: 100%; 
  border-collapse: collapse; 
  font-size: 14px; 
}

thead th { 
  text-align: left; 
  padding: 8px 12px; 
  font-size: 11px; 
  text-transform: uppercase; 
  letter-spacing: 0.05em; 
  color: hsl(215, 14%, 50%); 
  border-bottom: 1px solid hsl(214, 25%, 85%); 
}

thead th.center { 
  text-align: center; 
}

tbody td { 
  padding: 12px; 
  border-bottom: 1px solid hsl(214, 25%, 85%); 
}

tbody tr:last-child td { 
  border-bottom: none; 
}

tbody tr:hover { 
  background: hsl(210, 15%, 93%, 0.5); 
}

.team-name { 
  font-weight: 700; 
}

.team-last { 
  font-size: 10px; 
  color: hsl(215, 14%, 50%); 
  text-transform: uppercase; 
  letter-spacing: 0.05em; 
  margin-left: 4px; 
}

.sub {
    font-size: 10px;
    position: relative;
    top: -12px;
}

td.center { 
  text-align: center; 
  color: hsl(215, 14%, 50%); 
}

td.pts-pos { 
  text-align: center; 
  font-weight: 700; 
  color: hsl(200, 70%, 35%);
}

td.pts-neg { 
  text-align: center; 
  font-weight: 700; 
  color: #ef4444;
}

/* Estado PENDIENTE: header gris atenuado */
.match-card.pendiente .match-header {
  background: hsl(220, 10%, 42%);
}
.match-card.pendiente .match-header .round {
  opacity: 0.7;
}
.match-card.pendiente .match-header .summary {
  color: hsl(220, 10%, 72%);
  font-style: italic;
  font-size: 11px;
}

/* Badge FINALIZADO a la derecha del header */
.badge-finalizado {
  font-size: 9px;
  font-weight: 600;
  letter-spacing: 0.1em;
  color: hsl(145, 30%, 68%);
  text-transform: uppercase;
  white-space: nowrap;
  flex-shrink: 0;
}

/* Nombres de jugadores más pequeños en el layout */
.team-left .player-name,
.team-right .player-name,
.team-left .team-name,
.team-right .team-name {
  font-size: 11px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 100%;
  display: block;
}
.team-left .player-name .a,
.team-right .player-name .a,
.team-left .team-name .a,
.team-right .team-name .a {
  font-size: 11px;
}
.team-left .player-name .b,
.team-right .player-name .b,
.team-left .team-name .b,
.team-right .team-name .b {
  font-size: 10px;
}


.match-row-layout {
  display: grid;
  grid-template-columns: minmax(0,1fr) auto auto auto minmax(0,1fr);
  align-items: center;
  gap: 6px;
  padding: 10px 12px;
  min-height: 52px;
}
.team-left {
  display: flex;
  flex-direction: column;
  gap: 6px;
  align-items: flex-start;
  min-width: 0;
  overflow: hidden;
}
.team-right {
  display: flex;
  flex-direction: column;
  gap: 6px;
  align-items: flex-end;
  text-align: right;
  min-width: 0;
  overflow: hidden;
}
.scores-left, .scores-right {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 5px;
}
.vs-center {
  font-size: 11px;
  color: hsl(215, 14%, 55%);
  font-weight: 500;
  padding: 0 2px;
}
.check-mark {
  display: inline-block;
  width: 14px;
  color: hsl(200, 55%, 42%);
  font-weight: 700;
  font-size: 11px;
  flex-shrink: 0;
}
.check-mark-right {
  color: hsl(200, 55%, 42%);
  font-weight: 700;
  font-size: 11px;
  margin-left: 2px;
}
@media (max-width: 480px) {
  .match-row-layout { gap: 4px; padding: 8px 8px; }
}

/* Modal Clasificación por grupo */
.modal-clasif-overlay { display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center; padding:16px; }
.modal-clasif-overlay.show { display:flex; }
.modal-clasif-box { background:#fff; border-radius:10px; width:100%; max-width:420px; overflow:hidden; box-shadow:0 8px 30px rgba(0,0,0,0.2); }
.modal-clasif-header { background:#374151; color:#fff; padding:12px 16px; display:flex; align-items:center; justify-content:space-between; font-weight:700; font-size:14px; }

/* Bracket Eliminatorio */
.bracket-wrapper { overflow-x:auto; -webkit-overflow-scrolling:touch; padding:8px 0 16px; }
.bracket-container { display:flex; align-items:stretch; min-width:max-content; gap:0; }
.bracket-ronda { flex:0 0 165px; display:flex; flex-direction:column; }
.bracket-ronda-header { text-align:center; font-size:11px; font-weight:600; color:hsl(215,14%,50%); text-transform:uppercase; letter-spacing:0.05em; padding:6px 0 10px; white-space:nowrap; }
.bracket-ronda-body { display:flex; flex-direction:column; justify-content:space-around; flex:1; position:relative; }
.bracket-card { background:#fff; border-radius:6px; border:1px solid hsl(214,25%,85%); overflow:hidden; margin:4px 3px; position:relative; z-index:1; }
.bracket-card-enjuego { border:2px solid #EBA652; }
.bracket-hd { background:#374151; color:#fff; padding:4px 8px; font-size:11px; display:flex; align-items:center; gap:6px; }
.bracket-hd-enjuego { background:#EBA652; }
.bracket-hd-pend { background:#6b7280; }
.bracket-hd .bk-pf { font-weight:600; }
.bracket-hd .bk-inf { flex:1; font-size:9px; opacity:0.85; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.bracket-hd .bk-badge { font-size:8px; letter-spacing:0.05em; color:#86efac; }
.bracket-hd .bk-badge-ej { font-size:8px; letter-spacing:0.05em; color:#fff; }
.bracket-row { display:flex; align-items:center; justify-content:space-between; padding:5px 8px; border-bottom:1px solid hsl(214,25%,90%); font-size:11px; line-height:1.25; }
.bracket-row:last-of-type { border-bottom:none; }
.bracket-row-winner { background:#e8f4f8; }
.bracket-row .bk-nm { flex:1; min-width:0; }
.bracket-row .bk-na { font-size:11px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.bracket-row-winner .bk-na { font-weight:600; }
.bracket-row .bk-nb { font-size:9px; color:hsl(215,14%,50%); text-transform:uppercase; }
.bracket-row .bk-sc { font-size:13px; font-weight:600; min-width:16px; text-align:right; flex-shrink:0; }
.bracket-row .bk-sc-win { color:hsl(220,20%,15%); }
.bracket-row .bk-sc-lose { color:hsl(215,14%,60%); }
.bracket-ft { padding:3px 8px; font-size:9px; color:hsl(215,14%,55%); }
.bracket-pend-nm { font-style:italic; color:hsl(215,14%,60%); }
.bracket-conn { flex:0 0 20px; position:relative; }
.bracket-conn svg { position:absolute; top:0; left:0; width:100%; height:100%; }
.bracket-conn svg line { stroke:hsl(214,25%,75%); stroke-width:1.5; }
@media (min-width:768px) {
  .bracket-ronda { flex:0 0 210px; }
  .bracket-hd .bk-inf { font-size:10px; }
  .bracket-row .bk-na { font-size:12px; }
  .bracket-conn { flex:0 0 28px; }
}
</style>";

echo "<script>
function toggleMatch(btn) {
  btn.classList.toggle('open');
  btn.nextElementSibling.classList.toggle('open');
}

function switchTab(tab) {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
  document.getElementById('tab-' + tab).classList.add('active');
  event.target.classList.add('active');
  if(tab === 'clasificacion') { setTimeout(drawBracketLines, 100); }
}

function openModalClasif(id) {
  document.getElementById(id).classList.add('show');
  document.body.style.overflow = 'hidden';
}
function closeModalClasif(id) {
  document.getElementById(id).classList.remove('show');
  document.body.style.overflow = '';
}
</script>";

function user_ci2($ci){
	if($ci==0)
		return true;
	global $usuIdxTvt;
	$row = isset($usuIdxTvt[$ci]) ? $usuIdxTvt[$ci] : array('nombre'=>'','apellido'=>'','pais_residencia'=>'');
	$pais=$row['pais_residencia'];
	$solonombre=explode(' ',$row['nombre']);
	$soloapellido=explode(' ',$row['apellido']);
	if(strlen(trim($soloapellido[0]))<4):
		$soloapellido[0]=$row['apellido'];
	endif;

	$imgs="";
	// Comentado para no mostrar banderas
	//if(trim(strlen($pais))>0)
	//$imgs="<img src='/icon/flags/".strtolower(trim($pais))."'>";

	return ("{$imgs}<span class='ab'><span class='a'>".mb_strtoupper($solonombre[0], 'UTF-8')."</span><br><span class='b'> <strong style='font-size:10px; color:gray'>".mb_strtoupper($soloapellido[0], 'UTF-8')."</strong></span></span>");
} //fin funcion

// $evento debe venir definido desde el archivo que incluye este
// Ya sea como variable global o desde $_GET/$_POST
if(!isset($evento)):
	if(isset($_GET['evento'])):
		$evento = abs(filter_var($_GET['evento'], FILTER_SANITIZE_STRING));
	elseif(isset($_POST['evento'])):
		$evento = abs(filter_var($_POST['evento'], FILTER_SANITIZE_STRING));	
	else:
		exit;
	endif;
endif;
$where=" WHERE _todosvstodos.evento={$evento} ";
//
$groupBy=" GROUP BY _todosvstodos.categoria ";
$orderBy=" ORDER BY _todosvstodos.categoria, _todosvstodos.grupo, _todosvstodos.partido_nro";
$mostrar="no";

// Precarga para el render (evita una query por jugador/etiqueta/categoría)
$usuIdxTvt=array();
$resPreT=$mysqli2->query("SELECT * FROM _p_usuarios");
while($rPreT=$resPreT->fetch_assoc()) if(!isset($usuIdxTvt[$rPreT['ci']])) $usuIdxTvt[$rPreT['ci']]=$rPreT;

$grpNombreIdx=array();
$resPreT=$mysqli2->query("SELECT id, grupo FROM _p_grupos");
while($rPreT=$resPreT->fetch_assoc()) $grpNombreIdx[$rPreT['id']]=$rPreT['grupo'];

$refNombreIdx=array();
$resPreT=$mysqli2->query("SELECT id, referencia FROM _referencia_etiquetas");
while($rPreT=$resPreT->fetch_assoc()) $refNombreIdx[$rPreT['id']]=$rPreT['referencia'];

$catVisIdx=array();
$resPreT=$mysqli2->query("SELECT id_categoria, visualizar_en_llaves FROM v_p_categorias WHERE id_evento={$evento}");
while($rPreT=$resPreT->fetch_assoc()) if(!isset($catVisIdx[$rPreT['id_categoria']])) $catVisIdx[$rPreT['id_categoria']]=$rPreT;


//las categorias
$sqlD="SELECT 
_todosvstodos.categoria, 
_todosvstodos.grupo,
date_format(_todosvstodos.fecha,'%d-%m-%Y') as fecha,
_todosvstodos.ci1_a,
_todosvstodos.ci1_b,
_todosvstodos.ci2_a,
_todosvstodos.ci2_b,
_p_grupos.grupo as textoGrupo
from  
_todosvstodos,
_p_grupos
{$where}
AND _p_grupos.id=_todosvstodos.grupo
{$groupBy}
{$orderBy}
 ";
 
$resultado=$mysqli2->query($sqlD); 

// DEBUG: Ver si la consulta funciona
if(isset($_GET['debug_cat'])):
	echo "<pre>DEBUG CATEGORÍAS:\n";
	echo "SQL: {$sqlD}\n";
	echo "Filas encontradas: " . ($resultado ? $resultado->num_rows : 0) . "\n";
	echo "</pre>";
endif;

$losCruces=$losGruposarray=$lasCategorias=array();
$pos=0;
while($row = $resultado->fetch_assoc()){
	// DEBUG
	if(isset($_GET['debug_cat'])):
		echo "<pre>Categoría encontrada: {$row['categoria']}\n";
	endif;
	
	// Verificar si la categoría debe visualizarse en llaves (precargado)
	$rowVV = isset($catVisIdx[$row['categoria']]) ? $catVisIdx[$row['categoria']] : null;
	
	// DEBUG
	if(isset($_GET['debug_cat'])):
		echo "Resultado: ";
		print_r($rowVV);
		echo "</pre>";
	endif;
	
	if(isset($rowVV['visualizar_en_llaves']) && $rowVV['visualizar_en_llaves']=='si')
		$lasCategorias[$row['categoria']]=$row['categoria'];
} 

//fin solo las categorias

// ============================================
// 🎮 TOGGLE "EN JUEGO" - Para marcar partidos en curso
// ============================================
if(isset($_GET['enJuego'])):
	if($_GET['estado']=='no'):
		$sqlUP="UPDATE _todosvstodos SET en_juego='si' WHERE evento={$evento} AND id=".abs($_GET['enJuego']);
	else:
		$sqlUP="UPDATE _todosvstodos SET en_juego='no' WHERE evento={$evento} AND id=".abs($_GET['enJuego']);
	endif;
	$mysqli2->query($sqlUP); 
endif;

if(isset($_GET['categoria'])):
	$mostrar="si";
	$groupBy='  ';
	$laCategoria=abs(filter_var($_GET['categoria'], FILTER_SANITIZE_STRING));
	$where=" WHERE _todosvstodos.evento={$evento} AND _todosvstodos.categoria={$laCategoria}";
elseif(isset($_POST['categoria'])):
	$mostrar="si";
	$groupBy='';	
	$laCategoria=abs(filter_var($_POST['categoria'], FILTER_SANITIZE_STRING));
	$where=" WHERE _todosvstodos.evento={$evento} AND _todosvstodos.categoria={$laCategoria}";
endif;

// ============================================================
// 🔄 AUTO-BYE: Cargar resultado 1-0 y propagar automáticamente
// ============================================================
if(isset($evento) && isset($laCategoria)):
	$sqlByeCheck = "SELECT id, evento, categoria, grupo, partido_nro, 
		ci1_a, ci1_b, ci2_a, ci2_b,
		rusultado_equipo1, resultado_equipo2,
		ref_tipo_regustado1, ref_tipo_regustado2
		FROM _todosvstodos 
		WHERE evento={$evento} AND categoria={$laCategoria}
		AND grupo IN (32, 26, 13, 15, 18, 19)
		AND (ref_tipo_regustado1 = 3 OR ref_tipo_regustado2 = 3)
		AND rusultado_equipo1 = 0 AND resultado_equipo2 = 0";
	$resByeCheck = $mysqli2->query($sqlByeCheck);
	if($resByeCheck && $resByeCheck->num_rows > 0):
		while($byeRow = $resByeCheck->fetch_assoc()):
			$byeEnA = ($byeRow['ref_tipo_regustado1'] == 3);
			$byeEnB = ($byeRow['ref_tipo_regustado2'] == 3);
			$tieneRealA = ($byeRow['ci1_a'] > 0);
			$tieneRealB = ($byeRow['ci2_a'] > 0);
			if(($byeEnA && $tieneRealB) || ($byeEnB && $tieneRealA)):
				if($byeEnB && $tieneRealA):
					$mysqli2->query("UPDATE _todosvstodos SET rusultado_equipo1=1, resultado_equipo2=0 WHERE id={$byeRow['id']}");
					$ci_g_a = $byeRow['ci1_a']; $ci_g_b = $byeRow['ci1_b'];
				elseif($byeEnA && $tieneRealB):
					$mysqli2->query("UPDATE _todosvstodos SET rusultado_equipo1=0, resultado_equipo2=1 WHERE id={$byeRow['id']}");
					$ci_g_a = $byeRow['ci2_a']; $ci_g_b = $byeRow['ci2_b'];
				endif;
				$ev = $byeRow['evento']; $cat = $byeRow['categoria'];
				$grp = $byeRow['grupo']; $nro = $byeRow['partido_nro'];
				$idVirt = getIdVirtualGrupo($mysqli2, $grp, $nro);
				$idBuscar = ($idVirt > 0) ? $idVirt : $grp;
				$r=$mysqli2->query("SELECT id FROM _todosvstodos WHERE evento={$ev} AND categoria={$cat} AND ref_etiqueta1={$idBuscar} AND ref_tipo_regustado1=1 AND (tipo_referencia='si' OR (tipo_referencia='no' AND ci1_a=0)) LIMIT 1");
				if($r && $r->num_rows>0){ $n=$r->fetch_assoc(); propagarJugador($mysqli2, $n['id'], 'ci1', $ci_g_a, $ci_g_b); }
				$r=$mysqli2->query("SELECT id FROM _todosvstodos WHERE evento={$ev} AND categoria={$cat} AND ref_etiqueta2={$idBuscar} AND ref_tipo_regustado2=1 AND (tipo_referencia='si' OR (tipo_referencia='no' AND ci2_a=0)) LIMIT 1");
				if($r && $r->num_rows>0){ $n=$r->fetch_assoc(); propagarJugador($mysqli2, $n['id'], 'ci2', $ci_g_a, $ci_g_b); }
			endif;
		endwhile;
	endif;
endif;
// ============================================================

// Equipos ya presentes en tabla_auxiliar (chequeo de siembra sin query por partido;
// el INSERT sigue disparándose en los mismos casos que antes)
$auxSeedIdx=array();
if(isset($evento) && isset($laCategoria)):
	$resPreT=$mysqli2->query("SELECT ci1_a, ci1_b FROM tabla_auxiliar WHERE id_evento={$evento} AND id_categoria={$laCategoria}");
	if($resPreT) while($rPreT=$resPreT->fetch_assoc()) $auxSeedIdx[$rPreT['ci1_a'].'|'.$rPreT['ci1_b']]=true;
endif;

///tema grupos - Organizar por fila y columna

$sqlD="SELECT 
_todosvstodos.categoria, 
_todosvstodos.grupo,
date_format(_todosvstodos.fecha,'%d-%m-%Y') as fecha,
_todosvstodos.ci1_a,
_todosvstodos.ci1_b,
_todosvstodos.ci2_a,
_todosvstodos.ci2_b,
_p_grupos.grupo as textoGrupo,
_p_grupos.orden,
_p_grupos.fila,
_p_grupos.columna
from  
_todosvstodos,
_p_grupos
{$where}
AND _p_grupos.id=_todosvstodos.grupo
GROUP BY _p_grupos.grupo
ORDER BY _p_grupos.orden ASC
 ";
 
$resultado=$mysqli2->query($sqlD); 
$contenido=array();
$pos=0;
while($row = $resultado->fetch_assoc()){
	$contenido[$row['fila']][$row['columna']]=$row['grupo'];
}
 
///tema grupos


$sqlD="SELECT
_todosvstodos.id,
_todosvstodos.categoria, 
_todosvstodos.grupo,
date_format(_todosvstodos.fecha,'%d-%m-%Y') as fecha,
_todosvstodos.hora,
_todosvstodos.ci1_a,
_todosvstodos.ci1_b,
_todosvstodos.ci2_a,
_todosvstodos.ci2_b,
_todosvstodos.rusultado_equipo1 as resultado11,
_todosvstodos.resultado_equipo2 as resultado12,
_todosvstodos.resultado2_equipo1 as resultado21,
_todosvstodos.resultado2_equipo2 as resultado22,
_todosvstodos.resultado3_equipo1 as resultado31,
_todosvstodos.resultado3_equipo2 as resultado32,
_todosvstodos.complejo,
_todosvstodos.cancha,
_todosvstodos.partido_nro as partidoNro,
_todosvstodos.tipo_referencia,
_todosvstodos.ref_tipo_regustado1,
_todosvstodos.ref_tipo_regustado2,
_todosvstodos.ref_etiqueta1,
_todosvstodos.ref_etiqueta2,
_todosvstodos.en_juego,
_p_grupos.grupo as textoGrupo,
_p_grupos.orden,
_p_grupos.fila,
_p_grupos.columna
from  
_todosvstodos,
_p_grupos
{$where}
AND _p_grupos.id=_todosvstodos.grupo

{$groupBy}
{$orderBy}
 ";
  
$resultado=$mysqli2->query($sqlD); 
$losCruces=$losGruposarray=$losEquipos=array();
$pos=0;
if(isset($laCategoria)): //inicion con categoria
while($row = $resultado->fetch_assoc()){
 
	$losGrupos[$row['grupo']]=$row['grupo'];
	$textoGrupos[$row['grupo']]=$row['textoGrupo'];//Grupo


	$winnerA=$winnerB='';
	/*if($row['resultado11']>0 && $row['resultado11']>$row['resultado21']):
		$winnerA="winner";
	elseif($row['resultado21']>0 && $row['resultado21']>$row['resultado12']):
		$winnerB="winner";
	elseif($row['resultado22']>0 && $row['resultado22']>$row['resultado21']):
		$winnerB="winner";
	elseif($row['resultado11']>0 && $row['resultado21']>$row['resultado22']):
			$winnerA="winner";
	elseif($row['resultado11']>0 && $row['resultado22']>$row['resultado21']):
			$winnerB="winner";
	endif;
	if($row['resultado11']>0  && $row['resultado11']>$row['resultado21'] && $row['resultado12']>$row['resultado22']):
		$winnerA="winner 1";
	endif;
	if($row['resultado21']>0  && $row['resultado21']>$row['resultado11'] && $row['resultado22']>$row['resultado12']):
		$winnerA="winner 2";
	endif;
	if($row['resultado11']>0  && $row['resultado11']>$row['resultado21'] && $row['resultado12']>$row['resultado22']):
			$winnerB="winner 3";
	endif;
	if($row['resultado21']>0  && $row['resultado21']>$row['resultado11'] && $row['resultado22']>$row['resultado12']):
		$winnerB="winner 4";
	endif;*/
    // Determinar ganador contando sets ganados
	$setsGanadosA = 0;
	$setsGanadosB = 0;
	
	// Set 1
	if($row['resultado11'] > 0 || $row['resultado12'] > 0):
		if($row['resultado11'] > $row['resultado12']) $setsGanadosA++;
		elseif($row['resultado12'] > $row['resultado11']) $setsGanadosB++;
	endif;
	// Set 2
	if($row['resultado21'] > 0 || $row['resultado22'] > 0):
		if($row['resultado21'] > $row['resultado22']) $setsGanadosA++;
		elseif($row['resultado22'] > $row['resultado21']) $setsGanadosB++;
	endif;
	// Set 3 (super tie-break)
	$resultado31=$resultado32=' ';
	if($row['resultado31'] > 0 || $row['resultado32'] > 0):
		$resultado31 = $row['resultado31'];
		$resultado32 = $row['resultado32'];
		if($row['resultado31'] > $row['resultado32']) $setsGanadosA++;
		elseif($row['resultado32'] > $row['resultado31']) $setsGanadosB++;
	endif;
	
	if($setsGanadosA > $setsGanadosB) $winnerA = "winner";
	elseif($setsGanadosB > $setsGanadosA) $winnerB = "winner";

	if($winnerA!='' || $winnerB!=''):
		$cadaEquipo[$row['ci1_a'].$row['ci1_b']]=[$row['ci1_a']."#".$row['ci1_b']];
		if(!isset($losEquiposT[$row['ci1_a'].$row['ci1_b']]['pj'])):
			$losEquiposT[$row['ci1_a'].$row['ci1_b']]['pj']=0;
			$losEquiposT[$row['ci1_a'].$row['ci1_b']]['pg']=0;
			$losEquiposT[$row['ci1_a'].$row['ci1_b']]['ca']=$row['ci1_a'];
			$losEquiposT[$row['ci1_a'].$row['ci1_b']]['cb']=$row['ci1_b'];
		endif;
		//if(isset($losEquiposT[$row['ci1_a'].$row['ci1_b']]['pj']))
			$losEquiposT[$row['ci1_a'].$row['ci1_b']]['pj']=abs($losEquiposT[$row['ci1_a'].$row['ci1_b']]['pj'])+1;
			if($winnerA!='')
			$losEquiposT[$row['ci1_a'].$row['ci1_b']]['pg']=abs($losEquiposT[$row['ci1_a'].$row['ci1_b']]['pg'])+1;

		if(!isset($losEquiposT[$row['ci2_a'].$row['ci2_b']]['pj'])):
			$losEquiposT[$row['ci2_a'].$row['ci2_b']]['pj']=0;
			$losEquiposT[$row['ci2_a'].$row['ci2_b']]['pg']=0;
			$losEquiposT[$row['ci2_a'].$row['ci2_b']]['ca']=$row['ci1_a'];
			$losEquiposT[$row['ci2_a'].$row['ci2_b']]['cb']=$row['ci1_b'];
		endif;
		//if(isset($losEquiposT[$row['ci1_a'].$row['ci1_b']]['pj']))
			$losEquiposT[$row['ci2_a'].$row['ci2_b']]['pj']=abs($losEquiposT[$row['ci2_a'].$row['ci2_b']]['pj'])+1;
			if($winnerB!='')
			$losEquiposT[$row['ci2_a'].$row['ci2_b']]['pg']=abs($losEquiposT[$row['ci2_a'].$row['ci2_b']]['pg'])+1;

		//if(isset($_GET['tabla'])): //tabla
			$kSeed=$row['ci1_a'].'|'.$row['ci1_b'];
			if(!isset($auxSeedIdx[$kSeed])):
				$auxSeedIdx[$kSeed]=true;
				$insertV="INSERT INTO tabla_auxiliar (id_evento, id_categoria,ci1_a,ci1_b,id_grupo)
											VALUES ({$evento},{$laCategoria},'{$row['ci1_a']}','{$row['ci1_b']}',{$row['grupo']})";
				$mysqli2->query($insertV);

			endif;

			$kSeed=$row['ci2_a'].'|'.$row['ci2_b'];
			if(!isset($auxSeedIdx[$kSeed])):
				$auxSeedIdx[$kSeed]=true;
				$insertV="INSERT INTO tabla_auxiliar (id_evento, id_categoria,ci1_a,ci1_b,id_grupo)
											VALUES ({$evento},{$laCategoria},'{$row['ci2_a']}','{$row['ci2_b']}',{$row['grupo']})";
				$mysqli2->query($insertV);
				if(isset($_GET['debug']))
				echo __LINE__." <div>{$insertV}</div><br>";

			endif;
		//endif; //fin tabla
	endif;
	// Generar prefijo legible para el header del partido
	$_tg = strtoupper($row['textoGrupo']);
	$_pn = $row['partidoNro'];
	if(strpos($_tg,'16VOS')!==false)      $prefijo='16.'.$_pn;
	elseif(strpos($_tg,'8VOS')!==false)   $prefijo='8.'.$_pn;
	elseif(strpos($_tg,'CUARTOS')!==false)$prefijo='C'.$_pn;
	elseif(strpos($_tg,'SEMI')!==false)   $prefijo='S'.$_pn;
	elseif(strpos($_tg,'FINAL')!==false)  $prefijo='F';
	elseif(strpos($_tg,'TERCER')!==false) $prefijo='3P';
	elseif(strpos($_tg,'GRUPO')!==false || strpos($_tg,'RONDA')!==false) $prefijo='R'.$_pn;
	else $prefijo=mb_substr($row['textoGrupo'],0,2,'UTF-8').$_pn;
	

	// ============================================
	// 📝 MODO EDICIÓN - Solo visible para administradores con ?carga
	// ============================================
	
	if(($row['tipo_referencia']=='no' || ($row['tipo_referencia']=='si' && ($row['ref_tipo_regustado1']==3 || $row['ref_tipo_regustado2']==3)) || ($row['tipo_referencia']=='si' && $row['ci1_a']>0 && $row['ci2_a']>0)) && isset($_GET['carga'])):
		// Clase CSS para estado en juego
		$claseEnJuego = ($row['en_juego'] == 'si') ? 'en-juego' : '';
		
		// Preparar datos de jugadores (etiqueta si ci=0)
		if($row['ci1_a']>0){
			$jugador1A = user_ci2($row['ci1_a']); $jugador1B = user_ci2($row['ci1_b']);
		} else {
			$g1 = isset($grpNombreIdx[$row['ref_etiqueta1']]) ? $grpNombreIdx[$row['ref_etiqueta1']] : '';
			$laRef1 = isset($refNombreIdx[$row['ref_tipo_regustado1']]) ? $refNombreIdx[$row['ref_tipo_regustado1']] : '';
			$jugador1A = ($row['ref_tipo_regustado1']==3) ? 'Bye Bye' : "{$laRef1} {$g1}";
			$jugador1B = '';
		}
		if($row['ci2_a']>0){
			$jugador2A = user_ci2($row['ci2_a']); $jugador2B = user_ci2($row['ci2_b']);
		} else {
			$g2 = isset($grpNombreIdx[$row['ref_etiqueta2']]) ? $grpNombreIdx[$row['ref_etiqueta2']] : '';
			$laRef2 = isset($refNombreIdx[$row['ref_tipo_regustado2']]) ? $refNombreIdx[$row['ref_tipo_regustado2']] : '';
			$jugador2A = ($row['ref_tipo_regustado2']==3) ? 'Bye Bye' : "{$laRef2} {$g2}";
			$jugador2B = '';
		}
		
		// Clases ganador/perdedor igual que modo público
		$winnerClassA = ($winnerA == 'winner') ? 'winner-name' : 'loser-name';
		$winnerClassB = ($winnerB == 'winner') ? 'winner-name' : 'loser-name';
		$checkA = ($winnerA == 'winner') ? '✓ ' : '&nbsp;&nbsp;';
		$checkB = ($winnerB == 'winner') ? ' ✓' : '';
		
		// Estado finalizado/pendiente igual que modo público
		$clasePartidoCarga = ($winnerA == 'winner' || $winnerB == 'winner') ? 'finalizado' : 'pendiente';
		
		// Construir resumen ganador para header del modo carga
		$resumenCarga = 'A continuación';
		if($winnerA == 'winner'):
			$t1 = mb_substr(trim(explode("\n", trim(strip_tags($jugador1A)))[0] ?? ''), 0, 12);
			$t2 = mb_substr(trim(explode("\n", trim(strip_tags($jugador1B)))[0] ?? ''), 0, 12);
			$sc = isset($sets[0]) ? $sets[0]['a'].'-'.$sets[0]['b'] : '';
			$resumenCarga = $t1 . ' / ' . $t2 . ($sc ? " ({$sc})" : '');
		elseif($winnerB == 'winner'):
			$t1 = mb_substr(trim(explode("\n", trim(strip_tags($jugador2A)))[0] ?? ''), 0, 12);
			$t2 = mb_substr(trim(explode("\n", trim(strip_tags($jugador2B)))[0] ?? ''), 0, 12);
			$sc = isset($sets[0]) ? $sets[0]['b'].'-'.$sets[0]['a'] : '';
			$resumenCarga = $t1 . ' / ' . $t2 . ($sc ? " ({$sc})" : '');
		endif;
		
		$badgeCarga = ($clasePartidoCarga == 'finalizado') ? "<span class='badge-finalizado'>FINALIZADO</span>" : '';
		
		// Abierto si pendiente, cerrado si finalizado
		$openCarga = ($clasePartidoCarga == 'pendiente') ? 'open' : '';
		
		// Datos del complejo
		$nombreComplejo = datosComplejo($row['complejo'])['nombre'];
		$nombreCancha = cancha_id($row['cancha']);
		
		$losCruces[$row['grupo']][$pos]="
		<div class='match-card {$claseEnJuego} {$clasePartidoCarga}' id='x{$row['id']}'>
			<button class='match-header {$openCarga}' onclick='toggleMatch(this)'>
				<span class='round'>{$prefijo}</span>
				<div class='info'>
					<span class='summary' style='font-size: 11px;'>{$resumenCarga}</span>
					<a href='?evento=".abs($_GET['evento'])."&categoria=".abs($_GET['categoria'])."&carga&estado={$row['en_juego']}&enJuego={$row['id']}#x{$row['id']}' 
					   style='color: white; text-decoration: none; font-size: 11px; margin-left: 12px;'
					   onclick='event.stopPropagation();'>
						En juego ({$row['en_juego']})
					</a>
					{$badgeCarga}
				</div>
				<span class='chevron'>▼</span>
			</button>
			
			<div class='match-body {$openCarga}'>
				<form method='post' action='?evento=".abs($_GET['evento'])."&categoria=".abs($_GET['categoria'])."&carga#x{$row['id']}'>
					<input type='hidden' name='idRes' value='{$row['id']}'>
					
					<div class='match-row-layout'>
						<div class='team-left'>
							<span class='team-name {$winnerClassA}'><span class='check-mark'>{$checkA}</span>{$jugador1A}</span>
							<span class='team-name {$winnerClassA}'><span class='check-mark'>{$checkA}</span>{$jugador1B}</span>
						</div>
						<div class='scores-left'>
							<input class='ic' type='number' name='rusultado_equipo1' value='{$row['resultado11']}'>
							<input class='ic' type='number' name='resultado2_equipo1' value='{$row['resultado21']}'>
							<input class='ic' type='number' name='resultado3_equipo1' value='{$row['resultado31']}' placeholder='3°' style='border: 1px dashed #c9a227; background: #fffbf0;'>
						</div>
						<div class='vs-center'>vs</div>
						<div class='scores-right'>
							<input class='ic' type='number' name='resultado_equipo2' value='{$row['resultado12']}'>
							<input class='ic' type='number' name='resultado2_equipo2' value='{$row['resultado22']}'>
							<input class='ic' type='number' name='resultado3_equipo2' value='{$row['resultado32']}' placeholder='3°' style='border: 1px dashed #c9a227; background: #fffbf0;'>
						</div>
						<div class='team-right'>
							<span class='team-name {$winnerClassB}'>{$jugador2A}<span class='check-mark-right'>{$checkB}</span></span>
							<span class='team-name {$winnerClassB}'>{$jugador2B}<span class='check-mark-right'>{$checkB}</span></span>
						</div>
					</div>
					
					<!-- Botón confirmar -->
					<div style='padding: 10px 14px; background: hsl(210, 20%, 98%); border-top: 1px solid hsl(214, 25%, 85%);'>
						<button type='submit' style='background: hsl(200, 45%, 50%); color: white; border: none; padding: 8px 24px; border-radius: 6px; cursor: pointer; font-weight: 600;'>
							💾 Guardar Resultados
						</button>
					</div>
				</form>
				
				<!-- Footer con complejo y cancha -->
				<div style='padding: 8px 16px; background: hsl(220, 20%, 25%); color: white; font-size: 12px; display: flex; justify-content: space-between;'>
					<span>{$nombreComplejo}</span>
					<span>{$nombreCancha}</span>
				</div>
			</div>
		</div>";
	else:
	// ============================================
	// 🎨 MODO PÚBLICO - Diseño moderno con cuadros colapsables
	// ============================================
	
	// Clases CSS para ganadores/perdedores
	$winnerClassA = ($winnerA == 'winner') ? 'winner-name' : 'loser-name';
	$winnerClassB = ($winnerB == 'winner') ? 'winner-name' : 'loser-name';
	$scoreClassA = ($winnerA == 'winner') ? 'winner-score' : 'loser-score';
	$scoreClassB = ($winnerB == 'winner') ? 'winner-score' : 'loser-score';
	$winnerRowA = ($winnerA == 'winner') ? 'winner-row' : '';
	$winnerRowB = ($winnerB == 'winner') ? 'winner-row' : '';
	
	// Obtener datos de jugadores o etiquetas (Primero/Segundo/Ganador de grupo)
	// tipo_referencia='si' → siempre etiquetas (admin puede ocultar nombres)
	// tipo_referencia='no' → siempre nombres reales
	if($row['tipo_referencia']=='si'):
		$g1 = isset($grpNombreIdx[$row['ref_etiqueta1']]) ? $grpNombreIdx[$row['ref_etiqueta1']] : '';
		$g2 = isset($grpNombreIdx[$row['ref_etiqueta2']]) ? $grpNombreIdx[$row['ref_etiqueta2']] : '';
		$laRef1 = isset($refNombreIdx[$row['ref_tipo_regustado1']]) ? $refNombreIdx[$row['ref_tipo_regustado1']] : '';
		$laRef2 = isset($refNombreIdx[$row['ref_tipo_regustado2']]) ? $refNombreIdx[$row['ref_tipo_regustado2']] : '';
		// Si ya tiene jugador propagado en ci1, mostrar nombre real aunque tipo_referencia='si'
		if($row['ref_tipo_regustado1']==3):
			$jugador1A = 'Bye Bye'; $jugador1B = '';
		elseif($row['ci1_a']>0):
			$jugador1A = user_ci2($row['ci1_a']); $jugador1B = user_ci2($row['ci1_b']);
		else:
			$jugador1A = "{$laRef1} {$g1}"; $jugador1B = '';
		endif;
		// Si ya tiene jugador propagado en ci2, mostrar nombre real aunque tipo_referencia='si'
		if($row['ref_tipo_regustado2']==3):
			$jugador2A = 'Bye Bye'; $jugador2B = '';
		elseif($row['ci2_a']>0):
			$jugador2A = user_ci2($row['ci2_a']); $jugador2B = user_ci2($row['ci2_b']);
		else:
			$jugador2A = "{$laRef2} {$g2}"; $jugador2B = '';
		endif;
	elseif($row['tipo_referencia']=='no' && $row['ref_tipo_regustado1']==3):
		$jugador1A = 'Bye Bye'; $jugador1B = '';
		$jugador2A = user_ci2($row['ci2_a']); $jugador2B = user_ci2($row['ci2_b']);
	elseif($row['tipo_referencia']=='no' && $row['ref_tipo_regustado2']==3):
		$jugador1A = user_ci2($row['ci1_a']); $jugador1B = user_ci2($row['ci1_b']);
		$jugador2A = 'Bye Bye'; $jugador2B = '';
	elseif($row['tipo_referencia']=='no' && ($row['ref_etiqueta1']>0 || $row['ref_etiqueta2']>0)):
		$g1 = isset($grpNombreIdx[$row['ref_etiqueta1']]) ? $grpNombreIdx[$row['ref_etiqueta1']] : '';
		$g2 = isset($grpNombreIdx[$row['ref_etiqueta2']]) ? $grpNombreIdx[$row['ref_etiqueta2']] : '';
		$laRef1 = isset($refNombreIdx[$row['ref_tipo_regustado1']]) ? $refNombreIdx[$row['ref_tipo_regustado1']] : '';
		$laRef2 = isset($refNombreIdx[$row['ref_tipo_regustado2']]) ? $refNombreIdx[$row['ref_tipo_regustado2']] : '';
		$jugador1A = ($row['ci1_a']>0) ? user_ci2($row['ci1_a']) : "{$laRef1} {$g1}";
		$jugador1B = ($row['ci1_a']>0) ? user_ci2($row['ci1_b']) : '';
		$jugador2A = ($row['ci2_a']>0) ? user_ci2($row['ci2_a']) : "{$laRef2} {$g2}";
		$jugador2B = ($row['ci2_a']>0) ? user_ci2($row['ci2_b']) : '';
	else:
		$jugador1A = user_ci2($row['ci1_a']); $jugador1B = user_ci2($row['ci1_b']);
		$jugador2A = user_ci2($row['ci2_a']); $jugador2B = user_ci2($row['ci2_b']);
	endif;
	
	// Formatear resultados
	$res11 = $row['resultado11'];
	$res21 = $row['resultado21'];
	$res12 = $row['resultado12'];
	$res22 = $row['resultado22'];
	
	// Determinar qué sets mostrar y en qué orden
	// Set 1: res11 vs res12 | Set 2: res21 vs res22 | Set 3: resultado31 vs resultado32
	$sets = [];
	// Set 1
	if($res11 > 0 || $res12 > 0):
		$sets[] = ['a' => $res11, 'b' => $res12];
	endif;
	// Set 2
	if($res21 > 0 || $res22 > 0):
		$sets[] = ['a' => $res21, 'b' => $res22];
	endif;
	// Set 3 (super tie-break)
	if($resultado31 != ' ' && ($resultado31 > 0 || $resultado32 > 0)):
		$sets[] = ['a' => $resultado31, 'b' => $resultado32];
	endif;
	
	// Si hay sets, poner primero el que ganó el equipo ganador
	if(count($sets) > 1):
		if($winnerA == 'winner'):
			// Ordenar: primero el set donde A ganó
			usort($sets, function($x, $y){ return ($x['a'] > $x['b']) ? -1 : 1; });
		elseif($winnerB == 'winner'):
			// Ordenar: primero el set donde B ganó
			usort($sets, function($x, $y){ return ($x['b'] > $x['a']) ? -1 : 1; });
		endif;
	endif;
	
	// Construir HTML de scores en columnas paralelas
	$scoresHTML_A = '';
	$scoresHTML_B = '';
	foreach($sets as $set):
		$scoresHTML_A .= "<span class='score {$scoreClassA}'>{$set['a']}</span>";
		$scoresHTML_B .= "<span class='score {$scoreClassB}'>{$set['b']}</span>";
	endforeach;
	
	// Si no hay ningún set jugado, mostrar guión
	if(empty($sets)):
		$scoresHTML_A = "<span class='score loser-score'>-</span>";
		$scoresHTML_B = "<span class='score loser-score'>-</span>";
	endif;
	
	// Obtener datos del complejo y cancha
	$nombreComplejo = datosComplejo($row['complejo'])['nombre'];
	$nombreCancha = cancha_id($row['cancha']);
	
	// Resumen para header - usar el score del set ganador (ya ordenado en $sets)
	$resumenGanador = 'A continuación';
	$claseEnJuego = ($row['en_juego'] == 'si') ? 'en-juego' : '';
	$textoEnJuego = ($row['en_juego'] == 'si') ? ' 🔴 EN JUEGO' : '';
	$clasePartido = 'pendiente';
	
	// Score del set ganador = primer elemento del array $sets ya ordenado
	$scoreGanadorA = isset($sets[0]) ? $sets[0]['a'] : 0;
	$scoreGanadorB = isset($sets[0]) ? $sets[0]['b'] : 0;
	
	if($winnerA == 'winner' && !empty($sets)):
		$temp1 = strip_tags($jugador1A);
		$temp2 = strip_tags($jugador1B);
		$lineas1 = explode("\n", trim($temp1));
		$lineas2 = explode("\n", trim($temp2));
		$nombre1 = mb_substr(trim($lineas1[0] ?? ''), 0, 12);
		$nombre2 = mb_substr(trim($lineas2[0] ?? ''), 0, 12);
		$resumenGanador = $nombre1 . ' / ' . $nombre2 . " ({$scoreGanadorA}-{$scoreGanadorB})" . $textoEnJuego;
		$clasePartido = 'finalizado';
	elseif($winnerB == 'winner' && !empty($sets)):
		$temp1 = strip_tags($jugador2A);
		$temp2 = strip_tags($jugador2B);
		$lineas1 = explode("\n", trim($temp1));
		$lineas2 = explode("\n", trim($temp2));
		$nombre1 = mb_substr(trim($lineas1[0] ?? ''), 0, 12);
		$nombre2 = mb_substr(trim($lineas2[0] ?? ''), 0, 12);
		$resumenGanador = $nombre1 . ' / ' . $nombre2 . " ({$scoreGanadorB}-{$scoreGanadorA})" . $textoEnJuego;
		$clasePartido = 'finalizado';
	elseif($row['en_juego'] == 'si'):
		$resumenGanador = 'A continuación' . $textoEnJuego;
		$clasePartido = '';
	endif;
	
	// Texto del header según estado
	$textoHeader = ($clasePartido == 'finalizado')
		? "<span class='summary'>{$resumenGanador}</span><span class='badge-finalizado'>FINALIZADO</span>"
		: "<span class='summary'>{$resumenGanador}</span>";
	
	$checkA = ($winnerA == 'winner') ? '✓ ' : '&nbsp;&nbsp;';
	$checkB = ($winnerB == 'winner') ? ' ✓' : '';
	
	$losCruces[$row['grupo']][$pos]="
	<div class='match-card {$claseEnJuego} {$clasePartido}'>
		<button class='match-header' onclick='toggleMatch(this)'>
			<span class='round'>{$prefijo}</span>
			<div class='info'>
				{$textoHeader}
			</div>
			<span class='chevron'>▼</span>
		</button>
		<div class='match-body'>
			<div class='match-row-layout'>
				<div class='team-left'>
					<span class='player-name {$winnerClassA}'><span class='check-mark'>{$checkA}</span>{$jugador1A}</span>
					<span class='player-name {$winnerClassA}'><span class='check-mark'>{$checkA}</span>{$jugador1B}</span>
				</div>
				<div class='scores-left'>{$scoresHTML_A}</div>
				<div class='vs-center'>vs</div>
				<div class='scores-right'>{$scoresHTML_B}</div>
				<div class='team-right'>
					<span class='player-name {$winnerClassB}'>{$jugador2A}<span class='check-mark-right'>{$checkB}</span></span>
					<span class='player-name {$winnerClassB}'>{$jugador2B}<span class='check-mark-right'>{$checkB}</span></span>
				</div>
			</div>
		</div>
	</div>";
	
	// ============================================
	// 🎨 FIN NUEVO DISEÑO VISUAL
	// ============================================
	// 🎨 FIN DISEÑO VISUAL
	// ============================================
	
	endif; // Fin if(isset($_GET['carga'])) else
	
	/*$losCruces[$row['grupo']][$pos]['ci1_b']=$row['ci1_b'];
	$losCruces[$row['grupo']][$pos]['ci2_a']=$row['ci2_a'];
	$losCruces[$row['grupo']][$pos]['ci2_b']=$row['ci2_b'];*/
	$pos++;
} 
endif; //con categoria

if(isset($_GET['debugs'])):
	echo "<pre>";
	print_r($losEquiposT);
	echo "<br>".__LINE__."<br>";
	exit;
endif;
?>
<?php
// Definir $h1 si no está definida (para URLs directas)
if(!isset($h1)):
	$h1 = "Seleccione una categoría";
	// Intentar obtener el nombre del evento si existe la función
	if(isset($evento) && function_exists('datosEvento')):
		$datosEvento = datosEvento($evento);
		if(isset($datosEvento['evento'])):
			$h1 = $datosEvento['evento'];
		endif;
	endif;
endif;
?>

<?php if(!isset($_GET['carga'])): ?>
<!-- Botones de navegación -->
<script>
// Capturar la query string ORIGINAL cuando carga la página
var queryStringOriginal = window.location.search;
</script>
<div id="stickyNav" style="text-align: center; margin-bottom: 1rem; padding: 0.75rem 0; background: #e5e7eb; z-index: 40;">
	<div style="display: inline-flex; gap: 0.75rem; flex-wrap: wrap; justify-content: center;">
		<a href="#" id="btn-informacion-link" style="background-color: white; color: #374151; border: 1px solid #d1d5db; font-size: 0.875rem; font-weight: 600; padding: 0.5rem 1.25rem; border-radius: 0.5rem; box-shadow: 0 1px 2px rgba(0,0,0,0.05); text-decoration: none; transition: all 0.3s;">
			Información
		</a>
		<a href="#" style="background-color: #2563eb; color: white; font-size: 0.875rem; font-weight: 600; padding: 0.5rem 1.25rem; border-radius: 0.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); text-decoration: none; display: inline-block; transition: all 0.3s;">
			Llaves
		</a>
	</div>
</div>
<style>
@media (max-width: 767px) {
  #stickyNav { position: sticky; top: 0; }
}
</style>
<script>
// Construir la URL de Información removiendo solo el parámetro categoria
var urlParams = new URLSearchParams(queryStringOriginal);
urlParams.delete('categoria');
var urlInformacion = 'grafico-llaves-v2.php?' + urlParams.toString();
document.getElementById('btn-informacion-link').href = urlInformacion;
</script>
<?php endif; ?>

<h2>
<?php echo $h1; ?>
</h2>

<?php if(!isset($_GET['carga'])): ?>
 <form method="get" name="ffiltrar" action="#informacion">
	<?php
	foreach($_GET as $key => $value):
		if($key != 'categoria'):
	?>
		<input type="hidden" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo htmlspecialchars($value); ?>">
	<?php
		endif;
	endforeach;
	?>
<input type="hidden" name="categoria" id="catHidden" value="<?php echo isset($laCategoria) ? $laCategoria : ''; ?>">
<style>
.cat-pills-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 8px; padding: 4px 0 12px; }
.cat-pills-grid button { width: 100%; overflow: hidden; text-overflow: ellipsis; }
@media (max-width: 767px) { .cat-pills-grid { grid-template-columns: repeat(3, 1fr); gap: 6px; } .cat-pills-grid button { font-size: 11px !important; padding: 7px 4px !important; } }
@media (max-width: 400px) { .cat-pills-grid { grid-template-columns: repeat(2, 1fr); } }
</style>
<div class="cat-pills-grid">
<?php
if(isset($lasCategorias) && count($lasCategorias) > 0):
	foreach ($lasCategorias as $categoria):
		$datosCategoria = datosCategoria($categoria);
		$isActive = (isset($laCategoria) && $categoria == $laCategoria);
		$activeStyle = $isActive 
			? "background: #374151; color: #fff; border-color: #374151;" 
			: "background: #fff; color: hsl(215, 14%, 40%); border-color: hsl(214, 25%, 75%);";
?>
	<button type="button" onclick="document.getElementById('catHidden').value='<?php echo $categoria; ?>'; document.ffiltrar.submit();" style="padding: 8px 6px; border-radius: 20px; font-size: 12.5px; font-weight: 500; border: 1.5px solid; cursor: pointer; white-space: nowrap; font-family: inherit; <?php echo $activeStyle; ?>"><?php echo $datosCategoria['categoria']; ?></button>
<?php
	endforeach;
else:
	echo "<span style='color: hsl(215, 14%, 50%); font-size: 13px;'>No hay categorías disponibles</span>";
endif;
?>
</div>
</form>
<?php else: ?>
	<!-- Modo backend: mostrar título de categoría -->
	<?php if(isset($laCategoria)): 
		$datosCategoria = datosCategoria($laCategoria);
	?>
	<h3 style="text-align: center; background: hsl(200, 45%, 50%); color: white; padding: 12px; border-radius: 8px; margin: 20px 0;">
		<?php echo $datosCategoria['categoria']; ?>
	</h3>
	<?php endif; ?>
<?php endif; ?>

<div style="padding: 1rem;">
<?php 
if($mostrar=='si'):
	?>

<?php if(!isset($_GET['carga'])): ?>
<!-- Tabs -->
<?php
$siguienteFaseTexto = '';
if(isset($laCategoria) && isset($evento)):
	$sqlFases = "SELECT DISTINCT g.grupo as textoGrupo, g.orden FROM _todosvstodos t INNER JOIN _p_grupos g ON g.id = t.grupo WHERE t.evento={$evento} AND t.categoria={$laCategoria} AND g.orden > 0 ORDER BY g.orden ASC";
	$resFases = $mysqli2->query($sqlFases);
	$fasesOrden = [];
	if($resFases): while($rF = $resFases->fetch_assoc()): $fasesOrden[$rF['orden']] = $rF['textoGrupo']; endwhile; endif;
	$ordenGrupo = 0;
	foreach($fasesOrden as $ord => $txt):
		$txtUp = strtoupper($txt);
		if(strpos($txtUp, 'GRUPO') !== false || strpos($txtUp, 'RONDA') !== false): $ordenGrupo = $ord; endif;
	endforeach;
	foreach($fasesOrden as $ord => $txt):
		if($ord > $ordenGrupo):
			$txtUp = strtoupper($txt);
			if(strpos($txtUp, '16VOS') !== false) $siguienteFaseTexto = '16vos';
			elseif(strpos($txtUp, '8VOS') !== false) $siguienteFaseTexto = '8vos';
			elseif(strpos($txtUp, 'CUARTOS') !== false) $siguienteFaseTexto = 'QF';
			elseif(strpos($txtUp, 'SEMI') !== false) $siguienteFaseTexto = 'SF';
			elseif(strpos($txtUp, 'FINAL') !== false) $siguienteFaseTexto = 'Final';
			else $siguienteFaseTexto = mb_substr($txt, 0, 6, 'UTF-8');
			break;
		endif;
	endforeach;
endif;
?>
<div class="tabs">
  <button class="tab-btn active" onclick="switchTab('resultados')">Resultados</button>
  <?php if(!empty($siguienteFaseTexto)): ?>
  <button class="tab-btn" onclick="switchTab('clasificacion')"><?php echo $siguienteFaseTexto; ?> →</button>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- Tab: Resultados -->
<?php if(!isset($_GET['carga'])): ?>
<div id="tab-resultados" class="tab-content active">
<?php else: ?>
<div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px; width: 100%;">
<?php
// 📊 PRE-CALCULAR CLASIFICACIÓN para botones modales
if(isset($laCategoria)):
	$sqlClasif="SELECT * FROM tabla_auxiliar WHERE id_categoria={$laCategoria} AND id_evento={$evento} AND id_grupo BETWEEN 1 AND 12 ORDER BY id_grupo ASC";
	$resClasif=$mysqli2->query($sqlClasif);
	$filas_clasif = [];
	if($resClasif): while($rc = $resClasif->fetch_assoc()): $filas_clasif[$rc['id_grupo']][] = $rc; endwhile; endif;
	$infoClasif = [];
	foreach($filas_clasif as $id_grp => $eqs_grp):
		$conf_c = [];
		$rcf = $mysqli2->query("SELECT ci1_a,ci1_b,ci2_a,ci2_b,rusultado_equipo1 as r11,resultado_equipo2 as r12,resultado2_equipo1 as r21,resultado2_equipo2 as r22,resultado3_equipo1 as r31,resultado3_equipo2 as r32 FROM _todosvstodos WHERE grupo={$id_grp} AND evento={$evento} AND categoria={$laCategoria} AND tipo_referencia='no'");
		if($rcf): while($pc = $rcf->fetch_assoc()):
			if($pc['r11']==0 && $pc['r12']==0 && $pc['r21']==0 && $pc['r22']==0 && $pc['r31']==0 && $pc['r32']==0) continue;
			$sa=0;$sb=0;
			if($pc['r11']>0||$pc['r12']>0){$pc['r11']>$pc['r12']?$sa++:$sb++;}
			if($pc['r21']>0||$pc['r22']>0){$pc['r21']>$pc['r22']?$sa++:$sb++;}
			if($pc['r31']>0||$pc['r32']>0){$pc['r31']>$pc['r32']?$sa++:$sb++;}
			$ka=$pc['ci1_a'].'-'.$pc['ci1_b']; $kb=$pc['ci2_a'].'-'.$pc['ci2_b'];
			if($sa!=$sb){if($sa>$sb){$conf_c[$ka][$kb]=1;$conf_c[$kb][$ka]=0;}else{$conf_c[$kb][$ka]=1;$conf_c[$ka][$kb]=0;}}
		endwhile; endif;
		foreach($eqs_grp as &$eq):$eq['_clave']=$eq['ci1_a'].'-'.$eq['ci1_b'];$eq['_conf']=isset($conf_c[$eq['_clave']])?$conf_c[$eq['_clave']]:[];endforeach;unset($eq);
		uasort($eqs_grp,function($a,$b){return $b['ganados']-$a['ganados'];});$eqs_grp=array_values($eqs_grp);
		$gpg2=[];foreach($eqs_grp as $i2=>$eq2){$gpg2[$eq2['ganados']][]=$i2;}$ord2=[];krsort($gpg2);
		foreach($gpg2 as $ixs2):
			if(count($ixs2)==1){$ord2[]=$eqs_grp[$ixs2[0]];}
			elseif(count($ixs2)==2){$e0=$eqs_grp[$ixs2[0]];$e1=$eqs_grp[$ixs2[1]];if(isset($e0['_conf'][$e1['_clave']])){if($e0['_conf'][$e1['_clave']]==1){$e0['_cd']=true;$ord2[]=$e0;$ord2[]=$e1;}else{$e1['_cd']=true;$ord2[]=$e1;$ord2[]=$e0;}}else{$sg0=$e0['g+']-$e0['g-'];$sg1=$e1['g+']-$e1['g-'];if($sg0>=$sg1){$ord2[]=$e0;$ord2[]=$e1;}else{$ord2[]=$e1;$ord2[]=$e0;}}}
			else{
				$sub2=array_map(fn($i)=>$eqs_grp[$i],$ixs2);
				usort($sub2,function($a,$b){$sgA=$a['g+']-$a['g-'];$sgB=$b['g+']-$b['g-'];if($sgB!=$sgA) return $sgB-$sgA;return $b['g+']-$a['g+'];});
				// Sub-desempatar por confronto directo entre los que tienen mismo SG
				$final_v=[];$iv=0;$tv=count($sub2);
				while($iv<$tv){
					$blq=[$sub2[$iv]];
					$sgR=(int)$sub2[$iv]['g+']-(int)$sub2[$iv]['g-'];
					$jv=$iv+1;
					while($jv<$tv){
						$sgN=(int)$sub2[$jv]['g+']-(int)$sub2[$jv]['g-'];
						if($sgN===$sgR){$blq[]=$sub2[$jv];$jv++;}
						else break;
					}
					if(count($blq)==2){
						$b0=$blq[0];$b1=$blq[1];
						if(isset($b0['_conf'][$b1['_clave']])){
							if($b0['_conf'][$b1['_clave']]==1){$b0['_cd']=true;$final_v[]=$b0;$final_v[]=$b1;}
							else{$b1['_cd']=true;$final_v[]=$b1;$final_v[]=$b0;}
						}else{foreach($blq as $bb)$final_v[]=$bb;}
					}else{foreach($blq as $bb)$final_v[]=$bb;}
					$iv=$jv;
				}
				foreach($final_v as $e)$ord2[]=$e;
			}
		endforeach;
		$pos=0;
		foreach($ord2 as $r2):
			$elSG=abs($r2['g+'])-abs($r2['g-']);
			$infoClasif[$id_grp][$pos]=['pos'=>$r2['puntos'],'pj'=>$elSG,'pg'=>$r2['ganados'],'pts'=>$r2['puntos'],'g+'=>$r2['g+'],'ci1_a'=>$r2['ci1_a'],'ci1_b'=>$r2['ci1_b'],'_cd'=>!empty($r2['_cd'])];
			$pos++;
		endforeach;
	endforeach;
endif;
?>
<?php
// Recorremos por filas (1-10) y columnas (1-2) como en el original
for($i=1; $i<=10; $i++):
	// Verificar si hay contenido en esta fila
	if(isset($contenido[$i])):
		
		// Columna 1 (izquierda) - En modo público solo grupos (id <= 12)
		if(isset($contenido[$i][1]) && isset($losCruces[$contenido[$i][1]]) && (isset($_GET['carga']) || $contenido[$i][1] <= 12)):
			$elTgrupo = $contenido[$i][1];
?>
<div style="width: 100%;">
	<div style="clear:both; display: flex; align-items: center; justify-content: space-between; margin: 10px 0;">
		<span style="font-weight: 700; font-size: 16px;"><?php echo $textoGrupos[$elTgrupo]; ?></span>
		<?php if(isset($infoClasif[$elTgrupo])): ?>
		<button onclick="openModalClasif('modal-clasif-<?php echo $elTgrupo; ?>')" style="display: inline-flex; align-items: center; gap: 4px; background: none; border: 1px solid hsl(214, 25%, 75%); border-radius: 6px; padding: 4px 10px; font-size: 12px; color: hsl(215, 14%, 50%); cursor: pointer; font-family: inherit;">
			&#9776; Clasificación
		</button>
		<?php endif; ?>
	</div>
	<?php
	foreach($losCruces[$elTgrupo] as $eldatos):
	?>
	<div style="margin-bottom: 12px;">
	<?php echo $eldatos; ?>
	</div>
	<?php
	endforeach;
	?>
</div>
<?php
		endif;
		
		// Columna 2 (derecha) - En modo público solo grupos (id <= 12)
		if(isset($contenido[$i][2]) && isset($losCruces[$contenido[$i][2]]) && (isset($_GET['carga']) || $contenido[$i][2] <= 12)):
			$elTgrupo = $contenido[$i][2];
?>
<div style="width: 100%;">
	<div style="clear:both; display: flex; align-items: center; justify-content: space-between; margin: 10px 0;">
		<span style="font-weight: 700; font-size: 16px;"><?php echo $textoGrupos[$elTgrupo]; ?></span>
		<?php if(isset($infoClasif[$elTgrupo])): ?>
		<button onclick="openModalClasif('modal-clasif-<?php echo $elTgrupo; ?>')" style="display: inline-flex; align-items: center; gap: 4px; background: none; border: 1px solid hsl(214, 25%, 75%); border-radius: 6px; padding: 4px 10px; font-size: 12px; color: hsl(215, 14%, 50%); cursor: pointer; font-family: inherit;">
			&#9776; Clasificación
		</button>
		<?php endif; ?>
	</div>
	<?php
	foreach($losCruces[$elTgrupo] as $eldatos):
	?>
	<div style="margin-bottom: 12px;">
	<?php echo $eldatos; ?>
	</div>
	<?php
	endforeach;
	?>
</div>
<?php
		endif;
		
	endif;
endfor;
?>
</div>

<?php
// 🏆 CUADRO DE CAMPEONES - Movido al tab bracket
?>

<?php
// ============================================================
// 📊 MODALES DE CLASIFICACIÓN POR GRUPO
// ============================================================
if(isset($laCategoria) && isset($infoClasif)):

	// Generar modales
	foreach($losGrupos as $elgrupo):
		if(isset($infoClasif[$elgrupo]) && $elgrupo <= 12):
?>
<div class="modal-clasif-overlay" id="modal-clasif-<?php echo $elgrupo; ?>" onclick="if(event.target===this) closeModalClasif(this.id)">
	<div class="modal-clasif-box">
		<div class="modal-clasif-header">
			<span><?php echo $textoGrupos[$elgrupo]; ?> — Clasificación</span>
			<button onclick="closeModalClasif('modal-clasif-<?php echo $elgrupo; ?>')" style="background:none; border:none; color:#fff; font-size:20px; cursor:pointer; padding:0 4px;">✕</button>
		</div>
		<table><thead><tr><th><b>Pos</b></th><th><b>Equipo</b></th><th class='center'><b>SG</b></th><th class='center'><b>PG</b></th><th class='center'><b>PTS</b></th></tr></thead><tbody>
		<?php $lp=0; foreach($infoClasif[$elgrupo] as $cd): $lp++; ?>
			<tr>
				<td><strong><?php echo $lp; ?></strong></td>
				<td><span class='team-name'><?php if(strlen($cd['ci1_a'])>0) echo user_ci2($cd['ci1_a']); ?></span><br><span class='team-name'><?php if(strlen($cd['ci1_b'])>0) echo user_ci2($cd['ci1_b']); ?></span></td>
				<td class='center'><?php echo $cd['pj'] ?> <span class="sub"><?php echo $cd['g+'] ?></span></td>
				<td class='center'><?php echo $cd['pg']; if(!empty($cd['_cd'])) echo " <span style='color:#e67e22;font-size:14px;font-weight:bold;'>⚡</span>"; ?></td>
				<td class='<?php echo ($cd['pts'] >= 0) ? "pts-pos" : "pts-neg"; ?>'><?php echo $cd['pts'] ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody></table>
	</div>
</div>
<?php
		endif;
	endforeach;
endif;
?>

</div><!-- Cierre tab-resultados -->

<?php if(!isset($_GET['carga'])): ?>
<!-- Tab: Bracket Eliminatorio -->
<div id="tab-clasificacion" class="tab-content">
<?php
if(isset($laCategoria)):
	$fasesEliminatorias = [
		32 => ['nombre' => '16vos de final', 'prefijo' => '16.', 'orden_visual' => 1],
		26 => ['nombre' => '8vos de final',  'prefijo' => '8.',  'orden_visual' => 2],
		13 => ['nombre' => 'Cuartos de final','prefijo' => 'C',  'orden_visual' => 3],
		15 => ['nombre' => 'Semifinal',       'prefijo' => 'S',  'orden_visual' => 4],
		18 => ['nombre' => 'Final',           'prefijo' => 'F',  'orden_visual' => 5],
		19 => ['nombre' => '3er puesto',      'prefijo' => '3P', 'orden_visual' => 6],
	];
	$sqlBracket = "SELECT t.id, t.grupo, t.partido_nro as partidoNro, t.ci1_a, t.ci1_b, t.ci2_a, t.ci2_b, t.rusultado_equipo1 as r11, t.resultado_equipo2 as r12, t.resultado2_equipo1 as r21, t.resultado2_equipo2 as r22, t.resultado3_equipo1 as r31, t.resultado3_equipo2 as r32, t.tipo_referencia, t.ref_tipo_regustado1, t.ref_tipo_regustado2, t.ref_etiqueta1, t.ref_etiqueta2, t.en_juego, date_format(t.fecha,'%d/%m') as fecha, t.hora, g.grupo as textoGrupo FROM _todosvstodos t INNER JOIN _p_grupos g ON g.id = t.grupo WHERE t.evento = {$evento} AND t.categoria = {$laCategoria} AND t.grupo IN (32, 26, 13, 15, 18, 19) ORDER BY g.orden ASC, t.partido_nro ASC";
	$resBracket = $mysqli2->query($sqlBracket);
	$partidosPorFase = [];
	if($resBracket): while($bp = $resBracket->fetch_assoc()): $partidosPorFase[$bp['grupo']][] = $bp; endwhile; endif;

	if(!empty($partidosPorFase)):
		$fasesConDatos = [];
		foreach($fasesEliminatorias as $idFase => $datosFase):
			if(isset($partidosPorFase[$idFase])): $fasesConDatos[] = ['id' => $idFase, 'datos' => $datosFase, 'partidos' => $partidosPorFase[$idFase]]; endif;
		endforeach;
?>
<div class="bracket-wrapper">
<div class="bracket-container">
<?php
		$totalFases = count($fasesConDatos);
		foreach($fasesConDatos as $faseIdx => $fase):
			$idFase = $fase['id']; $datosFase = $fase['datos']; $partidos = $fase['partidos'];
?>
<div class="bracket-ronda" id="bracket-col-<?php echo $idFase; ?>">
	<div class="bracket-ronda-header"><?php echo $datosFase['nombre']; ?></div>
	<div class="bracket-ronda-body" id="bracket-body-<?php echo $idFase; ?>">
<?php foreach($partidos as $bp):
	$sA=0;$sB=0;
	if($bp['r11']>0||$bp['r12']>0){$bp['r11']>$bp['r12']?$sA++:$sB++;}
	if($bp['r21']>0||$bp['r22']>0){$bp['r21']>$bp['r22']?$sA++:$sB++;}
	if($bp['r31']>0||$bp['r32']>0){$bp['r31']>$bp['r32']?$sA++:$sB++;}
	$hayResultado=($bp['r11']>0||$bp['r12']>0||$bp['r21']>0||$bp['r22']>0||$bp['r31']>0||$bp['r32']>0);
	$hayGanador=($sA!=$sB&&$hayResultado);$ganaA=($sA>$sB);

	// Auto-ganador si hay Bye Bye: el rival real gana automáticamente 1-0
	$esByeBye = false;
	if(!$hayGanador):
		$byeEnA = ($bp['ref_tipo_regustado1']==3);
		$byeEnB = ($bp['ref_tipo_regustado2']==3);
		if($byeEnA && ($bp['ci2_a']>0)):
			// Bye en equipo 1, gana equipo 2
			$hayGanador=true; $ganaA=false; $esByeBye=true;
		elseif($byeEnB && ($bp['ci1_a']>0)):
			// Bye en equipo 2, gana equipo 1
			$hayGanador=true; $ganaA=true; $esByeBye=true;
		endif;
	endif;

	// Score para mostrar
	$scoreShowA='-';$scoreShowB='-';
	if($esByeBye):
		$scoreShowA=$ganaA?'1':'0'; $scoreShowB=$ganaA?'0':'1';
	elseif($bp['r11']>0||$bp['r12']>0):
		$scoreShowA=$bp['r11'];$scoreShowB=$bp['r12'];
	elseif($bp['r21']>0||$bp['r22']>0):
		$scoreShowA=$bp['r21'];$scoreShowB=$bp['r22'];
	elseif($bp['r31']>0||$bp['r32']>0):
		$scoreShowA=$bp['r31'];$scoreShowB=$bp['r32'];
	endif;
	$prefijoBk=$datosFase['prefijo'];if($idFase!=18&&$idFase!=19)$prefijoBk.=$bp['partidoNro'];
	if($bp['ci1_a']>0):$j1=strip_tags(user_ci2($bp['ci1_a']));$j1b=($bp['ci1_b']>0)?strip_tags(user_ci2($bp['ci1_b'])):'';
	elseif($bp['ref_tipo_regustado1']==3):$j1='Bye Bye';$j1b='';
	elseif($bp['ref_etiqueta1']>0):$rGb=$mysqli2->query("SELECT grupo FROM _p_grupos WHERE id={$bp['ref_etiqueta1']}");$rGbr=$rGb->fetch_assoc();$rRefb=$mysqli2->query("SELECT referencia FROM _referencia_etiquetas WHERE id={$bp['ref_tipo_regustado1']}");$rRefbr=$rRefb->fetch_assoc();$j1=$rRefbr['referencia'].' '.$rGbr['grupo'];$j1b='';
	else:$j1='?';$j1b='';endif;
	if($bp['ci2_a']>0):$j2=strip_tags(user_ci2($bp['ci2_a']));$j2b=($bp['ci2_b']>0)?strip_tags(user_ci2($bp['ci2_b'])):'';
	elseif($bp['ref_tipo_regustado2']==3):$j2='Bye Bye';$j2b='';
	elseif($bp['ref_etiqueta2']>0):$rGb=$mysqli2->query("SELECT grupo FROM _p_grupos WHERE id={$bp['ref_etiqueta2']}");$rGbr=$rGb->fetch_assoc();$rRefb=$mysqli2->query("SELECT referencia FROM _referencia_etiquetas WHERE id={$bp['ref_tipo_regustado2']}");$rRefbr=$rRefb->fetch_assoc();$j2=$rRefbr['referencia'].' '.$rGbr['grupo'];$j2b='';
	else:$j2='?';$j2b='';endif;
	$resumenBk='Pendiente';
	if($hayGanador):$nomGan=$ganaA?explode("\n",$j1)[0]:explode("\n",$j2)[0];$sgH=$ganaA?$scoreShowA:$scoreShowB;$spH=$ganaA?$scoreShowB:$scoreShowA;$resumenBk=mb_substr(trim($nomGan),0,10,'UTF-8')." ({$sgH}-{$spH})";endif;
	$cardClass='bracket-card';$hdClass='bracket-hd';$badgeHtml='';
	if($bp['en_juego']=='si'):$cardClass.=' bracket-card-enjuego';$hdClass.=' bracket-hd-enjuego';$badgeHtml="<span class='bk-badge-ej'>EN JUEGO</span>";
	elseif($hayGanador):$badgeHtml="<span class='bk-badge'>FIN</span>";
	else:$hdClass.=' bracket-hd-pend';endif;
	$rowA=$hayGanador&&$ganaA?' bracket-row-winner':'';$rowB=$hayGanador&&!$ganaA?' bracket-row-winner':'';
	$scClsA=$hayGanador&&$ganaA?'bk-sc bk-sc-win':'bk-sc bk-sc-lose';$scClsB=$hayGanador&&!$ganaA?'bk-sc bk-sc-win':'bk-sc bk-sc-lose';
	$scoreA=$scoreShowA;$scoreB=$scoreShowB;
	$j1StyleCls=($bp['ci1_a']==0&&$bp['ref_tipo_regustado1']!=3)?' bracket-pend-nm':'';
	$j2StyleCls=($bp['ci2_a']==0&&$bp['ref_tipo_regustado2']!=3)?' bracket-pend-nm':'';
?>
		<div class="<?php echo $cardClass; ?>">
			<div class="<?php echo $hdClass; ?>"><span class="bk-pf"><?php echo $prefijoBk; ?></span><span class="bk-inf"><?php echo $resumenBk; ?></span><?php echo $badgeHtml; ?></div>
			<div class="bracket-row<?php echo $rowA; ?>"><div class="bk-nm"><div class="bk-na<?php echo $j1StyleCls; ?>"><?php echo $j1; ?></div><?php if($j1b): ?><div class="bk-nb"><?php echo $j1b; ?></div><?php endif; ?></div><div class="<?php echo $scClsA; ?>"><?php echo $scoreA; ?></div></div>
			<div class="bracket-row<?php echo $rowB; ?>"><div class="bk-nm"><div class="bk-na<?php echo $j2StyleCls; ?>"><?php echo $j2; ?></div><?php if($j2b): ?><div class="bk-nb"><?php echo $j2b; ?></div><?php endif; ?></div><div class="<?php echo $scClsB; ?>"><?php echo $scoreB; ?></div></div>
			<?php if($bp['fecha']): ?><div class="bracket-ft"><?php echo $bp['fecha']; ?><?php if($bp['hora']) echo ' '.$bp['hora']; ?></div><?php endif; ?>
		</div>
<?php endforeach; // partidos ?>
	</div>
</div>
<?php
			if($faseIdx < $totalFases - 1 && $fasesConDatos[$faseIdx + 1]['id'] != 19 && $idFase != 19):
?><div class="bracket-conn" id="bracket-conn-<?php echo $idFase; ?>-<?php echo $fasesConDatos[$faseIdx + 1]['id']; ?>"><svg></svg></div><?php
			elseif($faseIdx < $totalFases - 1): echo "<div style='flex: 0 0 12px;'></div>"; endif;
		endforeach;
?>
<?php
// Cerrar bracket-container y bracket-wrapper
?>
</div></div>
<?php
// 🏆 Generar div de campeones oculto — JS lo posiciona debajo de la Final
$sqlFC="SELECT t.ci1_a, t.ci1_b, t.ci2_a, t.ci2_b, t.rusultado_equipo1 as r1, t.resultado_equipo2 as r2, t.resultado2_equipo1 as r21, t.resultado2_equipo2 as r22, t.resultado3_equipo1 as r31, t.resultado3_equipo2 as r32 FROM _todosvstodos t WHERE t.evento={$evento} AND t.categoria={$laCategoria} AND t.grupo=18 AND (t.rusultado_equipo1>0 OR t.resultado_equipo2>0 OR t.resultado2_equipo1>0 OR t.resultado2_equipo2>0) LIMIT 1";
$rFC=$mysqli2->query($sqlFC);
if($rFC && $rFC->num_rows>0):
	$dFC=$rFC->fetch_assoc();
	$sfA=0;$sfB=0;
	if($dFC['r1']>0||$dFC['r2']>0){$dFC['r1']>$dFC['r2']?$sfA++:$sfB++;}
	if($dFC['r21']>0||$dFC['r22']>0){$dFC['r21']>$dFC['r22']?$sfA++:$sfB++;}
	if($dFC['r31']>0||$dFC['r32']>0){$dFC['r31']>$dFC['r32']?$sfA++:$sfB++;}
	if($sfA!=$sfB):
		$gNC=function($ci) use ($mysqli2){if($ci==0)return '';$r=$mysqli2->query("SELECT nombre,apellido FROM _p_usuarios WHERE ci={$ci}");if(!$r)return '';$u=$r->fetch_assoc();$n=explode(' ',trim($u['nombre']));$a=explode(' ',trim($u['apellido']));$ap=(strlen(trim($a[0]))<4)?$u['apellido']:$a[0];return mb_strtoupper($n[0],'UTF-8').' '.mb_strtoupper($ap,'UTF-8');};
		$cA=$sfA>$sfB?$gNC($dFC['ci1_a']):$gNC($dFC['ci2_a']);
		$cB=$sfA>$sfB?$gNC($dFC['ci1_b']):$gNC($dFC['ci2_b']);
?>
<div id="campeonesCard" style="display:none; border: 2px solid #c9a227; border-radius: 8px; padding: 10px 8px; text-align: center; background: white; margin: 6px 3px 0; box-shadow: 0 0 10px rgba(201,162,39,0.15);">
	<div style="font-size: 1rem; margin-bottom: 2px;">🏆</div>
	<div style="font-size: 9px; font-weight: 800; letter-spacing: 0.12em; color: #c9a227; margin-bottom: 4px;">CAMPEONES</div>
	<div style="font-size: 10px; font-weight: 600; color: #1a1a1a; line-height: 1.5; text-transform: uppercase;"><?php echo $cA; ?><br><?php echo $cB; ?></div>
</div>
<?php
	endif;
endif;
?>
<script>
function drawBracketLines(){var cs=document.querySelectorAll('.bracket-conn');cs.forEach(function(conn){var svg=conn.querySelector('svg');if(!svg)return;svg.innerHTML='';var ids=conn.id.replace('bracket-conn-','').split('-');var fb=document.getElementById('bracket-body-'+ids[0]);var tb=document.getElementById('bracket-body-'+ids[1]);if(!fb||!tb)return;var fc=fb.querySelectorAll('.bracket-card');var tc=tb.querySelectorAll('.bracket-card');var cr=conn.getBoundingClientRect();var nf=fc.length,nt=tc.length;for(var i=0;i<nt&&i*2+1<nf;i++){var c1=fc[i*2],c2=fc[i*2+1],ct=tc[i];if(!c1||!c2||!ct)continue;var r1=c1.getBoundingClientRect(),r2=c2.getBoundingClientRect(),rt=ct.getBoundingClientRect();var y1=Math.round(r1.top+r1.height/2-cr.top),y2=Math.round(r2.top+r2.height/2-cr.top),yt=Math.round(rt.top+rt.height/2-cr.top);var w=cr.width,mx=Math.round(w/2);function ml(x1,yl1,x2,yl2){var l=document.createElementNS('http://www.w3.org/2000/svg','line');l.setAttribute('x1',x1);l.setAttribute('y1',yl1);l.setAttribute('x2',x2);l.setAttribute('y2',yl2);svg.appendChild(l);}ml(0,y1,mx,y1);ml(0,y2,mx,y2);ml(mx,y1,mx,y2);ml(mx,yt,w,yt);}if(nf==1&&nt==1){var s=fc[0],t=tc[0];var rs=s.getBoundingClientRect(),rt2=t.getBoundingClientRect();var ys=Math.round(rs.top+rs.height/2-cr.top),yt2=Math.round(rt2.top+rt2.height/2-cr.top);var l=document.createElementNS('http://www.w3.org/2000/svg','line');l.setAttribute('x1',0);l.setAttribute('y1',ys);l.setAttribute('x2',cr.width);l.setAttribute('y2',yt2);svg.appendChild(l);}});}

// Posicionar campeones como columna al lado de la Final
function positionCampeones() {
  var card = document.getElementById('campeonesCard');
  if (!card) return;
  var finalCol = document.getElementById('bracket-col-18');
  if (!finalCol) return;
  // Crear una nueva columna bracket-ronda
  var col = document.createElement('div');
  col.className = 'bracket-ronda';
  col.style.cssText = 'flex: 0 0 165px; display: flex; flex-direction: column;';
  // Spacer para header
  var hdr = document.createElement('div');
  hdr.className = 'bracket-ronda-header';
  hdr.innerHTML = '&nbsp;';
  col.appendChild(hdr);
  // Body que centra verticalmente
  var body = document.createElement('div');
  body.className = 'bracket-ronda-body';
  body.style.cssText = 'display: flex; flex-direction: column; justify-content: space-around; flex: 1;';
  card.style.display = 'block';
  body.appendChild(card);
  col.appendChild(body);
  // Insertar después de la columna Final
  finalCol.insertAdjacentElement('afterend', col);
}

setTimeout(function(){ drawBracketLines(); positionCampeones(); }, 200);
window.addEventListener('resize', drawBracketLines);
</script>
<?php
	else: echo "<div style='text-align: center; padding: 40px 16px; color: hsl(215, 14%, 50%); font-size: 14px;'>No hay partidos eliminatorios para esta categoría</div>";
	endif;
endif;
?>
</div><!-- Cierre tab-clasificacion (bracket) -->
<?php endif; // Cierre if(!isset($_GET['carga'])) ?>

<?php
echo  "</div>";
if(!isset($mostrado)):
if(isset($_GET['tabla'])):

	$sqlD2="SELECT *
from  
 tabla_auxiliar
 WHERE id_grupo BETWEEN 1 AND 12
 ORDER BY id_grupo, pos DESC
 ";
 
$resultado2=$mysqli2->query($sqlD2); 
 
 $pos=0;
while($row2 = $resultado2->fetch_assoc()){
	$indice=$row2['id_grupo'];
	$info[$indice][$pos]['pos']=$row2['pos'];
	$info[$indice][$pos]['pj']=$row2['pj'];
	$info[$indice][$pos]['pg']=$row2['pg'];
	$info[$indice][$pos]['pts']=$row2['pts'];
	$info[$indice][$pos]['ci1_a']=$row2['ci1_a'];
	$info[$indice][$pos]['ci1_b']=$row2['ci1_b'];
	$info[$indice][$pos]['id_grupo']=$row2['id_grupo'];
	$pos++;
} 
 
	
	 //echo "<pre>";
	 //print_r($cadaEquipo);
	 //print_r($losEquiposT);
	 //echo "</pre>";
	 echo "<div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 16px; width: 100%;'>"; //completo
	 foreach($losGrupos as $elgrupo):
		
		if(isset($info[$elgrupo])):
		 echo "<div class='standings'>";
		 echo "<div class='standings-header'>".$textoGrupos[$elgrupo]." — Clasificación</div>";	 
			foreach($info[$elgrupo] as $cadaDato):
				$mostrado=true;
				?>
				<table>
					<thead>
						<tr>
							<th>Pos</th>
							<th>Equipo</th>
							<th class='center'>PJ</th>
							<th class='center'>PG</th>
							<th class='center'>PTS</th>
						</tr>
					</thead>
					<tbody>
				<?php  
					if(isset($cadaDato['pos'])):
					?>
						
						<tr>
						<td><strong><?php echo $cadaDato['pos'] ?></strong></td>
						<td>
							<span class='team-name'><?php echo user_ci2($cadaDato['ci1_a']); ?></span><br>
							<span class='team-name'><?php echo user_ci2($cadaDato['ci1_b']); ?></span>
						</td>
						<td class='center'><?php echo $cadaDato['pj'] ?></td>
						<td class='center'><?php echo $cadaDato['pg'] ?></td>
						<td class='<?php echo ($cadaDato['pts'] >= 0) ? "pts-pos" : "pts-neg"; ?>'><?php echo $cadaDato['pts'] ?></td>
						</tr>
				<?php endif;   ?>
				</tbody>
				</table>
				 
				<?php endforeach;
				echo "</div>";   
		endif;
	 endforeach;
	
	echo "</div>"; //completo
endif;
endif;

?>
<div style="border:solid red 2px;margin:2px; display:none"><!-- cruces -->
<?php
foreach($losGrupos as $elgrupo):
?>
<div style="border:solid gold 2px;margin:2px"><!-- cada grupo -->
<?php echo $textoGrupos[$elgrupo]; ?><hr>
<?php
foreach($losCruces[$elgrupo] as $eldatos): //los competidores
?>
<div  style="border:solid black 2px;margin:2px">
<?php echo $eldatos; ?>
</div>
<?php
endforeach; //los competidores
?>
</div><!-- cada grupo -->
<?php
endforeach; //los grupos
?>
</div><!-- cruces -->
<?php 
endif;
?><?php
if(isset($pagina))
  include 'logica/grafico_styles.php';
else
  include 'grafico_styles.php';
?>
</div>
</div>