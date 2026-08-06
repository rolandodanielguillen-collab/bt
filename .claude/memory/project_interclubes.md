# Interclubes — Nuevo sistema de competencia (2026-08-01)

## FIX 2026-08-05 — admin cliente ASO + evento 15 (COMPLETO)
ASO (cliente, id 65) tenía asignado ev15 en _admin_evento pero al loguearse
en tvt_admin_v2.php veía el admin COMPLETO: tvt_admin_v2 nunca restringió por
tipo. Fix (commit dac29c9, deployado con .bak-20260805, verificado con curl
+ sesión real de ASO):
- tvt_admin_v2.php: tipo!=superadmin → redirect a cargador.php.
- cargador.php: evento id_tipo_evento=5 (interclubes) abre
  interclubes_resultados.php?evento=N en vez del flujo TVT.
Pendiente conocido (pre-existente, NO tocado): tvt_api.php y
interclubes_resultados/sorteo solo exigen sesión, no validan que el admin
tenga el evento asignado — un cliente con URL directa puede tocar otros
eventos.

## FIX 2026-08-05 — visualizar_en_llaves por categoría (COMPLETO)
interclubes-llaves.php pública mostraba las 9 categorías con sorteo de
_ic_sorteo ignorando el flag `visualizar_en_llaves` de
_relacion_evento_categoria (ev15: solo 5 y 10 en 'si'). Fix: JOIN con
_relacion_evento_categoria filtrando visualizar_en_llaves='si' y
estado='activo' en la query de categorías de logica/interclubes.llaves.inc.php
(el guard de $idCat existente cubre el acceso directo por URL a una oculta).
Deployado al VPS (.bak-20260805), verificado en vivo, commit c84fa20 pusheado.

## CIERRE 2026-08-04 — modelo POR PARTIDO completo y LIVE (retomar acá)
Sesión completa deployada y pusheada (commits 507691d..a96b296). El sistema
quedó: elección de jugadores POR PARTIDO en admin (selects, definir/en
juego/cambiar), pública CLUB vs CLUB con mini-cards por partido, sets en
filas S1/S2/S3, ganador al 1er set, confronto directo en posiciones, podio
escalonado oro/plata/bronce al definirse la final.
AL RETOMAR MAÑANA:
- El usuario estuvo probando en el demo ev16 (datos de prueba suyos cargados;
  semis generadas AREA4vsARENA / MOESvsLUJINI). Seguir iterando ahí.
- Para el EVENTO REAL (ev15 MUNICH ULTRA): cargar el sorteo oficial de
  ARCHIVOS/sorteo1..3.jpeg (10 categorías: +40/C/D/E/OPEN × fem/masc, 6
  clubes, 2 grupos de 3) vía interclubes_sorteo.php; sigue faltando el 6to
  club (Area 4), las categorías reales y fecha_fin_inscripcion.
- Borrar demo ev16 cuando ya no haga falta (SQL de limpieza en sección DEMO).
- Criterio último de desempate = posición del sorteo; si el torneo define
  otro (moneda/sorteo presencial), cambiarlo en ic_posiciones.

## RETOMADO 2026-08-04 — sorteo oficial + NUEVO MODELO por partido
- Sorteo oficial en ARCHIVOS/sorteo1..3.jpeg: 10 categorías (+40 FEM/MASC, C, D, E,
  OPEN × fem/masc), 6 clubes, 2 grupos de 3 por categoría.
- CAMBIO pedido: la elección de jugadores pasa a ser POR PARTIDO — en el admin se
  cargan los jugadores que disputan cada partido (ya no dupla N = orden de
  inscripción); la vista pública muestra CLUB vs CLUB. Demo ev16 sigue vivo para
  iterar visual.
- IMPLEMENTADO Y LIVE 2026-08-04 (deployado a VPS, .bak-20260804):
  - interclubes_resultados.php: cada slot pendiente muestra selects de jugadores
    (inscriptos + suplentes) con 3 botones: Cargar resultado / 📌 Definir
    jugadores / 🎾 En juego. Partido sin resultado tiene "↔ Cambiar jugadores".
    Badge PAREJA N / MIXTA derivado de los CIs reales (badgeDes) en TODOS los
    partidos. Backend: acción `definir_partido` (generaliza definir_desempate,
    con tope de slots regulares por cruce) y `cambiar_jugadores` (generaliza
    cambiar_dupla_desempate, cualquier partido sin ganador); alias viejos siguen
    aceptados. guardar_partido valida dupla con jugadores distintos.
    Removidos del JS: marcarEnJuego, guardarDesempate, definirDesempate,
    cambiarPareja, guardarCambioPareja (reemplazados por genéricos).
  - logica/interclubes.llaves.inc.php: slot sin fila → CLUB vs CLUB con
    "Jugadores a designar" + "por jugar" (sin nombres de duplas derivadas);
    ic_fila_partido perdió los params dupSlotA/dupSlotB. Partidos cargados con
    badge ic_badge_pareja real.
  - grafico-interclubes.inc.php sin cambios (solo muestra partidos ya cargados).
  - Verificado en vivo con demo ev16: 10 slots "a designar", 16 PAREJA + 2 MIXTA,
    EN JUEGO ok, ambas páginas 200, php -l limpio, JS validado con node.
  - Slots por serie sigue = min(duplas inscriptas de ambos clubes).
  - El admin (con sesión) lo valida el usuario en UI.
