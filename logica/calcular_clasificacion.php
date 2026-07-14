<?php
date_default_timezone_set('America/Asuncion');
include_once __DIR__ . "/../db/conection.inc.php";

$idEvento = isset($_GET['evento']) ? abs((int)$_GET['evento']) : 0;
$catFiltro = isset($_GET['categoria']) ? abs((int)$_GET['categoria']) : 0;
if($idEvento == 0) { echo "Error: falta ?evento=N"; exit; }

if($catFiltro > 0) {
    $categorias = [$catFiltro];
} else {
    $rCats = $mysqli2->query("SELECT DISTINCT categoria FROM _todosvstodos WHERE evento={$idEvento} AND grupo<13");
    $categorias = [];
    while($rc = $rCats->fetch_assoc()) $categorias[] = (int)$rc['categoria'];
}

echo "<pre>Evento: {$idEvento} | Categorias: " . implode(', ', $categorias) . "\n";

foreach($categorias as $cat_aux):
    $ev_aux = $idEvento;
    echo "\n=== Categoria {$cat_aux} ===\n";

    $mysqli2->query("UPDATE tabla_auxiliar SET jugados=0,`g+`=0,`g-`=0,sg=0,ganados=0,puntos=0,la_posicion=0 WHERE id_evento={$ev_aux} AND id_categoria={$cat_aux}");
    echo "Stats reseteados\n";

    $resAux = $mysqli2->query("SELECT id,ci1_a,ci1_b,ci2_a,ci2_b,grupo,
        rusultado_equipo1,resultado_equipo2,
        resultado3_equipo1,resultado3_equipo2
        FROM _todosvstodos
        WHERE evento={$ev_aux} AND categoria={$cat_aux} AND grupo<13
        AND (rusultado_equipo1>0 OR resultado_equipo2>0 OR resultado3_equipo1>0 OR resultado3_equipo2>0)");

    $partidos = 0;
    while($rowAux = $resAux->fetch_assoc()):
        $idGrpAux = (int)$rowAux['grupo'];
        $sA=0; $sB=0;
        $r1a=abs($rowAux['rusultado_equipo1']); $r1b=abs($rowAux['resultado_equipo2']);
        $r3a=abs($rowAux['resultado3_equipo1']); $r3b=abs($rowAux['resultado3_equipo2']);
        if($r1a>0||$r1b>0){ $r1a>$r1b ? $sA++ : $sB++; }
        if($r3a>0||$r3b>0){ $r3a>$r3b ? $sA++ : $sB++; }
        if($sA==$sB) continue;

        $gA=$r1a+$r3a; $gB=$r1b+$r3b;
        if($sA>$sB){
            $ganA=$rowAux['ci1_a']; $ganB=$rowAux['ci1_b'];
            $perA=$rowAux['ci2_a']; $perB=$rowAux['ci2_b'];
            $tAF=$gA; $tEC=$gB;
        } else {
            $ganA=$rowAux['ci2_a']; $ganB=$rowAux['ci2_b'];
            $perA=$rowAux['ci1_a']; $perB=$rowAux['ci1_b'];
            $tAF=$gB; $tEC=$gA;
        }

        $rG=$mysqli2->query("SELECT id FROM tabla_auxiliar WHERE (ci1_a='{$ganA}' OR ci1_b='{$ganA}') AND id_grupo={$idGrpAux} AND id_categoria={$cat_aux} AND id_evento={$ev_aux}");
        if($rG->num_rows==0)
            $mysqli2->query("INSERT INTO tabla_auxiliar (id_grupo,ci1_a,ci1_b,id_categoria,id_evento,tipo_proceso) VALUES ({$idGrpAux},'{$ganA}','{$ganB}',{$cat_aux},{$ev_aux},'web')");
        else {
            $rowG=$rG->fetch_assoc();
            $mysqli2->query("UPDATE tabla_auxiliar SET jugados=jugados+1,ganados=ganados+1,`g+`=`g+`+{$tAF},`g-`=`g-`+{$tEC} WHERE id={$rowG['id']}");
        }

        $rP=$mysqli2->query("SELECT id FROM tabla_auxiliar WHERE (ci1_a='{$perA}' OR ci1_b='{$perA}') AND id_grupo={$idGrpAux} AND id_categoria={$cat_aux} AND id_evento={$ev_aux}");
        if($rP->num_rows==0)
            $mysqli2->query("INSERT INTO tabla_auxiliar (id_grupo,ci1_a,ci1_b,id_categoria,id_evento,tipo_proceso) VALUES ({$idGrpAux},'{$perA}','{$perB}',{$cat_aux},{$ev_aux},'web')");
        else {
            $rowP=$rP->fetch_assoc();
            $mysqli2->query("UPDATE tabla_auxiliar SET jugados=jugados+1,`g-`=`g-`+{$tAF},`g+`=`g+`+{$tEC} WHERE id={$rowP['id']}");
        }
        $partidos++;
    endwhile;
    echo "Partidos procesados: {$partidos}\n";

    $rFix=$mysqli2->query("SELECT id,`g+`,`g-`,ganados FROM tabla_auxiliar WHERE id_evento={$ev_aux} AND id_categoria={$cat_aux}");
    while($rF=$rFix->fetch_assoc()){
        $elSG=abs($rF['g+'])-abs($rF['g-']);
        $ptos=$rF['ganados']+$elSG;
        $mysqli2->query("UPDATE tabla_auxiliar SET sg={$elSG},puntos={$ptos} WHERE id={$rF['id']}");
    }
    echo "SG y puntos recalculados\n";

    $rPos=$mysqli2->query("SELECT * FROM tabla_auxiliar WHERE id_evento={$ev_aux} AND id_categoria={$cat_aux} ORDER BY id_grupo ASC");
    $grps_aux=[];
    while($rPr=$rPos->fetch_assoc()) $grps_aux[$rPr['id_grupo']][]=$rPr;

    foreach($grps_aux as $idGrp=>$eqs_aux){
        $conf_aux=[];
        $rCf=$mysqli2->query("SELECT ci1_a,ci1_b,ci2_a,ci2_b,
            rusultado_equipo1 as r11,resultado_equipo2 as r12,
            resultado3_equipo1 as r31,resultado3_equipo2 as r32
            FROM _todosvstodos WHERE grupo={$idGrp} AND evento={$ev_aux} AND categoria={$cat_aux} AND tipo_referencia='no'");
        if($rCf) while($pC=$rCf->fetch_assoc()){
            if($pC['r11']==0&&$pC['r12']==0&&$pC['r31']==0&&$pC['r32']==0) continue;
            $sa2=0;$sb2=0;
            if($pC['r11']>0||$pC['r12']>0){$pC['r11']>$pC['r12']?$sa2++:$sb2++;}
            if($pC['r31']>0||$pC['r32']>0){$pC['r31']>$pC['r32']?$sa2++:$sb2++;}
            $kA2=$pC['ci1_a'].'-'.$pC['ci1_b'];
            $kB2=$pC['ci2_a'].'-'.$pC['ci2_b'];
            if($sa2!=$sb2){
                if($sa2>$sb2){$conf_aux[$kA2][$kB2]=1;$conf_aux[$kB2][$kA2]=0;}
                else{$conf_aux[$kB2][$kA2]=1;$conf_aux[$kA2][$kB2]=0;}
            }
        }

        foreach($eqs_aux as &$eq2){
            $eq2['_clave']=$eq2['ci1_a'].'-'.$eq2['ci1_b'];
            $eq2['_conf']=isset($conf_aux[$eq2['_clave']])?$conf_aux[$eq2['_clave']]:[];
        } unset($eq2);

        uasort($eqs_aux,function($a,$b){return $b['ganados']-$a['ganados'];});
        $eqs_aux=array_values($eqs_aux);

        $gpg2=[];
        foreach($eqs_aux as $i2=>$eq2) $gpg2[$eq2['ganados']][]=$i2;
        $ord2=[];
        krsort($gpg2);

        foreach($gpg2 as $ixs2){
            if(count($ixs2)==1){
                $ord2[]=$eqs_aux[$ixs2[0]];
            } elseif(count($ixs2)==2){
                $e0=$eqs_aux[$ixs2[0]]; $e1=$eqs_aux[$ixs2[1]];
                if(isset($e0['_conf'][$e1['_clave']])){
                    if($e0['_conf'][$e1['_clave']]==1){$ord2[]=$e0;$ord2[]=$e1;}
                    else{$ord2[]=$e1;$ord2[]=$e0;}
                } else {
                    $sg0=$e0['g+']-$e0['g-']; $sg1=$e1['g+']-$e1['g-'];
                    if($sg0>=$sg1){$ord2[]=$e0;$ord2[]=$e1;}
                    else{$ord2[]=$e1;$ord2[]=$e0;}
                }
            } else {
                $sub2=array_map(fn($i)=>$eqs_aux[$i],$ixs2);
                usort($sub2,function($a,$b){
                    $sgA=$a['g+']-$a['g-']; $sgB=$b['g+']-$b['g-'];
                    if($sgB!=$sgA) return $sgB-$sgA;
                    return $b['g+']-$a['g+'];
                });
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
                        } else { foreach($blq as $bb) $final_p[]=$bb; }
                    } else { foreach($blq as $bb) $final_p[]=$bb; }
                    $ip=$jp;
                }
                foreach($final_p as $e) $ord2[]=$e;
            }
        }

        $posAux=1;
        foreach($ord2 as $eq2){
            $mysqli2->query("UPDATE tabla_auxiliar SET la_posicion={$posAux} WHERE id={$eq2['id']}");
            echo "  Grupo {$idGrp} Pos {$posAux}: ci1_a={$eq2['ci1_a']} ganados={$eq2['ganados']} sg=" . ($eq2['g+']-$eq2['g-']) . "\n";
            $posAux++;
        }
    }
    echo "Posiciones asignadas\n";
endforeach;

echo "\n=== Propagando a eliminatoria ===\n";
$urlP2 = "http://bt.com.py/logica/cargar.auxiliar.v2-parte2.php?evento={$idEvento}";
$result = @file_get_contents($urlP2);
echo "parte2 ejecutado\n";

echo "\n=== Verificacion ===\n";
foreach($categorias as $cat_v):
    $rV = $mysqli2->query("SELECT id, grupo, partido_nro, ci1_a, ci2_a, tipo_referencia FROM _todosvstodos WHERE evento={$idEvento} AND categoria={$cat_v} AND grupo>=13 ORDER BY grupo, partido_nro");
    while($rv = $rV->fetch_assoc()){
        $status = ($rv['ci1_a']>0 && $rv['ci2_a']>0) ? 'LISTO' : (($rv['ci1_a']>0 || $rv['ci2_a']>0) ? 'PARCIAL' : 'VACIO');
        echo "Cat {$cat_v} Grupo {$rv['grupo']} P{$rv['partido_nro']}: ci1={$rv['ci1_a']} ci2={$rv['ci2_a']} ref={$rv['tipo_referencia']} [{$status}]\n";
    }
endforeach;

echo "\nListo.\n</pre>";
