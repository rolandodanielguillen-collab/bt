<?php
date_default_timezone_set('America/Asuncion');
if(isset($pagina)):
include_once "db/conection.inc.php";
include_once "funciones.php";
elseif(!isset($pagina)):
	include_once "../db/conection.inc.php";
	include_once "../funciones.php";
endif;

// Detectar evento: priorizar parámetro GET, luego buscar recientes, luego activos
if(isset($_GET['evento']) && abs($_GET['evento'])>0):
	$eventosActivos=[abs($_GET['evento'])];
	echo "<div><b>Evento forzado por GET: {$eventosActivos[0]}</b></div>";
else:
	$slq0="SELECT DISTINCT evento
	FROM `_todosvstodos`
	WHERE TIMESTAMPDIFF(MINUTE, carga_resultado, NOW()) <=60
	ORDER BY evento ASC";
	echo "<div>{$slq0}</div>";
	$resultado0=$mysqli2->query($slq0);
	$eventosActivos=[];
	while($row0=$resultado0->fetch_assoc()):
		$eventosActivos[]=$row0['evento'];
	endwhile;
	if(count($eventosActivos)==0):
		// fallback: buscar eventos con partidos pendientes de propagar
		$rFb=$mysqli2->query("SELECT DISTINCT evento FROM _todosvstodos WHERE tipo_referencia='si' AND grupo>12 ORDER BY evento DESC LIMIT 3");
		while($fb=$rFb->fetch_assoc()) $eventosActivos[]=$fb['evento'];
	endif;
endif;

if(count($eventosActivos)==0){ echo "Sin eventos activos"; exit; }

foreach($eventosActivos as $idEvento):
echo "<hr><div><b>Procesando evento: {$idEvento}</b></div>";

