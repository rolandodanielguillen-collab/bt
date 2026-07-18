<?php
//.llaves     /* display: block; opacity: 100;
//campo contrincante es el equipo 1
//campo equipo es el equipo 2

 if (isset($_GET['debug'])) {
    echo __LINE__."<br>";
 
  }
 
if(isset($pagina)):
  include_once "db/conection.inc.php";
  include_once "funciones.php";
else:
  include "../db/conection.inc.php";
  include_once "../funciones.php";
endif;
if (isset($_GET['evento'])) {
	$SHA1evento = filter_var($_GET['evento'], FILTER_SANITIZE_STRING);
    //$evento = abs($evento);


$sqlEvento="SELECT 
    id,
    estado,
    codigo_evento,
    evento,
    nombre_evento2,
    date_format(fecha, '%d-%m-%Y') AS fecha,
    date_format(fecha_fin, '%d-%m-%Y') AS fecha_fin,
    costo1,
    descripcion,
    url_amigable,
    flyer,
    estado,
    boton_llaves,
    boton_fixture,
    fixture_publicado

    FROM  
    _p_eventos

	   WHERE 
	  sha1(id)='{$SHA1evento}'";
	
  if (isset($_GET['debug'])) {
    echo __LINE__."<div>{$sqlEvento}</div>";
  }   
	global $mysqli2;
	$resultadoEvento = $mysqli2->query($sqlEvento);
	$rowEvento = $resultadoEvento->fetch_assoc();	
 $botonLlaves=$rowEvento['boton_llaves'];
 $idEventos=$rowEvento['id'];
 $fixture_publicado=$rowEvento['fixture_publicado'];
 $estadoEvento=$rowEvento['estado'];
 	
	
 if($rowEvento['boton_fixture']=='oculto' && !isset($_GET['tp'])):
  echo "<div>Datos temporalmente bloqueados!</div>";
  echo "<div>Por favor vuelva más tarde</div>";
  exit;
 endif;
 /*
 $resultado = $mysqli2->query($sqlD);
$matriz = array();
$hijos1 = $columnas = array();
while ($row = $resultado->fetch_assoc()) {
	*/
}
//equipos
$idcategoria = 0;
$whereCa="";
if (isset($_GET['categoria'])) {
	$idcategoria = filter_var($_GET['categoria'], FILTER_SANITIZE_NUMBER_INT);
    $idcategoria = abs($idcategoria);
	$whereCa="AND categoria={$idcategoria}";
}
//categoria
 
/*
//inicio cache
	$cachetime = 300;
	if($estadoEvento=='culminado')
    $cachetime = 60;
	
	$adicional="fi-".$SHA1evento."-".$idcategoria;

	$url = $_SERVER["SCRIPT_NAME"]; //obtenemos el nombre la url y nombres de archivo actual
	$break = explode("/", $url); //dividimos las uniones por / y obtenemos una matriz de datos
	$file = $break[count($break)-1];
	$cachefile = "cache/cached-".substr_replace($file ,"",-4).$adicional.".html"; //creamos un nombre nuevo para el caché, este será HTML para optimizar recursos
 	echo "<!-- {$cachefile} -->";
	if(file_exists($cachefile)  && time()-$cachetime < filemtime($cachefile)):
		include($cachefile);
echo '<script>
 function ocultarDiv() {
      const div = document.getElementById("cargando");
      div.style.display = "none";
    }
ocultarDiv();
</script>';
		exit;
	endif;
	ob_start();  

//echo "<div>Última actualización: ".date('d-m-Y H:i:s')."</div>"; 

//fin cache*/
//
//inicio funciones
/*
Para una mejor organización del código, todas las funciones irán en funciones.php

*/

//***fin funciones