- ITERACIÓN 2 (2026-08-04, LIVE): 
  1) Información (grafico-interclubes.inc.php): pestaña Sorteo muestra SOLO la
     conformación de grupos (clubes numerados); posiciones/enfrentamientos/
     resultados removidos (viven en Llaves). Se removió la carga de _ic_partidos
     e icNombres/ic_n_duplas (código muerto).
  2) Sets APILADOS (columna por set: arriba club izq, abajo club der) en admin
     (setsInputs .set-col) y pública (.p-score .set inline-flex column).
  3) CARGA PROGRESIVA set a set: ic_ganador_partido ahora exige 2 sets ganados
     (mejor de 3) — partido con 1 set = sin ganador. guardar_partido acepta
     parciales: sin ganador ⇒ en_juego='si' automático; con ganador ⇒ 'no'.
     Guardar con 0 sets rechazado (usar Definir jugadores). Verificado que
     ningún partido histórico cambia de estado (query GREATEST(w1,w2)<2 vacía).
  - E2E por SQL en demo: 1 set → EN JUEGO + 1 columna apilada + PAREJA + sin
    ganador; 2do set → 2 columnas + ✓ ganador + sin EN JUEGO. Fila test borrada.
  - OJO: el usuario borró TODOS los partidos del demo ev16 probando el admin —
    _ic_partidos quedó en 0 para ev16; playground limpio, no es bug.
- ITERACIÓN 3 (2026-08-04, LIVE, commits a1594f4+2d2c354+795df17):
  - Etiquetas S1/S2/S3 en el cargador; luego sets pasaron a FILAS verticales
    (S1 [6]-[4] hacia el lado de cada club) en admin y pública (.set-row/.set-n).
  - Sorteo público: categorías COLAPSADAS en acordeón (reusa toggleAccordion);
    clic despliega Grupo 1/2 con clubes.
  - Ganador REVERTIDO a "líder en sets": cargar el 1er set ya finaliza el
    partido (EN JUEGO→FINALIZADO automático); solo queda en juego con sets
    empatados. La serie sigue cerrando al completar los slots.
  - GOTCHA: opcache puede servir la versión vieja unos segundos tras scp —
    un FINALIZADO fantasma en la verificación fue eso, no bug.
- ITERACIÓN 4 (2026-08-04, LIVE, commit 8c5d83b): en la pública cada PARTIDO
  de una serie es una mini-card COLAPSADA (.pm-card) con estado en el header:
  FINALIZADO verde + ganador + sets / 🔴 EN JUEGO naranja + parcial / por
  jugar gris. togglePartido() JS; aplica a grupos y llaves (mismo renderer).
  El usuario cargó datos reales por el admin nuevo en vivo (funciona E2E).
  GOTCHA: NO insertar filas de prueba por SQL mientras el usuario carga en el
  admin — mis test rows le ensuciaron la serie (borradas, ids 49-50).
- ITERACIÓN 6 (2026-08-04, LIVE, hasta 0dbb5c4):
  - EN JUEGO y partidos con jugadores definidos: abiertos en la pública.
  - Marcador del header FINALIZADO desde la perspectiva del ganador (flip si
    gana club2): ARENA BAR 6-4, no 4-6 (miniFmt público / miniSets(s,flip) admin).
  - POSICIONES con CONFRONTO DIRECTO: pts → mini-liga entre empatados (ic_stats
    reutilizada) → difs globales → posición del sorteo (empate circular
    perfecto, caso real del demo G2: 3 clubes todo igual). Semis 1°G1vs2°G2
    quedaron consistentes (MOES 1° G2 por sorteo).
  - Cargador: quitados los botones 🎲 Sorteo y ← Admin del topbar.
- ITERACIÓN 7 (2026-08-04, LIVE, hasta a96b296): card 3er Puesto acotada
  (.tercer-box 520px). PODIO al definirse la final: evolucionó pills doradas →
  card sobria → PODIO ESCALONADO horizontal (campeón bloque oro 84px, vice
  plata 54px al medio, tercero bronce 32px al otro extremo, apoyados en la
  base de la card; labels uppercase + club en tamaño decreciente). Igual en
  pública (.podio en interclubes.llaves.inc.php) y admin (col() JS + vars tema).
