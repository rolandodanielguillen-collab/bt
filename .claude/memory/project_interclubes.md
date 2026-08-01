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

## Pendiente
- Listado imprimible por club/categoría (para llevar al sorteo presencial) — nice to have,
  el tab Clubes ya muestra parejas por club.
- Fase futura: sorteo público con carga manual (club→grupo/posición en vivo) +
  enfrentamientos club vs club (cada cruce expande a partidos por categoría).
- El usuario crea la categoría SENIOR desde el admin cuando arme el evento real.
