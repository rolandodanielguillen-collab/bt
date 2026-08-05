<?php
/**
 * logica/grafico-interclubes.inc.php — Contenido de la vista Interclubes
 * Detalles + cronograma + inscriptos organizados por CLUBES y por CATEGORÍAS.
 */
if (isset($pagina)):
    include_once "db/conection.inc.php";
else:
    include "../db/conection.inc.php";
endif;

// ── Evento por sha1(id), solo Interclubes ────────────────────────────────────
$SHA1evento = isset($_GET['evento']) ? trim($_GET['evento']) : '';
$rowEvento  = null;

if (preg_match('/^[a-f0-9]{40}$/i', $SHA1evento)) {
    $st = $mysqli2->prepare(
        "SELECT id, evento, nombre_evento2, estado,
                date_format(fecha, '%d-%m-%Y') AS fecha,
                date_format(fecha_fin, '%d-%m-%Y') AS fecha_fin,
                descripcion, flyer, boton_fixture, boton_llaves
           FROM _p_eventos
          WHERE sha1(id) = ? AND id_tipo_evento = 5 LIMIT 1");
    $st->bind_param('s', $SHA1evento);
    $st->execute();
    $rowEvento = $st->get_result()->fetch_assoc();
}

if (!$rowEvento) {
    echo '<div style="text-align:center;padding:60px 20px;font-family:sans-serif;">Evento no encontrado.</div>';
    return;
}
if ($rowEvento['boton_fixture'] === 'oculto' && !isset($_GET['tp'])) {
    echo '<div>Datos temporalmente bloqueados!</div><div>Por favor vuelva más tarde</div>';
    return;
}

$idEventos = (int)$rowEvento['id'];

// Cronograma: imagen cargada en "Imagen del Programa" (dentro de descripcion)
$cronoImgUrl = '';
if (preg_match('/src="([^"]+)"/', (string)$rowEvento['descripcion'], $mCr)) {
    $cronoImgUrl = $mCr[1];
}

// ── Clubes del evento ────────────────────────────────────────────────────────
$clubesIC = [];
$st = $mysqli2->prepare("SELECT id, nombre FROM _p_clubes WHERE id_evento = ? ORDER BY nombre ASC");
$st->bind_param('i', $idEventos);
$st->execute();
$res = $st->get_result();
while ($r = $res->fetch_assoc()) {
    $r['parejas'] = [];   // [id_categoria => [parejas]]
    $r['total']   = 0;
    $clubesIC[(int)$r['id']] = $r;
}

// ── Parejas del evento (una fila por pareja, con nombres y club) ─────────────
$st = $mysqli2->prepare(
    "SELECT i.id_club, i.id_categoria,
            COALESCE(cat.categoria, 'SIN CATEGORÍA') AS categoria,
            TRIM(CONCAT(COALESCE(u1.nombre,''),' ',COALESCE(u1.apellido,''))) AS j1,
            TRIM(CONCAT(COALESCE(u2.nombre,''),' ',COALESCE(u2.apellido,''))) AS j2
       FROM _p_incripciones i
       LEFT JOIN _p_categorias cat ON cat.id = i.id_categoria
       LEFT JOIN _p_usuarios u1 ON TRIM(u1.ci) = TRIM(i.ci)
       LEFT JOIN _p_usuarios u2 ON TRIM(u2.ci) = TRIM(i.ci_dupla)
      WHERE i.id_evento = ? AND i.estado <> 'bloqueado'
        AND CAST(i.ci AS UNSIGNED) < CAST(i.ci_dupla AS UNSIGNED)
      ORDER BY cat.categoria ASC, i.id ASC");
$st->bind_param('i', $idEventos);
$st->execute();
$res = $st->get_result();

$porCategoria  = [];  // [nombre_cat => [ ['j1','j2','club'] ]]
$totalParejas  = 0;
while ($p = $res->fetch_assoc()) {
    $idCl   = (int)($p['id_club'] ?? 0);
    $nomCat = $p['categoria'];
    $club   = ($idCl && isset($clubesIC[$idCl])) ? $clubesIC[$idCl]['nombre'] : '';
    $totalParejas++;
    $porCategoria[$nomCat][] = ['j1' => $p['j1'], 'j2' => $p['j2'], 'club' => $club];
    if ($idCl && isset($clubesIC[$idCl])) {
        $clubesIC[$idCl]['parejas'][$nomCat][] = $p;
        $clubesIC[$idCl]['total']++;
    }
}

// ── Sorteo público (grupos de clubes por categoría) ──────────────────────────
require_once __DIR__ . '/../interclubes.functions.php';

$sorteoIC = [];   // [nombre_cat => ['idcat' => N, 'grupos' => [g => [['id','nombre'], …]]]]
$st = $mysqli2->prepare(
    "SELECT s.id_categoria, COALESCE(cat.categoria, 'SIN CATEGORÍA') AS nomcat,
            s.grupo, s.id_club, cl.nombre AS club
       FROM _ic_sorteo s
       JOIN _p_clubes cl ON cl.id = s.id_club
       LEFT JOIN _p_categorias cat ON cat.id = s.id_categoria
      WHERE s.id_evento = ?
      ORDER BY cat.categoria ASC, s.grupo ASC, s.posicion ASC");
$st->bind_param('i', $idEventos);
$st->execute();
$res = $st->get_result();
while ($r = $res->fetch_assoc()) {
    $sorteoIC[$r['nomcat']]['idcat'] = (int)$r['id_categoria'];
    $sorteoIC[$r['nomcat']]['grupos'][(int)$r['grupo']][] = ['id' => (int)$r['id_club'], 'nombre' => $r['club']];
}
$haySorteo = !empty($sorteoIC);

// La Información muestra SOLO la conformación de los grupos; resultados,
// posiciones y enfrentamientos viven en interclubes-llaves.php (botón Llaves).

// El desarrollo (grupos + llaves estilo TVT) vive en interclubes-llaves.php.
// El switch "Botón Llaves" del evento controla si se ofrece el acceso.
$verLlaves = ($rowEvento['boton_llaves'] === 'visible');

function ic_nombre($n) {
    if (!mb_check_encoding((string)$n, 'UTF-8')) $n = mb_convert_encoding($n, 'UTF-8', 'ISO-8859-1');
    return htmlspecialchars(mb_strtoupper(trim((string)$n), 'UTF-8'));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($rowEvento['evento']); ?> - BT.com.py</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .accordion-content { max-height: 0; overflow: hidden; transition: max-height 0.3s ease-out; }
        .accordion-button.active svg { transform: rotate(180deg); }
        .pill-ic { transition: all .15s; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 flex flex-col min-h-screen">
    <main class="flex-grow max-w-5xl mx-auto px-4 py-6 w-full">

        <div class="text-center mb-6">
            <span class="inline-block bg-blue-600 text-white text-xs font-bold tracking-widest uppercase px-4 py-1 rounded-full mb-2">Torneo Interclubes</span>
            <h1 class="text-xl md:text-2xl font-extrabold text-gray-900 leading-tight">
                <?php echo mb_strtoupper(htmlspecialchars($rowEvento['evento']), 'UTF-8'); ?>
                <?php if ($rowEvento['nombre_evento2']): ?>
                    <span class="block text-sm font-semibold text-gray-500 mt-1"><?php echo htmlspecialchars($rowEvento['nombre_evento2']); ?></span>
                <?php endif; ?>
            </h1>
            <div class="flex flex-wrap justify-center gap-3 mt-3">
                <button class="bg-blue-600 text-white text-sm font-semibold py-2 px-5 rounded-lg shadow">Información</button>
                <?php if ($verLlaves): ?>
                <a href="interclubes-llaves.php?<?php echo htmlspecialchars($_SERVER['QUERY_STRING']); ?>"
                   class="bg-white text-gray-700 border border-gray-300 text-sm font-semibold py-2 px-5 rounded-lg shadow-sm hover:bg-gray-50 transition">
                    Llaves
                </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">

            <!-- Columna izquierda: detalles + cronograma + sorteo -->
            <div class="lg:col-span-2 space-y-4">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <button class="accordion-button w-full flex justify-between items-center p-4 focus:outline-none" onclick="toggleAccordion(this)">
                        <span class="text-lg font-bold text-gray-900 flex items-center">
                            <i class="fa-solid fa-circle-info mr-2 text-blue-600"></i>
                            DETALLES
                        </span>
                        <svg class="w-4 h-4 text-gray-500 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div class="accordion-content">
                    <div class="px-4 pb-4">
                    <div class="space-y-2 text-sm">
                        <div class="flex items-start">
                            <i class="fa-regular fa-calendar-days mr-2 text-gray-500 w-4 mt-0.5"></i>
                            <div class="flex-1">
                                <span class="text-gray-600 mr-1">Fechas:</span>
                                <span class="font-medium text-gray-900"><?php echo $rowEvento['fecha']; ?> al <?php echo $rowEvento['fecha_fin']; ?></span>
                            </div>
                        </div>
                        <div class="flex items-start">
                            <i class="fa-solid fa-building mr-2 text-gray-500 w-4 mt-0.5"></i>
                            <div class="flex-1">
                                <span class="text-gray-600 mr-1">Clubes:</span>
                                <span class="font-medium text-gray-900"><?php echo count($clubesIC); ?></span>
                            </div>
                        </div>
                        <div class="flex items-start">
                            <i class="fa-solid fa-user-group mr-2 text-gray-500 w-4 mt-0.5"></i>
                            <div class="flex-1">
                                <span class="text-gray-600 mr-1">Parejas:</span>
                                <span class="font-medium text-gray-900"><?php echo $totalParejas; ?></span>
                            </div>
                        </div>
                    </div>

                    <?php if ($cronoImgUrl): ?>
                    <div class="mt-4 pt-3 border-t border-gray-200">
                        <div class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2 flex items-center">
                            <i class="fa-regular fa-clock mr-1.5 text-blue-600"></i> Cronograma
                        </div>
                        <a href="<?php echo htmlspecialchars($cronoImgUrl); ?>" target="_blank" rel="noopener" title="Ver cronograma completo">
                            <img src="<?php echo htmlspecialchars($cronoImgUrl); ?>" alt="Cronograma"
                                 class="w-full h-auto rounded-lg border border-gray-200 hover:opacity-90 transition"
                                 onload="const c=this.closest('.accordion-content'); if (c && c.style.maxHeight) c.style.maxHeight = c.scrollHeight + 'px';">
                        </a>
                    </div>
                    <?php endif; ?>
                    </div>
                    </div>
                </div>

                <?php if ($haySorteo): ?>
                <!-- ══ SORTEO (card colapsable; categorías con clubes de cada grupo) ══ -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <button class="accordion-button w-full flex justify-between items-center p-4 focus:outline-none" onclick="toggleAccordion(this)">
                        <span class="text-lg font-bold text-gray-900 flex items-center">
                            <i class="fa-solid fa-shuffle mr-2 text-blue-600"></i>
                            SORTEO
                        </span>
                        <svg class="w-4 h-4 text-gray-500 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div class="accordion-content">
                    <div class="px-4 pb-4 space-y-3">
                        <?php foreach ($sorteoIC as $nomCat => $catData):
                            $gruposCat = $catData['grupos'];
                            $totClubes = count($gruposCat[1] ?? []) + count($gruposCat[2] ?? []);
                        ?>
                        <div class="border border-gray-700 rounded-lg overflow-hidden">
                            <button class="accordion-button w-full flex justify-between items-center p-3 bg-gray-800 hover:bg-gray-700 transition focus:outline-none" onclick="toggleAccordion(this)">
                                <span class="flex items-center min-w-0">
                                    <span class="w-7 h-7 rounded-lg bg-blue-600 flex items-center justify-center mr-2.5 flex-shrink-0">
                                        <i class="fa-solid fa-shuffle text-white" style="font-size:.7rem"></i>
                                    </span>
                                    <span class="text-base font-semibold text-white truncate uppercase"><?php echo htmlspecialchars($nomCat); ?></span>
                                </span>
                                <span class="flex items-center flex-shrink-0 ml-2">
                                    <span class="bg-blue-600 text-white text-xs font-bold px-2.5 py-0.5 rounded-full mr-2"><?php echo $totClubes; ?> clubes</span>
                                    <svg class="w-4 h-4 text-gray-400 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </span>
                            </button>
                            <div class="accordion-content bg-gray-900">
                                <div class="p-3 grid grid-cols-1 gap-3">
                                    <?php foreach ([1, 2] as $g):
                                        $clubesG = $gruposCat[$g] ?? [];
                                    ?>
                                    <div class="border border-gray-700 rounded-lg overflow-hidden">
                                        <div class="bg-gray-800 px-3 py-2 flex items-center justify-between">
                                            <span class="text-sm font-bold text-white">Grupo <?php echo $g; ?></span>
                                            <span class="bg-blue-600 text-white text-[.65rem] font-bold px-2 py-0.5 rounded-full"><?php echo count($clubesG); ?> clubes</span>
                                        </div>
                                        <div>
                                            <?php if (!$clubesG): ?>
                                                <div class="px-3 py-2.5 text-xs italic" style="color:rgba(255,255,255,.35)">Aún sin sortear</div>
                                            <?php endif; ?>
                                            <?php foreach ($clubesG as $i => $c): ?>
                                            <div class="px-3 py-2.5 flex items-center gap-2 <?php echo $i > 0 ? 'border-t border-gray-800' : ''; ?>">
                                                <span class="w-5 h-5 rounded-full bg-blue-600/20 border border-blue-500/40 text-blue-300 text-[.6rem] font-extrabold flex items-center justify-center flex-shrink-0"><?php echo $i + 1; ?></span>
                                                <span class="text-[.8rem] font-bold text-slate-100 uppercase truncate"><?php echo ic_nombre($c['nombre']); ?></span>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Columna derecha: inscriptos -->
            <div class="lg:col-span-3">
                <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200">
                    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                        <h2 class="text-lg font-bold text-gray-900 flex items-center">
                            <i class="fa-solid fa-users mr-2 text-blue-600"></i>
                            INSCRIPTOS
                        </h2>
                        <div class="flex bg-gray-100 rounded-lg p-1">
                            <button id="pillClubes" onclick="vistaIC('clubes')"
                                    class="pill-ic text-xs font-bold px-4 py-1.5 rounded-md bg-blue-600 text-white shadow">
                                <i class="fa-solid fa-building mr-1"></i> Por clubes
                            </button>
                            <button id="pillCats" onclick="vistaIC('cats')"
                                    class="pill-ic text-xs font-bold px-4 py-1.5 rounded-md text-gray-600 hover:text-gray-900">
                                <i class="fa-solid fa-tags mr-1"></i> Por categorías
                            </button>
                        </div>
                    </div>

                    <!-- ══ VISTA POR CLUBES ══ -->
                    <div id="vistaClubes" class="space-y-3">
                        <?php if (!$clubesIC): ?>
                            <p class="text-sm text-gray-500 italic py-4 text-center">Aún no hay clubes registrados.</p>
                        <?php endif; ?>
                        <?php foreach ($clubesIC as $cl): ?>
                        <div class="border border-gray-700 rounded-lg overflow-hidden">
                            <button class="accordion-button w-full flex justify-between items-center p-3 bg-gray-800 hover:bg-gray-700 transition focus:outline-none" onclick="toggleAccordion(this)">
                                <span class="flex items-center min-w-0">
                                    <span class="w-7 h-7 rounded-lg bg-blue-600 flex items-center justify-center mr-2.5 flex-shrink-0">
                                        <i class="fa-solid fa-building text-white" style="font-size:.7rem"></i>
                                    </span>
                                    <span class="text-base font-semibold text-white truncate"><?php echo ic_nombre($cl['nombre']); ?></span>
                                </span>
                                <span class="flex items-center flex-shrink-0 ml-2">
                                    <span class="bg-blue-600 text-white text-xs font-bold px-2.5 py-0.5 rounded-full mr-2"><?php echo $cl['total']; ?> parejas</span>
                                    <svg class="w-4 h-4 text-gray-400 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </span>
                            </button>
                            <div class="accordion-content bg-gray-900">
                                <div class="p-3 space-y-4">
                                    <?php if (!$cl['parejas']): ?>
                                        <p class="text-xs italic" style="color:rgba(255,255,255,.4)">Sin parejas inscriptas aún</p>
                                    <?php endif; ?>
                                    <?php ksort($cl['parejas']); foreach ($cl['parejas'] as $nomCat => $listaP): ?>
                                    <div>
                                        <div class="flex items-center mb-1.5">
                                            <span class="text-[.65rem] font-extrabold uppercase tracking-widest text-blue-400"><?php echo htmlspecialchars($nomCat); ?></span>
                                            <span class="ml-2 text-[.6rem] font-bold px-1.5 rounded-full" style="background:rgba(59,130,246,.2);color:#93c5fd"><?php echo count($listaP); ?></span>
                                        </div>
                                        <div class="space-y-1.5">
                                            <?php foreach ($listaP as $p): ?>
                                            <div class="rounded-lg px-3 py-2" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08)">
                                                <div class="text-[.78rem] font-bold text-slate-100 uppercase leading-snug truncate"><?php echo ic_nombre($p['j1'] ?: '—'); ?></div>
                                                <div class="text-[.78rem] font-bold text-slate-100 uppercase leading-snug truncate"><?php echo ic_nombre($p['j2'] ?: '—'); ?></div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- ══ VISTA POR CATEGORÍAS ══ -->
                    <div id="vistaCats" class="space-y-3" style="display:none">
                        <?php if (!$porCategoria): ?>
                            <p class="text-sm text-gray-500 italic py-4 text-center">Aún no hay parejas inscriptas.</p>
                        <?php endif; ?>
                        <?php ksort($porCategoria); foreach ($porCategoria as $nomCat => $listaP): ?>
                        <div class="border border-gray-700 rounded-lg overflow-hidden">
                            <button class="accordion-button w-full flex justify-between items-center p-3 bg-gray-800 hover:bg-gray-700 transition focus:outline-none" onclick="toggleAccordion(this)">
                                <span class="text-base font-semibold text-white truncate"><?php echo htmlspecialchars(mb_strtoupper($nomCat, 'UTF-8')); ?></span>
                                <span class="flex items-center flex-shrink-0 ml-2">
                                    <span class="bg-blue-600 text-white text-xs font-bold px-2.5 py-0.5 rounded-full mr-2"><?php echo count($listaP); ?></span>
                                    <svg class="w-4 h-4 text-gray-400 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </span>
                            </button>
                            <div class="accordion-content bg-gray-900">
                                <div class="p-3 space-y-1.5">
                                    <?php foreach ($listaP as $p): ?>
                                    <div class="rounded-lg px-3 py-2 flex items-center justify-between gap-2" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08)">
                                        <div class="min-w-0">
                                            <div class="text-[.78rem] font-bold text-slate-100 uppercase leading-snug truncate"><?php echo ic_nombre($p['j1'] ?: '—'); ?></div>
                                            <div class="text-[.78rem] font-bold text-slate-100 uppercase leading-snug truncate"><?php echo ic_nombre($p['j2'] ?: '—'); ?></div>
                                        </div>
                                        <?php if ($p['club']): ?>
                                        <span class="flex-shrink-0 text-[.6rem] font-extrabold uppercase tracking-wide px-2 py-1 rounded-md" style="background:rgba(59,130,246,.18);color:#93c5fd;border:1px solid rgba(59,130,246,.3)">
                                            <i class="fa-solid fa-building mr-1"></i><?php echo ic_nombre($p['club']); ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
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
            // Acordeones anidados: agrandar los ancestros abiertos para que
            // el contenido interno no quede recortado (sobra max-height, no molesta)
            let p = content.parentElement && content.parentElement.closest('.accordion-content');
            while (p) {
                if (p.style.maxHeight) p.style.maxHeight = (p.scrollHeight + content.scrollHeight) + 'px';
                p = p.parentElement && p.parentElement.closest('.accordion-content');
            }
        }
        function vistaIC(cual) {
            const vistas = { llaves: 'vistaLlaves', clubes: 'vistaClubes', cats: 'vistaCats' };
            const pills  = { llaves: 'pillLlaves',  clubes: 'pillClubes', cats: 'pillCats' };
            const on  = ['bg-blue-600','text-white','shadow'];
            const off = ['text-gray-600'];
            for (const k in vistas) {
                const v = document.getElementById(vistas[k]);
                const p = document.getElementById(pills[k]);
                if (!p) continue;
                if (v) v.style.display = (k === cual) ? '' : 'none';
                if (k === cual) { p.classList.add(...on); p.classList.remove(...off); }
                else            { p.classList.remove(...on); p.classList.add(...off); }
            }
        }
        function ocultarDiv() {
            const div = document.getElementById("cargando");
            if (div) div.style.display = "none";
        }
        ocultarDiv();
    </script>
</body>
</html>