- ITERACIÓN 5 (2026-08-04, LIVE, commits ce5aadf+6a61301+a13e566):
  - Pública: series ABIERTAS por defecto; serie definida → colapsada con
    FINALIZADO (igual que admin). Partidos internos siempre colapsados.
  - Admin: mini-cards por partido idénticas a la pública (cardPartido/
    resumenPartido/miniSets JS): FINALIZADO colapsado con ganador+sets en el
    header; EN JUEGO (naranja) y pendientes abiertos. Desempate = card violeta
    pm-des. Al guardar con ganador se colapsa y sale de EN JUEGO solo.

## CIERRE 2026-08-03 — probado y aprobado por el usuario
Badges PAREJA 1/2, definidor del 3er partido, edición de inscripciones con
torneo iniciado y bracket placeholder (público + admin): TODO LIVE, probado
por el usuario en el demo. Proyecto BT cerrado por ahora.
PENDIENTE al retomar: borrar el DEMO evento 16 cuando ya no haga falta
(SQL de limpieza abajo, sección DEMO); ev15 real sigue sin fecha_fin_inscripcion
y sin el 6to club (Area 4).

## Badges + definidor desempate + edición inscripciones (2026-08-03, LIVE, commits f1..88d7eaa)
- F1 badges: pills PAREJA N (slot) / MIXTA (CIs vs ic_duplas) en cargador admin
  (filaPartido/badgeDes) y vista pública (ic_fila_partido/ic_badge_pareja). CSS .pj-badge.
- F2 definidor 3er partido: acciones `definir_desempate` (fila 0-0 es_desempate=1,
  en_juego opcional → pública la muestra "por jugar") y `cambiar_dupla_desempate`
  (solo sin ganador). UI: Cargar desempate / 📌 Definir pareja / 🎾 En juego;
  ↔ Cambiar pareja con selects preseleccionados (registry SERIES[sid]).
  Suplentes entran a los selects del desempate con "(suplente)".
- F3 edición torneo iniciado (solo admin): tvt_api `editar_jugador_pareja`
  actualiza AMBAS filas espejo con WHERE exacto (ci+ci_dupla+club+ev+cat);
  need_datos=true si jugador nuevo sin nombre (UI repregunta por prompt);
  promover suplente propio lo borra de _ic_suplentes; suplente ajeno bloquea.
  UI tab Clubes → parejas: P1/P2 numeradas + lápiz por jugador + atajo suplente.
  VERIFICADO: orden Pareja N se conserva (id de fila canónica no cambia);
  partidos jugados conservan CIs históricos; form del club refleja el cambio.
- Verificación admin JS: node check_js (extrae <script> y new Function) — el
  cargador/admin no se puede curl-ear (sesión); el usuario valida en UI.
- Bracket visible desde el inicio (commit a6b2f9e): pestaña SF de la vista
  pública ya no espera a _ic_llaves; muestra cards placeholder con los cruces
  (1°G1 vs 2°G2, 1°G2 vs 2°G1, Ganador SF1 vs SF2, 3er puesto) + conectores.
  ic_card_placeholder() en interclubes.llaves.inc.php. Commit 49c0a46: mismo
  bracket placeholder en el cargador admin (cardPlaceholder JS, nota de
  autogeneración debajo del cuadro).

## DEMO recreado (2026-08-03) + PLAN badges/edición/definidor (pendiente OK del usuario)
- Demo evento 16 recreado con SQL `ARCHIVOS/demo_interclubes.sql` (sin commitear):
  6 clubes (ids 101-106), 26 jugadores CIs 9200001-26 medio='demo-ic', cat 25,
  2 suplentes (AREA 4: Kike Suarez, MOES-YOYI: Beto Ramirez). Estados mixtos:
  G1 A4-LUJ finalizada 2-0, A4-VB 1-1 SIN desempate (playground definidor),
  LUJ-VB P1 EN JUEGO; G2 MOES-ARENA 2-1 con desempate mezclado jugado.
  sha1(16)=1574bddb75c78a6fd2251d61e2993b5146201319.
  URLs: grafico-interclubes.php?demo-interclubes&e=<sha1>&evento=<sha1> ·
  interclubes_resultados.php?evento=16 · form club: token md5('demo-club-101'..106).
  Limpieza: DELETE id_evento=16 en _ic_partidos/_ic_sorteo/_ic_llaves/_ic_suplentes/
  _p_incripciones/_p_clubes/_relacion_evento_categoria; _p_usuarios medio='demo-ic'; _p_eventos id=16.
- PLAN 3 fases (análisis hecho, sin implementar):
  F1 badges PAREJA 1/2 en cuadros: derivar del slot (partido regular N = pareja N,
  NO se guarda en _ic_partidos); desempate compara CIs vs ic_duplas → PAREJA N o MIXTA.
  Tocar filaPartido (admin JS) + ic_fila_partido (público PHP) + CSS pill.
  F2 definidor pareja 3er partido: fila 0-0 es_desempate=1 con CIs elegidos
  (acción definir_desempate + cambiar dupla si sin ganador); render existente ya
  soporta fila 0-0 (admin inputs, público "por jugar"); selects + suplentes.
  F3 editar inscripciones torneo iniciado (SOLO admin, tab Clubes → parejas):
  acción tvt_api editar_jugador_pareja — UPDATE de AMBAS filas espejo (a,b)+(b,a);
  orden Pareja N se conserva (ids no cambian); partidos jugados conservan CIs
  históricos; promover suplente lo saca de _ic_suplentes.

