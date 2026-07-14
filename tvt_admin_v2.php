<?php
/**
 * tvt_admin_v2.php — Dashboard Super Admin para bt.com.py
 * ================================================================
 * Reemplaza tvt_admin.php con diseño profesional + 8 secciones
 * Backend: tvt_api.php (JSON)
 * Original: tvt_admin.php (sin modificar, como respaldo)
 * ================================================================
 */
session_start();
include_once "db/conection.inc.php";

// -- LOGOUT --
if (isset($_GET['logout'])) { session_destroy(); header('Location: tvt_admin_v2.php'); exit; }

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
        header('Location: tvt_admin_v2.php');
        exit;
    }
    $loginError = 'Usuario o contraseña incorrectos';
}

// -- NO LOGUEADO -> mostrar login --
if (!isset($_SESSION['admin_id'])):
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>BT Admin — Login</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'DM Sans','Segoe UI',system-ui,sans-serif;background:#0f1117;color:#f0f0f5;min-height:100vh;display:flex;align-items:center;justify-content:center}
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
  <h1><i class="fas fa-volleyball-ball" style="color:#6366f1;margin-right:8px;"></i>BT Admin v2</h1>
  <p>Ingresa tus credenciales de administrador</p>
  <?php if(isset($loginError)):?><div class="error"><?=$loginError?></div><?php endif;?>
  <form method="post">
    <div class="fg"><label>Usuario</label><input name="usuario" required autofocus></div>
    <div class="fg"><label>Contraseña</label><input name="pase" type="password" required></div>
    <button class="btn-login" type="submit"><i class="fas fa-sign-in-alt"></i> Ingresar</button>
  </form>
</div>
</body>
</html>
<?php exit; endif;
$adminUser = $_SESSION['admin_user'];
$adminTipo = $_SESSION['admin_tipo'];
?>
<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>BT Admin v2 — Beach Tennis Dashboard</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{
  --bg-primary:#0f1117;--bg-secondary:#1a1d27;--bg-card:#1e2130;--bg-hover:#252838;
  --border:#2a2d3a;--border2:#333648;
  --text-primary:#f0f0f5;--text-secondary:#8b8fa3;--text-muted:#5a5e72;
  --accent:#6366f1;--accent-hover:#818cf8;--accent-glow:rgba(99,102,241,.15);
  --success:#22c55e;--success-bg:rgba(34,197,94,.1);
  --danger:#ef4444;--danger-bg:rgba(239,68,68,.1);
  --warning:#f59e0b;--warning-bg:rgba(245,158,11,.1);
  --info:#3b82f6;--info-bg:rgba(59,130,246,.1);
  --font:'DM Sans',-apple-system,BlinkMacSystemFont,sans-serif;
  --radius-sm:8px;--radius-md:12px;--radius-lg:16px;
  --shadow-card:0 1px 3px rgba(0,0,0,.3),0 1px 2px rgba(0,0,0,.2);
  --shadow-up:0 10px 40px rgba(0,0,0,.4);
  --tr:all .25s cubic-bezier(.4,0,.2,1);
  --sidebar-w:260px;--topbar-h:64px;
}
[data-theme="light"]{
  --bg-primary:#f5f6fa;--bg-secondary:#fff;--bg-card:#fff;--bg-hover:#f0f1f5;
  --border:#e2e4ea;--border2:#d1d5db;
  --text-primary:#1a1d2e;--text-secondary:#6b7084;--text-muted:#9ca0b4;
  --shadow-card:0 1px 3px rgba(0,0,0,.08),0 1px 2px rgba(0,0,0,.04);
  --shadow-up:0 10px 40px rgba(0,0,0,.1);
}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
html{scroll-behavior:smooth;-webkit-text-size-adjust:100%}
body{font-family:var(--font);background:var(--bg-primary);color:var(--text-primary);overflow-x:hidden;min-height:100vh}

/* Layout */
.layout{display:grid;grid-template-columns:1fr;min-height:100vh}
@media(min-width:1024px){.layout{grid-template-columns:var(--sidebar-w) 1fr}}

/* Sidebar */
.sb{background:var(--bg-secondary);border-right:1px solid var(--border);position:fixed;top:0;left:-280px;width:var(--sidebar-w);height:100vh;z-index:900;transition:left .3s ease;display:flex;flex-direction:column;overflow-y:auto}
.sb.open{left:0}
@media(min-width:1024px){.sb{position:sticky;left:0;top:0;height:100vh}}
.sb-ov{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:899}
.sb-ov.show{display:block}
@media(min-width:1024px){.sb-ov{display:none!important}}

.sb-brand{padding:20px 20px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px}
.sb-logo{width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,var(--accent),#a78bfa);display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:700;color:#fff;flex-shrink:0}
.sb-brand-t{font-size:15px;font-weight:700}
.sb-brand-s{font-size:11px;color:var(--text-muted);margin-top:2px}

.nav-s{padding:12px 12px 4px}
.nav-l{font-size:10px;text-transform:uppercase;letter-spacing:1.2px;color:var(--text-muted);font-weight:600;padding:8px 12px 6px}
.nav-i{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:var(--radius-sm);color:var(--text-secondary);font-size:13px;font-weight:500;cursor:pointer;transition:var(--tr);margin-bottom:2px;text-decoration:none}
.nav-i:hover{background:var(--bg-hover);color:var(--text-primary)}
.nav-i.active{background:var(--accent-glow);color:var(--accent);font-weight:600}
.nav-i i{width:18px;text-align:center;flex-shrink:0}
.nav-badge{margin-left:auto;background:var(--accent);color:#fff;font-size:10px;font-weight:700;padding:2px 7px;border-radius:10px}
.sb-foot{margin-top:auto;padding:16px;border-top:1px solid var(--border);display:flex;align-items:center;gap:10px}
.avatar{width:36px;height:36px;border-radius:50%;background:var(--accent);color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0}

/* Topbar */
.topbar{height:var(--topbar-h);background:var(--bg-secondary);border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px;padding:0 16px;position:sticky;top:0;z-index:800}
.hamburger{width:40px;height:40px;border-radius:var(--radius-sm);display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--text-secondary);transition:var(--tr);background:none;border:none}
.hamburger:hover{background:var(--bg-hover)}
@media(min-width:1024px){.hamburger{display:none}}
.topbar-t{font-size:16px;font-weight:700;white-space:nowrap}
.topbar-sp{flex:1}
.topbar-ic{width:38px;height:38px;border-radius:var(--radius-sm);display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--text-secondary);transition:var(--tr);position:relative;background:none;border:none}
.topbar-ic:hover{background:var(--bg-hover);color:var(--text-primary)}
.notif-dot{position:absolute;top:8px;right:8px;width:7px;height:7px;border-radius:50%;background:var(--danger)}

/* Content */
.main{display:flex;flex-direction:column;min-height:100vh}
.content{padding:16px;flex:1}
@media(min-width:768px){.content{padding:24px}}
@media(min-width:1440px){.content{padding:32px}}

.page{display:none}.page.active{display:block}
.pg-hdr{margin-bottom:20px}
.pg-title{font-size:22px;font-weight:700}
.pg-sub{font-size:13px;color:var(--text-muted);margin-top:4px}
.pg-row{display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px}

/* KPI */
.kpi-grid{display:grid;grid-template-columns:1fr;gap:16px;margin-bottom:24px}
@media(min-width:600px){.kpi-grid{grid-template-columns:repeat(2,1fr)}}
@media(min-width:1024px){.kpi-grid{grid-template-columns:repeat(4,1fr)}}
.kpi{background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-md);padding:20px;display:flex;align-items:flex-start;gap:14px;box-shadow:var(--shadow-card);transition:var(--tr);animation:fadeUp .5s ease forwards;opacity:0}
.kpi:hover{transform:translateY(-2px);box-shadow:var(--shadow-up)}
.kpi:nth-child(1){animation-delay:.05s}.kpi:nth-child(2){animation-delay:.1s}.kpi:nth-child(3){animation-delay:.15s}.kpi:nth-child(4){animation-delay:.2s}
@keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
.kpi-ic{width:44px;height:44px;border-radius:var(--radius-sm);display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.kpi-ct{flex:1}
.kpi-lb{font-size:12px;color:var(--text-secondary);font-weight:500;display:block}
.kpi-val{font-size:1.75rem;font-weight:700;display:block;margin-top:4px;line-height:1}
.kpi-ch{font-size:12px;font-weight:600;margin-top:6px;display:inline-flex;align-items:center;gap:3px}

/* Charts */
.charts-g{display:grid;grid-template-columns:1fr;gap:20px;margin-bottom:24px}
@media(min-width:768px){.charts-g{grid-template-columns:repeat(2,1fr)}}
@media(min-width:1440px){.charts-g{grid-template-columns:2fr 1fr}}
.ch-card{background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-md);padding:20px;box-shadow:var(--shadow-card);animation:fadeUp .5s ease forwards;opacity:0;animation-delay:.25s}
.ch-title{font-size:14px;font-weight:600;margin-bottom:16px}
.ch-box{position:relative;height:300px}
@media(min-width:768px){.ch-box{height:320px}}

/* Tables */
.tbl-card{background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-md);box-shadow:var(--shadow-card);overflow:hidden;animation:fadeUp .5s ease forwards;opacity:0;animation-delay:.3s}
.tbl-hdr{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px}
.tbl-title{font-size:15px;font-weight:600}
.tbl-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch}
table{width:100%;border-collapse:collapse;min-width:600px}
th{background:var(--bg-hover);padding:12px 16px;text-align:left;font-size:.75rem;text-transform:uppercase;letter-spacing:.5px;color:var(--text-secondary);font-weight:600;white-space:nowrap}
td{padding:12px 16px;border-top:1px solid var(--border);font-size:.875rem;white-space:nowrap}
tr:hover td{background:var(--bg-hover)}

.badge{padding:4px 10px;border-radius:20px;font-size:.75rem;font-weight:600;display:inline-block}
.badge-s{background:var(--success-bg);color:var(--success)}
.badge-w{background:var(--warning-bg);color:var(--warning)}
.badge-d{background:var(--danger-bg);color:var(--danger)}
.badge-i{background:var(--info-bg);color:var(--info)}
.badge-a{background:var(--accent-glow);color:var(--accent)}

/* Buttons */
.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 16px;border-radius:var(--radius-sm);font-size:13px;font-weight:600;cursor:pointer;border:1px solid transparent;transition:var(--tr);font-family:var(--font);white-space:nowrap}
.btn-p{background:var(--accent);color:#fff;border-color:var(--accent)}.btn-p:hover{background:var(--accent-hover)}
.btn-gh{background:transparent;color:var(--text-secondary);border-color:var(--border)}.btn-gh:hover{background:var(--bg-hover);color:var(--text-primary)}
.btn-ok{background:var(--success);color:#fff}.btn-ok:hover{opacity:.9}
.btn-no{background:var(--danger);color:#fff}.btn-no:hover{opacity:.9}
.btn-sm{padding:6px 12px;font-size:12px}
.btn-warn{background:var(--warning);color:#fff}.btn-warn:hover{opacity:.9}

/* Forms */
.fg{margin-bottom:16px}
.fl{display:block;font-size:12px;font-weight:600;color:var(--text-secondary);margin-bottom:6px;text-transform:uppercase;letter-spacing:.3px}
.fi,.fs{width:100%;padding:10px 14px;border-radius:var(--radius-sm);border:1px solid var(--border);background:var(--bg-primary);color:var(--text-primary);font-size:14px;font-family:var(--font);transition:var(--tr)}
.fi:focus,.fs:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-glow)}
.fs{cursor:pointer}

/* Modal */
.modal-ov{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:1000;align-items:center;justify-content:center;padding:16px}
.modal-ov.show{display:flex}
.modal{background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);width:100%;max-width:600px;max-height:90vh;overflow-y:auto;box-shadow:var(--shadow-up)}
.modal-h{padding:20px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.modal-h h3{font-size:16px;font-weight:700}
.modal-x{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--text-muted);transition:var(--tr);background:none;border:none}
.modal-x:hover{background:var(--bg-hover);color:var(--text-primary)}
.modal-b{padding:24px}
.modal-f{padding:16px 24px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:8px}

/* Tabs */
.tabs{display:flex;gap:0;border-bottom:1px solid var(--border);margin-bottom:20px;overflow-x:auto;flex-wrap:nowrap}
.tab{padding:10px 18px;font-size:13px;font-weight:500;color:var(--text-muted);cursor:pointer;white-space:nowrap;border-bottom:2px solid transparent;transition:var(--tr)}
.tab:hover{color:var(--text-primary)}
.tab.active{color:var(--accent);border-bottom-color:var(--accent);font-weight:600}

/* Filters */
.fbar{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:16px}
.fbar .fs{width:auto;min-width:160px;padding:8px 12px;font-size:13px}

/* Grids */
.g2{display:grid;grid-template-columns:1fr;gap:20px}
@media(min-width:768px){.g2{grid-template-columns:1fr 1fr}}
.g3{display:grid;grid-template-columns:1fr;gap:16px}
@media(min-width:768px){.g3{grid-template-columns:repeat(2,1fr)}}
@media(min-width:1024px){.g3{grid-template-columns:repeat(3,1fr)}}

/* Category card */
.cat-card{background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-md);padding:20px;box-shadow:var(--shadow-card);transition:var(--tr)}
.cat-card:hover{border-color:var(--accent)}

/* Sorteo groups */
.grp-card{background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-md);overflow:hidden}
.grp-hdr{padding:10px 14px;font-size:13px;font-weight:700;background:var(--bg-hover);border-bottom:1px solid var(--border)}
.grp-item{padding:6px 10px;font-size:13px;border-bottom:1px solid var(--border)}
.grp-item:last-child{border-bottom:none}