// ============================================================
// PASO 1: CLASIFICACIÓN — recalcular tabla_auxiliar desde resultados de grupo
// ============================================================
$rCats=$mysqli2->query("SELECT DISTINCT categoria FROM _todosvstodos WHERE evento={$idEvento} AND grupo<13 AND (rusultado_equipo1>0 OR resultado_equipo2>0 OR resultado3_equipo1>0 OR resultado3_equipo2>0)");
while($rcRow=$rCats->fetch_assoc()):
	$cat_aux=(int)$rcRow['categoria'];
	$ev_aux=(int)$idEvento;
	echo "<div>Clasificacion cat {$cat_aux}</div>";

	$mysqli2->query("UPDATE tabla_auxiliar SET jugados=0,`g+`=0,`g-`=0,sg=0,ganados=0,puntos=0,la_posicion=0 WHERE id_evento={$ev_aux} AND id_categoria={$cat_aux}");

	$resAux=$mysqli2->query("SELECT id,ci1_a,ci1_b,ci2_a,ci2_b,grupo,
		rusultado_equipo1,resultado_equipo2,resultado3_equipo1,resultado3_equipo2
		FROM _todosvstodos WHERE evento={$ev_aux} AND categoria={$cat_aux} AND grupo<13
		AND (rusultado_equipo1>0 OR resultado_equipo2>0 OR resultado3_equipo1>0 OR resultado3_equipo2>0)");
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
		$rCf=$mysqli2->query("SELECT ci1_a,ci1_b,ci2_a,ci2_b,rusultado_equipo1 as r11,resultado_equipo2 as r12,resultado3_equipo1 as r31,resultado3_equipo2 as r32 FROM _todosvstodos WHERE grupo={$idGrp} AND evento={$ev_aux} AND categoria={$cat_aux} AND tipo_referencia='no'");
		if($rCf) while($pC=$rCf->fetch_assoc()){if($pC['r11']==0&&$pC['r12']==0&&$pC['r31']==0&&$pC['r32']==0) continue;$sa2=0;$sb2=0;if($pC['r11']>0||$pC['r12']>0){$pC['r11']>$pC['r12']?$sa2++:$sb2++;}if($pC['r31']>0||$pC['r32']>0){$pC['r31']>$pC['r32']?$sa2++:$sb2++;}$kA2=$pC['ci1_a'].'-'.$pC['ci1_b'];$kB2=$pC['ci2_a'].'-'.$pC['ci2_b'];if($sa2!=$sb2){if($sa2>$sb2){$conf_aux[$kA2][$kB2]=1;$conf_aux[$kB2][$kA2]=0;}else{$conf_aux[$kB2][$kA2]=1;$conf_aux[$kA2][$kB2]=0;}}}
		foreach($eqs_aux as &$eq2){$eq2['_clave']=$eq2['ci1_a'].'-'.$eq2['ci1_b'];$eq2['_conf']=isset($conf_aux[$eq2['_clave']])?$conf_aux[$eq2['_clave']]:[];}unset($eq2);
		uasort($eqs_aux,function($a,$b){return $b['ganados']-$a['ganados'];});$eqs_aux=array_values($eqs_aux);
		$gpg2=[];foreach($eqs_aux as $i2=>$eq2){$gpg2[$eq2['ganados']][]=$i2;}$ord2=[];krsort($gpg2);
		foreach($gpg2 as $ixs2){
			if(count($ixs2)==1){$ord2[]=$eqs_aux[$ixs2[0]];}
			elseif(count($ixs2)==2){
				$e0=$eqs_aux[$ixs2[0]];$e1=$eqs_aux[$ixs2[1]];
				if(isset($e0['_conf'][$e1['_clave']])){if($e0['_conf'][$e1['_clave']]==1){$ord2[]=$e0;$ord2[]=$e1;}else{$ord2[]=$e1;$ord2[]=$e0;}}
				else{$sg0=$e0['g+']-$e0['g-'];$sg1=$e1['g+']-$e1['g-'];if($sg0>=$sg1){$ord2[]=$e0;$ord2[]=$e1;}else{$ord2[]=$e1;$ord2[]=$e0;}}
			} else {
				$sub2=array_map(fn($i)=>$eqs_aux[$i],$ixs2);
				usort($sub2,function($a,$b){$sgA=$a['g+']-$a['g-'];$sgB=$b['g+']-$b['g-'];if($sgB!=$sgA) return $sgB-$sgA;return $b['g+']-$a['g+'];});
				$final_p=[];$ip=0;$tp=count($sub2);
				while($ip<$tp){$blq=[$sub2[$ip]];$sgR=(int)$sub2[$ip]['g+']-(int)$sub2[$ip]['g-'];$jp=$ip+1;while($jp<$tp){$sgN=(int)$sub2[$jp]['g+']-(int)$sub2[$jp]['g-'];if($sgN===$sgR){$blq[]=$sub2[$jp];$jp++;}else break;}
				if(count($blq)==2){$b0=$blq[0];$b1=$blq[1];if(isset($b0['_conf'][$b1['_clave']])){if($b0['_conf'][$b1['_clave']]==1){$final_p[]=$b0;$final_p[]=$b1;}else{$final_p[]=$b1;$final_p[]=$b0;}}else{foreach($blq as $bb)$final_p[]=$bb;}}else{foreach($blq as $bb)$final_p[]=$bb;}$ip=$jp;}
				foreach($final_p as $e)$ord2[]=$e;
			}
		}
		$posAux=1;foreach($ord2 as $eq2){$mysqli2->query("UPDATE tabla_auxiliar SET la_posicion={$posAux} WHERE id={$eq2['id']}");$posAux++;}
	}
	echo "<div>Posiciones asignadas cat {$cat_aux}</div>";
endwhile;