## Suplente opcional por categoría (2026-08-03, LIVE)
- 1 jugador suplente OPCIONAL por club por categoría en el form del club
  (interclubes.php): botón "+ Suplente (opcional)" outline debajo de "+ Agregar
  pareja", form de 1 jugador (ps_ci/ps_cel/ps_nombre/ps_apellido) con buscar_ci.
- Tabla nueva `_ic_suplentes` (id_evento, id_categoria, id_club, ci; UNIQUE
  ev+cat+club = máx 1 por club). COLLATE utf8mb4_unicode_ci — GOTCHA: con el
  default general_ci el JOIN TRIM(u.ci)=TRIM(s.ci) contra _p_usuarios
  (unicode_ci) tira "Illegal mix of collations" y la página da 500.
- NO toca el patrón espejo de _p_incripciones ni los conteos FLOOR(/2).
- Validaciones cruzadas: suplente no puede estar en pareja de la cat (ni
  viceversa), ni ser suplente en la cat para otro club. Quitar libre hasta cierre.
- asegurarJugador reutilizado (alta en _p_usuarios si es nuevo, medio interclubes).
- JS: toggleForm(id) y buscarCI(input, formId, prefix, estId) generalizados.
- E2E verificado en prod (club tmp borrado): alta/duplicado/conflicto pareja↔
  suplente/quitar. GOTCHA test: curl -X POST + -L re-postea el 302 del PRG en
  loop infinito → usar -d sin -X POST.
- El suplente NO aparece aún en vista pública (grafico-interclubes) ni en el
  cargador de resultados (selects de desempate) — pendiente si se pide.
- Dato real: ARENA BAR ya inscribió 2 parejas (cat 3) al 2026-08-03.

## Qué es
Competencia entre clubes: dueños de clubes reciben una URL con token para
inscribir hasta 2 parejas por categoría. Categorías se crean desde el admin
(Senior la crea el usuario después). Inscripción SOLO vía URL del club.
Luego: sorteo público (carga manual presencial) → enfrentamientos club vs club.

## Decisiones aprobadas (usuario 2026-08-01)
1. Sistema permite CREAR categorías desde admin (no hardcodear Senior).
2. Interclubes se inscribe únicamente por la URL del dueño del club; CI autocompleta datos como hoy.

## Estado: E0-E2 LIVE en producción (2026-08-01, commit 6d15f2c, push OK)

### Verificación E2E completa (2026-08-01, con datos de prueba luego borrados)
- Form: token inválido→404; render evento+club+categorías; alta pareja (filas espejo
  id_club, estado='inscripto', medio='interclubes'); duplicado rechazado; tope 2/2;
  quitar pareja; cierre por fecha_fin_inscripcion bloquea banner y POST. ✓
- API admin (sesión real): todas_cats=23; crear_categoria; clubes_evento con contador;
  club_inscriptos con nombres; crear/editar club; eliminar_club rechaza con inscriptos;
  regenerar_token. ✓
- Vecinos OK: home 200, inscripcion.php ev13 200, admin 200, kpis OK.
- Deploy: scp + .bak-20260801 de tvt_admin_v2.php y tvt_api.php en VPS; php -l limpio.

### E0 — DB (HECHO en VPS)
- Backup: /home/bt.com.py/bt_backup_pre_interclubes_20260801.sql.gz
- CREATE TABLE `_p_clubes` (id, id_evento, nombre, responsable, celular, email, token UNIQUE 32-hex, estado, created) MyISAM utf8mb4.
- ALTER `_p_incripciones` ADD id_club INT NULL + KEY.

### E1 — Admin (CÓDIGO LISTO local, falta deploy)
- tvt_api.php: `todas_cats` sin IN hardcodeado; nuevas acciones `crear_categoria`,
  `clubes_evento`, `crear_club`, `editar_club`, `eliminar_club` (bloquea si tiene inscriptos),
  `regenerar_token_club`, `club_inscriptos` — todas con prepared statements.
- tvt_admin_v2.php: tipo evento 5 "Interclubes" en select (onchange toggleTabClubes);
  tab 6 "🏢 Clubes" (visible solo tipo 5) con CRUD + copiar link + ver parejas;
  link "+ crear nueva" categoría en form de agregar cat; tabs dinámicos 5/6
  (evTabTotalActual, Guardar visible en n>=5); _clubesCache para editar/eliminar.
- Link del club: https://bt.com.py/interclubes.php?token=XXXX

