<?php
date_default_timezone_set('America/Asuncion');
    $titulo="BT.com.py Fixture";
	$h1="Llaves";
	$descripcion="El mejor sitio de BT del Paraguay";
   
        $pagina = "logica/grafico-llaves.incTMP.php"; 
     
	//$pagina="app.inc.php";
	echo "<div id='cargando' class='cargando'><img src='/img/cargando(1).gif' style='width:100px'></div>";
echo "<style>
.cargando {
 display: flex;
    justify-content: center;
    align-items: center;
    background: white;
    position: fixed;
    z-index: 999999;
    width: 100%;
    height:1000px;
}
</style>";
    include "plantilla.php";

?><script>
 function ocultarDiv() {
      const div = document.getElementById("cargando");
      div.style.display = "none";
    }
ocultarDiv();
</script>