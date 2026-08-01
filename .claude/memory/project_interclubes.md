# Interclubes — Nuevo sistema de competencia (2026-08-01)

## Qué es
Competencia entre clubes: dueños de clubes reciben una URL con formulario para
inscribir hasta 2 parejas por categoría. 12 categorías actuales + 1 nueva (senior).
Luego: sorteo público (carga manual presencial) → genera enfrentamientos.

## Estado: ANÁLISIS en progreso
- Schema DB relevado: `_p_eventos` (id_tipo_evento 1-4, interclubes sería 5),
  `_p_categorias` (23 cats, evento 13 usa 12 activas: C/D/E masc+fem, MIXTO C/D/E/OPEN, OPEN m/f),
  `_p_incripciones`, `_tabla_parejas`, `_equipos`, `_relacion_evento_categoria`.
- NO existe concepto "club" en DB (46 tablas revisadas; `_p_complejos` = predios).
- Admins: `_usuario_admin` (superadmin fabian/ROLANDO, clientes EXAS/ASO/PRUE) + `_admin_evento`.
- Archivos clave bajados a scratchpad: inscripcion.php, inscripcion-v2.inc.php,
  post_inscripcion.php, 3_tvt_generar_sorteo.php, generar_sorteo_tvt.php, tvt_plantillas.php.
- 2 agentes Explore analizando: flujo inscripción y flujo sorteo/admin.

## Hallazgos sorteo/admin (agente, 2026-08-01)
- Eventos se crean por UI: tvt_admin_v2.php modal 5 pestañas → tvt_api.php `crear_evento` (INSERT 31 cols). UI solo expone tipos 1/2/3.
- Sorteo NO es aleatorio: determinístico por ranking (serpiente + cabezas de serie). No hay carga manual de posiciones (solo swap post-sorteo `intercambiar_parejas`).
- Flujo: _p_incripciones → generar_equipos.php → _equipos (solo contadores); _tabla_parejas es la fuente real del sorteo (generador = grafico-llaves-v2.php, no analizado).
- 3_tvt_generar_sorteo.php = versión activa; genera grupos + eliminatoria desde _tvt_plantillas (42 plantillas, selección automática por N parejas, JSON cruces_eliminatoria).
- _p_grupos es catálogo fijo (nunca se escribe). Virtual→madre: 32=16avos, 26=8vos, 13=cuartos, 15=semis, 18=final, 19=3er.
- GOTCHA: `todas_cats` en tvt_api.php filtra WHERE id IN (12 ids hardcodeados) — categoría Senior nueva requiere editar ese IN.
- Auth admin: _usuario_admin pass EN TEXTO PLANO, sin CSRF, sin autorización por evento en v2 (solo cargador.php filtra _admin_evento).

## Hallazgos inscripción (agente, 2026-08-01)
- Form público sin login: `inscripcion.php?url=SLUG` (rewrite `torneo-SLUG`). Wizard 4 pasos, pareja se inscribe UNA vez → 2 filas espejo en `_p_incripciones` (ci↔ci_dupla). Conteos dividen /2; generar_equipos deduplica LEAST/GREATEST.
- `buscar_ci` (AJAX): busca _p_usuarios y padrón _ci_py; FUGA PII (devuelve nombre/cel/email reales en JSON).
- POST no revalida NADA: categoría no se chequea contra evento/sexo/cupo/estado; `fecha_fin_inscripcion` no se consulta en ningún lado; checkboxes términos solo client-side; check duplicado sin transacción.
- `post_inscripcion.php` = endpoint legacy huérfano (1 fila sin espejo, SQLi directo). No usar.
- `boton_team` no existe en código. Cero concepto de club/equipo real en inscripción.
- v2/post_inscripcion concatenan SQL inyectable; inscripcion.php (v1 nuevo) escapa bien.

## Plan propuesto (2026-08-01, PENDIENTE APROBACIÓN)
- E0: DB — `_p_clubes` (token URL por club), ALTER `_p_incripciones` ADD id_club, cat SENIOR + fix IN hardcodeado todas_cats.
- E1: Admin — tipo evento 5 "Interclubes" en select + pestaña Clubes (CRUD + link token + contadores).
- E2: `interclubes.php?token=` — form público por club: hasta 2 parejas/categoría, validación server completa (token, fechas, cupos, duplicados), filas espejo con id_club, editable hasta cierre.
- E3: Vista admin inscriptos por club.
- Futuro: sorteo público carga manual club→posición + enfrentamientos club vs club (cada cruce expande a partidos por categoría en _todosvstodos).
- Stack: PHP legacy mismo patrón (tvt_admin_v2/tvt_api), prepared statements en código nuevo, deploy scp + .bak.