### E2 — interclubes.php (EN PROGRESO)
Form público por token: hasta 2 parejas/categoría, CI autocompleta (como inscripcion.php),
validación server (token, evento abierto + fecha_fin_inscripcion, ≤2 parejas, duplicados,
categoría del evento), filas espejo en _p_incripciones con id_club, alta jugadores nuevos,
club puede quitar parejas hasta el cierre.

## Contexto técnico (análisis 2026-08-01)
- Patrón espejo: pareja = 2 filas en _p_incripciones (ci↔ci_dupla); conteos FLOOR(/2);
  dedup CAST(ci AS UNSIGNED) < CAST(ci_dupla).
- buscar_ci en inscripcion.php: _p_usuarios → fallback padrón _ci_py. (Fuga PII en el
  form viejo: devuelve datos reales en JSON; en interclubes va detrás del token.)
- fecha_fin_inscripcion NO se valida en el form viejo; en interclubes SÍ.
- Sorteo actual: determinístico por ranking, plantillas _tvt_plantillas; sin carga manual
  de posiciones (pendiente para fase sorteo interclubes).
- Deploy BT: sin git en VPS; editar local (repo BT) → scp con .bak-YYYYMMDD → probar.
- Baseline commit local: a9ddd4e (sync VPS 16-jul).

## Ajuste front (2026-08-01, commit fda6ed6)
- Evento 15 "INTERCLUBES MUNICH ULTRA" creado por el usuario (tipo 5, slug interclubes-munich-ultra).
- inscripcion.php: si id_tipo_evento=5 → página de aviso "inscripción por club" (con flyer), exit
  antes del wizard. Verificado: ev15 aviso, ev13 wizard normal intacto.
- tvt_admin_v2: select "Versión Formulario Inscripción" (fgVersionForm) oculto cuando tipo=5.
- Backup VPS: inscripcion.php.bak-20260801.
- Cronograma (commit bf53fb4): la imagen de "Imagen del Programa" (dentro de `descripcion`,
  extraída con regex src=) se muestra en el aviso de inscripcion.php Y en interclubes.php
  (arriba del listado de categorías). Verificado con ev15 (programa-interclubes.jpeg).

## UX (commit 1706723)
- guardarEvento: al CREAR, el modal ya no se cierra — setea evId, pasa a pestaña Categorías
  (alert guía menciona Clubes si es interclubes). Al EDITAR sigue cerrando como antes.
- Las categorías de un evento se agregan SOLO en: Eventos → lápiz → pestaña 🏷️ Categorías.
  La página "Categorías" del menú lateral es solo visualización.

- Fix 89d21b6: en el form del club, si el CI es de jugador ya registrado (source=usuarios),
  nombre/apellido/celular quedan readonly (server igual nunca pisa datos de existentes).
  Si viene del padrón (ci_py): celular editable. Jugador nuevo: todo editable.

- Fix f4a5fc7: CIs del listado de parejas enmascaradas (maskCi: 3 dígitos + asteriscos).
  Los hidden ci1/ci2 del form quitar siguen completos (necesarios para el DELETE).

## Auto-registro de clubes (commit abdfc1d, LIVE)
- `interclubes-registro.php?ev=<sha1(id_evento)>` — link ÚNICO por evento para repartir a dueños:
  form nombre club/responsable/celular/email → INSERT _p_clubes con token propio →
  redirect a interclubes.php?token=... con flash "guardá esta dirección".
- Admin: botón "Link de registro" en pestaña Clubes (sha1_evento viene en clubes_evento).
- Valida: evento tipo 5, estado activo/registro, fecha_fin_inscripcion, nombre duplicado.
- GOTCHA descubierto: PHP 8.4 mysqli tira excepción con encoding inválido (curl latin1 'ñ'
  → 500); conexión usa utf8. Fix: try/catch en INSERTs con texto libre (registro + agregar
  pareja). Navegadores reales mandan UTF-8 y funcionan.
- GOTCHA infra: hay auto_prepend_file `db/input_sanitizer.php` (bloquea UNION SELECT etc.)
  y log PHP en /home/bt.com.py/logs/php_log.
- Evento 15 real: club ARENA BAR (id 3) registrado por el usuario; tests limpiados.

## Vista pública propia (commit 202a641, LIVE)
- REGLA del usuario: NO tocar grafico-llaves-v2; la vista interclubes se selecciona en
  el campo URL Fixture del evento. Mis 2 ediciones a grafico-llaves.incTMP fueron revertidas.
- `grafico-interclubes.php` (wrapper igual a grafico-llaves-v2: $pagina + plantilla.php) +
  `logica/grafico-interclubes.inc.php` (contenido). Link front: /<url_fixture>.php?<slug>&e=<sha1>&evento=<sha1>
  (se arma en eventos.inc.php:404).
- Contenido: badge Torneo Interclubes + DETALLES (fechas/clubes/parejas) + CRONOGRAMA
  (img ancho completo de la columna, alto proporcional, click abre completa) + INSCRIPTOS
  con toggle "Por clubes" (club→categorías→parejas) / "Por categorías" (pareja + chip club).
  Estética idéntica al fixture: Tailwind, bg-gray-50, headers gray-800, azul blue-600.