// ============================================================
// PASO 2: PROPAGACIÓN grupos → eliminatoria (ref_tipo_regustado 4=1ro, 5=2do)
// ============================================================
	$slq01="SELECT *
	FROM `_todosvstodos`
	WHERE evento={$idEvento}
    AND grupo>12
    AND (ref_tipo_regustado1>3 OR ref_tipo_regustado2>3)";
	echo "<div>{$slq01}</div>";
	$resultado01=$mysqli2->query($slq01);
 	$i=0;
	while($row01 = $resultado01->fetch_assoc()):
		$idRV=$row01['id'];
		$PosAbuscarA=abs($row01['ref_tipo_regustado1'])-3;
		$PosAbuscarB=abs($row01['ref_tipo_regustado2'])-3;
		$idCategoria=$row01['categoria'];
		echo "<div>{$PosAbuscarA} / {$PosAbuscarB}</div>";
		$idGupoA=$row01['ref_etiqueta1'];
		$idGupoB=$row01['ref_etiqueta2'];
		echo "<div>{$idGupoA} / {$idGupoB}</div>";

		// Solo propagar si todos los partidos del grupo terminaron
		if($PosAbuscarA>0 && $idGupoA>0):
			$rSinA=$mysqli2->query("SELECT COUNT(*) as sinres FROM _todosvstodos
				WHERE grupo={$idGupoA} AND evento={$idEvento} AND categoria={$idCategoria}
				AND tipo_referencia='no'
				AND rusultado_equipo1=0 AND resultado_equipo2=0
				AND resultado3_equipo1=0 AND resultado3_equipo2=0");
			$sinResA=$rSinA->fetch_assoc()['sinres'];
			echo "<div>Grupo {$idGupoA} partidos sin resultado: {$sinResA}</div>";
			if($sinResA > 0):
				echo "<div><b>Grupo {$idGupoA} incompleto - no se propaga ci1</b></div>";
				$PosAbuscarA = 0;
			endif;
		endif;
		if($PosAbuscarB>0 && $idGupoB>0):
			$rSinB=$mysqli2->query("SELECT COUNT(*) as sinres FROM _todosvstodos
				WHERE grupo={$idGupoB} AND evento={$idEvento} AND categoria={$idCategoria}
				AND tipo_referencia='no'
				AND rusultado_equipo1=0 AND resultado_equipo2=0
				AND resultado3_equipo1=0 AND resultado3_equipo2=0");
			$sinResB=$rSinB->fetch_assoc()['sinres'];
			echo "<div>Grupo {$idGupoB} partidos sin resultado: {$sinResB}</div>";
			if($sinResB > 0):
				echo "<div><b>Grupo {$idGupoB} incompleto - no se propaga ci2</b></div>";
				$PosAbuscarB = 0;
			endif;
		endif;

		if($PosAbuscarA>0):
		$sqlP="SELECT *
		FROM `tabla_auxiliar`
		WHERE  (la_posicion={$PosAbuscarA}) AND id_grupo={$idGupoA} AND id_categoria={$idCategoria} AND id_evento={$idEvento}";
		echo __LINE__."<div><b>{$sqlP}</b></div>";
		$resultadoP=$mysqli2->query($sqlP);
  		$rowP = $resultadoP->fetch_assoc();
		if($rowP && $rowP['ci1_a']>0):
			$C1=$rowP['ci1_a'];
			$C2=$rowP['ci1_b'];
			echo "<div><b>ID encontrado #{$idRV} - Propagando ci1: {$C1}/{$C2}</b></div>";
			$sqlUU="UPDATE _todosvstodos SET ci1_a={$C1}, ci1_b={$C2} WHERE id={$idRV}";
			echo "<div>{$sqlUU}</div>";
			$mysqli2->query($sqlUU);
		else:
			echo "<div><b>Sin datos en tabla_auxiliar para pos:{$PosAbuscarA} grupo:{$idGupoA} cat:{$idCategoria}</b></div>";
		endif;
		endif;
		if($PosAbuscarB>0):
		$sqlP="SELECT *
		FROM `tabla_auxiliar`
		WHERE  (la_posicion={$PosAbuscarB}) AND id_grupo={$idGupoB} AND id_categoria={$idCategoria} AND id_evento={$idEvento}";
		echo __LINE__."<div><b>{$sqlP}</b></div>";
		$resultadoP=$mysqli2->query($sqlP);
  		$rowP = $resultadoP->fetch_assoc();
		if($rowP && $rowP['ci1_a']>0):
			$C1=$rowP['ci1_a'];
			$C2=$rowP['ci1_b'];
			echo "<div><b>ID encontrado #{$idRV} - Propagando ci2: {$C1}/{$C2}</b></div>";
			$sqlUU="UPDATE _todosvstodos SET ci2_a={$C1}, ci2_b={$C2} WHERE id={$idRV}";
			echo "<div>{$sqlUU}</div>";
			$mysqli2->query($sqlUU);
		else:
			echo "<div><b>Sin datos en tabla_auxiliar para pos:{$PosAbuscarB} grupo:{$idGupoB} cat:{$idCategoria}</b></div>";
		endif;
		endif;
	endwhile;

// ============================================================
// PASO 3: Marcar partidos listos (ambos slots llenos → tipo_referencia='no')
// ============================================================
	$sql02="SELECT *
	FROM `_todosvstodos` WHERE evento={$idEvento}  AND tipo_referencia='si' AND grupo>12";
	$resultado=$mysqli2->query($sql02);
	echo "<hr>";
	while($row = $resultado->fetch_assoc()):
		if($row['ci1_a']>0 && $row['ci1_b']>0 && $row['ci2_a']>0 && $row['ci2_b']>0):
			$sqlUU="UPDATE _todosvstodos SET tipo_referencia='no', tmp='398' WHERE id={$row['id']}";
			echo "<div>{$sqlUU}</div>";
			$mysqli2->query($sqlUU);
		endif;

		if($row['ci1_a']>0 && $row['ci1_b']>0 && $row['ci2_a']==0 && $row['ci2_b']==0 && $row['ref_tipo_regustado2']==3):
			$sqlUU="UPDATE _todosvstodos SET tipo_referencia='no', tmp='404' WHERE id={$row['id']}";
			echo "<div>{$sqlUU}</div>";
			$mysqli2->query($sqlUU);
		endif;

		if($row['ci2_a']>0 && $row['ci2_b']>0 && $row['ci1_a']==0 && $row['ci1_b']==0 && $row['ref_tipo_regustado1']==3):
			$sqlUU="UPDATE _todosvstodos SET tipo_referencia='no', tmp='410' WHERE id={$row['id']}";
			echo "<div>{$sqlUU}</div>";
			$mysqli2->query($sqlUU);
		endif;
	endwhile;

// ============================================================
// PASO 4: PROPAGACIÓN eliminatoria → siguiente ronda
// ============================================================
$slq="SELECT * FROM `_todosvstodos`
WHERE
grupo>=13
AND (rusultado_equipo1>0 OR resultado_equipo2>0)
AND evento={$idEvento}
ORDER by grupo, categoria";
echo __LINE__.$slq;
$resultado=$mysqli2->query($slq);
echo "<hr>";
while($row = $resultado->fetch_assoc()):
	echo "<h3>{$row['id']}</h3>";
	$Abuscar=0;
	if($row['partido_nro']==1 && $row['grupo']==13): $Abuscar=20; endif;
	if($row['partido_nro']==2 && $row['grupo']==13): $Abuscar=21; endif;
	if($row['partido_nro']==3 && $row['grupo']==13): $Abuscar=22; endif;
	if($row['partido_nro']==4 && $row['grupo']==13): $Abuscar=23; endif;
	if($row['partido_nro']==1 && $row['grupo']==15): $Abuscar=24; endif;
	if($row['partido_nro']==2 && $row['grupo']==15): $Abuscar=25; endif;

	$idCategoria=$row['categoria'];
	if($row['rusultado_equipo1']>$row['resultado_equipo2']):
		$G1a=$row['ci1_a']; $G1b=$row['ci1_b'];
		$P1a=$row['ci2_a']; $P1b=$row['ci2_b'];
	elseif($row['resultado_equipo2']>$row['rusultado_equipo1']):
		$G1a=$row['ci2_a']; $G1b=$row['ci2_b'];
		$P1a=$row['ci1_a']; $P1b=$row['ci1_b'];
	endif;

	$sqlT1="SELECT *
	FROM `_todosvstodos` WHERE evento={$idEvento} AND (ref_etiqueta1={$Abuscar}  OR ref_etiqueta2={$Abuscar}) AND categoria={$idCategoria} ";
	echo "<div>ID {$row['id']}</div>";
	echo __LINE__."<div>sqlT1 {$sqlT1}</div>";
	$resultadoT1=$mysqli2->query($sqlT1);
 	$rowT1 = $resultadoT1->fetch_assoc();
	$idRV=$rowT1['id'];
	if($rowT1['ref_etiqueta1']==$Abuscar && $rowT1['ref_tipo_regustado1']==1  && $G1a>0 && $G1b>0 && $idRV!=$row['id']):
		echo "<h2>grupo: {$rowT1['grupo']}</h2>";
		if($rowT1['grupo']==18)
        	$sqlUU="UPDATE _todosvstodos SET ci1_a={$G1a}, ci1_b={$G1b},   tmp='472' WHERE id={$idRV} AND categoria={$idCategoria}";
        else
			$sqlUU="UPDATE _todosvstodos SET ci1_a={$G1a}, ci1_b={$G1b}, tipo_referencia='no', tmp='472' WHERE id={$idRV} AND categoria={$idCategoria}";
		echo __LINE__."<div>{$sqlUU}</div>";
		$mysqli2->query($sqlUU);
	endif;
	if($rowT1['ref_etiqueta2']==$Abuscar && $rowT1['ref_tipo_regustado2']==1 && $G1a>0 && $G1b>0 && $idRV!=$row['id']):
		if($rowT1['grupo']==18)
        	$sqlUU="UPDATE _todosvstodos SET ci2_a={$G1a}, ci2_b={$G1b},  tmp='487' WHERE id={$idRV} AND categoria={$idCategoria}";
        else
			$sqlUU="UPDATE _todosvstodos SET ci2_a={$G1a}, ci2_b={$G1b}, tipo_referencia='no', tmp='482' WHERE id={$idRV} AND categoria={$idCategoria}";
		echo __LINE__."<div>{$sqlUU}</div>";
		$mysqli2->query($sqlUU);
	endif;
endwhile;

endforeach; // fin loop eventos activos

?>
