<?php
    $titulo="BT.com.py Ranking";
	$h1="Ranking";
 
    include_once "db/conection.inc.php";

    if(isset($_GET['url'])):
        $url=filter_var($_GET['url'], FILTER_SANITIZE_STRING);
        $sqlurl="SELECT  id,
        upper( (CAST(CONVERT(nombre USING latin1) AS BINARY) )) as nombre,
        descripcion,
        url,
        url_amigable
         FROM  _circuitos WHERE 
        url_amigable='{$url}'"; //	evento={$evento} AND
        $resultadoU=$mysqli2->query($sqlurl);  
        $rowU = $resultadoU->fetch_assoc();
        $circuito=abs($rowU['id']);
        $descripcion="Ranking {$rowU['nombre']} ";
        $titulo=$descripcion;
        $laurl=$_SERVER['REDIRECT_SCRIPT_URI'];
 
    endif;



	//$descripcion="El mejor sitio de Padel del Paraguay";

    if(isset($_GET['grabar'])):
        $pagina = "logica/ranking-padel.inc.php";
    elseif(isset($_GET['ic'])):            // pestaña INTERCLUBES (ranking de clubes)
        $pagina = "logica/mostrar-ranking-interclubes.php";
        $titulo = "Ranking Interclubes" . (isset($rowU['nombre']) ? " {$rowU['nombre']}" : '');
        $h1     = "Ranking Interclubes";
    else:
        $pagina = "logica/mostrar-ranking.php";
    endif;
    


    //if(isset($_GET['v3']))
    //$pagina = "logica/mostrar-ranking.php"; 
    //else
    //$pagina = "logica/ranking-padel.inc.php"; 
    include "plantilla.php";
?>