- Admin: opción "grafico-interclubes" en select URL Fixture; al elegir tipo 5 se
  auto-setea si estaba en el default. ev15 ya apuntado por SQL.
- Verificado con pareja temporal (luego borrada): contador, categoría y nombres OK.
  Evento 15 hoy: 0 inscripciones reales (las pruebas del usuario fueron quitadas).

## Sorteo público + enfrentamientos (commit 956f153, LIVE)
- Modelo (ref ARCHIVOS/SORTEO.jpeg): sorteo POR CATEGORÍA, 2 grupos de 3 CLUBES,
  round robin dentro del grupo (misma lógica que grupos de 3 parejas del TVT).
- Tabla `_ic_sorteo` (id_evento, id_categoria, id_club, grupo, posicion; UNIQUE ev+cat+club).
- `interclubes_sorteo.php?evento=N` (requiere sesión admin): pills de categorías (con nº
  de clubes inscriptos), pool "por sortear" con botones →G1/→G2, posición = orden de
  salida del sorteo en vivo, quitar (recompacta posiciones), limpiar categoría,
  preview de enfrentamientos. Constantes IC_GRUPOS=2, IC_CLUBES_POR_GRUPO=3 (ponytail).
- Vista pública grafico-interclubes: pestaña "Sorteo" (default si hay datos) — por
  categoría, tablas Grupo 1|Grupo 2 con clubes numerados + enfrentamientos VS por grupo.
- Admin: botón "Sorteo" en pestaña Clubes abre la pantalla en otra tab.
- Verificado E2E (asignar/duplicado/estado/vista pública) con clubes tmp, luego borrados.
- Evento 15 real: 5 clubes registrados vía link (ARENA BAR, Vista Bar, En lo de chiqui
  Beach, Moes-Yoyi, Lujini) + categorías SUB 40+ MASC./FEM. creadas por el usuario (25/26).

## Resultados + posiciones (commit 2014b70, LIVE)
MODELO DEFINITIVO (6 audios del usuario, transcritos con Deepgram — key en jasvir-voz;
Gemini dio 429): serie club vs club por categoría:
- Partido 1 = dupla 1 vs dupla 1; Partido 2 = dupla 2 vs dupla 2 (dupla N = orden de
  inscripción del club, se muestra "Pareja N" en el form). Cada partido: 2 sets + 3er
  set desempate si empatan sets.
- Serie 1-1 → 3er PARTIDO de desempate con dupla MEZCLADA (1 jugador de dupla 1 +
  1 de dupla 2, a elección — selects en la pantalla).
- Sub 40: 1 dupla por club; Open: hasta 3 → columna `max_parejas` en
  _relacion_evento_categoria (default 2), editable en admin (whitelist + UI), el form
  del club la respeta. Slots de serie = min(duplas A, duplas B).
- Posiciones: 1 PTS por serie ganada (serie definida = slots regulares jugados y wins
  distintos); desempates: dif partidos → dif sets → dif games. También se jugará 3er
  puesto tras grupos (fase eliminatoria PENDIENTE).
Implementación:
- `_ic_partidos` (+ es_desempate) · `interclubes.functions.php` (ic_sets_partido,
  ic_ganador_partido, ic_duplas, ic_estado_serie, ic_posiciones) compartido admin+público.
- `interclubes_resultados.php?evento=N` (sesión admin): pills de categorías con sorteo,
  por grupo: tabla posiciones + series con slots automáticos por dupla, desempate con
  selects de jugadores, validación "debe haber ganador", editar sets / borrar partido.
- Vista pública: en pestaña Sorteo, por grupo: tabla Posiciones (Series/Partidos/Pts) +
  badge de serie (ganada/en juego/desempate) + partidos con sets (★ = desempate).
- Admin: botón "Resultados" en pestaña Clubes.
- E2E verificado (2 clubes test, serie 1-1, desempate mezclado, posiciones OK, público OK);
  datos de prueba borrados. Audios en BT/ARCHIVOS/1..6.ogg.

## Fase de llaves + DEMO (commit 7da3bba, LIVE)
- `_ic_llaves` (ev/cat/fase/clubA/clubB, UNIQUE ev+cat+fase) + columna `fase` en _ic_partidos
  (grupo|semi1|semi2|final|tercer; llaves usan grupo=0).
- Admin resultados: panel 🏆 Llaves — "Generar semifinales" (habilitado cuando TODAS las
  series de ambos grupos están definidas; cruces 1°G1vs2°G2 y 1°G2vs2°G1), luego "Generar
  Final y 3er Puesto" al definirse las semis. Series de llaves = misma mecánica
  (serieBloque JS reutilizado, duplas + desempate mezclado). estado devuelve duplas/
  jugadores a nivel categoría (no por grupo).
