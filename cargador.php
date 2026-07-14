<?php
session_start();
include_once "db/conection.inc.php";
include_once "propagacion.functions.php";
@include_once "funciones.php";

// -- LOGOUT --
if (isset($_GET['logout'])) { session_destroy(); header('Location: cargador.php'); exit; }

// -- LOGIN POST --
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['usuario'], $_POST['pase'])) {
    $u = $mysqli2->real_escape_string(trim($_POST['usuario']));
    $p = $mysqli2->real_escape_string(trim($_POST['pase']));
    $r = $mysqli2->query("SELECT id, usuario, tipo FROM _usuario_admin WHERE usuario='$u' AND pase='$p' LIMIT 1");
    if ($r && $r->num_rows > 0) {
        $admin = $r->fetch_assoc();
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_user'] = $admin['usuario'];
        $_SESSION['admin_tipo'] = $admin['tipo'];
        header('Location: cargador.php');
        exit;
    }
    $loginError = 'Usuario o contraseña incorrectos';
}

// -- API AJAX --
if (isset($_GET['api'])) {
    header('Content-Type: application/json; charset=utf-8');
    if (!isset($_SESSION['admin_id'])) { echo json_encode(['error' => 'No autenticado']); exit; }

    $api = $_GET['api'];

    if ($api === 'toggle_en_juego') {
        $id = abs((int)$_GET['id']);
        $r = $mysqli2->query("SELECT en_juego FROM _todosvstodos WHERE id=$id");
        $row = $r->fetch_assoc();
        $nuevo = ($row['en_juego'] === 'si') ? 'no' : 'si';
        $mysqli2->query("UPDATE _todosvstodos SET en_juego='$nuevo' WHERE id=$id");
        echo json_encode(['success' => true, 'en_juego' => $nuevo]);
        exit;
    }

    if ($api === 'guardar') {
        $id  = abs((int)$_GET['id']);
        $s1a = abs((int)$_GET['s1a']);
        $s1b = abs((int)$_GET['s1b']);
        $s2a = abs((int)$_GET['s2a']);
        $s2b = abs((int)$_GET['s2b']);
        $s3a = abs((int)$_GET['s3a']);
        $s3b = abs((int)$_GET['s3b']);
        $mysqli2->query("UPDATE _todosvstodos SET
            rusultado_equipo1=$s1a, resultado_equipo2=$s1b,
            resultado2_equipo1=$s2a, resultado2_equipo2=$s2b,
            resultado3_equipo1=$s3a, resultado3_equipo2=$s3b,
            fecha_resultado=CURDATE(), en_juego='no'
            WHERE id=$id");

        // Propagacion
        $resP = $mysqli2->query("SELECT id, evento, categoria, grupo, partido_nro, ci1_a, ci1_b, ci2_a, ci2_b,
            rusultado_equipo1 as r11, resultado_equipo2 as r12,
            resultado2_equipo1 as r21, resultado2_equipo2 as r22,
            resultado3_equipo1 as r31, resultado3_equipo2 as r32,
            tipo_referencia FROM _todosvstodos WHERE id=$id");
        $partido = $resP ? $resP->fetch_assoc() : null;
        $propagado = false;
        if ($partido) {
            $ev = $partido['evento']; $cat = $partido['categoria'];
            $grp = $partido['grupo']; $nroP = $partido['partido_nro'];
            $idVirtual = getIdVirtualGrupo($mysqli2, $grp, $nroP);
            $idParaBuscar = ($idVirtual > 0) ? $idVirtual : $grp;
            resetarSlotsDestino($mysqli2, $ev, $cat, $idParaBuscar);
            if ($idVirtual > 0) resetarSlotsDestino($mysqli2, $ev, $cat, $grp);
            $sA = 0; $sB = 0;
            if ($partido['r11']>0||$partido['r12']>0) { $partido['r11']>$partido['r12']?$sA++:$sB++; }
            if ($partido['r21']>0||$partido['r22']>0) { $partido['r21']>$partido['r22']?$sA++:$sB++; }
            if ($partido['r31']>0||$partido['r32']>0) { $partido['r31']>$partido['r32']?$sA++:$sB++; }
            if ($sA != $sB) {
                $ci_g_a=($sA>$sB)?$partido['ci1_a']:$partido['ci2_a'];
                $ci_g_b=($sA>$sB)?$partido['ci1_b']:$partido['ci2_b'];
                $ci_p_a=($sA>$sB)?$partido['ci2_a']:$partido['ci1_a'];
                $ci_p_b=($sA>$sB)?$partido['ci2_b']:$partido['ci1_b'];
                // Ganador -> slot ci1
                $r=$mysqli2->query("SELECT id FROM _todosvstodos WHERE evento=$ev AND categoria=$cat AND ref_etiqueta1=$idParaBuscar AND ref_tipo_regustado1=1 AND (tipo_referencia='si' OR (tipo_referencia='no' AND ci1_a=0)) LIMIT 1");
                if($r&&$r->num_rows>0){$n=$r->fetch_assoc();propagarJugador($mysqli2,$n['id'],'ci1',$ci_g_a,$ci_g_b);$propagado=true;}
                // Ganador -> slot ci2
                $r=$mysqli2->query("SELECT id FROM _todosvstodos WHERE evento=$ev AND categoria=$cat AND ref_etiqueta2=$idParaBuscar AND ref_tipo_regustado2=1 AND (tipo_referencia='si' OR (tipo_referencia='no' AND ci2_a=0)) LIMIT 1");
                if($r&&$r->num_rows>0){$n=$r->fetch_assoc();propagarJugador($mysqli2,$n['id'],'ci2',$ci_g_a,$ci_g_b);$propagado=true;}
                // Perdedor -> slot ci1
                $r=$mysqli2->query("SELECT id FROM _todosvstodos WHERE evento=$ev AND categoria=$cat AND ref_etiqueta1=$idParaBuscar AND ref_tipo_regustado1=2 AND (tipo_referencia='si' OR (tipo_referencia='no' AND ci1_a=0)) LIMIT 1");
                if($r&&$r->num_rows>0){$n=$r->fetch_assoc();propagarJugador($mysqli2,$n['id'],'ci1',$ci_p_a,$ci_p_b);$propagado=true;}
                // Perdedor -> slot ci2
                $r=$mysqli2->query("SELECT id FROM _todosvstodos WHERE evento=$ev AND categoria=$cat AND ref_etiqueta2=$idParaBuscar AND ref_tipo_regustado2=2 AND (tipo_referencia='si' OR (tipo_referencia='no' AND ci2_a=0)) LIMIT 1");
                if($r&&$r->num_rows>0){$n=$r->fetch_assoc();propagarJugador($mysqli2,$n['id'],'ci2',$ci_p_a,$ci_p_b);$propagado=true;}
            }
        }
        echo json_encode(['success'=>true,'propagado'=>$propagado]);
        exit;
    }

    if ($api === 'categorias') {
        $ev = abs((int)$_GET['evento']);
        $sql = "SELECT rc.id_categoria, c.categoria as nombre,
                (SELECT COUNT(*) FROM _todosvstodos t WHERE t.evento=$ev AND t.categoria=rc.id_categoria) as total_partidos,
                (SELECT COUNT(*) FROM _todosvstodos t WHERE t.evento=$ev AND t.categoria=rc.id_categoria AND (t.rusultado_equipo1>0 OR t.resultado_equipo2>0)) as finalizados
                FROM _relacion_evento_categoria rc
                LEFT JOIN _p_categorias c ON c.id = rc.id_categoria
                WHERE rc.id_evento=$ev
                ORDER BY c.categoria ASC";
        $r = $mysqli2->query($sql);
        $cats = [];
        while ($row = $r->fetch_assoc()) $cats[] = $row;
        echo json_encode(['success'=>true,'categorias'=>$cats], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($api === 'partidos') {
        $ev  = abs((int)$_GET['evento']);
        $cat = abs((int)$_GET['categoria']);
        $sql = "SELECT t.id, t.grupo, t.partido_nro, t.ci1_a, t.ci1_b, t.ci2_a, t.ci2_b,
                t.rusultado_equipo1, t.resultado_equipo2,
                t.resultado2_equipo1, t.resultado2_equipo2,
                t.resultado3_equipo1, t.resultado3_equipo2,
                t.en_juego, t.tipo_referencia,
                t.ref_etiqueta1, t.ref_etiqueta2,
                t.ref_tipo_regustado1, t.ref_tipo_regustado2,
                t.complejo, t.cancha,
                g.grupo as textoGrupo, g.id_etiqueta as grupo_etiqueta
                FROM _todosvstodos t
                LEFT JOIN _p_grupos g ON g.id = t.grupo
                WHERE t.evento=$ev AND t.categoria=$cat
                ORDER BY t.grupo ASC, t.partido_nro ASC";
        $r = $mysqli2->query($sql);
        $partidos = [];
        while ($row = $r->fetch_assoc()) {
            foreach (['ci1_a','ci1_b','ci2_a','ci2_b'] as $ci) {
                $key = str_replace('ci','j',$ci);
                if ($row[$ci] > 0) {
                    $ru = $mysqli2->query("SELECT nombre, apellido FROM _p_usuarios WHERE ci='{$row[$ci]}' LIMIT 1");
                    $u = $ru ? $ru->fetch_assoc() : null;
                    $row[$key] = $u ? trim($u['nombre'].' '.$u['apellido']) : $row[$ci];
                } else {
                    $row[$key] = '';
                }
            }
            if ($row['tipo_referencia'] !== 'no' || $row['ci1_a'] == 0) {
                if ($row['ref_etiqueta1'] > 0) {
                    $rg = $mysqli2->query("SELECT grupo AS nombre FROM _p_grupos WHERE id={$row['ref_etiqueta1']} LIMIT 1");
                    $g = $rg ? $rg->fetch_assoc() : null;
                    $re = $mysqli2->query("SELECT referencia FROM _referencia_etiquetas WHERE id={$row['ref_tipo_regustado1']} LIMIT 1");
                    $ref = $re ? $re->fetch_assoc() : null;
                    $row['label1'] = ($row['ref_tipo_regustado1']==3) ? 'BYE' : (($ref?$ref['referencia']:'').' '.($g?$g['nombre']:''));
                }
                if ($row['ref_etiqueta2'] > 0) {
                    $rg = $mysqli2->query("SELECT grupo AS nombre FROM _p_grupos WHERE id={$row['ref_etiqueta2']} LIMIT 1");
                    $g = $rg ? $rg->fetch_assoc() : null;
                    $re = $mysqli2->query("SELECT referencia FROM _referencia_etiquetas WHERE id={$row['ref_tipo_regustado2']} LIMIT 1");
                    $ref = $re ? $re->fetch_assoc() : null;
                    $row['label2'] = ($row['ref_tipo_regustado2']==3) ? 'BYE' : (($ref?$ref['referencia']:'').' '.($g?$g['nombre']:''));
                }
            }
            $partidos[] = $row;
        }
        echo json_encode(['success'=>true,'partidos'=>$partidos,'total'=>count($partidos)], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['error'=>'Accion no valida']);
    exit;
}

// -- NO LOGUEADO -> mostrar login --
if (!isset($_SESSION['admin_id'])):
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cargador de Resultados</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#0f1117;color:#f0f0f5;min-height:100vh;display:flex;align-items:center;justify-content:center}
.login-box{background:#1a1d27;border:1px solid #2a2d3a;border-radius:16px;padding:40px;width:90%;max-width:380px;box-shadow:0 20px 60px rgba(0,0,0,.4)}
.login-box h1{font-size:20px;margin-bottom:6px;text-align:center}
.login-box p{font-size:12px;color:#8b8fa3;text-align:center;margin-bottom:24px}
.fg{margin-bottom:16px}
.fg label{display:block;font-size:12px;font-weight:600;color:#8b8fa3;margin-bottom:6px}
.fg input{width:100%;padding:10px 14px;background:#252838;border:1px solid #333648;border-radius:8px;color:#f0f0f5;font-size:14px;outline:none}
.fg input:focus{border-color:#6366f1}
.btn-login{width:100%;padding:12px;background:#6366f1;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;transition:background .2s}
.btn-login:hover{background:#818cf8}
.error{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#ef4444;padding:10px;border-radius:8px;font-size:12px;margin-bottom:16px;text-align:center}
</style>
</head>
<body>
<div class="login-box">
  <h1><i class="fas fa-volleyball-ball" style="color:#6366f1;margin-right:8px;"></i>Cargador BT</h1>
  <p>Ingresa tus credenciales de administrador</p>
  <?php if(isset($loginError)):?><div class="error"><?=$loginError?></div><?php endif;?>
  <form method="post">
    <div class="fg"><label>Usuario</label><input name="usuario" required autofocus></div>
    <div class="fg"><label>Contrasena</label><input name="pase" type="password" required></div>
    <button class="btn-login" type="submit"><i class="fas fa-sign-in-alt"></i> Ingresar</button>
  </form>
</div>
</body>
</html>
<?php exit; endif;

// -- LOGUEADO -> Cargar eventos asignados --
$adminId   = $_SESSION['admin_id'];
$adminUser = $_SESSION['admin_user'];
$adminTipo = $_SESSION['admin_tipo'];

if ($adminTipo === 'superadmin') {
    $rEv = $mysqli2->query("SELECT id, evento, fecha, estado FROM _p_eventos ORDER BY id DESC");
} else {
    $rEv = $mysqli2->query("SELECT e.id, e.evento, e.fecha, e.estado
        FROM _p_eventos e
        INNER JOIN _admin_evento ae ON ae.id_evento = e.id
        WHERE ae.id_admin = $adminId
        ORDER BY e.id DESC");
}
$eventos = [];
while ($row = $rEv->fetch_assoc()) $eventos[] = $row;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cargador &mdash; <?=htmlspecialchars($adminUser)?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#0f1117;color:#f0f0f5;min-height:100vh}

.topbar{background:#1a1d27;border-bottom:1px solid #2a2d3a;padding:12px 16px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100}
.topbar h1{font-size:16px;font-weight:700}
.topbar .user{font-size:12px;color:#8b8fa3;display:flex;align-items:center;gap:8px}
.topbar .user a{color:#6366f1;text-decoration:none;font-size:11px}
.breadcrumb{padding:12px 16px;font-size:12px;color:#8b8fa3;display:flex;align-items:center;gap:6px;flex-wrap:wrap}
.breadcrumb a{color:#6366f1;text-decoration:none;cursor:pointer}

.container{padding:16px;max-width:800px;margin:0 auto}

.ev-grid{display:grid;gap:12px}
.ev-card{background:#1e2130;border:1px solid #2a2d3a;border-radius:12px;padding:16px;cursor:pointer;transition:all .2s}
.ev-card:hover{border-color:#6366f1;background:#252838}
.ev-card .ev-name{font-size:15px;font-weight:600;margin-bottom:4px}
.ev-card .ev-meta{font-size:11px;color:#8b8fa3}
.ev-badge{display:inline-block;font-size:10px;padding:2px 8px;border-radius:6px;font-weight:600}
.ev-badge.activo{background:rgba(34,197,94,.15);color:#22c55e}
.ev-badge.culminado{background:rgba(139,143,163,.1);color:#8b8fa3}
.ev-badge.registro{background:rgba(59,130,246,.15);color:#3b82f6}

.cats-wrap{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:20px}
.cat-pill{display:inline-flex;align-items:center;gap:6px;padding:10px 16px;background:#1e2130;border:1px solid #2a2d3a;border-radius:10px;cursor:pointer;transition:all .2s;font-size:13px;font-weight:500}
.cat-pill:hover{border-color:#6366f1;background:#252838}
.cat-pill.active{border-color:#6366f1;background:rgba(99,102,241,.15);color:#818cf8}
.cat-pill .cat-count{font-size:10px;background:#252838;padding:2px 6px;border-radius:6px;color:#8b8fa3}

.match-card{background:#1e2130;border:1px solid #2a2d3a;border-radius:12px;margin-bottom:10px;overflow:hidden;transition:all .2s}
.match-card.en-juego{border-color:rgba(239,68,68,.5);box-shadow:0 0 12px rgba(239,68,68,.15)}
.match-card.finalizado{opacity:.7}
.match-card.finalizado:hover{opacity:1}

.match-header{width:100%;background:#252838;border:none;color:#f0f0f5;padding:12px 16px;display:flex;align-items:center;gap:10px;cursor:pointer;font-family:inherit;text-align:left}
.match-header:hover{background:#2a2d3a}
.match-round{color:#fff;font-size:10px;font-weight:700;padding:3px 8px;border-radius:6px;flex-shrink:0;background:#6366f1}
.phase-grupos .match-round{background:#6366f1}
.phase-elim .match-round{background:#f59e0b}
.phase-semi .match-round{background:#f97316}
.phase-final .match-round{background:#ef4444}
.group-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin:16px 0 8px;padding:6px 12px;border-radius:8px;display:inline-block}
.group-label.gl-grupos{color:#6366f1;background:rgba(99,102,241,.1);border-left:3px solid #6366f1}
.group-label.gl-elim{color:#f59e0b;background:rgba(245,158,11,.1);border-left:3px solid #f59e0b}
.group-label.gl-semi{color:#f97316;background:rgba(249,115,22,.1);border-left:3px solid #f97316}
.group-label.gl-final{color:#ef4444;background:rgba(239,68,68,.1);border-left:3px solid #ef4444}
.match-summary{flex:1;font-size:12px;font-weight:500;display:flex;align-items:center;gap:8px}
.match-enjuego{font-size:9px;padding:2px 6px;border-radius:4px;font-weight:700}
.match-enjuego.si{background:#ef4444;color:#fff;animation:pulse 2s infinite}
.match-enjuego.fin{background:#22c55e;color:#fff}
.match-enjuego.pend{background:#333648;color:#8b8fa3}
.match-chevron{color:#5a5e72;transition:transform .2s;font-size:12px}
.match-header.open .match-chevron{transform:rotate(180deg)}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.5}}

.match-body{display:none;padding:0}
.match-body.open{display:block}

.match-teams{display:flex;align-items:center;padding:12px 16px;gap:8px}
.team{flex:1;font-size:12px;line-height:1.4}
.team.left{text-align:left}
.team.right{text-align:right}
.team .name{font-weight:600}
.team .name.winner{color:#22c55e}
.team .name.loser{color:#ef4444;opacity:.6}

.scores-wrap{display:flex;gap:4px;align-items:center;flex-shrink:0}
.scores-wrap input{width:36px;height:36px;text-align:center;background:#252838;border:1px solid #333648;border-radius:6px;color:#f0f0f5;font-size:16px;font-weight:700;outline:none;-moz-appearance:textfield}
.scores-wrap input::-webkit-outer-spin-button,.scores-wrap input::-webkit-inner-spin-button{-webkit-appearance:none}
.scores-wrap input:focus{border-color:#6366f1}
.scores-wrap input.s3{border-style:dashed;border-color:#c9a227;background:#1a1810}
.scores-wrap .vs{color:#5a5e72;font-size:10px;font-weight:600;padding:0 2px}
.scores-wrap .sep{width:6px}

.match-actions{display:flex;align-items:center;justify-content:space-between;padding:10px 16px;background:#181a24;border-top:1px solid #2a2d3a;gap:8px;flex-wrap:wrap}
.btn{padding:8px 16px;border:none;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:all .2s}
.btn-save{background:#6366f1;color:#fff}.btn-save:hover{background:#818cf8}
.btn-play{background:rgba(239,68,68,.1);color:#ef4444;border:1px solid rgba(239,68,68,.3)}.btn-play:hover{background:rgba(239,68,68,.2)}
.btn-play.active{background:#ef4444;color:#fff}
.btn-save:disabled{opacity:.4;cursor:not-allowed}

.match-footer{padding:6px 16px;background:#181a24;font-size:10px;color:#5a5e72;display:flex;justify-content:space-between}

.empty{text-align:center;padding:40px;color:#5a5e72;font-size:13px}
.loading{text-align:center;padding:40px;color:#6366f1;font-size:13px}

.stats-bar{display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap}
.stat{background:#1e2130;border:1px solid #2a2d3a;border-radius:8px;padding:8px 14px;font-size:11px;display:flex;align-items:center;gap:6px}
.stat .num{font-size:16px;font-weight:700;color:#6366f1}
.stat .num.green{color:#22c55e}
.stat .num.red{color:#ef4444}

.toast{position:fixed;bottom:20px;right:20px;background:#22c55e;color:#fff;padding:12px 20px;border-radius:10px;font-size:13px;font-weight:600;box-shadow:0 8px 30px rgba(0,0,0,.3);transform:translateY(100px);opacity:0;transition:all .3s;z-index:999}
.toast.show{transform:translateY(0);opacity:1}
.toast.error{background:#ef4444}

@media(max-width:600px){
  .match-teams{flex-direction:column;gap:8px}
  .team.right{text-align:left}
  .scores-wrap{flex-wrap:wrap;justify-content:center}
}
</style>
</head>
<body>

<div class="topbar">
  <h1><i class="fas fa-volleyball-ball" style="color:#6366f1;margin-right:8px;"></i>Cargador BT</h1>
  <div class="user">
    <i class="fas fa-user-circle"></i> <?=htmlspecialchars($adminUser)?>
    <a href="?logout"><i class="fas fa-sign-out-alt"></i> Salir</a>
  </div>
</div>

<div class="breadcrumb" id="breadcrumb">
  <a onclick="goHome()"><i class="fas fa-home"></i></a>
  <span>&rsaquo;</span>
  <span id="bcEvento">Seleccionar evento</span>
</div>

<div class="container">
  <!-- Vista 1: Eventos -->
  <div id="vistaEventos">
    <?php if(empty($eventos)):?>
      <div class="empty"><i class="fas fa-calendar-times" style="font-size:32px;margin-bottom:12px;display:block;"></i>No tenes eventos asignados.<br>Contacta al administrador.</div>
    <?php else:?>
      <div class="ev-grid">
      <?php foreach($eventos as $ev):?>
        <div class="ev-card" onclick="selectEvento(<?=$ev['id']?>,'<?=addslashes(htmlspecialchars($ev['evento'],ENT_QUOTES))?>')">
          <div class="ev-name"><?=htmlspecialchars($ev['evento'])?></div>
          <div class="ev-meta">
            <?=$ev['fecha']?> &nbsp;
            <span class="ev-badge <?=$ev['estado']?>"><?=strtoupper($ev['estado'])?></span>
          </div>
        </div>
      <?php endforeach;?>
      </div>
    <?php endif;?>
  </div>

  <!-- Vista 2: Categorias + Partidos -->
  <div id="vistaCarga" style="display:none">
    <div class="cats-wrap" id="catsPills"></div>
    <div class="stats-bar" id="statsBar"></div>
    <div id="matchCards"></div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
let currentEvento=0, currentCat=0, currentEventoNombre='';

function showToast(msg, isError){
  const t=document.getElementById('toast');
  t.textContent=msg;t.className='toast show'+(isError?' error':'');
  setTimeout(()=>t.className='toast',3000);
}

function goHome(){
  document.getElementById('vistaEventos').style.display='';
  document.getElementById('vistaCarga').style.display='none';
  document.getElementById('bcEvento').textContent='Seleccionar evento';
  currentEvento=0;currentCat=0;
}

async function selectEvento(id, nombre){
  currentEvento=id; currentEventoNombre=nombre;
  document.getElementById('vistaEventos').style.display='none';
  document.getElementById('vistaCarga').style.display='';
  document.getElementById('bcEvento').innerHTML='<a onclick="goHome()" style="cursor:pointer">'+nombre+'</a> <span style="color:#5a5e72">&rsaquo;</span> <span id="bcCat">Categorias</span>';
  const r=await fetch('cargador.php?api=categorias&evento='+id).then(r=>r.json());
  if(!r.success)return;
  const wrap=document.getElementById('catsPills');
  wrap.innerHTML='';
  r.categorias.forEach(function(c){
    const pill=document.createElement('div');
    pill.className='cat-pill';
    const fin = parseInt(c.finalizados)||0;
    const tot = parseInt(c.total_partidos)||0;
    const pend = tot - fin;
    const countClass = pend > 0 ? 'color:#ef4444' : 'color:#22c55e';
    pill.innerHTML=c.nombre+' <span class="cat-count" style="'+countClass+'">'+fin+'/'+tot+'</span>';
    pill.onclick=function(){
      document.querySelectorAll('.cat-pill').forEach(function(p){p.classList.remove('active')});
      pill.classList.add('active');
      loadPartidos(c.id_categoria, c.nombre);
    };
    wrap.appendChild(pill);
  });
}

async function loadPartidos(catId, catNombre){
  currentCat=catId;
  document.getElementById('bcCat').textContent=catNombre;
  document.getElementById('matchCards').innerHTML='<div class="loading"><i class="fas fa-spinner fa-spin"></i> Cargando partidos...</div>';

  const r=await fetch('cargador.php?api=partidos&evento='+currentEvento+'&categoria='+catId).then(function(r){return r.json()});
  if(!r.success){document.getElementById('matchCards').innerHTML='<div class="empty">Error cargando partidos</div>';return;}

  var total=r.partidos.length, conRes=0, enJuego=0;
  r.partidos.forEach(function(p){
    if(parseInt(p.rusultado_equipo1)>0||parseInt(p.resultado_equipo2)>0)conRes++;
    if(p.en_juego==='si')enJuego++;
  });
  document.getElementById('statsBar').innerHTML=
    '<div class="stat"><span class="num">'+total+'</span>Total</div>'+
    '<div class="stat"><span class="num green">'+conRes+'</span>Finalizados</div>'+
    '<div class="stat"><span class="num">'+(total-conRes)+'</span>Pendientes</div>'+
    (enJuego?'<div class="stat"><span class="num red">'+enJuego+'</span>En juego</div>':'');

  var groups={};
  r.partidos.forEach(function(p){
    var g=p.grupo;
    if(!groups[g])groups[g]={label:p.textoGrupo||'Grupo '+g, etiqueta:p.grupo_etiqueta, partidos:[]};
    groups[g].partidos.push(p);
  });

  // ponytail: etiqueta→fase visual. 1=grupos, 9/2/3=elim, 4/6=semi, 5/8=final
  function phaseClass(etiq){
    var e=parseInt(etiq)||0;
    if(e===1||e===11) return 'grupos';
    if(e===4||e===6) return 'semi';
    if(e===5||e===8) return 'final';
    return 'elim';
  }

  var html='';
  var gKeys=Object.keys(groups).sort(function(a,b){return a-b});
  gKeys.forEach(function(gId){
    var grp=groups[gId];
    var ph=phaseClass(grp.etiqueta);
    html+='<div class="group-label gl-'+ph+'">'+grp.label+'</div>';
    grp.partidos.forEach(function(p){
      var tieneRes=(parseInt(p.rusultado_equipo1)>0||parseInt(p.resultado_equipo2)>0);
      var enJ=(p.en_juego==='si');
      var estado=tieneRes?'finalizado':(enJ?'en-juego':'');
      var openClass=tieneRes?'':'open';

      var sA=0,sB=0;
      if(parseInt(p.rusultado_equipo1)>0||parseInt(p.resultado_equipo2)>0){parseInt(p.rusultado_equipo1)>parseInt(p.resultado_equipo2)?sA++:sB++;}
      if(parseInt(p.resultado2_equipo1)>0||parseInt(p.resultado2_equipo2)>0){parseInt(p.resultado2_equipo1)>parseInt(p.resultado2_equipo2)?sA++:sB++;}
      if(parseInt(p.resultado3_equipo1)>0||parseInt(p.resultado3_equipo2)>0){parseInt(p.resultado3_equipo1)>parseInt(p.resultado3_equipo2)?sA++:sB++;}
      var wA=sA>sB?'winner':(sB>sA?'loser':'');
      var wB=sB>sA?'winner':(sA>sB?'loser':'');

      var eq1a=p.j1_a||(p.label1||'');
      var eq1b=p.j1_b||'';
      var eq2a=p.j2_a||(p.label2||'');
      var eq2b=p.j2_b||'';

      var summary='Pendiente';
      if(tieneRes){
        var wName=(sA>sB)?eq1a:eq2a;
        summary=wName+' ('+p.rusultado_equipo1+'-'+p.resultado_equipo2+')';
      } else if(enJ) summary='En juego...';

      var badge='<span class="match-enjuego pend">PEND</span>';
      if(tieneRes) badge='<span class="match-enjuego fin">FIN</span>';
      else if(enJ) badge='<span class="match-enjuego si">EN JUEGO</span>';

      var roundLabel=p.textoGrupo?p.textoGrupo.substring(0,3).toUpperCase()+p.partido_nro:'#'+p.partido_nro;

      var compNombre = p.complejo||'';
      var canchaNombre = p.cancha||'';

      html+='<div class="match-card '+estado+' phase-'+ph+'" id="mc'+p.id+'">';
      html+='<button class="match-header '+openClass+'" onclick="toggleCard(this)">';
      html+='<span class="match-round">'+roundLabel+'</span>';
      html+='<span class="match-summary">'+summary+' '+badge+'</span>';
      html+='<span class="match-chevron"><i class="fas fa-chevron-down"></i></span>';
      html+='</button>';
      html+='<div class="match-body '+openClass+'">';
      html+='<div class="match-teams">';
      html+='<div class="team left"><div class="name '+wA+'">'+eq1a+'</div>'+(eq1b?'<div class="name '+wA+'" style="font-size:11px;">'+eq1b+'</div>':'')+'</div>';
      html+='<div class="scores-wrap">';
      html+='<input type="number" min="0" max="7" id="s1a_'+p.id+'" value="'+(parseInt(p.rusultado_equipo1)||'')+'">';
      html+='<span class="vs">-</span>';
      html+='<input type="number" min="0" max="7" id="s1b_'+p.id+'" value="'+(parseInt(p.resultado_equipo2)||'')+'">';
      html+='<span class="sep"></span>';
      html+='<input type="number" min="0" max="7" id="s2a_'+p.id+'" value="'+(parseInt(p.resultado2_equipo1)||'')+'" placeholder="S2">';
      html+='<span class="vs">-</span>';
      html+='<input type="number" min="0" max="7" id="s2b_'+p.id+'" value="'+(parseInt(p.resultado2_equipo2)||'')+'" placeholder="S2">';
      html+='<span class="sep"></span>';
      html+='<input type="number" min="0" max="10" id="s3a_'+p.id+'" value="'+(parseInt(p.resultado3_equipo1)||'')+'" placeholder="S3" class="s3">';
      html+='<span class="vs">-</span>';
      html+='<input type="number" min="0" max="10" id="s3b_'+p.id+'" value="'+(parseInt(p.resultado3_equipo2)||'')+'" placeholder="S3" class="s3">';
      html+='</div>';
      html+='<div class="team right"><div class="name '+wB+'">'+eq2a+'</div>'+(eq2b?'<div class="name '+wB+'" style="font-size:11px;">'+eq2b+'</div>':'')+'</div>';
      html+='</div>';
      html+='<div class="match-actions">';
      html+='<button class="btn btn-play '+(enJ?'active':'')+'" onclick="toggleEnJuego('+p.id+')" id="btnEj_'+p.id+'">';
      html+='<i class="fas fa-'+(enJ?'stop':'play')+'"></i> '+(enJ?'Detener':'En Juego');
      html+='</button>';
      html+='<button class="btn btn-save" onclick="guardar('+p.id+')"><i class="fas fa-save"></i> Guardar</button>';
      html+='</div>';
      if(compNombre||canchaNombre){
        html+='<div class="match-footer">';
        html+='<span>'+(compNombre?'<i class="fas fa-map-marker-alt"></i> '+compNombre:'')+'</span>';
        html+='<span>'+(canchaNombre?'Cancha: '+canchaNombre:'')+'</span>';
        html+='</div>';
      }
      html+='</div></div>';
    });
  });
  document.getElementById('matchCards').innerHTML=html||'<div class="empty">Sin partidos para esta categoria</div>';
}

function toggleCard(btn){
  btn.classList.toggle('open');
  btn.nextElementSibling.classList.toggle('open');
}

async function toggleEnJuego(id){
  var r=await fetch('cargador.php?api=toggle_en_juego&id='+id).then(function(r){return r.json()});
  if(r.success){
    var btn=document.getElementById('btnEj_'+id);
    var card=document.getElementById('mc'+id);
    if(r.en_juego==='si'){
      btn.className='btn btn-play active';btn.innerHTML='<i class="fas fa-stop"></i> Detener';
      card.classList.add('en-juego');card.classList.remove('finalizado');
      showToast('Partido EN JUEGO');
    } else {
      btn.className='btn btn-play';btn.innerHTML='<i class="fas fa-play"></i> En Juego';
      card.classList.remove('en-juego');
      showToast('Partido detenido');
    }
  }
}

async function guardar(id){
  function g(k){return document.getElementById(k+'_'+id).value||0;}
  var url='cargador.php?api=guardar&id='+id+'&s1a='+g('s1a')+'&s1b='+g('s1b')+'&s2a='+g('s2a')+'&s2b='+g('s2b')+'&s3a='+g('s3a')+'&s3b='+g('s3b');
  var r=await fetch(url).then(function(r){return r.json()});
  if(r.success){
    showToast(r.propagado?'Resultado guardado y propagado':'Resultado guardado');
    loadPartidos(currentCat, document.getElementById('bcCat').textContent);
  } else {
    showToast('Error al guardar',true);
  }
}
</script>
</body>
</html>
