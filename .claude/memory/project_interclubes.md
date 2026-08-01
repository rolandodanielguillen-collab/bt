# Interclubes — Nuevo sistema de competencia (2026-08-01)

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

## Pendiente
- Listado imprimible por club/categoría (para llevar al sorteo presencial) — nice to have,
  el tab Clubes ya muestra parejas por club.
- Fase futura: sorteo público con carga manual (club→grupo/posición en vivo) +
  enfrentamientos club vs club (cada cruce expande a partidos por categoría).
- El usuario crea la categoría SENIOR desde el admin cuando arme el evento real.