- Vista pública: pestaña "Llaves" — VISIBLE SOLO SI `boton_llaves='visible'` en el evento
  (el switch del admin ahora gobierna esto). Bracket por categoría + badge 🏆 Campeón.
- EVENTO 16 = DEMO con datos ficticios (estado 'previsualizacion', NO sale en la home):
  6 clubes (Area 4, Lujini, Vista Bar, Moes-Yoyi, Arena Bar, En lo de Chiqui), 24
  jugadores ficticios (CIs 9200001-24, medio='demo-ic'), CAT. C - MASC. completa:
  grupos con desempates → semis → final (CAMPEÓN Area 4) → 3er puesto (Lujini).
  URL: /grafico-interclubes.php?demo-interclubes&e=<sha1(16)>&evento=<sha1(16)>
  LIMPIEZA cuando ya no se necesite: DELETE de _ic_partidos/_ic_llaves/_ic_sorteo/
  _p_incripciones WHERE id_evento=16; _p_clubes WHERE id_evento=16; _p_usuarios WHERE
  medio='demo-ic'; _relacion_evento_categoria WHERE id_evento=16; _p_eventos id=16.

## Desarrollo estilo TVT + automático + en juego (commit ac036a4, LIVE)
- PEDIDO del usuario: comportamiento y diseño como todos-vs-todos, pills EN JUEGO,
  avance automático, y acceso vía botón Llaves desde la página de inscriptos.