/* NUEVO proceso */
// Rebuild de _tabla_parejas solo si cambiaron las inscripciones del evento.
// Los sorteos TVT leen esta tabla; antes se hacía DELETE+INSERT completo en cada visita.
$rebuildParejas = false;
$firmaInsc = '';
$archivoFirma = '';
if(isset($idEventos)):
	$resSig=$mysqli2->query("SELECT COUNT(*) c, COALESCE(MAX(id),0) mx,
		COALESCE(SUM(CRC32(CONCAT_WS('|',id,ci,IFNULL(ci_dupla,''),id_categoria,IFNULL(phorario,''),IFNULL(comentario,''),IFNULL(estado,'')))),0) s
		FROM _p_incripciones WHERE id_evento={$idEventos}");
	$rowSig=$resSig ? $resSig->fetch_assoc() : null;
	$firmaInsc = $rowSig ? $rowSig['c'].'-'.$rowSig['mx'].'-'.$rowSig['s'] : '';
	$archivoFirma = "cache/parejas-sig-{$idEventos}.txt";
	$rebuildParejas = ($firmaInsc !== '' && (!file_exists($archivoFirma) || trim(@file_get_contents($archivoFirma)) !== $firmaInsc));
endif;

if($rebuildParejas):
$SQLD="DELETE
FROM _tabla_parejas
WHERE evento={$idEventos}";
//$mysqli2->query($SQLD);
$mysqli2->query($SQLD);
$sql1="SELECT 
id, 
fecha_inscripcion, 
    fecha_pago, 
    id_evento, 
    ci, 
    id_categoria, 
    ci_dupla, 
    obs, 
    estado, 
    comprobante_pago, 
    sexo, 
    concat(nombre,' ', apellido) AS dato 
FROM
    v_p_inscriptos where sha1(id_evento)='{$SHA1evento}'";
if (isset($_GET['debug'])) {
  echo __LINE__."<div>{$sql1}</div>";
}  
$dupla2='';
 
$resultado1 = $mysqli2->query($sql1);
while($row1 = $resultado1->fetch_assoc()):
  $idEvento=abs($row1['id_evento']);
	$ci1=trim($row1['ci']); //ci principal
		
	$ci2=trim($row1['ci_dupla']); //ci dupla
	$inscripcionnrodupla1=$row1['id']; //id inscripcion
	$dupla1=trim($row1['dato']); //nombre y apellido
 	
		$id_categoria=$row1['id_categoria']; //categoria
 		$phorario='';
		$comentario='';
    // sha1(id_evento)='{$SHA1evento}'
		$sql2="SELECT id,ci,phorario,comentario FROM `_p_incripciones` 
		WHERE sha1(id_evento) = '{$SHA1evento}'  AND ci LIKE '%". $ci2. "%'
		AND id_categoria=".$id_categoria;

    if (isset($_GET['debug'])) {
      echo __LINE__."<div>{$sql2}</div>";
    }  

		$resultado2 = $mysqli2->query($sql2);
    $Dataset2 = $resultado2->fetch_assoc();
		//sc_lookup(Dataset2,$sql2);
		$inscripcionnrodupla2=0;
		//if(!empty({Dataset2})):
			$inscripcionnrodupla2=$Dataset2['id']; //{Dataset2[0][0]};	
			$phorario=$Dataset2['phorario']; //{Dataset2[0][2]};	
			$comentario=$Dataset2['comentario']; //{Dataset2[0][3]};

			$sqlD="
			SELECT concat(nombre,' ', apellido) AS dato, cel
			FROM _p_usuarios 
			WHERE ci = '".$ci2."' 
			ORDER BY ci, nombre, apellido"; 
			if (isset($_GET['debug'])) {
        echo __LINE__."<div>{$sqlD}</div>";
      }  
			//sc_lookup(Datasetd,$sqlD);
      $resultado3 = $mysqli2->query($sqlD);
      $Datasetd = $resultado3->fetch_assoc();
			$dupla2="";
			$cel2="";
			//if(!empty({Datasetd})):
				$dupla2=$Datasetd['dato']; //{Datasetd[0][0]};
				$cel2=$Datasetd['cel']; //{Datasetd[0][1]};
			//endif;

		//endif;
	
		$sqlD="
		SELECT concat(nombre,' ', apellido) AS dato, cel
		FROM _p_usuarios 
		WHERE ci = '".$ci1."' 
		ORDER BY ci, nombre, apellido"; 
    if (isset($_GET['debug'])) {
      echo __LINE__."<div>{$sqlD}</div>";
    }  
    $resultado3 = $mysqli2->query($sqlD);
    $Datasetd = $resultado3->fetch_assoc();

      $cel1=$Datasetd['cel']; //{Datasetd[0][1]};

		$sqli="INSERT INTO _tabla_parejas 
		(categoria,ci1,dupla1,ci2,dupla2,evento,inscripcionnrodupla1,inscripcionnrodupla2,phorario, comentario, cel1, cel2) 
		VALUES 
		(".$id_categoria.",".$ci1.",'".$dupla1."',".$ci2.",'".$dupla2."',{$idEvento},".$inscripcionnrodupla1.",".$inscripcionnrodupla2.",'".$phorario."','".$comentario."','".$cel1."','".$cel2."')";
		//echo $sqli."<br>";
		//if(!isset($arrayCis[$inscripcionnrodupla1]) && !isset($arrayCis[$inscripcionnrodupla2])):
			$sqlVV="SELECT count(id) as cantidad FROM 
			_tabla_parejas 
			WHERE  (ci1=hola 
			 OR  ci2=hola) AND categoria=chau AND evento={$idEvento}";
  if (isset($_GET['debug'])) {
    echo __LINE__."<div>{$sqlVV}</div>";
  }  
			$sqlVV=str_replace("hola","$ci1" ,$sqlVV);
			$sqlVV=str_replace("chau","$id_categoria" ,$sqlVV);
			//echo $sqlVV."<br>";

      $resultado4 = $mysqli2->query($sqlVV);
      $DatasetVV = $resultado4->fetch_assoc();

			//sc_lookup(DatasetVV, $sqlVV);
			//echo {DatasetVV[0][0]}."<br>";
			if( $DatasetVV['cantidad']==0)
        $mysqli2->query($sqli);
			//	sc_exec_sql ($sqli);
      if (isset($_GET['debug'])) {
        echo __LINE__."<div>{$sqli}</div>";
      }  
			//if($inscripcionnrodupla1>0)
 			//$arrayCis[$inscripcionnrodupla1]=$inscripcionnrodupla1;

			//if($inscripcionnrodupla2>0)
			//$arrayCis[$inscripcionnrodupla2]=$inscripcionnrodupla2;
		//endif;
	
		$ci1='';
		$dupla1='';
		$ci2='';
		$dupla2='';
		$inscripcionnrodupla1=$inscripcionnrodupla2=0;
		
		//echo $sqli."<br>"; 		
	 //endif;
	//endforeach;
endwhile;
@file_put_contents($archivoFirma, $firmaInsc);
endif; // rebuildParejas
//fin nuevo proceso

$cod_evento = $idEventos;
$categoriasHab = categorias($cod_evento);
$datosEvento = evento($cod_evento);
$numeroDeParejas = numeroParejas($cod_evento);
$complejos = complejos($cod_evento);

// Precarga para el render (evita una query por jugador/pareja)
$usuariosIdx=array();
$resPre=$mysqli2->query("SELECT ci, nombre, apellido, cel FROM _p_usuarios");
while($rPre=$resPre->fetch_assoc()) if(!isset($usuariosIdx[$rPre['ci']])) $usuariosIdx[$rPre['ci']]=$rPre;

$inscEvento=array();
$resPre=$mysqli2->query("SELECT * FROM _p_incripciones WHERE id_evento={$idEventos}");
while($rPre=$resPre->fetch_assoc()) $inscEvento[]=$rPre;

// mismo resultado que user_name() de funciones.php, sin query por jugador
function rk_user_name($ci){
	global $usuariosIdx;
	$row=isset($usuariosIdx[$ci]) ? $usuariosIdx[$ci] : array('ci'=>$ci,'nombre'=>'','apellido'=>'');
	$elnombre=explode(' ',(string)$row['nombre']);
	if(count($elnombre)>1) $row['nombre']=($elnombre[0]);
	if(!mb_check_encoding((string)$row['nombre'], 'UTF-8'))
		$row['nombre'] = mb_convert_encoding($row['nombre'], 'UTF-8', 'ISO-8859-1');
	$elapellido=explode(' ',(string)$row['apellido']);
	if(count($elapellido)>1) $row['apellido']=($elapellido[0]);
	if(strlen((string)$row['apellido'])<3 && isset($elapellido[1]))
		$row['apellido']=$elapellido[0]." ".$elapellido[1];
	if(!mb_check_encoding((string)$row['apellido'], 'UTF-8'))
		$row['apellido'] = mb_convert_encoding($row['apellido'], 'UTF-8', 'ISO-8859-1');
	return ($row);
}
// primera inscripción del evento donde participa el CI (como el LIMIT 1 original)
function rk_estado_pago($ci){
	global $inscEvento;
	foreach($inscEvento as $iRow)
		if($iRow['ci']==$ci || $iRow['ci_dupla']==$ci) return ($iRow['estado']=='pagado');
	return false;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $datosEvento['evento']; ?> - BT.com.py</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .accordion-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }
        .accordion-button.active svg {
            transform: rotate(180deg);
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 flex flex-col min-h-screen">
    <main class="flex-grow max-w-5xl mx-auto px-4 py-6 w-full">
        <div class="text-center mb-6">
            <h1 class="text-xl md:text-2xl font-bold text-gray-900 mb-3 leading-tight">
                <?php echo strtoupper($datosEvento['evento']); ?>
            </h1>
            <div class="flex flex-wrap justify-center gap-3">
                <button id="btn-informacion" onclick="mostrarTab('informacion')" class="bg-blue-600 text-white text-sm font-semibold py-2 px-5 rounded-lg shadow hover:bg-blue-700 transition">
                    Información
                </button>
                <?php if($botonLlaves=='visible'): ?>
                <a href="todos-vs-todos.php?<?php echo $_SERVER['QUERY_STRING']; ?>" class="bg-white text-gray-700 border border-gray-300 text-sm font-semibold py-2 px-5 rounded-lg shadow-sm hover:bg-gray-50 transition">
                    Llaves
                </a>
                <?php endif; ?>
            </div>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-1 space-y-4">
                <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200">
                    <h2 class="text-lg font-bold text-gray-900 mb-3 flex items-center">
                        <i class="fa-solid fa-circle-info mr-2 text-blue-600"></i>
                        DETALLES
                    </h2>
                    <div class="space-y-2 text-sm">
                        <div class="flex items-start">
                            <i class="fa-regular fa-calendar-days mr-2 text-gray-500 w-4 mt-0.5"></i>
                            <div class="flex-1">
                                <span class="text-gray-600 mr-1">Fechas:</span>
                                <span class="font-medium text-gray-900"><?php echo $rowEvento['fecha']; ?> al <?php echo $rowEvento['fecha_fin']; ?></span>
                            </div>
                        </div>
                        <?php
                        // Obtener nombre del complejo
                        if(!empty($complejos)):
                            foreach ($complejos as $complejo):
                                $datosComplejo = datosComplejo($complejo);
                                if($datosComplejo):
                        ?>
                        <div class="flex items-start">
                            <i class="fa-solid fa-location-dot mr-2 text-gray-500 w-4 mt-0.5"></i>
                            <div class="flex-1">
                                <span class="text-gray-600 mr-1">Complejo:</span>
                                <span class="font-medium text-gray-900"><?php echo $datosComplejo['nombre']; ?></span>
                            </div>
                        </div>
                        <?php
                                endif;
                            endforeach;
                        endif;
                        ?>
                    </div>
                </div>
            </div>
            <div class="lg:col-span-2">
                <div id="tab-informacion" class="tab-content">
                    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200">
                        <h2 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                            <i class="fa-solid fa-users mr-2 text-blue-600"></i>
                            CATEGORÍAS
                        </h2>
                        <p class="text-xs text-gray-500 mb-4 flex items-center">
                            <i class="fa-solid fa-check-circle text-green-500 mr-1"></i>
                            Inscripción pagada
                        </p>
                        <div class="space-y-3">
                            <?php
                            $cantidad_categorias_hab = 0;
                            foreach ($categoriasHab as $categoria) :
                                $datosCategoria = datosCategoria($categoria);
                                $cantidad = cantidadParejasXCategoria($cod_evento, $categoria);
                                if($datosCategoria){
                                    $cantidad_categorias_hab++;
                                    $inscripcion_par = (pareja($cod_evento, $categoria,2));
                                    $lenth = count($inscripcion_par);
                            ?>
                            <div class="border border-gray-700 rounded-lg overflow-hidden">
                                <button class="accordion-button w-full flex justify-between items-center p-3 bg-gray-800 hover:bg-gray-700 transition focus:outline-none" onclick="toggleAccordion(this)">
                                    <span class="text-base font-semibold text-white"><?php echo strtoupper($datosCategoria['categoria']); ?></span>
                                    <div class="flex items-center">
                                        <span class="bg-blue-600 text-white text-xs font-bold px-2.5 py-0.5 rounded-full mr-2"><?php echo round($lenth,0); ?></span>
                                        <svg class="w-4 h-4 text-gray-400 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </div>
                                </button>
                                <div class="accordion-content bg-gray-900">
                                    <?php
                                    // ── ACCESO ADMIN: solo con ?rk=clave se muestran puntos y se ordena ──
                                    $RK_SECRET = 'c61fd3c895d9149ed9edf665';
                                    $esAdmin = isset($_GET['rk']) && $_GET['rk'] === $RK_SECRET;

                                    // ── RANKING: obtener circuito del evento (1 sola vez) ──
                                    if($esAdmin && !isset($idCircuitoEv)):
                                        $sqlCircuito="SELECT id_circuito FROM _p_eventos WHERE id={$idEventos}";
                                        $resCircuito=$mysqli2->query($sqlCircuito);
                                        $rowCircuito=$resCircuito->fetch_assoc();
                                        $idCircuitoEv = $rowCircuito ? abs($rowCircuito['id_circuito']) : 0;

                                        // Si id_circuito=0 en el evento, buscarlo desde _ranking
                                        if($idCircuitoEv == 0):
                                            $resCir2=$mysqli2->query("SELECT DISTINCT circuito FROM _ranking 
                                                WHERE evento={$idEventos} AND circuito > 0 LIMIT 1");
                                            $rowCir2=$resCir2 ? $resCir2->fetch_assoc() : null;
                                            if($rowCir2) $idCircuitoEv = abs($rowCir2['circuito']);
                                        endif;
                                    endif;
                                    if(!isset($idCircuitoEv)) $idCircuitoEv = 0;

                                    // Closure para obtener puntos
                                    // ANTES: SUM(puntos) directo — no distinguía grupo único vs bracket
                                    // y para categorías hijo sumaba incorrectamente el padre
                                    // AHORA: replica la misma lógica de mostrar-ranking.php
                                    $fnPts = function($ci, $idCat) use ($idCircuitoEv, $idEventos, $mysqli2, $esAdmin){
                                        if(!$esAdmin || !$ci || !$idCircuitoEv) return 0;

                                        // Obtener categoría padre
                                        $resPadre=$mysqli2->query("SELECT id_categoria_padre FROM v_p_categorias WHERE id_categoria={$idCat} LIMIT 1");
                                        $rowPadre=$resPadre ? $resPadre->fetch_assoc() : null;
                                        $idPadre = $rowPadre ? (int)$rowPadre['id_categoria_padre'] : 0;

                                        // Obtener todos los eventos del circuito donde participó este CI en esta categoría
                                        $resEvts=$mysqli2->query("SELECT DISTINCT _p_incripciones.id_evento
                                            FROM _p_incripciones
                                            JOIN _p_eventos ON _p_eventos.id=_p_incripciones.id_evento
                                            WHERE (_p_incripciones.ci='{$ci}' OR _p_incripciones.ci_dupla='{$ci}')
                                            AND _p_incripciones.id_categoria={$idCat}
                                            AND _p_eventos.id_circuito={$idCircuitoEv}");

                                        $totalPts = 0;
                                        while($rowEvt=$resEvts->fetch_assoc()){
                                            $evId=$rowEvt['id_evento'];

                                            // Detectar grupo único: busca POS en categoría hija O padre
                                            $inClause = $idPadre > 0 ? "{$idCat},{$idPadre}" : "{$idCat}";
                                            $resGU=$mysqli2->query("SELECT COUNT(*) as total FROM _relacion_etiquetas_eventos r
                                                JOIN _p_etiquetas e ON r.id_etiqueta=e.id
                                                WHERE r.id_evento={$evId} AND r.id_categoria IN ({$inClause})
                                                AND e.value IN ('POS1','POS2','POS3','POS4')");
                                            $esGU = ($resGU->fetch_assoc()['total'] > 0);

                                            // Puntos del padre en este evento
                                            $ppEvt = 0;
                                            if($idPadre > 0){
                                                $resPP=$mysqli2->query("SELECT puntos FROM _ranking 
                                                    WHERE evento={$evId} AND circuito={$idCircuitoEv} 
                                                    AND ci='{$ci}' AND categoria={$idPadre}");
                                                $ppEvt = ($r=$resPP->fetch_assoc()) ? abs($r['puntos']) : 0;
                                            }

                                            // Puntos del hijo en este evento (acumulados en BD)
                                            $resPH=$mysqli2->query("SELECT puntos FROM _ranking 
                                                WHERE evento={$evId} AND circuito={$idCircuitoEv} 
                                                AND ci='{$ci}' AND categoria={$idCat}");
                                            $phAcum = ($r=$resPH->fetch_assoc()) ? abs($r['puntos']) : 0;

                                            // Mismo cálculo que mostrar-ranking.php
                                            $ptsMixto = $ppEvt;
                                            $ptsHijo  = $esGU ? $phAcum : max(0, $phAcum - $ppEvt);
                                            $totalPts += $ptsMixto + $ptsHijo;
                                        }
                                        return $totalPts;
                                    };

                                    // Construir array de parejas
                                    $parejasOrdenadas = [];
                                    for($i=0; $i<$lenth; $i++){
                                        $insc = $inscripcion_par[$i];
                                        $pts1 = $fnPts($insc['ci'],       $categoria);
                                        $pts2 = $fnPts($insc['ci_dupla'], $categoria);
                                        $parejasOrdenadas[] = [
                                            'insc'  => $insc,
                                            'pts1'  => $pts1,
                                            'pts2'  => $pts2,
                                            'total' => $pts1 + $pts2,
                                        ];
                                    }
                                    // Ordenar por puntos solo si es admin
                                    if($esAdmin):
                                        usort($parejasOrdenadas, fn($a,$b) => $b['total'] <=> $a['total']);
                                    endif;
                                    ?>

                                    <div class="p-2 space-y-2">
                                        <?php
                                        $numPar = 0;
                                        foreach($parejasOrdenadas as $par):
                                            $insc    = $par['insc'];
                                            $pts1    = $par['pts1'];
                                            $pts2    = $par['pts2'];
                                            $primero = rk_user_name($insc['ci']);
                                            $segundo = rk_user_name($insc['ci_dupla']);
                                            $numPar++;

                                            $pagado1 = rk_estado_pago($insc['ci']);
                                            $pagado2 = rk_estado_pago($insc['ci_dupla']);
                                        ?>
                                        <!-- Pareja Card -->
                                        <div style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:10px;overflow:hidden">
                                            <!-- Número de pareja -->
                                            <div style="background:rgba(255,255,255,.06);padding:3px 10px;display:flex;align-items:center;justify-content:space-between">
                                                <span style="font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:rgba(255,255,255,.35)">Pareja #<?=$numPar?></span>
                                                <?php if($esAdmin && ($pts1+$pts2)>0): ?>
                                                <span style="font-size:.6rem;font-weight:800;background:#eab308;color:#000;padding:1px 6px;border-radius:4px"><?=($pts1+$pts2)?> pts</span>
                                                <?php endif; ?>
                                            </div>
                                            <!-- Jugador 1 -->
                                            <a href="/jugador.php?ci=<?=htmlspecialchars($insc['ci'])?>" style="display:flex;align-items:center;gap:8px;padding:8px 10px;text-decoration:none;border-bottom:1px solid rgba(255,255,255,.06);transition:background .15s" onmouseover="this.style.background='rgba(59,130,246,.15)'" onmouseout="this.style.background=''">
                                                <span style="width:22px;height:22px;border-radius:50%;background:<?=$pagado1?'rgba(34,197,94,.2)':'rgba(255,255,255,.08)'?>;border:1px solid <?=$pagado1?'rgba(34,197,94,.4)':'rgba(255,255,255,.12)'?>;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                                    <i class="fas <?=$pagado1?'fa-check':'fa-user'?>" style="font-size:.5rem;color:<?=$pagado1?'#4ade80':'rgba(255,255,255,.35)'?>"></i>
                                                </span>
                                                <div style="flex:1;min-width:0">
                                                    <div style="font-size:.78rem;font-weight:700;color:<?=$pagado1?'#f1f5f9':'rgba(255,255,255,.6)'?>;text-transform:uppercase;line-height:1.2;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                                        <?=htmlspecialchars(mb_strtoupper($primero['nombre'].' '.$primero['apellido'],'UTF-8'))?>
                                                    </div>
                                                    <?php if($esAdmin && $pts1>0): ?>
                                                    <div style="font-size:.6rem;color:#fbbf24;font-weight:600;margin-top:1px"><?=$pts1?> pts ranking</div>
                                                    <?php endif; ?>
                                                </div>
                                                <i class="fas fa-external-link-alt" style="font-size:.5rem;color:rgba(99,179,237,.5);flex-shrink:0"></i>
                                            </a>
                                            <!-- Jugador 2 -->
                                            <?php if(!empty($insc['ci_dupla']) && $insc['ci_dupla']>0): ?>
                                            <a href="/jugador.php?ci=<?=htmlspecialchars($insc['ci_dupla'])?>" style="display:flex;align-items:center;gap:8px;padding:8px 10px;text-decoration:none;transition:background .15s" onmouseover="this.style.background='rgba(59,130,246,.15)'" onmouseout="this.style.background=''">
                                                <span style="width:22px;height:22px;border-radius:50%;background:<?=$pagado2?'rgba(34,197,94,.2)':'rgba(255,255,255,.08)'?>;border:1px solid <?=$pagado2?'rgba(34,197,94,.4)':'rgba(255,255,255,.12)'?>;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                                    <i class="fas <?=$pagado2?'fa-check':'fa-user'?>" style="font-size:.5rem;color:<?=$pagado2?'#4ade80':'rgba(255,255,255,.35)'?>"></i>
                                                </span>
                                                <div style="flex:1;min-width:0">
                                                    <div style="font-size:.78rem;font-weight:700;color:<?=$pagado2?'#f1f5f9':'rgba(255,255,255,.6)'?>;text-transform:uppercase;line-height:1.2;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                                        <?=htmlspecialchars(mb_strtoupper($segundo['nombre'].' '.$segundo['apellido'],'UTF-8'))?>
                                                    </div>
                                                    <?php if($esAdmin && $pts2>0): ?>
                                                    <div style="font-size:.6rem;color:#fbbf24;font-weight:600;margin-top:1px"><?=$pts2?> pts ranking</div>
                                                    <?php endif; ?>
                                                </div>
                                                <i class="fas fa-external-link-alt" style="font-size:.5rem;color:rgba(99,179,237,.5);flex-shrink:0"></i>
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <?php
                                }  
                            endforeach;
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <script>
        function toggleAccordion(button) {
            button.classList.toggle('active');
            const content = button.nextElementSibling;
            if (button.classList.contains('active')) {
                content.style.maxHeight = content.scrollHeight + "px";
            } else {
                content.style.maxHeight = null;
            }
        }
        function ocultarDiv() {
            const div = document.getElementById("cargando");
            if (div) div.style.display = "none";
        }
        ocultarDiv();
    </script>

    <!-- Mobile compact: al final del body para ganar especificidad a Tailwind CDN -->
    <style>
        @media (max-width: 768px) {
            body main.flex-grow { padding: 0.35rem !important; }
            body .text-center.mb-6 { margin-bottom: 0.4rem !important; }
            body .grid.gap-6 { gap: 0.4rem !important; }
            body .bg-white.p-4 { padding: 0.5rem !important; }
            body .rounded-xl { border-radius: 0.3rem !important; }
            body .space-y-3 > * + * { margin-top: 0.2rem !important; }
            body .space-y-4 > * + * { margin-top: 0.3rem !important; }
            body .accordion-button { padding: 0.35rem 0.5rem !important; }
            body .accordion-button .text-base { font-size: 0.8rem !important; }
            body .accordion-button .rounded-full { padding: 0.1rem 0.45rem !important; font-size: 0.68rem !important; }
            body .accordion-content > div { padding: 0.3rem 0.4rem !important; }
            body .accordion-content .gap-2 { gap: 0.15rem !important; }
            body .text-lg.font-bold { font-size: 0.85rem !important; margin-bottom: 0.3rem !important; }
            body .mb-4 { margin-bottom: 0.4rem !important; }
            body .mb-3 { margin-bottom: 0.3rem !important; }
            body .border.border-gray-700 { border-radius: 0.25rem !important; }
        }
    </style>
</body>
</html>
<?php   
    if($estadoEvento=='culminado')
    echo "<div>Evento culminado</div>";
?>