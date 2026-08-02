<?php
session_start();
   
    $pagina = "logica/todos.vs.todos.php"; 
include_once "db/conection.inc.php";
include_once "funciones.php";

// IMPORTANTE: Guardar los parámetros originales antes de procesarlos
$_SESSION['parametros_originales'] = $_GET;

 if (isset($_GET['evento'])):
	$idEvento=filter_var($_GET['evento'], FILTER_SANITIZE_STRING);
 endif;
 
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
	  sha1(id)='".sha1(($idEvento))."' OR sha1(id)='".$idEvento."'";
	
  if (isset($_GET['debug'])) {
    echo __LINE__."<div>{$sqlEvento}</div>";
  }   
	 
	$resultadoEvento = $mysqli2->query($sqlEvento);
	$rowEvento = $resultadoEvento->fetch_assoc();	
  	$evento=$rowEvento['id']; 
 	$titulo=$rowEvento['evento'];
	$h1=$rowEvento['evento'];
	$descripcion="El mejor sitio de BT del Paraguay  | Evento: {$rowEvento['evento']} ";

	//if(!isset($_GET['test'])):
	//if(isset($_GET['categoria']) && $_GET['categoria']==4)
	//	exit;
	//endif;
    include "plantilla.php";
?>