- `interclubes-llaves.php` + `logica/interclubes.llaves.inc.php`: página pública de
  desarrollo — CSS calcado del TVT (match-card blanco, header #374151 colapsable,
  resumen ganador + badge FINALIZADO verde, EN JUEGO naranja #EBA652 pulsante),
  pills de categoría (?categoria=), posiciones por grupo, series de grupos
  ("G1 · Serie N"), llaves (Semifinal 1/2, Final, 3er Puesto) y 🏆 CAMPEÓN.
  Slots sin jugar muestran "por jugar" con las duplas.
- grafico-interclubes (info): botones Información | Llaves arriba (patrón TVT);
  Llaves aparece solo si `boton_llaves='visible'`. La pestaña llaves anterior fue
  ELIMINADA de la info (superada). Pestaña Sorteo se mantiene.
- `ic_autogenerar_llaves()` en interclubes.functions.php: semis (1°G1vs2°G2,
  1°G2vs2°G1) y final/3er se crean SOLAS (INSERT IGNORE) al completarse
  grupos/semis; se llama en estado y tras cada guardar_partido. Botones manuales
  eliminados de la UI (las actions generar_* siguen existiendo, sin uso).
- `en_juego` en _ic_partidos: acción `en_juego` (toggle por id, o crea fila 0-0
  en_juego='si' para un slot); guardar resultado la apaga. Admin: botón 🎾 por slot.
- Verificado con demo ev16: 10 series FINALIZADO, campeón, toggle en juego OK.

## Ajuste layout TVT exacto (commit 20b2c66, LIVE)
- interclubes-llaves ahora calca el layout del todos-vs-todos: grupos LADO A LADO
  (groups-grid repeat(auto-fit,minmax(340px,1fr))), botón "☰ Clasificación" arriba de
  cada grupo que abre MODAL con la tabla (modal-clasif igual TVT), y tabs
  "Resultados | SF →" — el tab SF contiene semis/final/3er + campeón.
- La tabla de posiciones ya NO está inline en la página: solo en el modal.

## Bracket con conectores (commits 9a39fe7 + 8f72384, LIVE)
- Iteración final (pedido usuario): las cards COMPLETAS de siempre (no compactas) —
  campeón banner arriba, columna Semifinales (2 match-cards con gap 28px +
  space-around) → conector SVG → columna Final (card centrada verticalmente).
  drawBracketLines busca '.match-card' y se redibuja al desplegar (toggleMatch),
  en resize y al entrar al tab. Layout .bracket-flex min-width 560px (scroll en móvil).
- 3er Puesto SEPARADO debajo del bracket. Sin sección de detalle duplicada.
- Si la final no existe aún: card gris "A definir — ganadores de las semis".

## Cargador estilo TVT + dark/light (commit 264b3a7, LIVE)
- interclubes_resultados.php FRONT reescrito (backend AJAX intacto): mismo layout que
  la vista pública — pills categorías, tabs "Grupos | SF →", grupos lado a lado con
  "☰ Clasificación" (modal), bracket con conectores SVG, 3er puesto abajo — pero cada
  match-card tiene los inputs de sets adentro (cards abiertas por defecto).
- Tema DARK por defecto + toggle ◐ a light (localStorage `bt-theme`, mismo patrón que
  cargador.php). Variables CSS :root / [data-theme=light].
- GOTCHA build: el PHP del archivo contiene '<!DOCTYPE' en el echo de "Sesión requerida"
  — al concatenar UI, verificar fin '?>' y ausencia de 'cargarCats();', no DOCTYPE.
- Fix 5d29fad: las series DEFINIDAS se renderizan colapsadas; abiertas solo las
  pendientes (al cargar el último resultado, la card se colapsa sola en el refresh).

## CIERRE DE SESIÓN 2026-08-01 — Sistema COMPLETO y LIVE
- DEMO (evento 16) ELIMINADO por completo: _ic_partidos/_ic_llaves/_ic_sorteo/
  inscripciones/clubes/jugadores demo-ic/rec/evento. Tablas _ic_* en cero,
  listas para el torneo real.
- Estado productivo: evento 15 "INTERCLUBES MUNICH ULTRA" (activo, tipo 5) con
  5 clubes reales registrados vía link: ARENA BAR(3), En lo de chiqui Beach(8),
  Vista Bar(9), Moes-Yoyi(10), Lujini Beach Tennis(13). Sin inscripciones aún.
- Flujo completo listo: registro de clubes por link → inscripción de parejas por
  club (tope por categoría) → sorteo público en vivo (2 grupos de 3) → carga de
  resultados estilo TVT dark/light → posiciones/semis/final/3er automáticos →
  vista pública Información + Llaves (bracket con conectores).
- Falta para el torneo real: fecha_fin_inscripcion del ev15 (hoy NULL = sin
  cierre), repartir link de registro al 6to club (Area 4 en el flyer), crear
  categorías Senior si aplica, y cargar el sorteo el día del evento.

## Pendiente
- Listado imprimible por club/categoría (para llevar al sorteo presencial) — nice to have,
  el tab Clubes ya muestra parejas por club.
- Fase futura: sorteo público con carga manual (club→grupo/posición en vivo) +
  enfrentamientos club vs club (cada cruce expande a partidos por categoría).
- El usuario crea la categoría SENIOR desde el admin cuando arme el evento real.

## 2026-08-05: card DETALLES colapsable en fixture (LIVE, commit 65edfa3)
Pedido: en grafico-interclubes la info (DETALLES + cronograma) colapsable,
colapsada por defecto. Hecho reutilizando toggleAccordion existente: el h2
pasó a button.accordion-button con chevron, contenido en .accordion-content.
GOTCHA cubierto: onload de la imagen del cronograma refresca maxHeight si el
acordeón está abierto (si no, la imagen quedaba cortada al cargar lenta).
Deploy: backup .bak-20260805-acordeon + scp /tmp + php -l + cp (receta de siempre).
Verificado en vivo ev16 demo: colapsado/abierto desktop + móvil 375.

## 2026-08-05 (2): SORTEO a card colapsable propia (LIVE)
Sorteo salió de los pills de INSCRIPTOS → card colapsable (colapsada) entre
DETALLES e INSCRIPTOS, columna izquierda. Pills quedan Por clubes (default) /
Por categorías; vistaIC sin 'sorteo'. Grupos en grid-cols-1 (columna angosta).
GOTCHA anidados: toggleAccordion ahora agranda max-height de ancestros
.accordion-content abiertos (con overshoot) — sin eso las categorías del
sorteo quedaban recortadas dentro de la card. Backup VPS .bak-20260805-sorteo.
Ajuste posterior: columna izquierda lg:grid-cols-5 → col-span-2 (antes 1/3);
los nombres de categorías del sorteo se leen completos en desktop. Móvil intacto.
Backup VPS .bak-20260805-colancha.

## 2026-08-06: link de inscripción "no independiente" — falsa pista, era la fecha
Reporte: al ocultar categorías del público, los clubes no podían cargar parejas
en interclubes.php?token=. Diagnóstico: el form NUNCA filtró por
visualizar_en_llaves (solo rec.estado='activo'); las 10 cats del ev15 activas.
Causa real: fecha_fin_inscripcion=2026-08-05 venció → $abierta=false → cero
botones "+ Agregar pareja" en TODAS las categorías (coincidió en el día con el
fix de visibilidad c84fa20, correlación no causa).
Fix: UPDATE _p_eventos SET fecha_fin_inscripcion=NULL WHERE id=15 (DB only,
sin cambios de código). Verificado en vivo: 8 botones agregar (E masc/fem al
tope), sin banner de cerrada. Para cerrar inscripción de verdad: volver a
setear la fecha en el admin.

## 2026-08-06: cierre de inscripción POR CATEGORÍA — LIVE (commit 6c3b63a)
El usuario eligió el toggle manual (opción cupo) sobre fecha por categoría.
interclubes.php?token= ahora respeta _relacion_evento_categoria.cupo:
'lleno' = categoría cerrada (sin agregar/quitar pareja ni suplente, POST
validado server-side, etiqueta "inscripción cerrada" en el header de la card).
El botón Lleno/Disponible ya existía en el admin (lista de categorías del
evento) — cero UI nueva. Probado en vivo con cat 131 (C MASC) lleno→disponible.
Backup VPS: interclubes.php.bak-20260806. Categorías 2/2 siguen permitiendo
suplente (eso es tope de parejas, no cierre).