/* Ranking */
.rank-item{display:flex;align-items:center;gap:14px;padding:14px 16px;border-bottom:1px solid var(--border);transition:var(--tr)}
.rank-item:hover{background:var(--bg-hover)}
.rank-pos{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0}
.rank-pos.g{background:rgba(245,158,11,.15);color:#f59e0b}
.rank-pos.s{background:rgba(148,163,184,.15);color:#94a3b8}
.rank-pos.b{background:rgba(180,83,9,.15);color:#b45309}
.rank-pos.n{background:var(--bg-hover);color:var(--text-muted)}
.rank-name{font-size:14px;font-weight:600}
.rank-detail{font-size:12px;color:var(--text-muted)}
.rank-pts{font-size:18px;font-weight:700;color:var(--accent);margin-left:auto}

/* Horarios grid */
.hor-cell{min-height:40px;border:1px dashed var(--border);border-radius:6px;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--text-muted);font-size:12px;padding:4px;transition:var(--tr)}
.hor-cell:hover{border-color:var(--accent);background:var(--accent-glow)}
.hor-filled{background:var(--accent-glow);border:1px solid var(--accent);border-radius:6px;padding:8px 10px;font-size:11px;font-weight:500;color:var(--text-primary)}

/* Loading */
.loading{position:relative;min-height:100px}
.loading::after{content:'';position:absolute;inset:0;background:var(--bg-card);opacity:.7;border-radius:inherit;z-index:5}
.loading::before{content:'';position:absolute;top:50%;left:50%;width:32px;height:32px;margin:-16px 0 0 -16px;border:3px solid var(--border);border-top-color:var(--accent);border-radius:50%;animation:spin .8s linear infinite;z-index:6}
@keyframes spin{to{transform:rotate(360deg)}}

.empty{text-align:center;padding:48px 24px;color:var(--text-muted)}
.empty i{font-size:40px;margin-bottom:12px;display:block;opacity:.3}

/* Bracket */
.bracket{display:flex;gap:24px;overflow-x:auto;padding:20px 0}
.bracket-round{display:flex;flex-direction:column;gap:16px;min-width:200px}
.bracket-rt{font-size:12px;font-weight:700;text-transform:uppercase;color:var(--text-muted);letter-spacing:.5px;margin-bottom:8px;text-align:center}
.bracket-match{background:var(--bg-hover);border:1px solid var(--border);border-radius:var(--radius-sm);overflow:hidden}
.bracket-team{padding:8px 12px;font-size:12px;display:flex;justify-content:space-between;align-items:center}
.bracket-team+.bracket-team{border-top:1px solid var(--border)}
.bracket-team.w{background:var(--success-bg);font-weight:600}
.bracket-sc{font-weight:700;color:var(--accent)}

/* Event Cards */
.ev-cards{display:grid;grid-template-columns:1fr;gap:16px;margin-bottom:24px}
@media(min-width:600px){.ev-cards{grid-template-columns:repeat(2,1fr)}}
@media(min-width:1024px){.ev-cards{grid-template-columns:repeat(3,1fr)}}
@media(min-width:1440px){.ev-cards{grid-template-columns:repeat(4,1fr)}}
.ev-card{background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-md);overflow:hidden;box-shadow:var(--shadow-card);transition:var(--tr);cursor:pointer;animation:fadeUp .5s ease forwards;opacity:0}
.ev-card:hover{transform:translateY(-3px);box-shadow:var(--shadow-up);border-color:var(--accent)}
.ev-card-top{padding:16px 18px 12px;position:relative;min-height:90px;display:flex;flex-direction:column;justify-content:flex-end;background:linear-gradient(135deg,rgba(99,102,241,.15),rgba(168,85,250,.1))}
.ev-card-top .ev-date{font-size:11px;color:var(--text-muted);position:absolute;top:12px;left:16px}
.ev-card-top .ev-badge{position:absolute;top:10px;right:14px}
.ev-card-top .ev-name{font-size:15px;font-weight:700;line-height:1.3}
.ev-card-bot{padding:12px 18px;display:flex;align-items:center;justify-content:space-between;border-top:1px solid var(--border)}
.ev-card-stat{display:flex;align-items:center;gap:6px;font-size:12px;color:var(--text-secondary)}
.ev-card-stat i{font-size:13px}
.ev-card-btn{padding:6px 14px;border-radius:6px;font-size:12px;font-weight:600;background:var(--accent);color:#fff;border:none;cursor:pointer;transition:var(--tr);text-decoration:none}
.ev-card-btn:hover{background:var(--accent-hover)}

.mt-16{margin-top:16px}.mb-16{margin-bottom:16px}.mb-24{margin-bottom:24px}
.badge-enjuego{background:rgba(239,68,68,.15);color:#ef4444;border:1px solid rgba(239,68,68,.3);animation:pulseEj 2s infinite}
.badge-fin{background:rgba(34,197,94,.15);color:#22c55e;border:1px solid rgba(34,197,94,.3)}
.badge-pend{background:rgba(139,143,163,.1);color:var(--text-muted);border:1px solid var(--border)}
.btn-enjuego{background:rgba(239,68,68,.1);color:#ef4444;border:1px solid rgba(239,68,68,.3);font-size:10px;padding:4px 8px;border-radius:6px;cursor:pointer;transition:var(--tr);font-weight:600}
.btn-enjuego:hover{background:rgba(239,68,68,.2)}
.btn-enjuego.active{background:#ef4444;color:#fff}
.tr-enjuego{background:rgba(239,68,68,.05)!important}
@keyframes pulseEj{0%,100%{opacity:1}50%{opacity:.5}}
</style>
</head>
<body>
<div class="layout">

<!-- SIDEBAR -->
<div class="sb-ov" id="sbOv" onclick="toggleSb()"></div>
<aside class="sb" id="sb">
  <div class="sb-brand">
    <div class="sb-logo">BT</div>
    <div><div class="sb-brand-t">BT Admin</div><div class="sb-brand-s">Beach Tennis Manager</div></div>
  </div>
  <div class="nav-s">
    <div class="nav-l">Principal</div>
    <div class="nav-i active" onclick="goPage('dashboard')"><i class="fas fa-chart-pie"></i> Dashboard</div>
    <div class="nav-i" onclick="goPage('eventos')"><i class="fas fa-trophy"></i> Eventos <span class="nav-badge" id="navEvBadge">—</span></div>
    <div class="nav-i" onclick="goPage('inscripciones')"><i class="fas fa-users"></i> Inscripciones</div>
    <div class="nav-i" onclick="goPage('categorias')"><i class="fas fa-tags"></i> Categorías</div>
    <div class="nav-i" onclick="goPage('jugadores')"><i class="fas fa-user-friends"></i> Jugadores</div>
  </div>
  <div class="nav-s">
    <div class="nav-l">Competencia</div>
    <div class="nav-i" onclick="goPage('resultados')"><i class="fas fa-clipboard-list"></i> Resultados</div>
    <div class="nav-i" onclick="goPage('horarios')"><i class="fas fa-calendar-alt"></i> Horarios</div>
    <div class="nav-i" onclick="goPage('ranking')"><i class="fas fa-medal"></i> Ranking</div>
  </div>
  <div class="nav-s">
    <div class="nav-l">Administración</div>
    <div class="nav-i" onclick="goPage('admins')"><i class="fas fa-user-shield"></i> Administradores</div>
    <div class="nav-i" onclick="goPage('puntajes')"><i class="fas fa-star"></i> Puntajes</div>
  </div>
  <div class="nav-s">
    <div class="nav-l">Sistema</div>
    <div class="nav-i" onclick="toggleTheme()"><i class="fas fa-adjust"></i> Cambiar tema</div>
    <a class="nav-i" href="tvt_admin.php" style="text-decoration:none"><i class="fas fa-arrow-left"></i> TVT Admin v1</a>
    <a class="nav-i" href="tvt_plantillas.php" style="text-decoration:none"><i class="fas fa-cogs"></i> Plantillas TVT</a>
    <a class="nav-i" href="?logout" style="text-decoration:none;color:var(--danger);"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a>
  </div>
  <div class="sb-foot">
    <div class="avatar"><?=strtoupper(substr($adminUser,0,2))?></div>
    <div><div style="font-size:13px;font-weight:600;"><?=htmlspecialchars($adminUser)?></div><div style="font-size:11px;color:var(--success);"><i class="fas fa-circle" style="font-size:7px;margin-right:4px;"></i><?=$adminTipo?></div></div>
  </div>
</aside>

<!-- MAIN -->
<div class="main">
  <header class="topbar">
    <button class="hamburger" onclick="toggleSb()"><i class="fas fa-bars"></i></button>
    <div class="topbar-t" id="topTitle">Dashboard</div>
    <div class="topbar-sp"></div>
    <button class="topbar-ic" onclick="loadAll()" title="Refrescar"><i class="fas fa-sync-alt"></i></button>
    <button class="topbar-ic" onclick="toggleTheme()" title="Tema"><i class="fas fa-moon" id="themeIc"></i></button>
  </header>

  <div class="content">

    <!-- ═══ 1. DASHBOARD ═══ -->
    <div class="page active" id="pg-dashboard">
      <div class="pg-hdr"><div class="pg-title">Dashboard</div><div class="pg-sub" id="dashDate"></div></div>
      <div class="kpi-grid" id="kpiGrid"></div>

      <!-- Cards de Torneos -->
      <div class="pg-hdr" style="margin-top:8px;"><div class="pg-title" style="font-size:18px;">Torneos</div><div class="pg-sub">Seleccioná un torneo para administrar sus categorías y generar sorteos</div></div>
      <div class="ev-cards" id="evCards"></div>

      <div class="charts-g">
        <div class="ch-card"><div class="ch-title">Inscripciones por Torneo</div><div class="ch-box"><canvas id="chInsc"></canvas></div></div>
        <div class="ch-card"><div class="ch-title">Distribución por Categoría</div><div class="ch-box"><canvas id="chCat"></canvas></div></div>
      </div>
      <div class="g2 mb-24">
        <div class="tbl-card">
          <div class="tbl-hdr"><span class="tbl-title">Eventos Activos</span><button class="btn btn-gh btn-sm" onclick="goPage('eventos')">Ver todos</button></div>
          <div class="tbl-wrap"><table><thead><tr><th>Torneo</th><th>Equipos</th><th>Partidos</th><th>Estado</th></tr></thead><tbody id="dashEv"></tbody></table></div>
        </div>
        <div class="tbl-card">
          <div class="tbl-hdr"><span class="tbl-title">Últimas Inscripciones</span><button class="btn btn-gh btn-sm" onclick="goPage('inscripciones')">Ver todas</button></div>
          <div class="tbl-wrap"><table><thead><tr><th>Equipo</th><th>Categoría</th><th>Evento</th></tr></thead><tbody id="dashInscr"></tbody></table></div>
        </div>
      </div>
    </div>

    <!-- ═══ 2. EVENTOS ═══ -->
    <div class="page" id="pg-eventos">
      <div class="pg-row">
        <div><div class="pg-title">Gestión de Eventos</div><div class="pg-sub">Todos los eventos del circuito</div></div>
        <button class="btn btn-p" onclick="abrirNuevoEvento()"><i class="fas fa-plus"></i> Nuevo Evento</button>
      </div>
      <div class="tabs" id="tabsEv">
        <div class="tab active" onclick="filtEv('',this)">Todos</div>
        <div class="tab" onclick="filtEv('activo',this)">Activos</div>
        <div class="tab" onclick="filtEv('registro',this)">Registro</div>
        <div class="tab" onclick="filtEv('culminado',this)">Culminados</div>
      </div>
      <div class="tbl-card"><div class="tbl-wrap"><table><thead><tr><th>ID</th><th>Torneo</th><th>Fecha</th><th>Categorías</th><th>Equipos</th><th>Partidos</th><th>Estado</th><th>Acciones</th></tr></thead><tbody id="tbEv"></tbody></table></div></div>
    </div>

    <!-- ═══ 3. INSCRIPCIONES ═══ -->
    <div class="page" id="pg-inscripciones">
      <div class="pg-row"><div><div class="pg-title">Inscripciones y Equipos</div><div class="pg-sub">Gestión de inscripciones por evento</div></div></div>
      <div class="fbar">
        <select class="fs" id="fInscEv" onchange="loadInscCats()"><option value="">Todos los eventos</option></select>
        <select class="fs" id="fInscCat" onchange="loadInscripciones()"><option value="">Todas las categorías</option></select>
        <input class="fs" type="text" id="fInscBuscar" placeholder="Buscar por nombre o CI..." style="min-width:180px;" onkeydown="if(event.key==='Enter')loadInscripciones()">
        <button class="btn btn-pr btn-sm" onclick="loadInscripciones()"><i class="fas fa-search"></i></button>
        <span class="tbl-title" id="inscCount" style="margin-left:auto;font-size:13px;"></span>
      </div>
      <div class="tbl-card"><div class="tbl-wrap"><table><thead><tr><th>#</th><th>Jugador 1</th><th>CI</th><th>Jugador 2</th><th>CI</th><th>Categoría</th><th>Estado</th><th>Acción</th></tr></thead><tbody id="tbInsc"></tbody></table></div></div>
    </div>

    <!-- ═══ 4. CATEGORÍAS (TVT Admin) ═══ -->
    <div class="page" id="pg-categorias">
      <div class="pg-row" id="catHeader">
        <div><div class="pg-title">Categorías por Evento</div><div class="pg-sub">Seleccioná un evento para ver sus categorías</div></div>
        <div class="fbar" style="margin:0;gap:6px;">
          <select class="fs" id="fCatEv" onchange="loadCategorias()"><option value="">Seleccionar evento</option></select>
          <button class="btn btn-gh btn-sm" onclick="toggleAllCats(true)" title="Colapsar todas"><i class="fas fa-compress-alt"></i></button>
          <button class="btn btn-gh btn-sm" onclick="toggleAllCats(false)" title="Expandir todas"><i class="fas fa-expand-alt"></i></button>
          <button class="btn btn-gh btn-sm" onclick="goPage('dashboard')"><i class="fas fa-arrow-left"></i> Volver</button>
        </div>
      </div>
      <!-- KPIs del evento -->
      <div class="kpi-grid" id="catKpis" style="display:none;"></div>
      <!-- Categorías detalle -->
      <div id="gridCats"></div>
    </div>

    <!-- ═══ JUGADORES ═══ -->
    <div class="page" id="pg-jugadores">
      <div class="pg-row">
        <div><div class="pg-title">Jugadores</div><div class="pg-sub">Buscar, ver y editar jugadores registrados</div></div>
      </div>
      <div class="fbar">
        <input class="fs" type="text" id="fJugBuscar" placeholder="Buscar por nombre, CI, email..." style="min-width:200px;" onkeydown="if(event.key==='Enter')loadJugadores(1)">
        <select class="fs" id="fJugEstado" onchange="loadJugadores(1)">
          <option value="">Todos los estados</option>
          <option value="activo">Activo</option>
          <option value="inactivo">Inactivo</option>
          <option value="eliminado">Eliminado</option>
        </select>
        <select class="fs" id="fJugTipo" onchange="loadJugadores(1)">
          <option value="">Todos los tipos</option>
          <option value="jugador">Jugador</option>
          <option value="admin">Admin</option>
          <option value="socio">Socio</option>
        </select>
        <button class="btn btn-pr btn-sm" onclick="loadJugadores(1)"><i class="fas fa-search"></i> Buscar</button>
      </div>
      <div class="tbl-card">
        <div class="tbl-hdr">
          <span class="tbl-title" id="jugCount">Jugadores</span>
        </div>
        <div class="tbl-wrap">
          <table>
            <thead>
              <tr>
                <th>CI</th>
                <th>Nombre</th>
                <th>Email</th>
                <th>Cel</th>
                <th>Sexo</th>
                <th>Estado</th>
                <th>Tipo</th>
                <th>Acción</th>
              </tr>
            </thead>
            <tbody id="tbJug"></tbody>
          </table>
        </div>
        <div id="jugPag" style="padding:12px 16px;display:flex;align-items:center;justify-content:center;gap:6px;flex-wrap:wrap;"></div>
      </div>
    </div>

    <!-- Modal Editar Jugador -->
    <div class="modal-ov" id="mdJugEdit">
      <div class="modal-box" style="max-width:560px;">
        <div class="modal-hdr"><span id="mdJugTitle">Editar Jugador</span><button class="modal-x" onclick="closeModal('mdJugEdit')">&times;</button></div>
        <div class="modal-body" style="padding:16px;display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <input type="hidden" id="jugEditId">
          <div><label style="font-size:11px;color:var(--text-muted);display:block;margin-bottom:4px;">Nombre</label><input class="fs" id="jugEditNombre"></div>
          <div><label style="font-size:11px;color:var(--text-muted);display:block;margin-bottom:4px;">Apellido</label><input class="fs" id="jugEditApellido"></div>
          <div><label style="font-size:11px;color:var(--text-muted);display:block;margin-bottom:4px;">CI</label><input class="fs" id="jugEditCi"></div>
          <div><label style="font-size:11px;color:var(--text-muted);display:block;margin-bottom:4px;">Email</label><input class="fs" id="jugEditEmail"></div>
          <div><label style="font-size:11px;color:var(--text-muted);display:block;margin-bottom:4px;">Celular</label><input class="fs" id="jugEditCel"></div>
          <div><label style="font-size:11px;color:var(--text-muted);display:block;margin-bottom:4px;">WhatsApp</label><input class="fs" id="jugEditWhatsapp"></div>
          <div><label style="font-size:11px;color:var(--text-muted);display:block;margin-bottom:4px;">Sexo</label>
            <select class="fs" id="jugEditSexo"><option value="hombre">Hombre</option><option value="mujer">Mujer</option><option value="mixto">Mixto</option></select>
          </div>
          <div><label style="font-size:11px;color:var(--text-muted);display:block;margin-bottom:4px;">Fecha Nacimiento</label><input class="fs" type="date" id="jugEditFechaNac"></div>
          <div><label style="font-size:11px;color:var(--text-muted);display:block;margin-bottom:4px;">Ciudad</label><input class="fs" id="jugEditCiudad"></div>
          <div><label style="font-size:11px;color:var(--text-muted);display:block;margin-bottom:4px;">Nacionalidad</label><input class="fs" id="jugEditNacionalidad"></div>
          <div><label style="font-size:11px;color:var(--text-muted);display:block;margin-bottom:4px;">Estado</label>
            <select class="fs" id="jugEditEstado"><option value="activo">Activo</option><option value="inactivo">Inactivo</option><option value="eliminado">Eliminado</option></select>
          </div>
          <div><label style="font-size:11px;color:var(--text-muted);display:block;margin-bottom:4px;">Tipo</label>
            <select class="fs" id="jugEditTipo"><option value="jugador">Jugador</option><option value="admin">Admin</option><option value="socio">Socio</option></select>
          </div>
          <div style="grid-column:1/-1;"><label style="font-size:11px;color:var(--text-muted);display:block;margin-bottom:4px;">Observación</label><textarea class="fs" id="jugEditObs" rows="2" style="width:100%;resize:vertical;"></textarea></div>
        </div>
        <div style="padding:12px 16px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:8px;">
          <button class="btn btn-gh btn-sm" onclick="closeModal('mdJugEdit')">Cancelar</button>
          <button class="btn btn-pr btn-sm" onclick="guardarJugador()"><i class="fas fa-save"></i> Guardar</button>
        </div>
      </div>
    </div>


    <!-- ═══ 6. RESULTADOS ═══ -->
    <div class="page" id="pg-resultados">
      <div class="pg-row"><div><div class="pg-title">Resultados</div><div class="pg-sub">Carga de resultados por categoría e instancia</div></div></div>
      <div class="fbar">
        <select class="fs" id="fResEv" onchange="loadResCats()"><option value="">Seleccionar evento</option></select>
        <select class="fs" id="fResCat" onchange="loadResultados()"><option value="">Seleccionar categoría</option></select>
        <select class="fs" id="fResGrupo" onchange="loadResultados()"><option value="">Todos los grupos</option></select>
      </div>
      <div class="tbl-card"><div class="tbl-hdr"><span class="tbl-title" id="resCount">Partidos</span></div><div class="tbl-wrap"><table><thead><tr><th>Grupo</th><th>#</th><th>Equipo 1</th><th>S1</th><th>S2</th><th>S3</th><th>Equipo 2</th><th>Estado</th><th>Acción</th></tr></thead><tbody id="tbRes"></tbody></table></div></div>
    </div>

    <!-- ═══ 7. HORARIOS ═══ -->
    <div class="page" id="pg-horarios">
      <div class="pg-row"><div><div class="pg-title">Horarios y Canchas</div><div class="pg-sub">Grilla de asignación (próximamente conectada a la BD)</div></div></div>
      <div class="ch-card"><div class="ch-title">Grilla de Canchas × Horarios</div>
        <div class="tbl-wrap"><table id="tblHor"><thead id="thHor"></thead><tbody id="tbHor"></tbody></table></div>
      </div>
    </div>

    <!-- ═══ 8. RANKING ═══ -->
    <div class="page" id="pg-ranking">
      <div class="pg-row">
        <div><div class="pg-title">Ranking de Jugadores</div><div class="pg-sub">Cálculo automático por evento culminado</div></div>
      </div>

      <!-- TABS -->
      <div class="tabs" id="tabsRank">
        <div class="tab active" onclick="switchRankTab('calcular',this)">⚡ Calcular Ranking</div>
        <div class="tab" onclick="switchRankTab('ver',this)">📋 Ver Ranking</div>
        <div class="tab" onclick="switchRankTab('etiquetas',this)">🏷️ Etiquetas / Puntajes</div>
      </div>

      <!-- TAB: CALCULAR -->
      <div id="rankTab-calcular">
        <div class="ch-card mb-24">
          <div class="ch-title">Eventos disponibles para calcular ranking</div>
          <div style="font-size:12px;color:var(--text-muted);margin-bottom:16px;">
            Solo se muestran eventos con estado <strong>culminado</strong>. El cálculo usa <code>_ranking_config</code> y detecta automáticamente las instancias de cada categoría.
          </div>
          <div id="rankEventos">
            <div class="loading" style="min-height:80px;border-radius:8px;"></div>
          </div>
        </div>
        <!-- Log de resultado -->
        <div id="rankLog" style="display:none;">
          <div class="tbl-card">
            <div class="tbl-hdr"><span class="tbl-title" id="rankLogTitle">Resultado del cálculo</span><button class="btn btn-gh btn-sm" onclick="document.getElementById('rankLog').style.display='none'">Cerrar</button></div>
            <div style="padding:16px;font-size:12px;font-family:monospace;background:var(--bg-primary);max-height:400px;overflow-y:auto;" id="rankLogBody"></div>
          </div>
        </div>
      </div>

      <!-- TAB: VER RANKING -->
      <div id="rankTab-ver" style="display:none;">
        <div class="fbar" style="margin-bottom:16px;">
          <input type="text" class="fi" style="width:auto;min-width:240px;" placeholder="Buscar jugador..." id="fRankQ" oninput="debounceRank()">
          <select class="fs" id="fRankCat" onchange="loadRanking()">
            <option value="">Todas las categorías</option>
          </select>
        </div>
        <div class="g2">
          <div class="tbl-card"><div class="tbl-hdr"><span class="tbl-title">Top Jugadores</span><span id="rankTotal" style="font-size:12px;color:var(--text-muted);"></span></div><div id="rankList"></div></div>
          <div class="ch-card"><div class="ch-title">Top 10 — Puntos</div><div class="ch-box"><canvas id="chRank"></canvas></div></div>
        </div>
      </div>

      <!-- TAB: ETIQUETAS / PUNTAJES -->
      <div id="rankTab-etiquetas" style="display:none;">
        <div class="ch-card mb-24">
          <div class="ch-title">Configurar Etiquetas de Puntaje por Evento</div>
          <div style="font-size:12px;color:var(--text-muted);margin-bottom:16px;">
            Asigna las etiquetas de puntaje a cada categoría de un evento. El sistema detecta automáticamente las fases (grupos, 8vos, cuartos, semis, final) según la plantilla/sorteo de cada categoría.
          </div>

          <!-- Selector de evento -->
          <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:20px;">
            <select class="fs" id="etqEvSel" onchange="loadEtiquetasEvento()" style="min-width:280px;">
              <option value="">— Seleccioná un evento —</option>
            </select>
            <button class="btn btn-ok btn-sm" id="btnAutoEtq" onclick="autoGenerarEtiquetas()" style="display:none;">
              <i class="fas fa-magic"></i> Auto-Generar Etiquetas
            </button>
            <button class="btn btn-warn btn-sm" id="btnLimpiarEtq" onclick="limpiarEtiquetasEvento()" style="display:none;">
              <i class="fas fa-trash"></i> Limpiar Todo
            </button>
          </div>

          <!-- Resultado auto-generación -->
          <div id="etqAutoLog" style="display:none;" class="mb-24">
            <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-md);padding:16px;font-size:12px;max-height:300px;overflow-y:auto;" id="etqAutoLogBody"></div>
          </div>

          <!-- Tabla de etiquetas -->
          <div id="etqContenido">
            <div class="empty"><i class="fas fa-tags"></i><p>Seleccioná un evento para ver sus etiquetas</p></div>
          </div>
        </div>

        <!-- Modal agregar etiqueta manual -->
        <div id="etqAddRow" style="display:none;background:var(--bg-card);border:1px solid var(--accent);border-radius:var(--radius-md);padding:16px;margin-top:12px;">
          <div style="font-weight:700;margin-bottom:12px;font-size:14px;">➕ Agregar etiqueta manual</div>
          <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
            <div>
              <label style="font-size:11px;color:var(--text-muted);display:block;margin-bottom:4px;">Categoría</label>
              <select class="fs" id="etqAddCat" style="min-width:180px;"></select>
            </div>
            <div>
              <label style="font-size:11px;color:var(--text-muted);display:block;margin-bottom:4px;">Etiqueta</label>
              <select class="fs" id="etqAddEtiq" style="min-width:180px;"></select>
            </div>
            <div>
              <label style="font-size:11px;color:var(--text-muted);display:block;margin-bottom:4px;">Puntos</label>
              <input type="number" class="fi" id="etqAddPts" style="width:80px;" min="0" value="0">
            </div>
            <button class="btn btn-ok btn-sm" onclick="guardarEtiquetaManual()"><i class="fas fa-check"></i> Guardar</button>
            <button class="btn btn-gh btn-sm" onclick="document.getElementById('etqAddRow').style.display='none'">Cancelar</button>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ 9. ADMINISTRADORES ═══ -->
    <div class="page" id="pg-admins">
      <div class="pg-row">
        <div><div class="pg-title">Administradores</div><div class="pg-sub">Gestión de admins y asignación de eventos al cargador</div></div>
        <button class="btn btn-p" onclick="openModal('modalAdmin')"><i class="fas fa-plus"></i> Nuevo Admin</button>
      </div>

      <div class="tbl-card">
        <div class="tbl-hdr">
          <span class="tbl-title">Usuarios Administradores</span>
        </div>
        <div class="tbl-wrap">
          <table>
            <thead><tr>
              <th>ID</th><th>Usuario</th><th>Tipo</th><th>Eventos Asignados</th><th>Acciones</th>
            </tr></thead>
            <tbody id="tbAdmins"></tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ═══ 10. PUNTAJES ═══ -->
    <div class="page" id="pg-puntajes">
      <div class="pg-row">
        <div><div class="pg-title">Puntajes por Evento</div><div class="pg-sub">Configuración de puntos por ronda y categoría para el cálculo de ranking</div></div>
      </div>

      <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:16px;">
        <select id="pjEvento" class="fi" style="max-width:300px;" onchange="loadPuntajesEvento()">
          <option value="">— Seleccionar evento —</option>
        </select>
        <select id="pjEventoOrigen" class="fi" style="max-width:250px;display:none;">
          <option value="">— Copiar desde —</option>
        </select>
        <button class="btn btn-gh btn-sm" id="btnCopiarPj" style="display:none;" onclick="copiarPuntajes()"><i class="fas fa-copy"></i> Copiar</button>
        <button class="btn btn-p btn-sm" id="btnGuardarPj" style="display:none;" onclick="guardarTodosPuntajes()"><i class="fas fa-save"></i> Guardar todo</button>
      </div>

      <div id="pjContainer"></div>
    </div>

  </div><!-- /content -->
</div><!-- /main -->
</div><!-- /layout -->

<!-- MODAL: Nuevo Evento -->
<div class="modal-ov" id="modalEvento">
  <div class="modal" style="max-width:780px;">
    <div class="modal-h">
      <h3 id="modalEventoTitle"><i class="fas fa-trophy" style="margin-right:8px;color:var(--accent);"></i>Nuevo Evento</h3>
      <button class="modal-x" onclick="closeModal('modalEvento')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-b" style="padding:20px 24px;">

      <!-- TABS del formulario -->
      <div class="tabs" id="tabsEvForm" style="margin-bottom:20px;">
        <div class="tab active" onclick="switchEvTab(1,this)">📋 General</div>
        <div class="tab" onclick="switchEvTab(2,this)">⚙️ Configuración</div>
        <div class="tab" onclick="switchEvTab(3,this)">💰 Costos y Fechas</div>
        <div class="tab" onclick="switchEvTab(4,this)">✉️ Email</div>
        <div class="tab" onclick="switchEvTab(5,this)">🏷️ Categorías</div>
      </div>

      <!-- TAB 1: General -->
      <div id="evTab-1">
        <div class="g2">
          <div class="fg">
            <label class="fl">Nombre del Evento *</label>
            <input class="fi" type="text" id="evNombre" placeholder="Ej: 8va. FECHA" oninput="autoUrlAmigable()">
          </div>
          <div class="fg">
            <label class="fl">URL Amigable *</label>
            <input class="fi" type="text" id="evUrl" placeholder="8va-fecha">
          </div>
        </div>
        <div class="g2">
          <div class="fg">
            <label class="fl">Estado *</label>
            <select class="fs" id="evEstado">
              <option value="inactivo">Inactivo</option>
              <option value="registro" selected>Registro</option>
              <option value="activo">Activo</option>
              <option value="previsualizacion">Previsualización</option>
              <option value="culminado">Culminado</option>
            </select>
          </div>
          <div class="fg">
            <label class="fl">Prioridad</label>
            <input class="fi" type="number" id="evPrioridad" value="1" min="1" max="99">
          </div>
        </div>
        <div class="g2">
          <div class="fg">
            <label class="fl">Circuito</label>
            <select class="fs" id="evCircuito">
              <option value="1">CIRCUITO HERNANDARIENSE DE BEACH TENNIS</option>
            </select>
          </div>
          <div class="fg">
            <label class="fl">Organizador</label>
            <select class="fs" id="evOrganizador">
              <option value="1">BEACH TENNIS</option>
            </select>
          </div>
        </div>
        <div class="g2">
          <div class="fg">
            <label class="fl">Ciudad</label>
            <select class="fs" id="evCiudad"><option value="">Seleccionar...</option></select>
          </div>
          <div class="fg">
            <label class="fl">Tipo/Modalidad</label>
            <select class="fs" id="evTipo">
              <option value="">Sin especificar</option>
              <option value="1">Doble Eliminación (Rondas)</option>
              <option value="2">Todos vs Todos</option>
              <option value="3">Eliminación Directa (nueva)</option>
            </select>
          </div>
        </div>
        <div class="fg">
          <label class="fl">Flyer del Evento</label>
          <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <input class="fi" type="text" id="evFlyerName" placeholder="nombre-del-flyer.jpeg" style="flex:1;min-width:200px;" readonly>
            <label class="btn btn-gh btn-sm" style="cursor:pointer;white-space:nowrap;">
              <i class="fas fa-image"></i> Subir flyer
              <input type="file" id="evFlyerFile" accept="image/*" style="display:none;" onchange="subirFlyerEvento()">
            </label>
          </div>
          <div id="evFlyerPreview" style="margin-top:8px;display:none;align-items:center;gap:8px;">
            <img id="evFlyerPreviewImg" src="" style="max-height:80px;border-radius:6px;border:1px solid var(--border);">
            <span id="evFlyerStatus" style="font-size:11px;color:var(--success);"></span>
          </div>
          <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">Portada del evento que se muestra en las cards del inicio.</div>
        </div>
        <div class="fg">
          <label class="fl">Imagen del Programa</label>
          <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <input class="fi" type="text" id="evDescImgUrl" placeholder="https://bt.com.py/sistema@/_lib/file/img/nombre-imagen.jpeg" style="flex:1;min-width:200px;">
            <label class="btn btn-gh btn-sm" style="cursor:pointer;white-space:nowrap;">
              <i class="fas fa-upload"></i> Subir imagen
              <input type="file" id="evImgFile" accept="image/*" style="display:none;" onchange="subirImagenEvento()">
            </label>
          </div>
          <div id="evImgPreview" style="margin-top:8px;display:none;">
            <img id="evImgPreviewImg" src="" style="max-height:80px;border-radius:6px;border:1px solid var(--border);">
            <span id="evImgStatus" style="font-size:11px;color:var(--success);margin-left:8px;"></span>
          </div>
          <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">Se sube a /sistema@/_lib/file/img/ y se inserta en la descripción.</div>
        </div>
        <input type="hidden" id="evReglamentacion" value="">
        <input type="hidden" id="evId" value="">
      </div>

      <!-- TAB 2: Configuración -->
      <div id="evTab-2" style="display:none;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
          <div class="fg">
            <label class="fl">Versión Formulario Inscripción</label>
            <select class="fs" id="evVersionForm">
              <option value="v2" selected>v2 (actual)</option>
              <option value="v1">v1 (legacy)</option>
            </select>
          </div>
          <div class="fg">
            <label class="fl">URL Fixture</label>
            <select class="fs" id="evUrlFixture">
              <option value="grafico-llaves" selected>grafico-llaves</option>
              <option value="grafico-llaves-v2">grafico-llaves-v2</option>
            </select>
          </div>
        </div>
        <div style="background:var(--bg-hover);border-radius:var(--radius-sm);padding:16px;margin-bottom:16px;">
          <div style="font-size:12px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px;">Visibilidad de Botones</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div class="fg" style="margin:0;">
              <label class="fl">Botón Fixture</label>
              <select class="fs" id="evBtnFixture">
                <option value="visible" selected>Visible</option>
                <option value="oculto">Oculto</option>
              </select>
            </div>
            <div class="fg" style="margin:0;">
              <label class="fl">Botón Inscripción</label>
              <select class="fs" id="evBtnInscripcion">
                <option value="si" selected>Visible</option>
                <option value="no">Oculto</option>
              </select>
            </div>
            <div class="fg" style="margin:0;">
              <label class="fl">Cantidad Inscriptos</label>
              <select class="fs" id="evCantInscriptos">
                <option value="si" selected>Visible</option>
                <option value="no">Oculto</option>
              </select>
            </div>
            <div class="fg" style="margin:0;">
              <label class="fl">Botón Llaves</label>
              <select class="fs" id="evBtnLlaves">
                <option value="oculto" selected>Oculto</option>
                <option value="visible">Visible</option>
              </select>
            </div>
          </div>
        </div>
        <div style="background:var(--bg-hover);border-radius:var(--radius-sm);padding:16px;">
          <div style="font-size:12px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px;">Bases y Condiciones</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div class="fg" style="margin:0;">
              <label class="fl">Bases y Condiciones</label>
              <select class="fs" id="evBasesCond">
                <option value="">Seleccione</option>
                <option value="a">Ferparpa</option>
                <option value="b">Otros</option>
              </select>
            </div>
            <div class="fg" style="margin:0;">
              <input type="hidden" id="evFixturePublicado" value="si">
            </div>
          </div>
        </div>
      </div>

      <!-- TAB 3: Costos y Fechas -->
      <div id="evTab-3" style="display:none;">
        <div class="g2">
          <div class="fg">
            <label class="fl">Fecha del Evento *</label>
            <input class="fi" type="date" id="evFecha">
          </div>
          <div class="fg">
            <label class="fl">Fecha Fin del Evento</label>
            <input class="fi" type="date" id="evFechaFin">
          </div>
        </div>
        <div class="g2">
          <div class="fg">
            <label class="fl">Fecha Fin Inscripción</label>
            <input class="fi" type="date" id="evFechaFinInscr">
          </div>
          <div class="fg">
            <label class="fl">Fecha Fin Pago</label>
            <input class="fi" type="date" id="evFechaFinPago">
          </div>
        </div>
        <div class="g2">
          <div class="fg">
            <label class="fl">Costo Único (Gs.)</label>
            <input class="fi" type="number" id="evCosto1" placeholder="0" min="0">
          </div>
          <div class="fg">
            <label class="fl">Costo Alternativo (Gs.)</label>
            <input class="fi" type="number" id="evCosto2" placeholder="0" min="0">
          </div>
        </div>
      </div>

      <!-- TAB 4: Email -->
      <div id="evTab-4" style="display:none;">
        <div class="g2" style="margin-bottom:16px;">
          <div class="fg">
            <label class="fl">Email de Inscripción</label>
            <select class="fs" id="evEmailInscr" onchange="toggleAsuntoEmail()">
              <option value="no" selected>No enviar</option>
              <option value="si">Enviar</option>
            </select>
          </div>
          <div class="fg">
            <label class="fl">Asunto del Email</label>
            <input class="fi" type="text" id="evAsuntoEmail" placeholder="Se autocompleta con el nombre del evento" readonly style="background:var(--bg-hover);color:var(--text-muted);">
          </div>
        </div>
        <div class="fg" id="fgTextoEmail">
          <label class="fl">Texto del Email</label>
          <div style="background:var(--bg-hover);border:1px solid var(--border);border-radius:var(--radius-sm);padding:12px;font-size:12px;color:var(--text-muted);margin-bottom:8px;line-height:1.6;">
            <strong style="color:var(--text-primary);">Plantilla estándar — Variables:</strong>
            <code style="color:var(--accent);">{usuario}</code> · <code style="color:var(--accent);">{evento}</code> · <code style="color:var(--accent);">{fecha}</code> · <code style="color:var(--accent);">{nombre}</code> · <code style="color:var(--accent);">{apellido}</code> · <code style="color:var(--accent);">{ci}</code> · <code style="color:var(--accent);">{costo}</code> · <code style="color:var(--accent);">{link_pago}</code> · <code style="color:var(--accent);">{limite_pago}</code>
          </div>
          <textarea class="fi" id="evTextoEmail" rows="6" style="font-size:11px;color:var(--text-muted);background:var(--bg-hover);" readonly></textarea>
        </div>
      </div>

      <!-- TAB 5: Categorías -->
      <div id="evTab-5" style="display:none;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
          <div style="font-size:13px;color:var(--text-muted);">Categorías habilitadas para este evento</div>
          <button class="btn btn-p btn-sm" onclick="agregarCatEvento()"><i class="fas fa-plus"></i> Agregar categoría</button>
        </div>

        <!-- Formulario agregar nueva categoría -->
        <div class="fg" style="margin-bottom:16px;display:none;background:var(--bg-hover);padding:14px;border-radius:var(--radius-sm);" id="evCatAddForm">
          <label class="fl" style="margin-bottom:8px;">Nueva categoría</label>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:8px;">
            <div><label class="fl" style="font-size:11px;">Categoría</label><select class="fs" id="evCatSelect"></select></div>
            <div><label class="fl" style="font-size:11px;">Estado</label>
              <select class="fs" id="evCatNuevoEstado">
                <option value="activo">Activo</option>
                <option value="inactivo">Inactivo</option>
              </select>
            </div>
            <div><label class="fl" style="font-size:11px;">Cupo</label>
              <select class="fs" id="evCatNuevoCupo">
                <option value="disponible">Disponible</option>
                <option value="lleno">Lleno</option>
              </select>
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:8px;margin-bottom:10px;">
            <div><label class="fl" style="font-size:11px;">Visualizar en llaves</label>
              <select class="fs" id="evCatNuevoLlaves">
                <option value="si">Sí</option>
                <option value="no">No</option>
              </select>
            </div>
            <div><label class="fl" style="font-size:11px;">Sexo</label>
              <select class="fs" id="evCatNuevoSexo">
                <option value="">Sin especificar</option>
                <option value="hombre">Hombre</option>
                <option value="mujer">Mujer</option>
                <option value="mixto">Mixto</option>
              </select>
            </div>
            <div><label class="fl" style="font-size:11px;">Costo (Gs.)</label><input class="fi" type="number" id="evCatNuevoCosto" placeholder="0"></div>
            <div><label class="fl" style="font-size:11px;">Orden</label><input class="fi" type="number" id="evCatNuevoOrden" value="1" min="1"></div>
          </div>
          <div style="margin-bottom:10px;"><label class="fl" style="font-size:11px;">Link de Grupos</label><input class="fi" type="text" id="evCatNuevoLink" placeholder="https://..."></div>
          <div style="display:flex;gap:8px;">
            <button class="btn btn-ok btn-sm" onclick="confirmarAgregarCat()"><i class="fas fa-check"></i> Agregar</button>
            <button class="btn btn-gh btn-sm" onclick="document.getElementById('evCatAddForm').style.display='none'">Cancelar</button>
          </div>
        </div>

        <!-- Lista de categorías existentes -->
        <div id="evCatList">
          <div class="loading" style="min-height:60px;border-radius:8px;"></div>
        </div>
      </div>

    </div>
    <div class="modal-f" style="justify-content:space-between;">
      <div style="display:flex;gap:8px;">
        <button class="btn btn-gh" id="btnEvPrev" onclick="prevEvTab()" style="display:none;"><i class="fas fa-arrow-left"></i> Anterior</button>
        <button class="btn btn-p" id="btnEvNext" onclick="nextEvTab()">Siguiente <i class="fas fa-arrow-right"></i></button>
      </div>
      <div style="display:flex;gap:8px;">
        <button class="btn btn-gh" onclick="closeModal('modalEvento')">Cancelar</button>
        <button class="btn btn-ok" id="btnEvGuardar" style="display:none;" onclick="guardarEvento()"><i class="fas fa-save"></i> Guardar Evento</button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL: Resumen Inscripciones / Partidos -->
<div class="modal-ov" id="modalResumen">
  <div class="modal" style="max-width:700px;">
    <div class="modal-h">
      <h3 id="modalResumenTitle"><i class="fas fa-chart-bar" style="margin-right:8px;color:var(--accent);"></i>Resumen</h3>
      <button class="modal-x" onclick="closeModal('modalResumen')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-b" style="padding:16px 24px;">
      <div class="tabs" id="tabsResumen" style="margin-bottom:16px;">
        <div class="tab active" onclick="switchResumenTab('eventos',this)">Por Evento</div>
        <div class="tab" onclick="switchResumenTab('categorias',this)">Por Categoría</div>
      </div>
      <div id="resumenContent">
        <div class="loading" style="min-height:80px;border-radius:8px;"></div>
      </div>
    </div>
  </div>
</div>

<!-- MODAL: Editar Inscripción -->
<div class="modal-ov" id="modalInscr">
  <div class="modal" style="max-width:540px;">
    <div class="modal-h">
      <h3><i class="fas fa-users" style="margin-right:8px;color:var(--accent);"></i>Editar Inscripción</h3>
      <button class="modal-x" onclick="closeModal('modalInscr')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-b">
      <input type="hidden" id="iInscId">
      <input type="hidden" id="iInscOrigCI1">
      <input type="hidden" id="iInscOrigCI2">
      <!-- Jugador 1 -->
      <div style="margin-bottom:14px;">
        <label class="fl">Jugador 1</label>
        <div style="display:flex;gap:8px;align-items:center;">
          <input class="fi" type="text" id="iInscNombre1" readonly style="background:var(--bg-hover);color:var(--text-primary);flex:1;">
          <input class="fi" type="text" id="iInscCI1" placeholder="CI" style="width:120px;text-align:center;">
        </div>
        <div style="position:relative;">
          <input class="fi" type="text" id="iBuscar1" placeholder="🔍 Buscar jugador por nombre o CI..." style="margin-top:6px;font-size:12px;" oninput="buscarJugadorInput(1,this.value)" onfocus="this.select()">
          <div id="iSugerencias1" style="position:absolute;top:100%;left:0;right:0;z-index:100;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-sm);max-height:200px;overflow-y:auto;display:none;box-shadow:var(--shadow-up);"></div>
        </div>
      </div>
      <!-- Jugador 2 -->
      <div style="margin-bottom:14px;">
        <label class="fl">Jugador 2</label>
        <div style="display:flex;gap:8px;align-items:center;">
          <input class="fi" type="text" id="iInscNombre2" readonly style="background:var(--bg-hover);color:var(--text-primary);flex:1;">
          <input class="fi" type="text" id="iInscCI2" placeholder="CI" style="width:120px;text-align:center;">
        </div>
        <div style="position:relative;">
          <input class="fi" type="text" id="iBuscar2" placeholder="🔍 Buscar jugador por nombre o CI..." style="margin-top:6px;font-size:12px;" oninput="buscarJugadorInput(2,this.value)" onfocus="this.select()">
          <div id="iSugerencias2" style="position:absolute;top:100%;left:0;right:0;z-index:100;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-sm);max-height:200px;overflow-y:auto;display:none;box-shadow:var(--shadow-up);"></div>
        </div>
      </div>
      <div class="g2">
        <div class="fg">
          <label class="fl">Estado</label>
          <select class="fs" id="iInscEstado">
            <option value="preinscripcion">Preinscripción</option>
            <option value="inscripto">Inscripto</option>
            <option value="pagado">Pagado</option>
            <option value="bloqueado">Bloqueado</option>
          </select>
        </div>
        <div class="fg">
          <label class="fl">Categoría ID</label>
          <input class="fi" type="number" id="iInscCat" placeholder="ID de categoría">
        </div>
      </div>
      <div class="fg">
        <label class="fl">Observaciones</label>
        <textarea class="fi" id="iInscObs" rows="2" placeholder="Observaciones..."></textarea>
      </div>
      <div style="background:var(--warning-bg);border:1px solid var(--warning);border-radius:var(--radius-sm);padding:10px 14px;font-size:12px;color:var(--warning);margin-top:8px;">
        <i class="fas fa-exclamation-triangle" style="margin-right:6px;"></i>
        Al cambiar el CI de un jugador se actualiza en <strong>_equipos</strong> y <strong>_p_incripciones</strong>.
      </div>
    </div>
    <div class="modal-f">
      <button class="btn btn-gh" onclick="closeModal('modalInscr')">Cancelar</button>
      <button class="btn btn-ok" onclick="guardarInscripcion()"><i class="fas fa-save"></i> Guardar Cambios</button>
    </div>
  </div>
</div>

<!-- MODAL: Listado Inscriptos por Categoría -->
<div class="modal-ov" id="modalListInscr">
  <div class="modal" style="max-width:700px;">
    <div class="modal-h">
      <h3><i class="fas fa-users" style="margin-right:8px;color:var(--accent);"></i><span id="mlInscTitle">Inscriptos</span></h3>
      <button class="modal-x" onclick="closeModal('modalListInscr')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-b" style="max-height:65vh;overflow-y:auto;">
      <div id="mlInscBody" style="min-height:60px;">
        <div class="loading" style="min-height:60px;border-radius:8px;"></div>
      </div>
    </div>
    <div class="modal-f">
      <button class="btn btn-gh" onclick="closeModal('modalListInscr')">Cerrar</button>
    </div>
  </div>
</div>

<!-- MODAL: Resultado -->
<div class="modal-ov" id="modalRes">
  <div class="modal" style="max-width:500px;">
    <div class="modal-h"><h3>Cargar Resultado</h3><button class="modal-x" onclick="closeModal('modalRes')"><i class="fas fa-times"></i></button></div>
    <div class="modal-b">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:20px;">
        <div style="text-align:center;flex:1;font-size:13px;font-weight:700;" id="mResEq1">—</div>
        <div style="font-size:12px;color:var(--text-muted);font-weight:600;">VS</div>
        <div style="text-align:center;flex:1;font-size:13px;font-weight:700;" id="mResEq2">—</div>
      </div>
      <input type="hidden" id="mResId">
      <div style="display:grid;grid-template-columns:1fr 20px 1fr;gap:8px;align-items:end;margin-bottom:12px;">
        <div class="fg" style="margin:0"><label class="fl">Set 1 Eq.1</label><input class="fi" type="number" min="0" max="7" id="mS1a"></div>
        <div style="text-align:center;color:var(--text-muted);padding-bottom:12px;">–</div>
        <div class="fg" style="margin:0"><label class="fl">Set 1 Eq.2</label><input class="fi" type="number" min="0" max="7" id="mS1b"></div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 20px 1fr;gap:8px;align-items:end;margin-bottom:12px;">
        <div class="fg" style="margin:0"><label class="fl">Set 2 Eq.1</label><input class="fi" type="number" min="0" max="7" id="mS2a"></div>
        <div style="text-align:center;color:var(--text-muted);padding-bottom:12px;">–</div>
        <div class="fg" style="margin:0"><label class="fl">Set 2 Eq.2</label><input class="fi" type="number" min="0" max="7" id="mS2b"></div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 20px 1fr;gap:8px;align-items:end;margin-bottom:12px;">
        <div class="fg" style="margin:0"><label class="fl">Set 3 Eq.1</label><input class="fi" type="number" min="0" max="10" id="mS3a"></div>
        <div style="text-align:center;color:var(--text-muted);padding-bottom:12px;">–</div>
        <div class="fg" style="margin:0"><label class="fl">Set 3 Eq.2</label><input class="fi" type="number" min="0" max="10" id="mS3b"></div>
      </div>
    </div>
    <div class="modal-f"><button class="btn btn-gh" onclick="closeModal('modalRes')">Cancelar</button><button class="btn btn-ok" onclick="guardarResultado()">Guardar Resultado</button></div>
  </div>
</div>

<script>
// ═══ CONFIG ═══
const API = 'tvt_api.php';

// ═══ NAV ═══
function toggleSb(){document.getElementById('sb').classList.toggle('open');document.getElementById('sbOv').classList.toggle('show')}
function goPage(id){
  document.querySelectorAll('.page').forEach(p=>p.classList.remove('active'));
  document.getElementById('pg-'+id).classList.add('active');
  document.querySelectorAll('.nav-i').forEach(n=>n.classList.remove('active'));
  const titles={dashboard:'Dashboard',eventos:'Eventos',inscripciones:'Inscripciones',categorias:'Categorías',jugadores:'Jugadores',resultados:'Resultados',horarios:'Horarios',ranking:'Ranking',admins:'Administradores',puntajes:'Puntajes'};
  document.getElementById('topTitle').textContent=titles[id]||id;
  event&&event.target&&document.querySelectorAll('.nav-i').forEach(n=>{if(n.textContent.trim().toLowerCase().includes(id.substring(0,5)))n.classList.add('active')});
  if(window.innerWidth<1024){document.getElementById('sb').classList.remove('open');document.getElementById('sbOv').classList.remove('show')}
  if(id==='ranking') loadRankEventos();
  if(id==='admins') loadAdmins();
  if(id==='puntajes') loadPuntajes();
  if(id==='jugadores') loadJugadores(1);
}
function openModal(id){document.getElementById(id).classList.add('show')}
function closeModal(id){document.getElementById(id).classList.remove('show')}

// ═══ THEME ═══
function toggleTheme(){
  const h=document.documentElement,n=h.getAttribute('data-theme')==='dark'?'light':'dark';
  h.setAttribute('data-theme',n);localStorage.setItem('bt-theme',n);
  document.getElementById('themeIc').className=n==='dark'?'fas fa-moon':'fas fa-sun';
  rebuildCharts();
}
function cssVar(v){return getComputedStyle(document.documentElement).getPropertyValue(v).trim()}

// ═══ LABEL DE GRUPO ═══
function grupoLabel(p) {
  // Si la API devuelve grupo_etiqueta, usarlo
  const etiq = parseInt(p.grupo_etiqueta || 0);
  const nombre = p.grupo_nombre || '';
  if (etiq === 1) return 'G' + p.grupo; // Fase de grupos → G1, G2...
  if (etiq === 2) return 'Octavos';
  if (etiq === 3) return 'Cuartos';
  if (etiq === 4) return 'Semis';
  if (etiq === 5) return 'Final';
  if (etiq === 9) return '16vos';
  if (etiq === 10) return '3er Puesto';
  if (nombre) return nombre;
  // Fallback por número de grupo
  if (p.grupo < 13) return 'G' + p.grupo;
  return 'Elim.';
}

// ═══ FETCH HELPER ═══
async function api(params){
  try{const r=await fetch(API+'?'+new URLSearchParams(params));return await r.json()}
  catch(e){console.error('API Error:',e);return{success:false,error:e.message}}
}

// ═══ 1. DASHBOARD ═══
async function loadDashboard(){
  document.getElementById('dashDate').textContent=new Date().toLocaleDateString('es-PY',{weekday:'long',year:'numeric',month:'long',day:'numeric'});
  // KPIs
  const k=await api({action:'kpis'});
  if(k.success){
    const d=k.kpis;
    document.getElementById('kpiGrid').innerHTML=`
      <div class="kpi"><div class="kpi-ic" style="background:var(--info-bg);color:var(--info);"><i class="fas fa-trophy"></i></div><div class="kpi-ct"><span class="kpi-lb">Eventos Activos</span><span class="kpi-val">${d.eventos_activos}</span></div></div>
      <div class="kpi" onclick="abrirResumenModal('inscripciones')" style="cursor:pointer;" title="Ver resumen por evento/categoría"><div class="kpi-ic" style="background:var(--success-bg);color:var(--success);"><i class="fas fa-users"></i></div><div class="kpi-ct"><span class="kpi-lb">Inscripciones <i class="fas fa-search" style="font-size:10px;opacity:.6;"></i></span><span class="kpi-val">${d.total_inscripciones}</span></div></div>
      <div class="kpi" onclick="abrirResumenModal('partidos')" style="cursor:pointer;" title="Ver resumen por evento/categoría"><div class="kpi-ic" style="background:var(--warning-bg);color:var(--warning);"><i class="fas fa-volleyball-ball"></i></div><div class="kpi-ct"><span class="kpi-lb">Partidos Generados <i class="fas fa-search" style="font-size:10px;opacity:.6;"></i></span><span class="kpi-val">${d.total_partidos.toLocaleString()}</span></div></div>
      <div class="kpi"><div class="kpi-ic" style="background:var(--danger-bg);color:var(--danger);"><i class="fas fa-user-check"></i></div><div class="kpi-ct"><span class="kpi-lb">Jugadores</span><span class="kpi-val">${d.jugadores}</span></div></div>`;
    document.getElementById('navEvBadge').textContent=d.eventos_activos;
  }
  // Event Cards
  const ev=await api({action:'eventos'});
  if(ev.success){
    // Only show active/registro events in cards (not culminado/inactivo)
    const openEvents=ev.eventos.filter(e=>e.estado==='activo'||e.estado==='registro');
    let cards='';
    openEvents.forEach((e,i)=>{
      const badgeClass='badge-s';
      const badgeText='ABIERTO';
      const fecha=e.fecha?new Date(e.fecha+'T12:00:00').toLocaleDateString('es-PY',{day:'2-digit',month:'2-digit',year:'numeric'}):'—';
      const nombre=e.evento.length>35?e.evento.substring(0,35)+'…':e.evento;
      const inscr=e.inscripciones||e.equipos||0;
      cards+=`<div class="ev-card" style="animation-delay:${0.05*(i+1)}s" onclick="gestionarEvento(${e.id})">
        <div class="ev-card-top">
          <div class="ev-date">${fecha}</div>
          <div class="ev-badge"><span class="badge ${badgeClass}">${badgeText}</span></div>
          <div class="ev-name">${nombre}</div>
        </div>
        <div class="ev-card-bot">
          <div class="ev-card-stat"><i class="fas fa-users"></i> ${inscr} inscriptos</div>
          <span class="ev-card-btn">Gestionar →</span>
        </div>
      </div>`;
    });
    document.getElementById('evCards').innerHTML=cards||'<div class="empty"><i class="fas fa-trophy"></i><p>Sin eventos abiertos</p></div>';

    // Also fill dashboard tables (active only)
    let h='';
    openEvents.slice(0,5).forEach(e=>{
      const bc=e.estado==='activo'?'badge-s':'badge-i';
      const inscr=e.inscripciones||e.equipos||0;
      h+=`<tr><td style="font-weight:600;">${e.evento}</td><td>${inscr}</td><td>${e.partidos}</td><td><span class="badge ${bc}">${e.estado}</span></td></tr>`;
    });
    document.getElementById('dashEv').innerHTML=h||'<tr><td colspan="4" class="empty">Sin eventos activos</td></tr>';
  }
  // Tabla inscripciones
  const ins=await api({action:'inscripciones',limit:5});
  if(ins.success){
    let h='';
    ins.inscripciones.slice(0,5).forEach(i=>{
      const n1=(i.nombre1||'')+' '+(i.apellido1||'');
      const n2=(i.nombre2||'')+' '+(i.apellido2||'');
      h+=`<tr><td style="font-weight:500;">${n1.trim()} / ${n2.trim()}</td><td>${i.cat_nombre||'—'}</td><td style="font-size:12px;">${(i.evento_nombre||'').substring(0,30)}</td></tr>`;
    });
    document.getElementById('dashInscr').innerHTML=h||'<tr><td colspan="3" class="empty">Sin inscripciones</td></tr>';
  }
}

// ═══ CHARTS ═══
let chInsc,chCat,chRank;
async function loadCharts(){
  const ci=await api({action:'chart_inscripciones'});
  if(ci.success&&ci.labels.length){
    if(chInsc)chInsc.destroy();
    chInsc=new Chart(document.getElementById('chInsc'),{type:'bar',data:{labels:ci.labels,datasets:[{label:'Equipos',data:ci.data,backgroundColor:cssVar('--accent')+'50',borderColor:cssVar('--accent'),borderWidth:2,borderRadius:6,borderSkipped:false,maxBarThickness:50}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,grid:{color:cssVar('--border')+'66'}},x:{grid:{display:false},ticks:{font:{size:10}}}}}});
  }
  const cc=await api({action:'chart_categorias'});
  if(cc.success&&cc.labels.length){
    if(chCat)chCat.destroy();
    const colors=['#6366f1','#3b82f6','#22c55e','#f59e0b','#ef4444','#ec4899','#8b5cf6','#14b8a6'];
    chCat=new Chart(document.getElementById('chCat'),{type:'doughnut',data:{labels:cc.labels,datasets:[{data:cc.data,backgroundColor:colors.slice(0,cc.labels.length),borderWidth:0,spacing:3,borderRadius:4}]},options:{responsive:true,maintainAspectRatio:false,cutout:'65%',plugins:{legend:{position:'bottom',labels:{padding:16,usePointStyle:true,font:{size:11}}}}}});
  }
}
function rebuildCharts(){
  Chart.defaults.color=cssVar('--text-secondary');
  Chart.defaults.borderColor=cssVar('--border');
  loadCharts();
  // Ranking se carga solo al entrar al tab
}

// ═══ 2. EVENTOS ═══
let evData=[];
async function loadEventos(estado=''){
  const ev=await api({action:'eventos',estado});
  if(ev.success){evData=ev.eventos;renderEventos()}
}
function renderEventos(){
  let h='';
  evData.forEach(e=>{
    const bc=e.estado==='activo'?'badge-s':e.estado==='registro'?'badge-i':e.estado==='culminado'?'badge-w':'badge-d';
    h+=`<tr><td>${e.id}</td><td style="font-weight:600;"><a href="#" onclick="selectEvento(${e.id});return false;" style="color:var(--text-primary);text-decoration:none;" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='var(--text-primary)'">${e.evento}</a></td><td>${e.fecha||'—'}</td><td>${e.categorias}</td><td>${e.equipos}</td><td>${e.partidos}</td><td><span class="badge ${bc}">${e.estado}</span></td>
    <td style="display:flex;gap:6px;"><button class="btn btn-gh btn-sm" onclick="selectEvento(${e.id})"><i class="fas fa-eye"></i> Ver</button><button class="btn btn-p btn-sm" onclick="editarEvento(${e.id})"><i class="fas fa-edit"></i> Editar</button></td></tr>`;
  });
  document.getElementById('tbEv').innerHTML=h||'<tr><td colspan="8" class="empty">Sin eventos</td></tr>';
}
function filtEv(estado,el){
  document.querySelectorAll('#tabsEv .tab').forEach(t=>t.classList.remove('active'));
  el.classList.add('active');
  loadEventos(estado);
}
function selectEvento(id){
  const setSafe=(elId,val)=>{const el=document.getElementById(elId);if(el)el.value=val;};
  setSafe('fResEv',id);
  setSafe('fCatEv',id);
  setSafe('fInscEv',id);
  goPage('categorias');
  loadCategorias();
}
function gestionarEvento(id){
  const setSafe=(elId,val)=>{const el=document.getElementById(elId);if(el)el.value=val;};
  setSafe('fResEv',id);
  setSafe('fCatEv',id);
  setSafe('fInscEv',id);
  goPage('categorias');
  loadCategorias();
}

// ═══ 3. INSCRIPCIONES ═══
async function loadInscCats(){
  const ev = document.getElementById('fInscEv').value;
  const sel = document.getElementById('fInscCat');
  sel.innerHTML = '<option value="">Todas las categorías</option>';
  if (!ev) { loadInscripciones(); return; }
  // Mostrar solo categorías con inscripciones reales
  const r = await api({action:'cats_con_inscriptos', evento:ev});
  if (r.success) {
    r.categorias.forEach(c => {
      const o = document.createElement('option');
      o.value = c.id_categoria;
      o.textContent = c.categoria + ' (' + c.total + ')';
      sel.appendChild(o);
    });
  }
  loadInscripciones();
}

async function loadInscripciones(){
  const ev=document.getElementById('fInscEv').value;
  const cat=document.getElementById('fInscCat').value;
  const buscar=document.getElementById('fInscBuscar').value.trim();
  const p={action:'inscripciones',limit:200};
  if(ev)p.evento=ev;if(cat)p.categoria=cat;if(buscar)p.buscar=buscar;
  const r=await api(p);
  if(r.success){
    document.getElementById('inscCount').textContent=r.total+' equipos';
    let h='';
    r.inscripciones.forEach((i,idx)=>{
      const n1=(i.nombre1||'')+' '+(i.apellido1||'');
      const n2=(i.nombre2||'')+' '+(i.apellido2||'');
      const estadoBadge = i.estado==='pagado'?'badge-s':i.estado==='inscripto'?'badge-i':i.estado==='bloqueado'?'badge-d':'badge-w';
      h+=`<tr>
        <td>${idx+1}</td>
        <td style="font-weight:500;">${n1.trim()}</td>
        <td style="font-size:12px;color:var(--text-muted);">${i.ci1_a}</td>
        <td style="font-weight:500;">${n2.trim()}</td>
        <td style="font-size:12px;color:var(--text-muted);">${i.ci1_b}</td>
        <td><span class="badge badge-a">${i.cat_nombre||'—'}</span></td>
        <td><span class="badge ${estadoBadge}">${i.estado||'—'}</span></td>
        <td style="display:flex;gap:4px;"><button class="btn btn-p btn-sm" onclick="editarInscripcion(${i.id},'${n1.trim().replace(/'/g,"\'")}','${n2.trim().replace(/'/g,"\'")}','${i.ci1_a}','${i.ci1_b}','${i.id_categoria}','${i.estado||''}','${i.obs||''}')"><i class="fas fa-edit"></i></button><button class="btn btn-sm" style="background:var(--danger-bg);color:var(--danger);" onclick="eliminarInscripcion(${i.id},'${n1.trim().replace(/'/g,"\\'")}','${n2.trim().replace(/'/g,"\\'")}')"><i class="fas fa-trash"></i></button></td>
      </tr>`;
    });
    document.getElementById('tbInsc').innerHTML=h||'<tr><td colspan="7" class="empty">Sin inscripciones</td></tr>';
  }
}

// ═══ 4. CATEGORÍAS (TVT Admin completo) ═══
let catSelectedGrupos = {}; // {catId: nGrupos}

async function loadCategorias(){
  const ev=document.getElementById('fCatEv').value;
  const grid=document.getElementById('gridCats');
  const kpis=document.getElementById('catKpis');

  if(!ev){
    grid.innerHTML='<div class="empty"><i class="fas fa-tags"></i><p>Seleccioná un evento desde las cards del Dashboard</p></div>';
    kpis.style.display='none';
    document.getElementById('catHeader').querySelector('.pg-title').textContent='Categorías por Evento';
    document.getElementById('catHeader').querySelector('.pg-sub').textContent='Seleccioná un evento para ver sus categorías';
    return;
  }

  grid.innerHTML='<div class="loading" style="min-height:100px;border-radius:12px;"></div>';
  const r=await api({action:'categorias',evento:ev});
  if(!r.success){grid.innerHTML='<div class="empty">Error al cargar</div>';return}

  // Update header
  const evName=r.evento?r.evento.evento:'Evento '+ev;
  document.getElementById('catHeader').querySelector('.pg-title').textContent=evName;
  document.getElementById('catHeader').querySelector('.pg-sub').textContent='Configurá grupos y generá el sorteo TVT por categoría';

  // KPIs del evento
  const s=r.resumen;
  kpis.style.display='grid';
  kpis.innerHTML=`
    <div class="kpi"><div class="kpi-ic" style="background:var(--info-bg);color:var(--info);"><i class="fas fa-tags"></i></div><div class="kpi-ct"><span class="kpi-lb">Categorías</span><span class="kpi-val">${s.total_categorias}</span><span class="kpi-ch" style="color:var(--info);">${s.total_categorias} activas</span></div></div>
    <div class="kpi"><div class="kpi-ic" style="background:var(--success-bg);color:var(--success);"><i class="fas fa-users"></i></div><div class="kpi-ct"><span class="kpi-lb">Parejas Inscriptas</span><span class="kpi-val">${s.total_parejas}</span><span class="kpi-ch" style="color:var(--text-muted);">total</span></div></div>
    <div class="kpi"><div class="kpi-ic" style="background:var(--warning-bg);color:var(--warning);"><i class="fas fa-random"></i></div><div class="kpi-ct"><span class="kpi-lb">Sorteos Listos</span><span class="kpi-val">${s.sorteos_listos}<span style="font-size:14px;color:var(--text-muted);">/${s.total_categorias}</span></span><span class="kpi-ch" style="color:${s.sorteos_listos<s.total_categorias?'var(--warning)':'var(--success)'};">${s.total_categorias-s.sorteos_listos} pendientes</span></div></div>
    <div class="kpi"><div class="kpi-ic" style="background:var(--accent-glow);color:var(--accent);"><i class="fas fa-volleyball-ball"></i></div><div class="kpi-ct"><span class="kpi-lb">Partidos Generados</span><span class="kpi-val">${s.total_partidos}</span><span class="kpi-ch" style="color:var(--success);">generados</span></div></div>`;

  // Render each category card
  let html='';
  r.categorias.forEach(c=>{
    const cid=c.id_categoria;
    const tiene=c.partidos>0;
    const nGrupos=catSelectedGrupos[cid]||(c.grupos_reales>0?c.grupos_reales:c.grupos_auto);

    // Recalcular distribución con grupos seleccionados
    const equipos=parseInt(c.parejas)||0;
    const inscriptos=parseInt(c.inscriptos)||equipos;
    const n=equipos>0?equipos:inscriptos; // Use equipos if generated, else inscriptos
    let g4=n-(3*nGrupos), g3=nGrupos-g4;
    if(g4<0||g3<0){g4=0;g3=nGrupos;}
    const distTxt=`${g3}×3 + ${g4}×4`;
    // Labels por grupo
    let grpLabels='';
    const letras='ABCDEFGHIJKL';
    for(let i=0;i<nGrupos;i++){
      const tam=i<g3?3:4;
      grpLabels+=`G${letras[i]}: ${tam}p · `;
    }
    grpLabels=grpLabels.slice(0,-3);

    // Estimate partidos
    const estP=g3*3+g4*6;

    // Group selector buttons
    let grpBtns='';
    for(let i=1;i<=c.max_grupos;i++){
      const sel=i===nGrupos;
      grpBtns+=`<button onclick="selGrupos(${ev},${cid},${i},${n})" id="gbtn-${cid}-${i}" style="width:34px;height:34px;border-radius:8px;border:1px solid ${sel?'var(--accent)':'var(--border)'};background:${sel?'var(--accent)':'var(--bg-primary)'};color:${sel?'#fff':'var(--text-secondary)'};font-size:13px;font-weight:700;cursor:pointer;transition:var(--tr);">${i}</button>`;
    }

    html+=`<div class="tbl-card mb-16" style="animation-delay:0.1s;">
      <!-- Category Header (clickeable para colapsar) -->
      <div onclick="toggleCat(${cid})" style="padding:16px 20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;border-bottom:1px solid var(--border);cursor:pointer;user-select:none;" class="cat-header-toggle">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
          <i id="cat-arrow-${cid}" class="fas fa-chevron-down" style="color:var(--text-muted);font-size:12px;transition:transform .25s;transform:rotate(-90deg);"></i>
          <div style="font-size:16px;font-weight:700;">${c.nombre}</div>
          <span class="badge badge-a" style="font-size:10px;">ID ${cid}</span>
          <span class="badge badge-i" style="font-size:10px;cursor:pointer;" onclick="event.stopPropagation();verInscriptosCat(${ev},${cid},'${c.nombre.replace(/'/g,"\\'")}')"><i class="fas fa-users" style="margin-right:3px;"></i>${inscriptos} inscriptos</span>
          ${equipos>0&&equipos!==inscriptos?`<span class="badge badge-a" style="font-size:10px;">${equipos} equipos</span>`:''}
        </div>
        <div style="display:flex;align-items:center;gap:8px;">${tiene
          ?'<span class="badge badge-s"><i class="fas fa-check" style="margin-right:3px;"></i>Sorteo listo</span>'
          :'<span class="badge badge-w">Sin sorteo</span>'}
          ${c.id_relacion?`<button onclick="event.stopPropagation();toggleCatCampo(${c.id_relacion},'cupo','${c.cupo==='disponible'?'lleno':'disponible'}',this,${ev})" title="Cupo: ${c.cupo||'disponible'}" style="font-size:10px;font-weight:600;padding:3px 8px;border-radius:6px;border:1px solid ${c.cupo==='lleno'?'var(--danger)':'var(--success)'};background:${c.cupo==='lleno'?'var(--danger-bg)':'var(--success-bg)'};color:${c.cupo==='lleno'?'var(--danger)':'var(--success)'};cursor:pointer;transition:var(--tr);">${c.cupo==='lleno'?'<i class="fas fa-lock"></i> Lleno':'<i class="fas fa-lock-open"></i> Disponible'}</button><button onclick="event.stopPropagation();toggleCatCampo(${c.id_relacion},'visualizar_en_llaves','${c.visualizar_en_llaves==='si'?'no':'si'}',this,${ev})" title="Llaves: ${c.visualizar_en_llaves||'si'}" style="font-size:10px;font-weight:600;padding:3px 8px;border-radius:6px;border:1px solid ${c.visualizar_en_llaves==='no'?'var(--warning)':'var(--info)'};background:${c.visualizar_en_llaves==='no'?'var(--warning-bg)':'var(--info-bg)'};color:${c.visualizar_en_llaves==='no'?'var(--warning)':'var(--info)'};cursor:pointer;transition:var(--tr);">${c.visualizar_en_llaves==='no'?'<i class="fas fa-eye-slash"></i> Oculto':'<i class="fas fa-eye"></i> Visible'}</button>`:''}</div>
      </div>

      <div id="cat-body-${cid}" style="padding:20px;display:none;">
        <!-- Stats row -->
        <div style="display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap;">
          <div onclick="verInscriptosCat(${ev},${cid},'${c.nombre.replace(/'/g,"\\'")}');" style="background:var(--bg-primary);border:1px solid var(--border);border-radius:var(--radius-sm);padding:12px 18px;text-align:center;min-width:80px;cursor:pointer;transition:var(--tr);" onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--border)'">
            <div style="font-size:22px;font-weight:700;">${inscriptos}</div><div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;">Inscriptos</div>
          </div>
          ${equipos>0?`<div style="background:var(--bg-primary);border:1px solid var(--border);border-radius:var(--radius-sm);padding:12px 18px;text-align:center;min-width:80px;">
            <div style="font-size:22px;font-weight:700;color:var(--accent);">${equipos}</div><div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;">Equipos</div>
          </div>`:''}
          <div style="background:var(--bg-primary);border:1px solid var(--border);border-radius:var(--radius-sm);padding:12px 18px;text-align:center;min-width:80px;">
            <div style="font-size:22px;font-weight:700;color:${tiene?'var(--success)':'var(--text-muted)'};">${c.partidos}</div><div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;">Partidos</div>
          </div>
          <div style="background:var(--bg-primary);border:1px solid var(--border);border-radius:var(--radius-sm);padding:12px 18px;text-align:center;min-width:80px;">
            <div style="font-size:22px;font-weight:700;">${nGrupos}</div><div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;">Grupos</div>
          </div>
          <div style="background:var(--bg-primary);border:1px solid var(--border);border-radius:var(--radius-sm);padding:12px 18px;text-align:center;min-width:80px;">
            <div style="font-size:22px;font-weight:700;">${estP}</div><div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;">Est. Partidos</div>
          </div>
        </div>

        <!-- Group selector -->
        <div style="background:var(--bg-primary);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px;margin-bottom:16px;">
          <div style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);font-weight:600;margin-bottom:10px;">Cantidad de grupos</div>
          <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px;">${grpBtns}</div>
          <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-top:4px;">
            <div style="font-size:12px;color:var(--text-secondary);" id="gdist-${cid}">Distribución: <strong>${distTxt}</strong> · ${grpLabels}</div>
            <button onclick="verPlantilla(${ev},${cid},${n})" id="btn-plant-${cid}"
              style="font-size:11px;font-weight:600;padding:4px 12px;border-radius:6px;border:1px solid var(--border);background:var(--bg-card);color:var(--text-secondary);cursor:pointer;transition:var(--tr);white-space:nowrap;">
              📋 Ver plantilla
            </button>
          </div>
          <div id="plantPanel-${cid}" style="display:none;margin-top:12px;"></div>
        </div>

        <!-- PASO 3: Generar Equipos -->
        <div style="background:var(--info-bg);border:1px solid var(--info);border-radius:var(--radius-sm);padding:12px 14px;margin-bottom:14px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
          <div>
            <div style="font-size:10px;text-transform:uppercase;letter-spacing:.5px;color:var(--info);font-weight:700;margin-bottom:4px;">PASO 3 — GENERAR EQUIPOS</div>
            <div style="font-size:12px;color:var(--text-secondary);">
              Inscriptos con dupla: <strong style="color:var(--text-primary);">${inscriptos}</strong>
              ${equipos>0?` &nbsp;·&nbsp; En _equipos: <strong style="color:var(--accent);">${equipos}</strong>`:''}
              ${inscriptos>0&&equipos===0
                ?' &nbsp;<span style="background:var(--warning-bg);color:var(--warning);font-size:10px;padding:1px 6px;border-radius:4px;font-weight:700;">SIN GENERAR</span>'
                :(inscriptos>0&&inscriptos===equipos
                  ?' &nbsp;<span style="background:var(--success-bg);color:var(--success);font-size:10px;padding:1px 6px;border-radius:4px;font-weight:700;">✓ SYNC</span>'
                  :(inscriptos>0&&inscriptos!==equipos
                    ?' &nbsp;<span style="background:var(--danger-bg);color:var(--danger);font-size:10px;padding:1px 6px;border-radius:4px;font-weight:700;">DESINCRONIZADO</span>'
                    :''))}
            </div>
          </div>
          <button class="btn btn-sm" onclick="generarEquipos(${ev},${cid})" id="btn-eq-${cid}"
            style="background:var(--info);color:#fff;border:none;">
            <i class="fas fa-users"></i> Generar Equipos
          </button>
        </div>
        <div id="res-eq-${cid}" style="display:none;margin-bottom:10px;"></div>

        <!-- Action buttons -->
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
          <button class="btn btn-gh btn-sm" onclick="previewCat(${ev},${cid})"><i class="fas fa-search"></i> Preview</button>
          ${tiene?`
            <button class="btn btn-p btn-sm" onclick="verResultadosCat(${ev},${cid})"><i class="fas fa-eye"></i> Ver fixture</button>
            ${c.partidos_con_resultado > 0
              ?`<button class="btn btn-gh btn-sm" disabled style="opacity:.4;cursor:not-allowed;" title="No se puede resetear: hay ${c.partidos_con_resultado} resultados cargados"><i class="fas fa-lock"></i> Resetear</button>
                <button class="btn btn-gh btn-sm" disabled style="opacity:.4;cursor:not-allowed;" title="No se puede regenerar: hay resultados cargados"><i class="fas fa-lock"></i> Regenerar</button>`
              :`<button class="btn btn-no btn-sm" onclick="resetearCat(${ev},${cid},'${c.nombre.replace(/'/g,"\\'")}',${c.partidos})"><i class="fas fa-trash"></i> Resetear</button>
                <button class="btn btn-warn btn-sm" onclick="regenerarCat(${ev},${cid},'${c.nombre.replace(/'/g,"\\'")}',${c.partidos})"><i class="fas fa-redo"></i> Regenerar</button>`}
          `:`
            <button class="btn btn-ok btn-sm" onclick="generarSorteoCat(${ev},${cid})"><i class="fas fa-random"></i> Generar sorteo</button>
          `}
          <label style="display:flex;align-items:center;gap:4px;font-size:12px;color:var(--text-secondary);margin-left:8px;cursor:pointer;">
            <input type="checkbox" id="3er-${cid}"> Con 3er puesto
          </label>
        </div>
        ${tiene && c.partidos_con_resultado > 0 ? `<div style="font-size:11px;color:var(--warning);margin-top:8px;"><i class="fas fa-info-circle" style="margin-right:4px;"></i>${c.partidos_con_resultado} partidos con resultado cargado — Resetear/Regenerar bloqueado</div>`:''}

        <!-- Preview / details panel -->
        <div id="catPanel-${cid}" style="margin-top:16px;"></div>

        <!-- Expandable: ver partidos -->
        ${tiene?`<details style="margin-top:12px;">
          <summary style="font-size:12px;color:var(--text-muted);cursor:pointer;font-weight:600;">▶ Ver ${c.partidos} partidos generados</summary>
          <div id="catPartidos-${cid}" style="margin-top:8px;"></div>
        </details>`:''}
      </div>
    </div>`;
  });

  grid.innerHTML=html||'<div class="empty"><i class="fas fa-tags"></i><p>Sin categorías con equipos</p></div>';

  // Attach toggle listeners for expandable partido lists
  grid.querySelectorAll('details').forEach(det=>{
    det.addEventListener('toggle',function(){
      if(!this.open)return;
      const box=this.querySelector('[id^="catPartidos-"]');
      if(box&&!box.innerHTML){
        const cid=box.id.replace('catPartidos-','');
        loadCatPartidos(ev,cid);
      }
    });
  });

  // Inicializar estado colapsado
  initCatCollapsed();
}

// Selector de grupos
function selGrupos(ev, cid, nGrupos, totalParejas){
  catSelectedGrupos[cid]=nGrupos;
  let g4=totalParejas-(3*nGrupos), g3=nGrupos-g4;
  if(g4<0||g3<0){g4=0;g3=nGrupos;}
  const distTxt=`${g3}×3 + ${g4}×4`;
  const letras='ABCDEFGHIJKL';
  let grpLabels='';
  for(let i=0;i<nGrupos;i++){grpLabels+=`G${letras[i]}: ${i<g3?3:4}p · `}
  grpLabels=grpLabels.slice(0,-3);
  const dist=document.getElementById('gdist-'+cid);
  if(dist)dist.innerHTML='Distribución: <strong>'+distTxt+'</strong> · '+grpLabels;
  // Update button styles
  for(let i=1;i<=12;i++){
    const b=document.getElementById('gbtn-'+cid+'-'+i);
    if(!b)continue;
    const sel=i===nGrupos;
    b.style.background=sel?'var(--accent)':'var(--bg-primary)';
    b.style.color=sel?'#fff':'var(--text-secondary)';
    b.style.borderColor=sel?'var(--accent)':'var(--border)';
  }
  // Limpiar panel de plantilla al cambiar grupos (para que recargue la nueva)
  const pp=document.getElementById('plantPanel-'+cid);
  if(pp){pp.style.display='none';pp.innerHTML='';}
  const bp=document.getElementById('btn-plant-'+cid);
  if(bp)bp.textContent='📋 Ver plantilla';
}

// Ver plantilla según grupos seleccionados
async function verPlantilla(ev, cid, totalParejas) {
  const panel = document.getElementById('plantPanel-' + cid);
  const btn   = document.getElementById('btn-plant-' + cid);
  if (!panel) return;

  // Toggle
  if (panel.style.display !== 'none' && panel.innerHTML) {
    panel.style.display = 'none';
    btn.textContent = '📋 Ver plantilla';
    return;
  }

  const nGrupos = catSelectedGrupos[cid] || 0;
  btn.textContent = '⏳ Cargando...';
  panel.style.display = 'block';
  panel.innerHTML = '<div class="loading" style="min-height:60px;border-radius:8px;"></div>';

  try {
    const r = await fetch(`tvt_api.php?action=plantilla&grupos=${nGrupos}&parejas=${totalParejas}`);
    const d = await r.json();

    if (!d.success || !d.plantilla) {
      panel.innerHTML = `<div style="background:var(--warning-bg);border:1px solid var(--warning);border-radius:var(--radius-sm);padding:10px 14px;font-size:12px;color:var(--warning);">
        <i class="fas fa-exclamation-triangle" style="margin-right:6px;"></i>
        ${d.mensaje || 'No se encontró plantilla para ' + nGrupos + ' grupos con ' + (catSelectedGrupos[cid]||'?') + ' parejas.'}
      </div>`;
      btn.textContent = '📋 Ver plantilla';
      return;
    }

    const p = d.plantilla;
    const fases = [];
    if(p.tiene_16vos)   fases.push('<span style="background:#7c3aed22;color:#7c3aed;font-size:10px;padding:2px 8px;border-radius:10px;font-weight:700;">16vos</span>');
    if(p.tiene_8vos)    fases.push('<span style="background:var(--info-bg);color:var(--info);font-size:10px;padding:2px 8px;border-radius:10px;font-weight:700;">8vos</span>');
    if(p.tiene_cuartos) fases.push('<span style="background:var(--accent-glow);color:var(--accent);font-size:10px;padding:2px 8px;border-radius:10px;font-weight:700;">Cuartos</span>');
    if(p.tiene_semis)   fases.push('<span style="background:var(--success-bg);color:var(--success);font-size:10px;padding:2px 8px;border-radius:10px;font-weight:700;">Semis</span>');
    if(p.tiene_final)   fases.push('<span style="background:var(--warning-bg);color:var(--warning);font-size:10px;padding:2px 8px;border-radius:10px;font-weight:700;">Final</span>');
    if(p.tiene_3er_puesto) fases.push('<span style="background:var(--danger-bg);color:var(--danger);font-size:10px;padding:2px 8px;border-radius:10px;font-weight:700;">3er Puesto</span>');

    // Agrupar cruces por fase en orden correcto
    const cruces = d.cruces || [];
    const faseOrden = ['16vos','8vos','cuartos','semis','final','3er'];
    const faseLabels = {'16vos':'16avos de Final','8vos':'Octavos de Final','cuartos':'Cuartos de Final','semis':'Semifinales','final':'Final','3er':'3er Puesto'};
    // Colores adaptados para dark/light mode usando opacidad sobre variables CSS
    const faseBg     = {
      '16vos':'rgba(109,40,217,.12)','8vos':'rgba(29,78,216,.12)',
      'cuartos':'rgba(99,102,241,.12)','semis':'rgba(21,128,61,.12)',
      'final':'rgba(180,83,9,.12)','3er':'rgba(185,28,28,.12)'
    };
    const faseBorder = {
      '16vos':'rgba(109,40,217,.35)','8vos':'rgba(29,78,216,.35)',
      'cuartos':'rgba(99,102,241,.35)','semis':'rgba(21,128,61,.35)',
      'final':'rgba(180,83,9,.35)','3er':'rgba(185,28,28,.35)'
    };
    const faseTitle  = {
      '16vos':'#a78bfa','8vos':'#60a5fa',
      'cuartos':'#818cf8','semis':'#4ade80',
      'final':'#fbbf24','3er':'#f87171'
    };
    const faseMatchBg= {
      '16vos':'rgba(109,40,217,.07)','8vos':'rgba(29,78,216,.07)',
      'cuartos':'rgba(99,102,241,.07)','semis':'rgba(21,128,61,.07)',
      'final':'rgba(180,83,9,.07)','3er':'rgba(185,28,28,.07)'
    };

    const porFase = {};
    cruces.forEach(c => { if(!porFase[c.fase]) porFase[c.fase]=[]; porFase[c.fase].push(c); });

    // Posición legible
    const posLabel = pos => {
      if(pos===1) return '<span style="color:#b45309;font-weight:700;">⭐ 1ro (Seed)</span>';
      if(pos===2) return '<span style="color:#1d4ed8;font-weight:600;">2do</span>';
      if(pos===4) return 'Ganador';
      if(pos===5) return 'Perdedor';
      return 'pos.'+pos;
    };
    const faseSource = (grupo, pos) => {
      // Si grupo > 12 es un partido virtual (viene de otra fase)
      if(grupo > 12) return `<span style="font-style:italic;color:var(--text-muted);">Ganador P.${grupo-27} de fase anterior</span>`;
      return `<span style="font-weight:600;">Grupo ${grupo}</span>`;
    };

    // Construir columnas bracket (una por fase)
    const fasesConCruces = faseOrden.filter(f => porFase[f]);
    let bracketHtml = '';

    if (fasesConCruces.length === 0) {
      bracketHtml = '<div style="color:var(--text-muted);font-size:12px;">Sin cruces definidos</div>';
    } else {
      bracketHtml = `<div style="display:flex;gap:16px;overflow-x:auto;padding-bottom:8px;">`;

      fasesConCruces.forEach(fase => {
        const partidos = porFase[fase];
        const bg     = faseBg[fase]     || '#f9fafb';
        const border = faseBorder[fase] || '#e5e7eb';
        const title  = faseTitle[fase]  || '#374151';
        const matchBg= faseMatchBg[fase]|| '#f3f4f6';

        // Header de columna (estilo imagen 1)
        let colHtml = `<div style="min-width:200px;flex:1;">
          <div style="background:${bg};border:2px solid ${border};border-radius:10px;padding:7px 14px;text-align:center;font-size:12px;font-weight:700;color:${title};margin-bottom:10px;">
            ${faseLabels[fase]||fase}
          </div>`;

        // Partidos de esta fase como "match cards"
        partidos.forEach(c => {
          // Determinar etiquetas de equipos
          let eq1Label, eq2Label;

          if(c.eq1_pos===1) eq1Label=`<span style="color:var(--warning);font-weight:700;">⭐ 1ro (Seed) — Grupo ${c.eq1_grupo}</span>`;
          else if(c.eq1_pos===2) eq1Label=`2do — Grupo ${c.eq1_grupo}`;
          else if(c.eq1_pos===4) eq1Label=`<span style="color:${faseTitle['8vos']||'#374151'};">Ganador (P.${c.partido} de ${faseLabels[fase]||fase})</span>`;
          else if(c.eq1_pos===5) eq1Label=`Perdedor (P.${c.partido})`;
          else eq1Label=`G${c.eq1_grupo} pos.${c.eq1_pos}`;

          // Para posición 4/5 referenciar la fase anterior
          const faseAnteriorIdx = faseOrden.indexOf(fase) - 1;
          const faseAnterior = faseAnteriorIdx >= 0 ? faseOrden[faseAnteriorIdx] : null;
          const labelFaseAnt = faseAnterior ? faseLabels[faseAnterior] : 'fase anterior';

          if(c.eq1_grupo > 12 && c.eq1_pos===4) eq1Label=`<span style="color:var(--text-secondary);font-style:italic;">Ganador (P.${c.eq1_grupo-27} de ${labelFaseAnt})</span>`;
          if(c.eq2_grupo > 12 && c.eq2_pos===4) eq2Label=`<span style="color:var(--text-secondary);font-style:italic;">Ganador (P.${c.eq2_grupo-27} de ${labelFaseAnt})</span>`;

          if(c.eq2_pos===1) eq2Label=`<span style="color:var(--warning);font-weight:700;">⭐ 1ro (Seed) — Grupo ${c.eq2_grupo}</span>`;
          else if(c.eq2_pos===2) eq2Label=eq2Label||`2do — Grupo ${c.eq2_grupo}`;
          else if(c.eq2_pos===4 && c.eq2_grupo<=12) eq2Label=`Ganador (P.${c.partido} de ${labelFaseAnt})`;
          else if(c.eq2_pos===5) eq2Label=`Perdedor (P.${c.partido})`;
          else eq2Label=eq2Label||`G${c.eq2_grupo} pos.${c.eq2_pos}`;

          colHtml += `
          <div style="border:1px solid ${border};border-radius:8px;overflow:hidden;margin-bottom:8px;">
            <div style="font-size:10px;color:${title};text-align:center;padding:3px 8px;background:${bg};border-bottom:1px solid ${border};font-weight:700;letter-spacing:.3px;">
              Partido ${c.partido}
            </div>
            <div style="background:${matchBg};border-bottom:1px dashed ${border};padding:6px 10px;display:flex;align-items:center;gap:6px;font-size:11px;color:var(--text-primary);">
              <span style="width:8px;height:8px;border-radius:2px;background:#3b82f6;flex-shrink:0;display:inline-block;"></span>
              ${eq1Label}
            </div>
            <div style="background:var(--bg-hover);padding:6px 10px;display:flex;align-items:center;gap:6px;font-size:11px;color:var(--text-primary);">
              <span style="width:8px;height:8px;border-radius:2px;background:#ef4444;flex-shrink:0;display:inline-block;"></span>
              ${eq2Label}
            </div>
          </div>`;
        });

        colHtml += '</div>';
        bracketHtml += colHtml;
      });

      bracketHtml += '</div>';
    }

    panel.innerHTML = `
      <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px;">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:14px;">
          <div>
            <div style="font-size:13px;font-weight:700;color:var(--text-primary);">${p.nombre}</div>
            <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">
              ${p.min_parejas}–${p.max_parejas} parejas · ${p.cantidad_grupos} grupos
              (${p.grupos_de_3}×3 + ${p.grupos_de_4}×4) · ID ${p.id}
            </div>
          </div>
          <div style="display:flex;gap:4px;flex-wrap:wrap;">${fases.join('')}</div>
        </div>
        ${bracketHtml}
      </div>`;

    btn.textContent = '📋 Ocultar plantilla';

  } catch(e) {
    panel.innerHTML = `<div style="color:var(--danger);font-size:12px;padding:8px;">Error: ${e.message}</div>`;
    btn.textContent = '📋 Ver plantilla';
  }
}

// Preview (toggle: click again to hide)
async function previewCat(ev,cid){
  const panel=document.getElementById('catPanel-'+cid);
  const nGrupos=catSelectedGrupos[cid]||0;

  // Toggle: if panel has content, clear it
  if(panel.innerHTML.trim()&&!panel.querySelector('.loading')){panel.innerHTML='';return}

  panel.innerHTML='<div class="loading" style="min-height:60px;border-radius:8px;"></div>';
  try{
    const r=await fetch(`3_tvt_generar_sorteo.php?evento=${ev}&categoria=${cid}&preview=1&grupos=${nGrupos}`);
    const d=await r.json();
    if(!d.grupos){panel.innerHTML=`<div style="color:var(--danger);font-size:13px;padding:8px;">Error: ${d.mensaje||'sin datos'}</div>`;return}

    let h=`<div style="font-size:13px;margin-bottom:12px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
      <span class="badge badge-i">${d.total_parejas} parejas → ${d.grupos_generados} grupos</span>
      <span style="color:var(--text-muted);font-size:12px;">${d.grupos_de_3||0}×3 + ${d.grupos_de_4||0}×4</span>
    </div>`;

    // Groups grid
    h+=`<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;margin-bottom:20px;">`;
    Object.entries(d.grupos).forEach(([ng,parejas])=>{
      const nP=parejas.length*(parejas.length-1)/2;
      h+=`<div class="grp-card"><div class="grp-hdr" style="display:flex;justify-content:space-between;"><span><i class="fas fa-layer-group" style="margin-right:4px;"></i>Grupo ${ng}</span><span style="font-size:10px;font-weight:400;opacity:.7;">${parejas.length}p · ${nP}pts</span></div>`;
      parejas.forEach((p,i)=>{
        h+=`<div class="grp-item" style="display:flex;justify-content:space-between;align-items:center;${i===0?'font-weight:600;':''}">
          <span style="font-size:12px;">${i+1}. ${i===0?'⭐ ':''}${p.dupla1} / ${p.dupla2}</span>
          <span style="font-size:11px;font-weight:700;color:var(--warning);white-space:nowrap;margin-left:8px;">${p.ranking}</span>
        </div>`;
      });
      h+='</div>';
    });
    h+='</div>';

    // Ranking table
    const ranking=d.ranking_completo||d.ranking||[];
    if(ranking.length){
      h+=`<div style="font-size:13px;font-weight:700;margin-bottom:8px;text-transform:uppercase;letter-spacing:.5px;color:var(--text-secondary);">Ranking completo — Cabezas de serie</div>`;
      h+=`<div class="tbl-wrap"><table style="min-width:100%;font-size:13px;">
        <thead><tr>
          <th style="width:40px;text-align:center;">#</th>
          <th style="text-align:left;">Pareja</th>
          <th style="width:80px;text-align:right;">Pts J1</th>
          <th style="width:80px;text-align:right;">Pts J2</th>
          <th style="width:80px;text-align:right;">Total</th>
          <th style="width:60px;text-align:center;">Grupo</th>
        </tr></thead><tbody>`;
      ranking.forEach((p,i)=>{
        const esSeed=p.es_cabeza||(i<d.grupos_generados);
        h+=`<tr${esSeed?' style="background:var(--warning-bg);"':''}>
          <td style="text-align:center;font-weight:700;">${i+1}</td>
          <td style="text-align:left;">${p.dupla1} / ${p.dupla2}${esSeed?` <span style="background:var(--warning);color:#fff;font-size:9px;padding:1px 5px;border-radius:4px;font-weight:700;margin-left:4px;">CS${i+1}</span>`:''}</td>
          <td style="text-align:right;">${p.pts1}</td>
          <td style="text-align:right;">${p.pts2}</td>
          <td style="text-align:right;font-weight:700;color:var(--warning);">${p.total}</td>
          <td style="text-align:center;"><span style="background:var(--info-bg);color:var(--info);font-size:10px;padding:2px 8px;border-radius:4px;font-weight:700;">G${p.grupo}</span></td>
        </tr>`;
      });
      h+='</tbody></table></div>';
    }

    panel.innerHTML=h;
  }catch(e){panel.innerHTML=`<div style="color:var(--danger);font-size:13px;">Error: ${e.message}</div>`}
}

// Toggle colapsar/expandir card de categoría
const catCollapsed = {};
// Marcar todas como colapsadas al inicializar
function initCatCollapsed(){
  document.querySelectorAll('[id^="cat-body-"]').forEach(el=>{
    const cid=el.id.replace('cat-body-','');
    catCollapsed[cid]=true;
  });
}
function toggleCat(cid) {
  const body  = document.getElementById('cat-body-' + cid);
  const arrow = document.getElementById('cat-arrow-' + cid);
  if (!body) return;
  catCollapsed[cid] = !catCollapsed[cid];
  if (catCollapsed[cid]) {
    body.style.display  = 'none';
    if (arrow) arrow.style.transform = 'rotate(-90deg)';
  } else {
    body.style.display  = 'block';
    if (arrow) arrow.style.transform = 'rotate(0deg)';
  }
}

// Colapsar todas / expandir todas
function toggleAllCats(colapsar) {
  document.querySelectorAll('[id^="cat-body-"]').forEach(el => {
    const cid = el.id.replace('cat-body-', '');
    const arrow = document.getElementById('cat-arrow-' + cid);
    if (colapsar) {
      el.style.display = 'none';
      catCollapsed[cid] = true;
      if (arrow) arrow.style.transform = 'rotate(-90deg)';
    } else {
      el.style.display = 'block';
      catCollapsed[cid] = false;
      if (arrow) arrow.style.transform = 'rotate(0deg)';
    }
  });
}

// Toggle rápido de cupo o visibilidad en _relacion_evento_categoria
async function toggleCatCampo(idRelacion, campo, nuevoValor, btn, ev) {
  btn.disabled = true;
  btn.style.opacity = '0.5';
  const r = await api({action:'actualizar_cat_evento', id_relacion: idRelacion, campo, valor: nuevoValor});
  btn.disabled = false;
  btn.style.opacity = '1';
  if (r.success) {
    // Recargar categorías para refrescar los botones
    loadCategorias();
  } else {
    alert('Error: ' + (r.error || 'desconocido'));
  }
}

// Generar Equipos (Paso 3): _p_incripciones → _equipos
async function generarEquipos(ev, cid) {
  const btn   = document.getElementById('btn-eq-' + cid);
  const panel = document.getElementById('res-eq-' + cid);
  try {
    const prev = await fetch(`generar_equipos.php?evento=${ev}&categoria=${cid}`);
    const d    = await prev.json();
    if (!d.success) {
      panel.style.display = 'block';
      panel.innerHTML = `<div style="background:var(--danger-bg);border:1px solid var(--danger);border-radius:var(--radius-sm);padding:8px 12px;font-size:12px;color:var(--danger);">❌ ${d.mensaje}</div>`;
      return;
    }
    const total = d.total_parejas || 0;
    const yaEq  = d.ya_en_equipos || 0;
    let msg = `¿Generar equipos para ${total} parejas?

Evento: ${ev}  ·  Categoría: ${cid}`;
    if (yaEq > 0) msg += `

⚠️ Ya hay ${yaEq} equipos — serán REEMPLAZADOS.`;
    if (!confirm(msg)) return;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generando...';
    panel.style.display = 'block';
    panel.innerHTML = '<div class="loading" style="min-height:36px;border-radius:6px;"></div>';
    const r2 = await fetch(`generar_equipos.php?evento=${ev}&categoria=${cid}&confirmar=1&json=1`);
    const d2  = await r2.json();
    if (d2.success) {
      panel.innerHTML = `<div style="background:var(--success-bg);border:1px solid var(--success);border-radius:var(--radius-sm);padding:8px 12px;font-size:12px;color:var(--success);"><i class="fas fa-check" style="margin-right:4px;"></i>${d2.mensaje}</div>`;
      setTimeout(() => loadCategorias(), 1600);
    } else {
      panel.innerHTML = `<div style="background:var(--danger-bg);border:1px solid var(--danger);border-radius:var(--radius-sm);padding:8px 12px;font-size:12px;color:var(--danger);">❌ ${d2.mensaje}${d2.errores?.length ? '<br>'+d2.errores.slice(0,2).join('<br>') : ''}</div>`;
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-users"></i> Generar Equipos';
    }
  } catch(e) {
    panel.style.display = 'block';
    panel.innerHTML = `<div style="color:var(--warning);font-size:12px;padding:8px;">⚠️ Error: ${e.message}</div>`;
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-users"></i> Generar Equipos';
  }
}

// Generar sorteo
async function generarSorteoCat(ev,cid){
  if(!confirm('¿Confirmar y generar el sorteo?'))return;
  const nGrupos=catSelectedGrupos[cid]||0;
  const con3er=document.getElementById('3er-'+cid)?.checked?'&con_3er=1':'';
  try{
    const r=await fetch(`3_tvt_generar_sorteo.php?evento=${ev}&categoria=${cid}&confirmar=1&json=1&grupos=${nGrupos}${con3er}`);
    const d=await r.json();
    if(d.success){alert(`Sorteo generado: ${d.total_partidos_insertados||d.mensaje||'OK'}`);loadCategorias()}
    else{alert('Error: '+(d.mensaje||'desconocido'))}
  }catch(e){alert('Error: '+e.message)}
}

// Regenerar (resetear + generar)
async function regenerarCat(ev,cid,nombre,partidos){
  if(!confirm(`¿Eliminar los ${partidos} partidos de "${nombre}" y regenerar?`))return;
  const r=await api({action:'resetear',evento:ev,categoria:cid});
  if(r.success){
    await generarSorteoCat(ev,cid);
  }else{alert('Error al resetear: '+(r.error||'desconocido'))}
}

// Ver partidos de una categoría (expandible) + intercambiar parejas
let _swapSeleccion = null; // {ev, cid, ci1, ci2, nombre, grupo}

async function loadCatPartidos(ev,cid){
  const box=document.getElementById('catPartidos-'+cid);
  if(!box)return;
  box.innerHTML='<div class="loading" style="min-height:40px;border-radius:6px;"></div>';
  const r=await api({action:'resultados',evento:ev,categoria:cid});
  if(r.success&&r.partidos.length){
    // Extraer parejas únicas por grupo
    const gruposMap = {};
    r.partidos.forEach(p=>{
      if (p.grupo >= 13) return; // solo grupo stage
      const gKey = p.grupo;
      if (!gruposMap[gKey]) gruposMap[gKey] = {parejas:{}, label: grupoLabel(p)};
      // Pareja local
      if (p.ci1_a && p.ci1_a != '0') {
        const key1 = p.ci1_a+'_'+p.ci1_b;
        if (!gruposMap[gKey].parejas[key1]) {
          gruposMap[gKey].parejas[key1] = {ci1:p.ci1_a, ci2:p.ci1_b, nombre: p.eq1_j1?(p.eq1_j1+(p.eq1_j2?' / '+p.eq1_j2:'')):'CI:'+p.ci1_a+'/'+p.ci1_b};
        }
      }
      // Pareja visitante
      if (p.ci2_a && p.ci2_a != '0') {
        const key2 = p.ci2_a+'_'+p.ci2_b;
        if (!gruposMap[gKey].parejas[key2]) {
          gruposMap[gKey].parejas[key2] = {ci1:p.ci2_a, ci2:p.ci2_b, nombre: p.eq2_j1?(p.eq2_j1+(p.eq2_j2?' / '+p.eq2_j2:'')):'CI:'+p.ci2_a+'/'+p.ci2_b};
        }
      }
    });

    // Render grupos con botón intercambiar
    let h = '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px;margin-bottom:16px;">';
    Object.entries(gruposMap).sort((a,b)=>a[0]-b[0]).forEach(([gKey,gData])=>{
      h += `<div class="grp-card"><div class="grp-hdr"><i class="fas fa-layer-group" style="margin-right:4px;"></i>${gData.label}</div>`;
      Object.values(gData.parejas).forEach((p,i)=>{
        h += `<div class="grp-item" style="display:flex;justify-content:space-between;align-items:center;gap:4px;" id="swap-${cid}-${p.ci1}-${p.ci2}">
          <span style="font-size:12px;flex:1;">${i+1}. ${p.nombre}</span>
          <button onclick="selSwap(${ev},${cid},'${p.ci1}','${p.ci2}','${p.nombre.replace(/'/g,"\\'")}','${gData.label}',this)" 
            style="font-size:10px;padding:2px 6px;border-radius:4px;border:1px solid var(--border);background:var(--bg-primary);color:var(--text-muted);cursor:pointer;flex-shrink:0;transition:var(--tr);" 
            title="Intercambiar esta pareja">⇄</button>
        </div>`;
      });
      h += '</div>';
    });
    h += '</div>';

    // Info de intercambio
    h += `<div id="swapInfo-${cid}" style="display:none;background:var(--info-bg);border:1px solid var(--info);border-radius:var(--radius-sm);padding:10px 14px;margin-bottom:12px;font-size:12px;color:var(--info);">
      <i class="fas fa-exchange-alt" style="margin-right:6px;"></i>
      <span id="swapText-${cid}"></span>
      <button onclick="cancelSwap(${cid})" style="margin-left:10px;font-size:11px;padding:2px 8px;border-radius:4px;border:1px solid var(--info);background:transparent;color:var(--info);cursor:pointer;">✕ Cancelar</button>
    </div>`;

    // Tabla de partidos
    h+='<div class="tbl-wrap"><table style="min-width:500px;font-size:12px;"><thead><tr><th>Grp</th><th>#</th><th>Equipo 1</th><th>S1</th><th>S2</th><th>S3</th><th>Equipo 2</th><th>Tipo</th></tr></thead><tbody>';
    r.partidos.forEach(p=>{
      const eq1=p.eq1_j1?(p.eq1_j1+(p.eq1_j2?' / '+p.eq1_j2:'')):'-';
      const eq2=p.eq2_j1?(p.eq2_j1+(p.eq2_j2?' / '+p.eq2_j2:'')):'-';
      const eli=p.tipo_referencia==='si';
      const s1=(p.rusultado_equipo1||'')+'–'+(p.resultado_equipo2||'');
      h+=`<tr style="${eli?'background:var(--info-bg);':''}"><td>${grupoLabel(p)}</td><td>${p.partido_nro}</td><td>${eq1}</td><td>${s1}</td><td>${(p.resultado2_equipo1||'')}–${(p.resultado2_equipo2||'')}</td><td>${(p.resultado3_equipo1||'')}–${(p.resultado3_equipo2||'')}</td><td>${eq2}</td><td>${eli?`<span class="badge badge-i" style="font-size:10px;">${grupoLabel(p)}</span>`:'<span class="badge badge-a" style="font-size:10px;">Grupo</span>'}</td></tr>`;
    });
    h+='</tbody></table></div>';
    if(r.total>200)h+=`<p style="font-size:11px;color:var(--text-muted);margin-top:4px;">Mostrando primeros 200 de ${r.total} partidos.</p>`;
    box.innerHTML=h;
  }else{box.innerHTML='<div style="font-size:12px;color:var(--text-muted);padding:8px;">Sin partidos generados</div>'}
}

function selSwap(ev, cid, ci1, ci2, nombre, grupoLabel, btn) {
  if (!_swapSeleccion) {
    // Primera selección
    _swapSeleccion = {ev, cid, ci1, ci2, nombre, grupoLabel};
    btn.style.background = 'var(--accent)';
    btn.style.color = '#fff';
    btn.style.borderColor = 'var(--accent)';
    const info = document.getElementById('swapInfo-'+cid);
    const text = document.getElementById('swapText-'+cid);
    if (info) { info.style.display='block'; text.textContent = `Seleccionada: ${nombre} (${grupoLabel}) — Ahora elegí la pareja con la que querés intercambiar`; }
  } else if (_swapSeleccion.cid === cid) {
    // Segunda selección — mismo categoría
    if (_swapSeleccion.ci1 === ci1 && _swapSeleccion.ci2 === ci2) {
      // Click en la misma → cancelar
      cancelSwap(cid);
      return;
    }
    const a = _swapSeleccion;
    if (!confirm(`¿Intercambiar?\n\n${a.nombre} (${a.grupoLabel})\n⇄\n${nombre} (${grupoLabel})`)) return;
    
    // Ejecutar swap
    ejecutarSwap(ev, cid, a.ci1, a.ci2, ci1, ci2);
  }
}

async function ejecutarSwap(ev, cid, a1, a2, b1, b2) {
  const info = document.getElementById('swapInfo-'+cid);
  if (info) { info.querySelector('span').textContent = 'Intercambiando...'; }
  
  const r = await api({action:'intercambiar_parejas', evento:ev, categoria:cid, a_ci1:a1, a_ci2:a2, b_ci1:b1, b_ci2:b2});
  
  _swapSeleccion = null;
  if (info) info.style.display = 'none';
  
  if (r.success) {
    alert('✓ ' + r.mensaje);
    // Forzar recarga de partidos
    const box = document.getElementById('catPartidos-'+cid);
    if (box) box.innerHTML = '';
    loadCatPartidos(ev, cid);
  } else {
    alert('Error: ' + (r.error || 'desconocido'));
  }
}

function cancelSwap(cid) {
  _swapSeleccion = null;
  const info = document.getElementById('swapInfo-'+cid);
  if (info) info.style.display = 'none';
  // Reset button styles
  document.querySelectorAll(`[id^="swap-${cid}-"] button`).forEach(b => {
    b.style.background = 'var(--bg-primary)';
    b.style.color = 'var(--text-muted)';
    b.style.borderColor = 'var(--border)';
  });
}

function verResultadosCat(ev,cat){
  document.getElementById('fResEv').value=ev;
  goPage('resultados');
  setTimeout(()=>{loadResCats();setTimeout(()=>{document.getElementById('fResCat').value=cat;loadResultados()},300)},100);
}

async function resetearCat(ev,cat,nombre,partidos){
  if(!confirm(`¿Eliminar los ${partidos} partidos de "${nombre}"?`))return;
  const r=await api({action:'resetear',evento:ev,categoria:cat});
  if(r.success){alert(`Eliminados ${r.eliminados} partidos`);loadCategorias()}
  else{alert('Error: '+(r.error||'desconocido'))}
}


async function previewSorteo(ev,cat){
  const box=document.getElementById('previewBox');
  box.innerHTML='<div class="loading" style="min-height:80px;border-radius:8px;"></div>';
  try{
    const r=await fetch(`3_tvt_generar_sorteo.php?evento=${ev}&categoria=${cat}&preview=1`);
    const d=await r.json();
    if(!d.grupos){box.innerHTML=`<div style="color:var(--danger);font-size:13px;">Error: ${d.mensaje||'sin datos'}</div>`;return}
    let h=`<div style="font-size:13px;margin-bottom:12px;"><span class="badge badge-i">${d.total_parejas} parejas → ${d.grupos_generados} grupos</span> <span style="color:var(--text-muted);">${d.grupos_de_3}×3 + ${d.grupos_de_4}×4</span></div><div class="g3">`;
    Object.entries(d.grupos).forEach(([ng,parejas])=>{
      h+=`<div class="grp-card"><div class="grp-hdr"><i class="fas fa-layer-group" style="margin-right:6px;"></i>Grupo ${ng}</div>`;
      parejas.forEach((p,i)=>{h+=`<div class="grp-item">${i+1}. ${p.dupla1} / ${p.dupla2} <span style="float:right;font-size:11px;color:var(--warning);">${p.ranking} pts</span></div>`});
      h+='</div>';
    });
    h+='</div>';
    box.innerHTML=h;
  }catch(e){box.innerHTML=`<div style="color:var(--danger);">Error: ${e.message}</div>`}
}
async function confirmarSorteo(ev,cat){
  if(!confirm('¿Confirmar y generar el sorteo?'))return;
  const btn=document.getElementById('btnConfirm');
  btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Generando...';btn.disabled=true;
  try{
    const r=await fetch(`3_tvt_generar_sorteo.php?evento=${ev}&categoria=${cat}&confirmar=1&json=1`);
    const d=await r.json();
    if(d.success){alert(`Sorteo generado: ${d.total_partidos_insertados} partidos`);loadSorteoDetalle()}
    else{alert('Error: '+(d.mensaje||'desconocido'))}
  }catch(e){alert('Error: '+e.message)}
  btn.innerHTML='<i class="fas fa-check"></i> Confirmar Sorteo';btn.disabled=false;
}

// ═══ 6. RESULTADOS ═══
async function loadResCats(){
  const ev=document.getElementById('fResEv').value;
  const sel=document.getElementById('fResCat');
  sel.innerHTML='<option value="">Seleccionar categoría</option>';
  document.getElementById('fResGrupo').innerHTML='<option value="">Todos los grupos</option>';
  document.getElementById('tbRes').innerHTML='';
  if(!ev)return;
  const r=await api({action:'categorias',evento:ev});
  if(r.success)r.categorias.forEach(c=>{const o=document.createElement('option');o.value=c.id_categoria;o.textContent=c.nombre;sel.appendChild(o)});
}
async function loadResultados(){
  const ev=document.getElementById('fResEv').value;
  const cat=document.getElementById('fResCat').value;
  const grupo=document.getElementById('fResGrupo').value;
  if(!ev||!cat){document.getElementById('tbRes').innerHTML='';return}
  const p={action:'resultados',evento:ev,categoria:cat};
  if(grupo!=='')p.grupo=grupo;
  const r=await api(p);
  if(r.success){
    document.getElementById('resCount').textContent=r.total+' partidos';
    // Populate grupo filter
    if(grupo===''){
      const gs=new Set(r.partidos.map(p=>p.grupo));
      const sel=document.getElementById('fResGrupo');
      sel.innerHTML='<option value="">Todos los grupos</option>';
      [...gs].sort((a,b)=>a-b).forEach(g=>{const o=document.createElement('option');o.value=g;const match=r.partidos.find(p=>p.grupo==g);o.textContent=match?grupoLabel(match):'Grupo '+g;sel.appendChild(o)});
    }
    let h='';
    r.partidos.forEach(p=>{
      const eq1=p.eq1_j1?(p.eq1_j1+(p.eq1_j2?' / '+p.eq1_j2:'')):('CI:'+p.ci1_a+'/'+p.ci1_b);
      const eq2=p.eq2_j1?(p.eq2_j1+(p.eq2_j2?' / '+p.eq2_j2:'')):('CI:'+p.ci2_a+'/'+p.ci2_b);
      const s1=p.rusultado_equipo1!=null?p.rusultado_equipo1+'-'+p.resultado_equipo2:'—';
      const s2=p.resultado2_equipo1!=null&&p.resultado2_equipo1>0?p.resultado2_equipo1+'-'+p.resultado2_equipo2:'—';
      const s3=p.resultado3_equipo1!=null&&p.resultado3_equipo1>0?p.resultado3_equipo1+'-'+p.resultado3_equipo2:'—';
      const tieneRes=(p.rusultado_equipo1>0||p.resultado_equipo2>0);
      const enJuego=(p.en_juego==='si');
      let estadoHtml;
      if(tieneRes){estadoHtml='<span class="badge badge-fin" style="font-size:10px;">FIN</span>';}
      else if(enJuego){estadoHtml='<span class="badge badge-enjuego" style="font-size:10px;">EN JUEGO</span> <button class="btn-enjuego active" onclick="toggleEnJuego('+p.id+')" title="Detener"><i class="fas fa-stop"></i></button>';}
      else{estadoHtml='<span class="badge badge-pend" style="font-size:10px;">Pend.</span> <button class="btn-enjuego" onclick="toggleEnJuego('+p.id+')" title="Iniciar"><i class="fas fa-play"></i></button>';}
      const gLabel=grupoLabel(p);
      const trClass=enJuego?' class="tr-enjuego"':'';
      h+=`<tr${trClass}><td><span class="badge badge-a" style="font-size:10px;">${gLabel}</span></td><td>${p.partido_nro}</td>
        <td style="font-weight:500;font-size:12px;">${eq1}</td><td>${s1}</td><td>${s2}</td><td>${s3}</td>
        <td style="font-weight:500;font-size:12px;">${eq2}</td><td>${estadoHtml}</td>
        <td><button class="btn btn-p btn-sm" onclick="abrirRes(${p.id},'${eq1.replace(/'/g,"\\'")}','${eq2.replace(/'/g,"\\'")}',${p.rusultado_equipo1||0},${p.resultado_equipo2||0},${p.resultado2_equipo1||0},${p.resultado2_equipo2||0},${p.resultado3_equipo1||0},${p.resultado3_equipo2||0})"><i class="fas fa-edit"></i></button></td></tr>`;
    });
    document.getElementById('tbRes').innerHTML=h||'<tr><td colspan="9" class="empty">Sin partidos</td></tr>';
  }
}
function abrirRes(id,eq1,eq2,s1a,s1b,s2a,s2b,s3a,s3b){
  document.getElementById('mResId').value=id;
  document.getElementById('mResEq1').textContent=eq1;
  document.getElementById('mResEq2').textContent=eq2;
  document.getElementById('mS1a').value=s1a;document.getElementById('mS1b').value=s1b;
  document.getElementById('mS2a').value=s2a;document.getElementById('mS2b').value=s2b;
  document.getElementById('mS3a').value=s3a;document.getElementById('mS3b').value=s3b;
  openModal('modalRes');
}
async function guardarResultado(){
  const id=document.getElementById('mResId').value;
  const r=await api({action:'guardar_resultado',id,
    s1a:document.getElementById('mS1a').value||0,s1b:document.getElementById('mS1b').value||0,
    s2a:document.getElementById('mS2a').value||0,s2b:document.getElementById('mS2b').value||0,
    s3a:document.getElementById('mS3a').value||0,s3b:document.getElementById('mS3b').value||0});
  if(r.success){closeModal('modalRes');loadResultados()}
  else{alert('Error: '+(r.error||'desconocido'))}
}


async function toggleEnJuego(id){
  const r=await api({action:'toggle_en_juego',id});
  if(r.success){loadResultados()}
  else{alert('Error: '+(r.error||'desconocido'))}
}
// ═══ 7. HORARIOS (demo) ═══
function loadHorarios(){
  const canchas=['Cancha 1','Cancha 2','Cancha 3','Cancha 4'];
  const horas=['08:00','09:00','10:00','11:00','12:00','14:00','15:00','16:00','17:00','18:00'];
  let th='<tr><th style="min-width:70px;">Hora</th>';
  canchas.forEach(c=>{th+=`<th style="min-width:140px;">${c}</th>`});
  th+='</tr>';
  document.getElementById('thHor').innerHTML=th;
  let tb='';
  horas.forEach(h=>{
    tb+=`<tr><td style="font-weight:600;color:var(--accent);">${h}</td>`;
    canchas.forEach(()=>{tb+=`<td><div class="hor-cell">+</div></td>`});
    tb+='</tr>';
  });
  document.getElementById('tbHor').innerHTML=tb;
}

// ═══ 8. RANKING ═══

// --- Tabs ---
function switchRankTab(tab, el) {
  document.querySelectorAll('#tabsRank .tab').forEach(t=>t.classList.remove('active'));
  el.classList.add('active');
  document.getElementById('rankTab-calcular').style.display  = tab==='calcular'  ? 'block' : 'none';
  document.getElementById('rankTab-ver').style.display       = tab==='ver'       ? 'block' : 'none';
  document.getElementById('rankTab-etiquetas').style.display = tab==='etiquetas' ? 'block' : 'none';
  if(tab==='ver') { loadRankingCats(); loadRanking(); }
  if(tab==='calcular') loadRankEventos();
  if(tab==='etiquetas') loadEtqEventos();
}

// --- TAB CALCULAR: listar eventos culminados ---
async function loadRankEventos() {
  const box = document.getElementById('rankEventos');
  box.innerHTML = '<div class="loading" style="min-height:80px;border-radius:8px;"></div>';
  const r = await api({action:'eventos', estado:'culminado'});
  if (!r.success || !r.eventos.length) {
    box.innerHTML = '<div class="empty"><i class="fas fa-trophy"></i><p>Sin eventos culminados</p></div>';
    return;
  }
  // Para cada evento, verificar si tiene ranking en _ranking
  let html = '<div style="display:flex;flex-direction:column;gap:12px;">';
  for (const ev of r.eventos) {
    const rk = await api({action:'ranking_count', evento: ev.id});
    const count = rk.success ? rk.total : 0;
    const tiene = count > 0;
    const badgeHtml = tiene
      ? `<span class="badge badge-s">✓ CALCULADO <span style="font-weight:400;">(${count} registros)</span></span>`
      : `<span class="badge badge-w">SIN CALCULAR</span>`;
    const btnLabel = tiene ? '🔄 Recalcular' : '⚡ Calcular Ranking';
    const btnClass = tiene ? 'btn-warn' : 'btn-ok';
    const fecha = ev.fecha ? new Date(ev.fecha+'T12:00:00').toLocaleDateString('es-PY',{day:'2-digit',month:'2-digit',year:'numeric'}) : '—';
    html += `
    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-md);padding:16px 20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
      <div>
        <div style="font-size:14px;font-weight:700;">${ev.evento}</div>
        <div style="font-size:12px;color:var(--text-muted);margin-top:2px;">${fecha} · ID ${ev.id}</div>
      </div>
      <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
        ${badgeHtml}
        <button class="btn ${btnClass} btn-sm" id="btnRank-${ev.id}" onclick="calcularRanking(${ev.id},'${ev.evento.replace(/'/g,"\\'")}')">
          ${btnLabel}
        </button>
      </div>
    </div>`;
  }
  html += '</div>';
  box.innerHTML = html;
}

// --- Calcular ranking de un evento ---
async function calcularRanking(idEvento, nombreEvento) {
  if (!confirm(`¿Calcular ranking para "${nombreEvento}"?\n\nEsto borrará y recalculará todos los puntos de ese evento.`)) return;
  const btn = document.getElementById('btnRank-' + idEvento);
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Calculando...';

  const logBox  = document.getElementById('rankLog');
  const logBody = document.getElementById('rankLogBody');
  const logTitle= document.getElementById('rankLogTitle');
  logBox.style.display = 'none';

  try {
    const resp = await fetch(`calculo_ranking_auto.php?evento=${idEvento}&debug=1`);
    const d = await resp.json();

    logTitle.textContent = d.success
      ? `✓ Ranking calculado — ${d.nombre}`
      : `✗ Error — Evento ${idEvento}`;

    if (d.success) {
      logBody.innerHTML = d.detalle.map(l => {
        let color = 'var(--text-primary)';
        if (l.startsWith('FINAL'))   color = 'var(--warning)';
        if (l.startsWith('PADRE'))   color = 'var(--info)';
        if (l.startsWith('GU'))      color = '#22c55e';
        if (l.startsWith('RONDA'))   color = 'var(--text-secondary)';
        if (l.startsWith('✓'))       color = 'var(--success)';
        if (l.startsWith('DELETE'))  color = 'var(--danger)';
        return `<div style="color:${color};padding:1px 0;border-bottom:1px solid var(--border)22;">${l}</div>`;
      }).join('');
      btn.innerHTML = '🔄 Recalcular';
      btn.className = 'btn btn-warn btn-sm';
    } else {
      logBody.innerHTML = `<div style="color:var(--danger);">Error: ${d.error || 'desconocido'}</div>`;
      btn.innerHTML = '⚡ Calcular Ranking';
    }
    logBox.style.display = 'block';
    logBox.scrollIntoView({behavior:'smooth', block:'start'});
    // Refrescar lista
    await loadRankEventos();
  } catch(e) {
    logBody.innerHTML = `<div style="color:var(--danger);">Error de conexión: ${e.message}</div>`;
    logBox.style.display = 'block';
    btn.disabled = false;
    btn.innerHTML = '⚡ Calcular Ranking';
  }
}

// --- TAB VER: ranking general ---
async function loadRankingCats() {
  const sel = document.getElementById('fRankCat');
  if (sel.options.length > 1) return; // ya cargado
  const r = await api({action: 'todas_cats'});
  if (r.success) r.categorias.forEach(c => {
    const o = document.createElement('option');
    o.value = c.id; o.textContent = c.categoria;
    sel.appendChild(o);
  });
}

let rankTimer;
function debounceRank(){clearTimeout(rankTimer);rankTimer=setTimeout(loadRanking,400)}
async function loadRanking(){
  const q   = document.getElementById('fRankQ')   ? document.getElementById('fRankQ').value   : '';
  const cat = document.getElementById('fRankCat') ? document.getElementById('fRankCat').value : '';
  const r = await api({action:'ranking', q, categoria: cat, limit: 50});
  if (r.success) {
    document.getElementById('rankTotal').textContent = r.total + ' jugadores';
    let h = '';
    r.ranking.forEach((j,i) => {
      const pc = i===0?'g':i===1?'s':i===2?'b':'n';
      const pts = j.puntos || j.partidos_ganados || 0;
      h += `<div class="rank-item">
        <div class="rank-pos ${pc}">${i+1}</div>
        <div style="flex:1;">
          <div class="rank-name">${j.nombre||''} ${j.apellido||''}</div>
          <div class="rank-detail">${j.categoria||''}</div>
        </div>
        <div class="rank-pts">${pts}</div>
      </div>`;
    });
    document.getElementById('rankList').innerHTML = h || '<div class="empty"><i class="fas fa-search"></i><p>Sin resultados</p></div>';
    // Chart
    if(chRank) chRank.destroy();
    const top = r.ranking.slice(0,10);
    if(top.length){
      chRank = new Chart(document.getElementById('chRank'),{
        type:'bar',
        data:{
          labels: top.map(j=>((j.nombre||'').split(' ')[0])+' '+((j.apellido||'').split(' ')[0])),
          datasets:[{
            label:'Puntos',
            data: top.map(j=>j.puntos||j.partidos_ganados||0),
            backgroundColor:['#6366f1','#3b82f6','#22c55e','#f59e0b','#ef4444','#ec4899','#8b5cf6','#14b8a6','#f97316','#06b6d4'],
            borderRadius:6,borderSkipped:false
          }]
        },
        options:{
          indexAxis:'y',responsive:true,maintainAspectRatio:false,
          plugins:{legend:{display:false}},
          scales:{x:{beginAtZero:true,grid:{color:cssVar('--border')+'66'}},y:{grid:{display:false}}}
        }
      });
    }
  }
}

// ═══ ETIQUETAS / PUNTAJES ═══
let etqEventoActual = 0;
let etqTodasEtiquetas = []; // cache de _p_etiquetas

async function loadEtqEventos() {
  const sel = document.getElementById('etqEvSel');
  if (sel.options.length > 1) return; // ya cargado
  const r = await api({action:'eventos'});
  if (r.success) r.eventos.forEach(ev => {
    const o = document.createElement('option');
    o.value = ev.id;
    o.textContent = ev.evento + ' (ID:' + ev.id + ')';
    sel.appendChild(o);
  });
}

async function loadEtiquetasEvento() {
  const sel = document.getElementById('etqEvSel');
  const idEv = parseInt(sel.value);
  etqEventoActual = idEv;
  const box = document.getElementById('etqContenido');
  const btnAuto = document.getElementById('btnAutoEtq');
  const btnLimp = document.getElementById('btnLimpiarEtq');
  document.getElementById('etqAutoLog').style.display = 'none';
  document.getElementById('etqAddRow').style.display = 'none';

  if (!idEv) {
    box.innerHTML = '<div class="empty"><i class="fas fa-tags"></i><p>Seleccioná un evento para ver sus etiquetas</p></div>';
    btnAuto.style.display = 'none';
    btnLimp.style.display = 'none';
    return;
  }

  box.innerHTML = '<div class="loading" style="min-height:100px;border-radius:8px;"></div>';
  btnAuto.style.display = 'inline-flex';

  const r = await api({action:'etiquetas_evento', evento: idEv});
  if (!r.success) { box.innerHTML = '<div class="empty"><p>Error al cargar</p></div>'; return; }

  btnLimp.style.display = r.total > 0 ? 'inline-flex' : 'none';

  if (r.total === 0) {
    box.innerHTML = `
      <div class="empty" style="padding:40px 20px;">
        <i class="fas fa-inbox" style="font-size:32px;margin-bottom:12px;color:var(--text-muted);"></i>
        <p style="font-size:14px;margin-bottom:8px;">Sin etiquetas configuradas para este evento</p>
        <p style="font-size:12px;color:var(--text-muted);margin-bottom:16px;">Usá "Auto-Generar" para crear las etiquetas automáticamente según las plantillas de cada categoría, o agregá manualmente.</p>
        <button class="btn btn-gh btn-sm" onclick="mostrarAddEtiqueta()"><i class="fas fa-plus"></i> Agregar manualmente</button>
      </div>`;
    return;
  }

  // Renderizar por categoría
  let html = '';
  r.por_categoria.forEach(cat => {
    const esMixta = [17,18,21,22,23].includes(parseInt(cat.id_categoria));
    const tienePos = cat.etiquetas.some(e => ['POS1','POS2','POS3','POS4'].includes(e.value));
    const tipoBadge = tienePos ? '<span class="badge badge-s" style="font-size:10px;">GRUPO ÚNICO</span>'
      : esMixta ? '<span class="badge badge-w" style="font-size:10px;">MIXTA</span>'
      : '<span class="badge" style="font-size:10px;background:var(--accent-light);color:var(--accent);">NORMAL</span>';

    html += `<div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-md);padding:16px;margin-bottom:12px;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px;">
        <div style="display:flex;align-items:center;gap:8px;">
          <span style="font-weight:700;font-size:14px;">${cat.categoria}</span>
          ${tipoBadge}
        </div>
        <div style="display:flex;gap:6px;">
          <button class="btn btn-gh btn-sm" style="font-size:11px;" onclick="limpiarEtiquetasCat(${idEv},${cat.id_categoria},'${cat.categoria.replace(/'/g,"\\'")}')">
            <i class="fas fa-trash-alt"></i> Limpiar
          </button>
        </div>
      </div>
      <div style="display:flex;flex-wrap:wrap;gap:8px;">`;

    cat.etiquetas.forEach(et => {
      const colorMap = {
        'FG':'#6366f1', '8':'#3b82f6', '4':'#06b6d4', '2':'#22c55e', 
        'VC':'#f59e0b', 'CC':'#ef4444', '1':'#ef4444',
        'POS1':'#ef4444', 'POS2':'#f59e0b', 'POS3':'#22c55e', 'POS4':'#06b6d4',
        'R':'#8b5cf6', 'NULL':'#6b7280'
      };
      const color = colorMap[et.value] || '#6b7280';
      html += `<div style="background:${color}18;border:1px solid ${color}44;border-radius:8px;padding:8px 12px;display:flex;align-items:center;gap:8px;min-width:180px;">
        <div style="width:6px;height:28px;border-radius:3px;background:${color};flex-shrink:0;"></div>
        <div style="flex:1;">
          <div style="font-size:11px;color:var(--text-muted);line-height:1.2;">${et.etiqueta}</div>
          <div style="font-size:16px;font-weight:700;color:var(--text-primary);">
            <input type="number" value="${et.puntos}" min="0" style="width:60px;border:none;background:transparent;font-size:16px;font-weight:700;color:var(--text-primary);font-family:inherit;padding:0;"
              onchange="actualizarPuntos(${et.id}, this.value)" title="Editar puntos">
          </div>
        </div>
        <button onclick="eliminarEtiqueta(${et.id})" style="background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:12px;padding:2px 4px;" title="Eliminar">
          <i class="fas fa-times"></i>
        </button>
      </div>`;
    });

    html += `</div></div>`;
  });

  html += `<div style="margin-top:12px;">
    <button class="btn btn-gh btn-sm" onclick="mostrarAddEtiqueta()"><i class="fas fa-plus"></i> Agregar etiqueta manual</button>
  </div>`;

  box.innerHTML = html;
}

async function autoGenerarEtiquetas() {
  if (!etqEventoActual) return;
  if (!confirm('¿Auto-generar etiquetas para TODAS las categorías de este evento?\n\nLas categorías que ya tienen etiquetas serán omitidas.')) return;

  const btn = document.getElementById('btnAutoEtq');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generando...';

  const r = await api({action:'generar_etiquetas', evento: etqEventoActual});

  btn.disabled = false;
  btn.innerHTML = '<i class="fas fa-magic"></i> Auto-Generar Etiquetas';

  const logBox = document.getElementById('etqAutoLog');
  const logBody = document.getElementById('etqAutoLogBody');

  if (r.success) {
    let logHtml = `<div style="font-weight:700;margin-bottom:8px;color:var(--success);">✓ ${r.mensaje}</div>`;
    r.detalle.forEach(d => {
      if (d.estado === 'generada') {
        const fases = [];
        if (d.es_grupo_unico) fases.push('GRUPO ÚNICO');
        else {
          if (d.fases['16vos']) fases.push('16vos');
          if (d.fases['8vos']) fases.push('8vos');
          if (d.fases.cuartos) fases.push('Cuartos');
          if (d.fases.semis) fases.push('Semis');
          if (d.fases.final) fases.push('Final');
        }
        logHtml += `<div style="padding:4px 0;border-bottom:1px solid var(--border)22;color:var(--success);">
          ✓ <strong>${d.categoria}</strong> — ${d.parejas} parejas · ${d.es_mixta?'MIXTA':'NORMAL'} · ${fases.join(' → ')} · ${d.etiquetas_insertadas} etiquetas
        </div>`;
      } else {
        logHtml += `<div style="padding:4px 0;border-bottom:1px solid var(--border)22;color:var(--text-muted);">
          ⏭ <strong>${d.categoria}</strong> — ${d.motivo}
        </div>`;
      }
    });
    logBody.innerHTML = logHtml;
    logBox.style.display = 'block';
    await loadEtiquetasEvento();
  } else {
    logBody.innerHTML = `<div style="color:var(--danger);">Error: ${r.error || 'desconocido'}</div>`;
    logBox.style.display = 'block';
  }
}

async function actualizarPuntos(id, nuevosPuntos) {
  const pts = parseInt(nuevosPuntos) || 0;
  const r = await api({action:'guardar_etiqueta', id: id, evento: etqEventoActual, categoria:0, etiqueta:0, puntos: pts});
  if (!r.success) alert('Error al actualizar: ' + (r.error||''));
}

async function eliminarEtiqueta(id) {
  if (!confirm('¿Eliminar esta etiqueta?')) return;
  const r = await api({action:'eliminar_etiqueta', id: id});
  if (r.success) {
    await loadEtiquetasEvento();
  } else {
    alert('Error: ' + (r.error||''));
  }
}

async function limpiarEtiquetasCat(idEv, idCat, nombre) {
  if (!confirm(`¿Eliminar TODAS las etiquetas de "${nombre}" en este evento?`)) return;
  const r = await api({action:'limpiar_etiquetas', evento: idEv, categoria: idCat});
  if (r.success) {
    await loadEtiquetasEvento();
  } else {
    alert('Error: ' + (r.error||''));
  }
}

async function limpiarEtiquetasEvento() {
  if (!etqEventoActual) return;
  if (!confirm('¿ELIMINAR TODAS las etiquetas de TODAS las categorías de este evento?\n\nEsta acción no se puede deshacer.')) return;
  const r = await api({action:'limpiar_etiquetas', evento: etqEventoActual});
  if (r.success) {
    await loadEtiquetasEvento();
    document.getElementById('etqAutoLog').style.display = 'none';
  } else {
    alert('Error: ' + (r.error||''));
  }
}

async function mostrarAddEtiqueta() {
  if (!etqEventoActual) return;
  const row = document.getElementById('etqAddRow');
  row.style.display = 'block';

  // Cargar categorías del evento
  const selCat = document.getElementById('etqAddCat');
  if (selCat.options.length <= 1) {
    selCat.innerHTML = '<option value="">— Categoría —</option>';
    const rc = await api({action:'cats_evento', evento: etqEventoActual});
    if (rc.success) rc.categorias.forEach(c => {
      const o = document.createElement('option');
      o.value = c.id_categoria;
      o.textContent = c.categoria;
      selCat.appendChild(o);
    });
  }

  // Cargar etiquetas
  const selEt = document.getElementById('etqAddEtiq');
  if (selEt.options.length <= 1) {
    selEt.innerHTML = '<option value="">— Etiqueta —</option>';
    const re = await api({action:'todas_etiquetas'});
    if (re.success) {
      etqTodasEtiquetas = re.etiquetas;
      re.etiquetas.forEach(e => {
        const o = document.createElement('option');
        o.value = e.id;
        o.textContent = e.etiqueta + (e.value ? ' ('+e.value+')' : '');
        selEt.appendChild(o);
      });
    }
  }

  row.scrollIntoView({behavior:'smooth', block:'center'});
}

async function guardarEtiquetaManual() {
  const idCat = parseInt(document.getElementById('etqAddCat').value);
  const idEtiq = parseInt(document.getElementById('etqAddEtiq').value);
  const pts = parseInt(document.getElementById('etqAddPts').value) || 0;
  if (!idCat || !idEtiq) { alert('Seleccioná categoría y etiqueta'); return; }

  const r = await api({action:'guardar_etiqueta', evento: etqEventoActual, categoria: idCat, etiqueta: idEtiq, puntos: pts});
  if (r.success) {
    document.getElementById('etqAddRow').style.display = 'none';
    await loadEtiquetasEvento();
  } else {
    alert('Error: ' + (r.error||''));
  }
}

// ═══ NUEVO EVENTO ═══
let evTabActual = 1;
const evTabTotal = 5;

function switchEvTab(n, el) {
  for (let i = 1; i <= evTabTotal; i++) {
    const t = document.getElementById('evTab-' + i);
    if (t) t.style.display = i === n ? 'block' : 'none';
  }
  document.querySelectorAll('#tabsEvForm .tab').forEach((t, idx) => {
    t.classList.toggle('active', idx + 1 === n);
  });
  evTabActual = n;
  document.getElementById('btnEvPrev').style.display  = n > 1 ? 'inline-flex' : 'none';
  document.getElementById('btnEvNext').style.display  = n < evTabTotal ? 'inline-flex' : 'none';
  document.getElementById('btnEvGuardar').style.display = n === evTabTotal ? 'inline-flex' : 'none';
  if (n === 5) cargarCatsEvento();
}
function nextEvTab() { if (evTabActual < evTabTotal) switchEvTab(evTabActual + 1, null); }
function prevEvTab() { if (evTabActual > 1) switchEvTab(evTabActual - 1, null); }

// Textos hardcodeados
const REGLAMENTACION_HARDCODED = `<h3>C&oacute;digo de Conducta</h3>
<p>Las reglas de conductas ser&aacute;n consideradas de acuerdo al C&oacute;digo de Conducta del World Tour.</p>
<p><a href="https://bt.com.py/bases-y-condiciones/codigo_de_conducta.pdf" target="_blank" rel="noopener">Ver C&oacute;digo de Conducta</a></p>
<h3>Condiciones F&iacute;sicas</h3>
<p>Todos los participantes deben certificar que se encuentran en condiciones f&iacute;sicas aptas para competir y sin impedimento m&eacute;dico alguno.</p>
<h3>Pol&iacute;tica de Privacidad</h3>
<p>Los datos recopilados ser&aacute;n utilizados exclusivamente para la gesti&oacute;n del torneo, conforme a nuestra pol&iacute;tica de privacidad.</p>`;

const EMAIL_HARDCODED = `<p style="line-height: 1;">Hola&nbsp;<strong>{usuario}</strong></p>
<p style="line-height: 1;">Bienvenido a <strong>BT.COM.PY !!!</strong></p>
<p style="line-height: 1;">Gracias por tu pre-inscripci&oacute;n al Torneo&nbsp;<strong>{evento}.</strong></p>
<p style="line-height: 1.5;">Fecha del Evento:&nbsp;<strong>{fecha}</strong></p>
<p style="line-height: 1.5;"><span style="color: #3598db;"><strong>DATOS DE LA DUPLA:</strong></span></p>
<p style="line-height: 1;">Nombre:&nbsp;<strong>{nombre} {apellido}</strong><br />N&deg;. de documento:&nbsp;<strong>{ci}&nbsp;</strong></p>
<p style="line-height: 1;"><strong>$dupla </strong></p>
<p><span style="color: #e03e2d;"><strong>INFORMACION IMPORTANTE: LA PRE-INSCRIPCION AL TORNEO DEBE SER ABONADA Y/O CONFIRMADA HASTA 24 HORAS ANTES DEL INICIO DEL EVENTO, DE LO CONTRARIO SE AUTO ELIMINA.</strong></span></p>
<p style="line-height: 1;"><span style="color: #3598db;"><strong><u>INFORMACION DE PAGO:</u></strong></span></p>
<p style="line-height: 1;"><span style="font-size: 12pt;">Costo de inscripci&oacute;n:&nbsp;<strong>{costo}&nbsp;</strong>Gs por PAREJA.</span></p>
<p style="line-height: 1;"><span style="font-size: 12pt;">Para realizar el pago correspondiente, ponemos a tu disposici&oacute;n el&nbsp;<span style="color: #e03e2d;"><strong>Pago On-line</strong></span>&nbsp;en el siguiente enlace:&nbsp;<strong>{link_pago}</strong></span></p>
<p style="line-height: 1;"><span style="font-size: 12pt;">Fecha l&iacute;mite de pago:&nbsp;<strong>{limite_pago}</strong></span></p>
<p>Un saludo y EXITOS !!</p>
<p><br /><a href="http://www.padelsys.com">www.bt.com.py</a></p>`;

function autoUrlAmigable() {
  const nombre = document.getElementById('evNombre').value;
  const url = nombre.toLowerCase()
    .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9\s-]/g, '')
    .trim().replace(/\s+/g, '-');
  document.getElementById('evUrl').value = url;
  document.getElementById('evAsuntoEmail').value = nombre;
}

// Inicializar campos hardcodeados al abrir el modal
function initHardcodedFields() {
  document.getElementById('evReglamentacion').value = REGLAMENTACION_HARDCODED;
  document.getElementById('evTextoEmail').value = EMAIL_HARDCODED;
}

function toggleAsuntoEmail() {
  const activo = document.getElementById('evEmailInscr').value === 'si';
  document.getElementById('evAsuntoEmail').readOnly = !activo;
  document.getElementById('evAsuntoEmail').style.background = activo ? 'var(--bg-primary)' : 'var(--bg-hover)';
  document.getElementById('evAsuntoEmail').style.color = activo ? 'var(--text-primary)' : 'var(--text-muted)';
}

async function loadCiudades() {
  const sel = document.getElementById('evCiudad');
  if (sel.options.length > 1) return; // ya cargado
  const r = await api({action: 'ciudades'});
  if (r.success) {
    r.ciudades.forEach(c => {
      const o = document.createElement('option');
      o.value = c.id; o.textContent = c.nombre;
      sel.appendChild(o);
    });
  }
}

// Abrir modal de nuevo evento
function abrirNuevoEvento() {
  document.getElementById('evId').value = '';
  document.getElementById('modalEventoTitle').innerHTML = '<i class="fas fa-trophy" style="margin-right:8px;color:var(--accent);"></i>Nuevo Evento';
  // Limpiar campos
  ['evNombre','evUrl','evDescImgUrl','evAsuntoEmail','evCosto1','evCosto2',
   'evFecha','evFechaFin','evFechaFinInscr','evFechaFinPago'].forEach(id=>{
    const el=document.getElementById(id); if(el) el.value='';
  });
  document.getElementById('evEstado').value='registro';
  document.getElementById('evPrioridad').value='1';
  document.getElementById('evCircuito').value='1';
  document.getElementById('evOrganizador').value='1';
  document.getElementById('evCiudad').value='';
  document.getElementById('evTipo').value='';
  document.getElementById('evVersionForm').value='v2';
  document.getElementById('evUrlFixture').value='grafico-llaves';
  document.getElementById('evBtnFixture').value='visible';
  document.getElementById('evBtnInscripcion').value='si';
  document.getElementById('evCantInscriptos').value='si';
  document.getElementById('evBtnLlaves').value='oculto';
  document.getElementById('evBasesCond').value='';
  document.getElementById('evFixturePublicado').value='si';
  document.getElementById('evEmailInscr').value='no';
  openModal('modalEvento');
  switchEvTab(1, null);
  loadCiudades();
  initHardcodedFields();
}

async function editarEvento(id) {
  document.getElementById('evId').value = id;
  document.getElementById('modalEventoTitle').innerHTML = '<i class="fas fa-edit" style="margin-right:8px;color:var(--accent);"></i>Editar Evento #' + id;
  openModal('modalEvento');
  switchEvTab(1, null);
  loadCiudades();
  initHardcodedFields();

  // Cargar datos del evento
  const r = await api({action: 'get_evento', id});
  if (!r.success) { alert('Error al cargar el evento'); return; }
  const e = r.evento;

  document.getElementById('evNombre').value        = e.evento || '';
  document.getElementById('evUrl').value           = e.url_amigable || '';
  document.getElementById('evEstado').value        = e.estado || 'inactivo';
  document.getElementById('evPrioridad').value     = e.prioridad || 1;
  document.getElementById('evCircuito').value      = e.id_circuito || 1;
  document.getElementById('evOrganizador').value   = e.id_organizador || 1;
  document.getElementById('evTipo').value          = e.id_tipo_evento || '';
  // descripcion se maneja via evDescImgUrl (ver más abajo)
  document.getElementById('evVersionForm').value   = e.version_formulario || 'v2';
  document.getElementById('evUrlFixture').value    = e.url_fixture || 'grafico-llaves';
  document.getElementById('evBtnFixture').value    = e.boton_fixture || 'visible';
  document.getElementById('evBtnInscripcion').value= e.boton_inscripcion || 'si';
  document.getElementById('evCantInscriptos').value= e.cantidad_inscriptos || 'si';
  document.getElementById('evBtnLlaves').value     = e.boton_llaves || 'oculto';
  document.getElementById('evBasesCond').value     = e.base_condiciones || '';
  document.getElementById('evFixturePublicado').value = e.fixture_publicado || 'si';
  // Fechas — formato YYYY-MM-DD requerido por input[type=date]
  const toDate = v => (v && v !== '0000-00-00') ? v.substring(0,10) : '';
  document.getElementById('evFecha').value          = toDate(e.fecha);
  document.getElementById('evFechaFin').value       = toDate(e.fecha_fin);
  document.getElementById('evFechaFinInscr').value  = toDate(e.fecha_fin_inscripcion);
  document.getElementById('evFechaFinPago').value   = toDate(e.fecha_fin_pago);

  // Costos — mostrar 0 si es 0
  document.getElementById('evCosto1').value = e.costo1 != null ? e.costo1 : '';
  document.getElementById('evCosto2').value = e.costo2 != null ? e.costo2 : '';

  // Email — primero setear el valor, luego toggleAsuntoEmail para no bloquear
  document.getElementById('evEmailInscr').value = e.email_inscipcion || 'no';
  document.getElementById('evAsuntoEmail').value = e.asunto_email_inscripcion || e.evento || '';
  toggleAsuntoEmail();

  // Imagen — extraer URL de src en descripción y mostrar preview
  const imgMatch = (e.descripcion||'').match(/src="([^"]+)"/);
  if (imgMatch) {
    const imgUrl = imgMatch[1];
    document.getElementById('evDescImgUrl').value = imgUrl;
    const preview = document.getElementById('evImgPreview');
    const previewImg = document.getElementById('evImgPreviewImg');
    previewImg.src = imgUrl;
    previewImg.style.display = 'block';
    preview.style.display = 'flex';
    document.getElementById('evImgStatus').textContent = 'Imagen actual';
  }

  // Flyer
  if (e.flyer) {
    document.getElementById('evFlyerName').value = e.flyer;
    var flyerUrl = '/sistema@/_lib/file/img/' + e.flyer;
    document.getElementById('evFlyerPreviewImg').src = flyerUrl;
    document.getElementById('evFlyerPreviewImg').style.display = 'block';
    document.getElementById('evFlyerPreview').style.display = 'flex';
    document.getElementById('evFlyerStatus').textContent = 'Flyer actual';
  } else {
    document.getElementById('evFlyerName').value = '';
    document.getElementById('evFlyerPreview').style.display = 'none';
  }

  // Ciudad — esperar a que loadCiudades termine
  const setCiudad = () => {
    const sel = document.getElementById('evCiudad');
    if (sel.options.length > 1 && e.id_ciudad) {
      sel.value = e.id_ciudad;
    } else if (e.id_ciudad) {
      setTimeout(setCiudad, 200);
    }
  };
  setCiudad();
}

async function subirFlyerEvento() {
  var file = document.getElementById('evFlyerFile').files[0];
  if (!file) return;
  var preview = document.getElementById('evFlyerPreview');
  var previewImg = document.getElementById('evFlyerPreviewImg');
  var status = document.getElementById('evFlyerStatus');
  preview.style.display = 'flex';
  previewImg.style.display = 'none';
  status.textContent = 'Subiendo...';
  status.style.color = 'var(--text-muted)';

  var formData = new FormData();
  formData.append('imagen', file);

  try {
    var r = await fetch('subir_imagen_evento.php', {method:'POST', body: formData});
    var d = await r.json();
    if (d.success) {
      document.getElementById('evFlyerName').value = d.nombre;
      previewImg.src = d.url;
      previewImg.style.display = 'block';
      status.textContent = 'Subido correctamente';
      status.style.color = 'var(--success)';
    } else {
      status.textContent = 'Error: ' + (d.error || 'desconocido');
      status.style.color = 'var(--danger)';
    }
  } catch(e) {
    status.textContent = 'Error de conexion';
    status.style.color = 'var(--danger)';
  }
}

async function subirImagenEvento() {
  const file = document.getElementById('evImgFile').files[0];
  if (!file) return;
  const preview = document.getElementById('evImgPreview');
  const previewImg = document.getElementById('evImgPreviewImg');
  const status = document.getElementById('evImgStatus');
  preview.style.display = 'flex';
  previewImg.style.display = 'none';
  status.textContent = 'Subiendo...';
  status.style.color = 'var(--text-muted)';

  const formData = new FormData();
  formData.append('imagen', file);

  try {
    const r = await fetch('subir_imagen_evento.php', {method:'POST', body: formData});
    const d = await r.json();
    if (d.success) {
      document.getElementById('evDescImgUrl').value = d.url;
      previewImg.src = d.url;
      previewImg.style.display = 'block';
      status.textContent = '✓ Subida correctamente';
      status.style.color = 'var(--success)';
    } else {
      status.textContent = '✗ Error: ' + (d.error || 'desconocido');
      status.style.color = 'var(--danger)';
    }
  } catch(e) {
    status.textContent = '✗ Error de conexión';
    status.style.color = 'var(--danger)';
  }
}

async function guardarEvento() {
  const nombre = document.getElementById('evNombre').value.trim();
  const url    = document.getElementById('evUrl').value.trim();
  const fecha  = document.getElementById('evFecha').value;
  if (!nombre) { alert('El nombre del evento es obligatorio'); switchEvTab(1,null); document.getElementById('evNombre').focus(); return; }
  if (!url)    { alert('La URL amigable es obligatoria'); switchEvTab(1,null); document.getElementById('evUrl').focus(); return; }
  if (!fecha)  { alert('La fecha del evento es obligatoria'); switchEvTab(3,null); document.getElementById('evFecha').focus(); return; }

  const btn = document.getElementById('btnEvGuardar');
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

  const params = {
    action:             document.getElementById('evId').value ? 'editar_evento' : 'crear_evento',
    id:                 document.getElementById('evId').value,
    evento:             nombre,
    url_amigable:       url,
    estado:             document.getElementById('evEstado').value,
    prioridad:          document.getElementById('evPrioridad').value,
    id_circuito:        document.getElementById('evCircuito').value,
    id_organizador:     document.getElementById('evOrganizador').value,
    id_ciudad:          document.getElementById('evCiudad').value,
    id_tipo_evento:     document.getElementById('evTipo').value,
    descripcion:        (()=>{
      const url = document.getElementById('evDescImgUrl').value.trim();
      return url ? '<p><img src="'+url+'" alt="" width="307" height="384" /></p>' : '';
    })(),
    reglamentacion:     document.getElementById('evReglamentacion').value,
    version_formulario: document.getElementById('evVersionForm').value,
    url_fixture:        document.getElementById('evUrlFixture').value,
    boton_fixture:      document.getElementById('evBtnFixture').value,
    boton_inscripcion:  document.getElementById('evBtnInscripcion').value,
    cantidad_inscriptos:document.getElementById('evCantInscriptos').value,
    boton_llaves:       document.getElementById('evBtnLlaves').value,
    base_condiciones:   document.getElementById('evBasesCond').value,
    fixture_publicado:  document.getElementById('evFixturePublicado').value,
    fecha:              fecha,
    fecha_fin:          document.getElementById('evFechaFin').value,
    fecha_fin_inscripcion: document.getElementById('evFechaFinInscr').value,
    fecha_fin_pago:     document.getElementById('evFechaFinPago').value,
    costo1:             document.getElementById('evCosto1').value || 0,
    costo2:             document.getElementById('evCosto2').value || 0,
    email_inscipcion:   document.getElementById('evEmailInscr').value,
    asunto_email_inscripcion: document.getElementById('evAsuntoEmail').value,
    texto_email_inscipcion:   document.getElementById('evTextoEmail').value,
    flyer:                  document.getElementById('evFlyerName').value,
  };

  const r = await api(params);
  btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Guardar Evento';

  if (r.success) {
    closeModal('modalEvento');
    alert(r.mensaje || '✓ Operación completada correctamente');
    loadEventos();
  } else {
    alert('Error: ' + (r.error || 'desconocido'));
  }
}

// ═══ RESUMEN DASHBOARD ═══
let resumenTipo = 'inscripciones';
let resumenTabActual = 'eventos';

async function abrirResumenModal(tipo) {
  resumenTipo = tipo;
  const titulo = tipo === 'inscripciones' ? 'Resumen de Inscripciones' : 'Resumen de Partidos Generados';
  document.getElementById('modalResumenTitle').innerHTML = 
    `<i class="fas fa-chart-bar" style="margin-right:8px;color:var(--accent);"></i>${titulo}`;
  openModal('modalResumen');
  // resetear tabs
  document.querySelectorAll('#tabsResumen .tab').forEach((t,i)=>t.classList.toggle('active',i===0));
  resumenTabActual = 'eventos';
  cargarResumen('eventos');
}

function switchResumenTab(tab, el) {
  document.querySelectorAll('#tabsResumen .tab').forEach(t=>t.classList.remove('active'));
  el.classList.add('active');
  resumenTabActual = tab;
  cargarResumen(tab);
}

async function cargarResumen(tab) {
  const box = document.getElementById('resumenContent');
  box.innerHTML = '<div class="loading" style="min-height:80px;border-radius:8px;"></div>';
  const accion = resumenTipo === 'inscripciones' ? 'resumen_inscripciones' : 'resumen_partidos';
  const r = await api({action: accion, agrupado: tab});
  if (!r.success) { box.innerHTML = '<div class="empty">Error al cargar</div>'; return; }

  const datos = r.datos;
  if (!datos || !datos.length) { box.innerHTML = '<div class="empty">Sin datos</div>'; return; }

  let h = '<div class="tbl-wrap"><table style="width:100%;font-size:13px;">';
  if (tab === 'eventos') {
    h += '<thead><tr><th>Evento</th><th style="text-align:right;">Total</th></tr></thead><tbody>';
    datos.forEach(d => {
      h += `<tr><td style="font-weight:500;">${d.nombre}</td><td style="text-align:right;font-weight:700;color:var(--accent);">${d.total}</td></tr>`;
    });
  } else {
    h += '<thead><tr><th>Categoría</th><th style="text-align:right;">Total</th></tr></thead><tbody>';
    datos.forEach(d => {
      h += `<tr><td><span class="badge badge-a">${d.nombre}</span></td><td style="text-align:right;font-weight:700;color:var(--accent);">${d.total}</td></tr>`;
    });
  }
  h += '</tbody></table></div>';
  // Total
  const gran_total = datos.reduce((s,d)=>s+(parseInt(d.total)||0),0);
  h += `<div style="text-align:right;font-size:13px;font-weight:700;margin-top:12px;color:var(--text-secondary);">TOTAL: <span style="color:var(--accent);font-size:16px;">${gran_total}</span></div>`;
  box.innerHTML = h;
}

// ═══ INSCRIPTOS POR CATEGORÍA — LISTADO ═══
let _inscCatEvento = 0;
let _inscCatId = 0;

async function verInscriptosCat(evento, catId, catNombre) {
  _inscCatEvento = evento;
  _inscCatId = catId;
  document.getElementById('mlInscTitle').textContent = catNombre + ' — Inscriptos';
  document.getElementById('mlInscBody').innerHTML = '<div class="loading" style="min-height:60px;border-radius:8px;"></div>';
  openModal('modalListInscr');

  const r = await api({action:'inscriptos_categoria', evento, categoria: catId});
  if (!r.success) { document.getElementById('mlInscBody').innerHTML = '<div class="empty">Error al cargar</div>'; return; }
  if (r.total === 0) { document.getElementById('mlInscBody').innerHTML = '<div class="empty" style="padding:20px;"><i class="fas fa-inbox" style="font-size:24px;margin-bottom:8px;display:block;"></i>No hay inscriptos en esta categoría</div>'; return; }

  let h = '<table style="width:100%;border-collapse:collapse;font-size:13px;"><thead><tr style="background:var(--bg-primary);color:var(--text-muted);font-size:11px;text-transform:uppercase;">';
  h += '<th style="padding:8px 10px;text-align:left;">#</th><th style="padding:8px 10px;text-align:left;">Jugador 1</th><th style="padding:8px 6px;">CI</th><th style="padding:8px 10px;text-align:left;">Jugador 2</th><th style="padding:8px 6px;">CI</th><th style="padding:8px 6px;">Estado</th><th style="padding:8px 6px;">Acción</th></tr></thead><tbody>';

  r.parejas.forEach((p, idx) => {
    const n1 = ((p.nombre1||'')+' '+(p.apellido1||'')).trim() || 'Sin nombre';
    const n2 = ((p.nombre2||'')+' '+(p.apellido2||'')).trim() || 'Sin nombre';
    const estadoBadge = p.estado==='pagado'?'badge-s':p.estado==='inscripto'?'badge-i':p.estado==='bloqueado'?'badge-d':'badge-w';
    const eqId = p.equipo_id || p.id;
    h += '<tr style="border-bottom:1px solid var(--border);">';
    h += '<td style="padding:8px 10px;color:var(--text-muted);">'+(idx+1)+'</td>';
    h += '<td style="padding:8px 10px;font-weight:500;">'+n1+'</td>';
    h += '<td style="padding:8px 6px;font-size:11px;color:var(--text-muted);text-align:center;">'+p.ci1+'</td>';
    h += '<td style="padding:8px 10px;font-weight:500;">'+n2+'</td>';
    h += '<td style="padding:8px 6px;font-size:11px;color:var(--text-muted);text-align:center;">'+p.ci2+'</td>';
    h += '<td style="padding:8px 6px;text-align:center;"><span class="badge '+estadoBadge+'" style="font-size:10px;">'+(p.estado||'—')+'</span></td>';
    h += '<td style="padding:8px 6px;text-align:center;"><button class="btn btn-p btn-sm" onclick="editarDesdeListado('+eqId+',\''+n1.replace(/'/g,"\\'")+'\',\''+n2.replace(/'/g,"\\'")+'\',\''+p.ci1+'\',\''+p.ci2+'\','+catId+',\''+(p.estado||'')+'\',\''+(p.obs||'').replace(/'/g,"\\'")+'\')"><i class="fas fa-edit"></i></button></td>';
    h += '</tr>';
  });
  h += '</tbody></table>';
  h += '<div style="text-align:right;font-size:12px;color:var(--text-muted);margin-top:10px;">'+r.total+' parejas</div>';
  document.getElementById('mlInscBody').innerHTML = h;
}

function editarDesdeListado(id, nombre1, nombre2, ci1, ci2, idCat, estado, obs) {
  closeModal('modalListInscr');
  setTimeout(()=> editarInscripcion(id, nombre1, nombre2, ci1, ci2, idCat, estado, obs), 300);
}

// ═══ INSCRIPCIONES — EDITAR (con buscador) ═══
function editarInscripcion(id, nombre1, nombre2, ci1, ci2, idCat, estado, obs) {
  document.getElementById('iInscId').value    = id;
  document.getElementById('iInscNombre1').value = nombre1;
  document.getElementById('iInscNombre2').value = nombre2;
  document.getElementById('iInscCI1').value   = ci1;
  document.getElementById('iInscCI2').value   = ci2;
  document.getElementById('iInscCat').value   = idCat;
  document.getElementById('iInscEstado').value = estado || 'preinscripcion';
  document.getElementById('iInscObs').value   = obs || '';
  // Guardar CIs originales para que el API sepa qué reemplazar en _todosvstodos
  document.getElementById('iInscOrigCI1').value = ci1;
  document.getElementById('iInscOrigCI2').value = ci2;
  // Limpiar buscadores
  document.getElementById('iBuscar1').value = '';
  document.getElementById('iBuscar2').value = '';
  document.getElementById('iSugerencias1').style.display = 'none';
  document.getElementById('iSugerencias2').style.display = 'none';
  openModal('modalInscr');
}

async function eliminarInscripcion(id, n1, n2){
  if(!confirm(`¿Eliminar la inscripción de ${n1} / ${n2}?\nEsta acción no se puede deshacer.`)) return;
  const r = await api({action:'eliminar_inscripcion', id});
  if(r.success){ showToast('Inscripción eliminada','success'); loadInscripciones(); }
  else showToast(r.error||'Error al eliminar','error');
}

// ═══ BUSCADOR DE JUGADOR ═══
let _buscarTimer = null;

function buscarJugadorInput(num, val) {
  clearTimeout(_buscarTimer);
  const box = document.getElementById('iSugerencias'+num);
  if (val.length < 2) { box.style.display='none'; return; }
  _buscarTimer = setTimeout(async ()=>{
    const r = await api({action:'buscar_jugador', q: val});
    if (!r.success || r.total===0) { box.innerHTML='<div style="padding:10px;font-size:12px;color:var(--text-muted);">Sin resultados</div>'; box.style.display='block'; return; }
    let h = '';
    r.jugadores.forEach(j=>{
      const nombre = ((j.nombre||'')+' '+(j.apellido||'')).trim();
      h += '<div onclick="seleccionarJugador('+num+',\''+j.ci+'\',\''+nombre.replace(/'/g,"\\'")+'\')" style="padding:8px 12px;cursor:pointer;font-size:13px;border-bottom:1px solid var(--border);transition:background .15s;" onmouseover="this.style.background=\'var(--bg-hover)\'" onmouseout="this.style.background=\'transparent\'">';
      h += '<span style="font-weight:600;">'+nombre+'</span> <span style="color:var(--text-muted);font-size:11px;margin-left:6px;">CI: '+j.ci+'</span>';
      if (j.ciudad) h += ' <span style="color:var(--text-muted);font-size:10px;">· '+j.ciudad+'</span>';
      h += '</div>';
    });
    box.innerHTML = h;
    box.style.display = 'block';
  }, 300);
}

function seleccionarJugador(num, ci, nombre) {
  document.getElementById('iInscCI'+num).value = ci;
  document.getElementById('iInscNombre'+num).value = nombre;
  document.getElementById('iBuscar'+num).value = '';
  document.getElementById('iSugerencias'+num).style.display = 'none';
}

// Cerrar sugerencias al hacer clic fuera
document.addEventListener('click', function(e) {
  if (!e.target.closest('#iSugerencias1') && e.target.id !== 'iBuscar1') document.getElementById('iSugerencias1').style.display='none';
  if (!e.target.closest('#iSugerencias2') && e.target.id !== 'iBuscar2') document.getElementById('iSugerencias2').style.display='none';
});

async function guardarInscripcion() {
  const id     = document.getElementById('iInscId').value;
  const ci1    = document.getElementById('iInscCI1').value.trim();
  const ci2    = document.getElementById('iInscCI2').value.trim();
  const estado = document.getElementById('iInscEstado').value;
  const idCat  = document.getElementById('iInscCat').value;
  const obs    = document.getElementById('iInscObs').value;

  if (!ci1 || !ci2) { alert('Los CI de ambos jugadores son obligatorios'); return; }

  const btn = document.querySelector('#modalInscr .btn-ok');
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

  const r = await api({action:'editar_inscripcion', id, ci1, ci2, estado, id_categoria: idCat, obs,
    orig_ci1: document.getElementById('iInscOrigCI1').value,
    orig_ci2: document.getElementById('iInscOrigCI2').value
  });

  btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Guardar Cambios';

  if (r.success) {
    closeModal('modalInscr');
    alert('✓ ' + r.mensaje);
    loadInscripciones();
    // Si venimos del listado por categoría, refrescar
    if (_inscCatEvento && _inscCatId) {
      // pequeño delay para que cierre el modal primero
      setTimeout(()=>{ loadCategorias(); }, 500);
    }
  } else {
    alert('Error: ' + (r.error || 'desconocido'));
  }
}

// ═══ TAB 5: CATEGORÍAS DEL EVENTO ═══
async function cargarCatsEvento() {
  const idEv = document.getElementById('evId').value;
  if (!idEv) {
    document.getElementById('evCatList').innerHTML = '<div class="empty">Guardá el evento primero para agregar categorías</div>';
    return;
  }
  const box = document.getElementById('evCatList');
  box.innerHTML = '<div class="loading" style="min-height:60px;border-radius:8px;"></div>';
  const r = await api({action: 'cats_evento', evento: idEv});
  if (!r.success) { box.innerHTML = '<div class="empty">Error al cargar</div>'; return; }
  if (!r.categorias.length) {
    box.innerHTML = '<div class="empty" style="padding:20px;">Sin categorías asignadas. Usá el botón Agregar categoría.</div>';
    return;
  }
  // Tabla compacta
  let h = `<table style="width:100%;border-collapse:collapse;font-size:13px;">
    <thead>
      <tr style="background:var(--bg-primary);color:var(--text-muted);font-size:11px;text-transform:uppercase;letter-spacing:.5px;">
        <th style="padding:8px 10px;text-align:left;">Categoría</th>
        <th style="padding:8px 6px;text-align:center;">Estado</th>
        <th style="padding:8px 6px;text-align:center;">Cupo</th>
        <th style="padding:8px 6px;text-align:center;">Llaves</th>
        <th style="padding:8px 6px;text-align:center;">Costo</th>
        <th style="padding:8px 6px;text-align:center;">Acciones</th>
      </tr>
    </thead><tbody>`;
  r.categorias.forEach(c => {
    const estColor = c.estado==='activo' ? 'var(--success)' : 'var(--danger)';
    const cupoColor = c.cupo==='disponible' ? 'var(--info)' : 'var(--warning)';
    h += `<tr style="border-bottom:1px solid var(--border);" id="rec-row-${c.id_relacion}">
      <td style="padding:10px 10px;font-weight:600;">${c.categoria}</td>
      <td style="padding:10px 6px;text-align:center;">
        <span style="color:${estColor};font-size:11px;font-weight:700;">${c.estado}</span>
      </td>
      <td style="padding:10px 6px;text-align:center;">
        <span style="color:${cupoColor};font-size:11px;">${c.cupo||'disponible'}</span>
      </td>
      <td style="padding:10px 6px;text-align:center;">
        ${c.visualizar_en_llaves==='si'
          ? '<i class="fas fa-check" style="color:var(--success);"></i>'
          : '<i class="fas fa-times" style="color:var(--text-muted);"></i>'}
      </td>
      <td style="padding:10px 6px;text-align:center;font-size:12px;">${c.costo?Number(c.costo).toLocaleString('es-PY'):'—'}</td>
      <td style="padding:10px 6px;text-align:center;">
        <button class="btn btn-gh btn-sm" style="margin-right:4px;" onclick="editarFilaCat(${c.id_relacion})">
          <i class="fas fa-edit"></i>
        </button>
        <button class="btn btn-sm" style="background:var(--danger-bg);color:var(--danger);" onclick="quitarCatEvento(${c.id_relacion},'${c.categoria}')">
          <i class="fas fa-times"></i>
        </button>
      </td>
    </tr>
    <!-- Fila de edición colapsada -->
    <tr id="rec-edit-${c.id_relacion}" style="display:none;background:var(--bg-hover);">
      <td colspan="6" style="padding:12px 10px;">
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:8px;">
          <div>
            <label style="font-size:10px;color:var(--text-muted);display:block;margin-bottom:3px;">ESTADO</label>
            <select class="fs" id="rec-estado-${c.id_relacion}">
              <option value="activo" ${c.estado==='activo'?'selected':''}>Activo</option>
              <option value="inactivo" ${c.estado==='inactivo'?'selected':''}>Inactivo</option>
            </select>
          </div>
          <div>
            <label style="font-size:10px;color:var(--text-muted);display:block;margin-bottom:3px;">CUPO</label>
            <select class="fs" id="rec-cupo-${c.id_relacion}">
              <option value="disponible" ${c.cupo==='disponible'?'selected':''}>Disponible</option>
              <option value="lleno" ${c.cupo==='lleno'?'selected':''}>Lleno</option>
            </select>
          </div>
          <div>
            <label style="font-size:10px;color:var(--text-muted);display:block;margin-bottom:3px;">LLAVES</label>
            <select class="fs" id="rec-llaves-${c.id_relacion}">
              <option value="si" ${c.visualizar_en_llaves==='si'?'selected':''}>Sí</option>
              <option value="no" ${c.visualizar_en_llaves==='no'?'selected':''}>No</option>
            </select>
          </div>
          <div>
            <label style="font-size:10px;color:var(--text-muted);display:block;margin-bottom:3px;">SEXO</label>
            <select class="fs" id="rec-sexo-${c.id_relacion}">
              <option value="" ${!c.sexo?'selected':''}>Sin especif.</option>
              <option value="hombre" ${c.sexo==='hombre'?'selected':''}>Hombre</option>
              <option value="mujer" ${c.sexo==='mujer'?'selected':''}>Mujer</option>
              <option value="mixto" ${c.sexo==='mixto'?'selected':''}>Mixto</option>
            </select>
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 2fr;gap:8px;margin-bottom:10px;">
          <div>
            <label style="font-size:10px;color:var(--text-muted);display:block;margin-bottom:3px;">COSTO (Gs.)</label>
            <input class="fi" type="number" id="rec-costo-${c.id_relacion}" value="${c.costo||0}" placeholder="0">
          </div>
          <div>
            <label style="font-size:10px;color:var(--text-muted);display:block;margin-bottom:3px;">ORDEN</label>
            <input class="fi" type="number" id="rec-orden-${c.id_relacion}" value="${c.orden_visualizacion||1}" min="1">
          </div>
          <div>
            <label style="font-size:10px;color:var(--text-muted);display:block;margin-bottom:3px;">LINK GRUPOS</label>
            <input class="fi" type="text" id="rec-link-${c.id_relacion}" value="${c.link_grupos||''}" placeholder="https://...">
          </div>
        </div>
        <div style="display:flex;gap:8px;">
          <button class="btn btn-ok btn-sm" onclick="guardarFilaCat(${c.id_relacion})"><i class="fas fa-save"></i> Guardar</button>
          <button class="btn btn-gh btn-sm" onclick="editarFilaCat(${c.id_relacion})">Cancelar</button>
        </div>
      </td>
    </tr>`;
  });
  h += '</tbody></table>';
  box.innerHTML = h;
}

function editarFilaCat(idRel) {
  const editRow = document.getElementById('rec-edit-' + idRel);
  editRow.style.display = editRow.style.display === 'none' ? 'table-row' : 'none';
}

async function guardarFilaCat(idRel) {
  const campos = {
    estado:              document.getElementById('rec-estado-'  + idRel).value,
    cupo:                document.getElementById('rec-cupo-'    + idRel).value,
    visualizar_en_llaves:document.getElementById('rec-llaves-'  + idRel).value,
    sexo:                document.getElementById('rec-sexo-'    + idRel).value,
    costo:               document.getElementById('rec-costo-'   + idRel).value,
    orden_visualizacion: document.getElementById('rec-orden-'   + idRel).value,
    link_grupos:         document.getElementById('rec-link-'    + idRel).value,
  };
  for (const [campo, valor] of Object.entries(campos)) {
    await api({action: 'actualizar_cat_evento', id_relacion: idRel, campo, valor});
  }
  editarFilaCat(idRel); // colapsar
  cargarCatsEvento();   // refrescar tabla
}


function agregarCatEvento() {
  const idEv = document.getElementById('evId').value;
  if (!idEv) { alert('Guardá el evento primero'); return; }
  const form = document.getElementById('evCatAddForm');
  form.style.display = form.style.display === 'none' ? 'block' : 'none';
  if (form.style.display === 'block') cargarTodasCats();
}

async function cargarTodasCats() {
  const r = await api({action: 'todas_cats'});
  const sel = document.getElementById('evCatSelect');
  sel.innerHTML = '<option value="">Seleccionar...</option>';
  if (r.success) r.categorias.forEach(c => {
    const o = document.createElement('option');
    o.value = c.id; o.textContent = c.categoria;
    sel.appendChild(o);
  });
}

async function confirmarAgregarCat() {
  const idEv    = document.getElementById('evId').value;
  const idCat   = document.getElementById('evCatSelect').value;
  const estado  = document.getElementById('evCatNuevoEstado').value;
  const cupo    = document.getElementById('evCatNuevoCupo').value;
  const llaves  = document.getElementById('evCatNuevoLlaves').value;
  const sexo    = document.getElementById('evCatNuevoSexo').value;
  const costo   = document.getElementById('evCatNuevoCosto').value || 0;
  const orden   = document.getElementById('evCatNuevoOrden').value || 1;
  const link    = document.getElementById('evCatNuevoLink').value;
  if (!idCat) { alert('Seleccioná una categoría'); return; }
  const r = await api({action: 'agregar_cat_evento', evento: idEv, categoria: idCat,
    estado, cupo, visualizar_en_llaves: llaves, sexo, costo, orden_visualizacion: orden, link_grupos: link});
  if (r.success) {
    document.getElementById('evCatAddForm').style.display = 'none';
    cargarCatsEvento();
  } else {
    alert('Error: ' + (r.error || 'desconocido'));
  }
}

async function quitarCatEvento(idRelacion, nombre) {
  if (!confirm('¿Quitar la categoría "' + nombre + '" de este evento?')) return;
  const r = await api({action: 'quitar_cat_evento', id_relacion: idRelacion});
  if (r.success) cargarCatsEvento();
  else alert('Error: ' + (r.error || 'desconocido'));
}

// ═══ POPULATE EVENT SELECTS ═══
async function populateSelects(){
  const ev=await api({action:'eventos'});
  if(!ev.success)return;
  const sels=['fInscEv','fCatEv','fResEv'];
  sels.forEach(id=>{
    const sel=document.getElementById(id);if(!sel)return;
    ev.eventos.forEach(e=>{const o=document.createElement('option');o.value=e.id;o.textContent=e.evento;sel.appendChild(o)});
  });
}

// ═══ INIT ═══
async function loadAll(){
  await loadDashboard();
  await loadCharts();
  await loadEventos();
  loadHorarios();
  // Ranking: se carga al entrar al tab, no en init
}

// ═══ JUGADORES ═══
let jugCurrentPage = 1;

async function loadJugadores(page) {
  if (!page) page = 1;
  jugCurrentPage = page;
  const buscar = document.getElementById('fJugBuscar').value.trim();
  const estado = document.getElementById('fJugEstado').value;
  const tipo = document.getElementById('fJugTipo').value;
  const params = { action: 'jugadores', page, buscar, estado, tipo };
  const r = await api(params);
  if (!r.success) return;
  document.getElementById('jugCount').textContent = `Jugadores (${r.total})`;
  const tb = document.getElementById('tbJug');
  if (r.jugadores.length === 0) {
    tb.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:24px;color:var(--text-muted);">No se encontraron jugadores</td></tr>';
  } else {
    tb.innerHTML = r.jugadores.map(j => {
      const estBadge = j.estado === 'activo' ? 'background:var(--success-bg);color:var(--success);' :
                       j.estado === 'inactivo' ? 'background:var(--warning-bg);color:var(--warning);' :
                       'background:var(--danger-bg);color:var(--danger);';
      const tipoBadge = j.tipo === 'admin' ? 'background:var(--info-bg);color:var(--info);' :
                        j.tipo === 'socio' ? 'background:var(--accent-glow);color:var(--accent);' :
                        'background:var(--bg-hover);color:var(--text-secondary);';
      return `<tr>
        <td>${j.ci || '—'}</td>
        <td><strong>${j.nombre || ''} ${j.apellido || ''}</strong></td>
        <td style="font-size:12px;">${j.email || '—'}</td>
        <td style="font-size:12px;">${j.cel || '—'}</td>
        <td>${j.sexo || '—'}</td>
        <td><span style="${estBadge}padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;">${j.estado}</span></td>
        <td><span style="${tipoBadge}padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;">${j.tipo}</span></td>
        <td><button class="btn btn-gh btn-sm" onclick="editarJugador(${j.id})" title="Editar"><i class="fas fa-pen"></i></button></td>
      </tr>`;
    }).join('');
  }
  // Paginación
  const pagDiv = document.getElementById('jugPag');
  if (r.pages <= 1) { pagDiv.innerHTML = ''; return; }
  let html = '';
  if (page > 1) html += `<button class="btn btn-gh btn-sm" onclick="loadJugadores(${page - 1})"><i class="fas fa-chevron-left"></i></button>`;
  const start = Math.max(1, page - 3);
  const end = Math.min(r.pages, page + 3);
  for (let i = start; i <= end; i++) {
    html += `<button class="btn ${i === page ? 'btn-pr' : 'btn-gh'} btn-sm" onclick="loadJugadores(${i})">${i}</button>`;
  }
  if (page < r.pages) html += `<button class="btn btn-gh btn-sm" onclick="loadJugadores(${page + 1})"><i class="fas fa-chevron-right"></i></button>`;
  html += `<span style="font-size:11px;color:var(--text-muted);margin-left:8px;">Pág ${page} de ${r.pages}</span>`;
  pagDiv.innerHTML = html;
}

async function editarJugador(id) {
  const r = await api({ action: 'get_jugador', id });
  if (!r.success) return alert(r.error);
  const j = r.jugador;
  document.getElementById('jugEditId').value = j.id;
  document.getElementById('jugEditNombre').value = j.nombre || '';
  document.getElementById('jugEditApellido').value = j.apellido || '';
  document.getElementById('jugEditCi').value = j.ci || '';
  document.getElementById('jugEditEmail').value = j.email || '';
  document.getElementById('jugEditCel').value = j.cel || '';
  document.getElementById('jugEditWhatsapp').value = j.whatsapp || '';
  document.getElementById('jugEditSexo').value = j.sexo || 'hombre';
  document.getElementById('jugEditFechaNac').value = j.fecha_nacimiento || '';
  document.getElementById('jugEditCiudad').value = j.ciudad || '';
  document.getElementById('jugEditNacionalidad').value = j.nacionalidad || '';
  document.getElementById('jugEditEstado').value = j.estado || 'activo';
  document.getElementById('jugEditTipo').value = j.tipo || 'jugador';
  document.getElementById('jugEditObs').value = j.observacion || '';
  document.getElementById('mdJugTitle').textContent = `Editar: ${j.nombre} ${j.apellido}`;
  openModal('mdJugEdit');
}

async function guardarJugador() {
  const id = document.getElementById('jugEditId').value;
  const params = {
    action: 'editar_jugador',
    id,
    nombre: document.getElementById('jugEditNombre').value,
    apellido: document.getElementById('jugEditApellido').value,
    ci: document.getElementById('jugEditCi').value,
    email: document.getElementById('jugEditEmail').value,
    cel: document.getElementById('jugEditCel').value,
    whatsapp: document.getElementById('jugEditWhatsapp').value,
    sexo: document.getElementById('jugEditSexo').value,
    fecha_nacimiento: document.getElementById('jugEditFechaNac').value,
    ciudad: document.getElementById('jugEditCiudad').value,
    nacionalidad: document.getElementById('jugEditNacionalidad').value,
    estado: document.getElementById('jugEditEstado').value,
    tipo: document.getElementById('jugEditTipo').value,
    observacion: document.getElementById('jugEditObs').value
  };
  const r = await api(params);
  if (r.success) {
    closeModal('mdJugEdit');
    loadJugadores(jugCurrentPage);
  } else {
    alert(r.error || 'Error al guardar');
  }
}

(async()=>{
  const saved=localStorage.getItem('bt-theme');
  if(saved){document.documentElement.setAttribute('data-theme',saved);document.getElementById('themeIc').className=saved==='dark'?'fas fa-moon':'fas fa-sun'}
  Chart.defaults.font.family="'DM Sans',sans-serif";
  Chart.defaults.color=cssVar('--text-secondary');
  Chart.defaults.borderColor=cssVar('--border');
  try{
    await populateSelects();
    await loadAll();
    console.log('Dashboard loaded OK');
  }catch(e){
    console.error('Init error:',e);
    document.querySelector('.content').insertAdjacentHTML('afterbegin',
      '<div style="background:var(--danger-bg);color:var(--danger);padding:12px 20px;border-radius:8px;margin-bottom:16px;font-size:13px;">Error al cargar: '+e.message+'</div>');
  }
})();

// ═══ ADMINISTRADORES CRUD ═══
async function loadAdmins(){
  const r=await fetch(API+'?action=list_admins').then(r=>r.json());
  if(!r.success)return;
  const tb=document.getElementById('tbAdmins');
  tb.innerHTML='';
  r.admins.forEach(function(a){
    var evHtml='';
    if(a.tipo==='superadmin'){
      evHtml='<span class="badge badge-a">Todos (superadmin)</span>';
    } else if(a.eventos && a.eventos.length>0){
      a.eventos.forEach(function(ev){
        evHtml+='<span class="badge badge-s" style="margin:2px;">'+ev.evento+'</span> ';
      });
    } else {
      evHtml='<span class="badge badge-w">Sin asignar</span>';
    }
    var tr=document.createElement('tr');
    tr.innerHTML='<td>'+a.id+'</td>'
      +'<td><strong>'+a.usuario+'</strong></td>'
      +'<td><span class="badge '+(a.tipo==='superadmin'?'badge-a':'badge-i')+'">'+a.tipo+'</span></td>'
      +'<td>'+evHtml+'</td>'
      +'<td>'
        +'<button class="btn btn-gh btn-sm" onclick="editAdmin('+a.id+')"><i class="fas fa-pen"></i></button> '
        +(a.tipo!=='superadmin'?'<button class="btn btn-p btn-sm" onclick="openAsignar('+a.id+',\''+a.usuario+'\')"><i class="fas fa-link"></i> Eventos</button> ':'')
        +'<button class="btn btn-no btn-sm" onclick="deleteAdmin('+a.id+',\''+a.usuario+'\')" style="margin-left:4px;"><i class="fas fa-trash"></i></button>'
      +'</td>';
    tb.appendChild(tr);
  });
}

function editAdmin(id){
  fetch(API+'?action=get_admin&id='+id).then(r=>r.json()).then(function(r){
    if(!r.success)return;
    document.getElementById('admId').value=r.admin.id;
    document.getElementById('admUser').value=r.admin.usuario;
    document.getElementById('admPass').value=r.admin.pase;
    document.getElementById('admTipo').value=r.admin.tipo;
    document.getElementById('modalAdminTitle').innerHTML='<i class="fas fa-user-shield" style="margin-right:8px;color:var(--accent);"></i>Editar Admin';
    openModal('modalAdmin');
  });
}

async function saveAdmin(){
  var id=document.getElementById('admId').value;
  var data={
    action: id ? 'update_admin' : 'create_admin',
    id: id||'',
    usuario: document.getElementById('admUser').value,
    pase: document.getElementById('admPass').value,
    tipo: document.getElementById('admTipo').value
  };
  var params=Object.keys(data).map(function(k){return k+'='+encodeURIComponent(data[k])}).join('&');
  var r=await fetch(API+'?'+params).then(r=>r.json());
  if(r.success){
    closeModal('modalAdmin');
    document.getElementById('admId').value='';
    document.getElementById('admUser').value='';
    document.getElementById('admPass').value='';
    document.getElementById('admTipo').value='cliente';
    document.getElementById('modalAdminTitle').innerHTML='<i class="fas fa-user-shield" style="margin-right:8px;color:var(--accent);"></i>Nuevo Admin';
    loadAdmins();
  } else {
    alert(r.error||'Error al guardar');
  }
}

async function deleteAdmin(id, nombre){
  if(!confirm('Eliminar admin "'+nombre+'"?'))return;
  var r=await fetch(API+'?action=delete_admin&id='+id).then(r=>r.json());
  if(r.success) loadAdmins();
  else alert(r.error||'Error');
}

async function openAsignar(adminId, nombre){
  document.getElementById('asigAdminId').value=adminId;
  document.getElementById('modalAsignarTitle').innerHTML='<i class="fas fa-link" style="margin-right:8px;color:var(--accent);"></i>Eventos de '+nombre;
  openModal('modalAsignar');
  var r=await fetch(API+'?action=admin_eventos&admin_id='+adminId).then(r=>r.json());
  if(!r.success)return;
  var wrap=document.getElementById('asigEventosList');
  wrap.innerHTML='';
  r.eventos.forEach(function(ev){
    var div=document.createElement('div');
    div.style.cssText='display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border:1px solid var(--border);border-radius:8px;margin-bottom:8px;background:var(--bg-primary)';
    div.innerHTML='<div><strong style="font-size:13px;">'+ev.evento+'</strong><div style="font-size:11px;color:var(--text-muted);">'+ev.fecha+' — '+ev.estado+'</div></div>'
      +'<label style="cursor:pointer;display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:'+(ev.asignado?'var(--success)':'var(--text-muted)')+'">'
      +'<input type="checkbox" '+(ev.asignado?'checked':'')+' onchange="toggleAsignacion('+adminId+','+ev.id+',this)" style="width:18px;height:18px;accent-color:var(--accent);cursor:pointer">'
      +(ev.asignado?'Asignado':'Sin asignar')
      +'</label>';
    wrap.appendChild(div);
  });
}

async function toggleAsignacion(adminId, eventoId, cb){
  var action=cb.checked?'asignar_evento':'desasignar_evento';
  var r=await fetch(API+'?action='+action+'&admin_id='+adminId+'&evento_id='+eventoId).then(r=>r.json());
  if(r.success){
    var label=cb.parentElement;
    if(cb.checked){label.style.color='var(--success)';label.lastChild.textContent='Asignado';}
    else{label.style.color='var(--text-muted)';label.lastChild.textContent='Sin asignar';}
  } else {
    cb.checked=!cb.checked;
    alert(r.error||'Error');
  }
}


// ═══ PUNTAJES ═══
const ETIQ_NAMES={1:'Grupo',2:'8vos',3:'Cuartos',4:'Semi',5:'Final',6:'Vice Campeón',7:'Rondas',8:'Campeón',9:'16avos',10:'3er Puesto',11:'Participación',12:'Campeón Tabla',13:'Vice Tabla',14:'3ro Tabla',15:'4to Tabla'};
const ETIQ_ORDER=[1,9,2,3,4,6,8,11,12,13,14,15];
const ETIQ_SHORT={1:'Grupo',9:'16avos',2:'8vos',3:'Cuartos',4:'Semi',6:'Vice',8:'Campeón',11:'Partic.',12:'Camp. Tabla',13:'Vice Tabla',14:'3ro Tabla',15:'4to Tabla'};
let pjEtiquetas=[];
let pjCategorias=[];

async function loadPuntajes(){
  var sel=document.getElementById('pjEvento');
  var selOr=document.getElementById('pjEventoOrigen');
  sel.innerHTML='<option value="">— Seleccionar evento —</option>';
  selOr.innerHTML='<option value="">— Copiar desde —</option>';
  try{
    var r=await fetch(API+'?action=eventos').then(function(r){return r.json();});
    if(r.eventos) r.eventos.forEach(function(e){
      sel.innerHTML+='<option value="'+e.id+'">'+e.id+'. '+e.evento+'</option>';
      selOr.innerHTML+='<option value="'+e.id+'">'+e.id+'. '+e.evento+'</option>';
    });
  }catch(ex){console.error('loadPuntajes error',ex);}
  document.getElementById('pjContainer').innerHTML='<div style="color:var(--text-muted);text-align:center;padding:40px;">Selecciona un evento para ver/editar sus puntajes</div>';
}

async function loadPuntajesEvento(){
  var idEv=document.getElementById('pjEvento').value;
  var ct=document.getElementById('pjContainer');
  if(!idEv){
    ct.innerHTML='<div style="color:var(--text-muted);text-align:center;padding:40px;">Selecciona un evento para ver/editar sus puntajes</div>';
    document.getElementById('btnCopiarPj').style.display='none';
    document.getElementById('btnGuardarPj').style.display='none';
    document.getElementById('pjEventoOrigen').style.display='none';
    return;
  }
  ct.innerHTML='<div style="text-align:center;padding:40px;color:var(--text-muted);">Cargando...</div>';
  try{
    var r=await fetch(API+'?action=puntajes_evento&id_evento='+idEv).then(function(r){return r.json();});
    pjEtiquetas=r.etiquetas||[];
    pjCategorias=r.categorias||[];
    renderPuntajes();
    document.getElementById('btnCopiarPj').style.display='';
    document.getElementById('btnGuardarPj').style.display='';
    document.getElementById('pjEventoOrigen').style.display='';
  }catch(ex){
    ct.innerHTML='<div style="color:var(--danger);text-align:center;padding:40px;">Error cargando puntajes</div>';
    console.error('loadPuntajesEvento error',ex);
  }
}

function renderPuntajes(){
  var ct=document.getElementById('pjContainer');
  if(!pjCategorias.length){
    ct.innerHTML='<div style="color:var(--text-muted);text-align:center;padding:40px;">Este evento no tiene categorías asignadas</div>';
    return;
  }
  var html='<style>'
    +'.pj-wrap{background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-md);box-shadow:var(--shadow-card);overflow:auto;max-width:100%;max-height:calc(100vh - 260px);}'
    +'.pj-mx{border-collapse:separate;border-spacing:0;width:100%;min-width:940px;font-size:12px;}'
    +'.pj-mx thead th{position:sticky;top:0;z-index:2;background:var(--bg-card);padding:10px 4px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;color:var(--text-muted);text-align:center;border-bottom:1px solid var(--border);white-space:nowrap;}'
    +'.pj-mx th.pj-cat,.pj-mx td.pj-cat{position:sticky;left:0;z-index:1;background:var(--bg-card);text-align:left;padding:6px 12px;min-width:140px;border-right:1px solid var(--border);}'
    +'.pj-mx thead th.pj-cat{z-index:3;}'
    +'.pj-mx td{padding:3px 2px;text-align:center;border-bottom:1px solid var(--border);}'
    +'.pj-mx tbody tr:last-child td{border-bottom:none;}'
    +'.pj-mx td.pj-cat{font-weight:600;color:var(--text-primary);font-size:11px;white-space:nowrap;}'
    +'.pj-mx tbody tr:hover td{background:var(--bg-hover);}'
    +'.pj-in{width:58px;padding:6px 2px;text-align:center;font-size:12px;font-family:inherit;background:transparent;border:1px solid transparent;border-radius:6px;color:var(--text-primary);-moz-appearance:textfield;}'
    +'.pj-in::-webkit-outer-spin-button,.pj-in::-webkit-inner-spin-button{-webkit-appearance:none;margin:0;}'
    +'.pj-in:hover{border-color:var(--border2);}'
    +'.pj-in:focus{outline:none;border-color:var(--accent);background:var(--bg-primary);}'
    +'.pj-in::placeholder{color:var(--text-muted);opacity:.4;}'
    +'.pj-in.has-val{background:var(--accent-glow);color:var(--accent);font-weight:700;}'
    +'</style>';
  html+='<div class="pj-wrap"><table class="pj-mx"><thead><tr><th class="pj-cat">Categoría</th>';
  ETIQ_ORDER.forEach(function(eid){
    html+='<th>'+(ETIQ_SHORT[eid]||ETIQ_NAMES[eid]||eid)+'</th>';
  });
  html+='</tr></thead><tbody>';
  pjCategorias.forEach(function(cat){
    var pjMap={};
    cat.puntajes.forEach(function(p){pjMap[p.id_etiqueta]=p.puntos;});
    html+='<tr data-cat="'+cat.id+'"><td class="pj-cat">'+cat.categoria+'</td>';
    ETIQ_ORDER.forEach(function(eid){
      var v=(pjMap[eid]!==undefined)?pjMap[eid]:'';
      html+='<td><input type="number" min="0" step="5" class="pj-in'+(v!==''?' has-val':'')+'" data-etiq="'+eid+'" value="'+v+'" placeholder="–" oninput="this.classList.toggle(\'has-val\',this.value!==\'\')"></td>';
    });
    html+='</tr>';
  });
  html+='</tbody></table></div>';
  html+='<div style="margin-top:10px;font-size:11px;color:var(--text-muted);"><i class="fas fa-info-circle" style="margin-right:6px;"></i>Celda vacía = esa ronda no otorga puntos en la categoría. Escribe un valor para habilitarla y pulsa "Guardar todo".</div>';
  ct.innerHTML=html;
}

async function guardarTodosPuntajes(){
  var idEv=document.getElementById('pjEvento').value;
  if(!idEv){alert('Selecciona un evento');return;}
  var filas=document.querySelectorAll('#pjContainer tr[data-cat]');
  var total=0,errors=0;
  for(var i=0;i<filas.length;i++){
    var catId=filas[i].getAttribute('data-cat');
    var puntajes=[];
    filas[i].querySelectorAll('.pj-in').forEach(function(inp){
      if(inp.value!==''){
        puntajes.push({id_etiqueta:parseInt(inp.getAttribute('data-etiq')),puntos:parseInt(inp.value)||0});
      }
    });
    try{
      var fd=new FormData();
      fd.append('action','guardar_puntajes_categoria');
      fd.append('id_evento',idEv);
      fd.append('id_categoria',catId);
      fd.append('puntajes',JSON.stringify(puntajes));
      var r=await fetch(API,{method:'POST',body:fd}).then(function(r){return r.json();});
      if(r.success) total+=r.count; else errors++;
    }catch(ex){errors++;}
  }
  if(errors) alert('Guardado con errores: '+errors+' categorías fallaron');
  else alert('Guardado correctamente: '+total+' registros en '+filas.length+' categorías');
}

async function copiarPuntajes(){
  var destino=document.getElementById('pjEvento').value;
  var origen=document.getElementById('pjEventoOrigen').value;
  if(!destino||!origen){alert('Selecciona evento origen y destino');return;}
  if(origen===destino){alert('Origen y destino son el mismo evento');return;}
  if(!confirm('¿Copiar todos los puntajes del evento seleccionado? Esto reemplazará la configuración actual.')) return;
  try{
    var fd=new FormData();
    fd.append('action','copiar_puntajes');
    fd.append('evento_origen',origen);
    fd.append('evento_destino',destino);
    var r=await fetch(API,{method:'POST',body:fd}).then(function(r){return r.json();});
    if(r.success){
      alert('Copiados '+r.count+' registros de puntaje');
      loadPuntajesEvento();
    } else {
      alert('Error: '+(r.error||'desconocido'));
    }
  }catch(ex){alert('Error de conexión');}
}
</script>
<!-- MODAL: Admin -->
<div class="modal-ov" id="modalAdmin">
  <div class="modal" style="max-width:500px;">
    <div class="modal-h">
      <h3 id="modalAdminTitle"><i class="fas fa-user-shield" style="margin-right:8px;color:var(--accent);"></i>Nuevo Admin</h3>
      <button class="modal-x" onclick="closeModal('modalAdmin')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-b">
      <input type="hidden" id="admId">
      <div class="fg"><label class="fl">Usuario</label><input class="fi" id="admUser" placeholder="Nombre de usuario"></div>
      <div class="fg"><label class="fl">Contraseña</label><input class="fi" id="admPass" type="text" placeholder="Contraseña"></div>
      <div class="fg"><label class="fl">Tipo</label>
        <select class="fs" id="admTipo"><option value="cliente">Cliente</option><option value="superadmin">Superadmin</option></select>
      </div>
    </div>
    <div class="modal-f">
      <button class="btn btn-gh" onclick="closeModal('modalAdmin')">Cancelar</button>
      <button class="btn btn-p" onclick="saveAdmin()"><i class="fas fa-check"></i> Guardar</button>
    </div>
  </div>
</div>

<!-- MODAL: Asignar Eventos -->
<div class="modal-ov" id="modalAsignar">
  <div class="modal" style="max-width:550px;">
    <div class="modal-h">
      <h3 id="modalAsignarTitle"><i class="fas fa-link" style="margin-right:8px;color:var(--accent);"></i>Asignar Eventos</h3>
      <button class="modal-x" onclick="closeModal('modalAsignar')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-b">
      <input type="hidden" id="asigAdminId">
      <p style="font-size:12px;color:var(--text-muted);margin-bottom:16px;">Selecciona los eventos que este admin podrá ver en el <strong>Cargador de Resultados</strong>.</p>
      <div id="asigEventosList"></div>
    </div>
    <div class="modal-f">
      <button class="btn btn-gh" onclick="closeModal('modalAsignar')">Cerrar</button>
    </div>
  </div>
</div>

</body>
</html>