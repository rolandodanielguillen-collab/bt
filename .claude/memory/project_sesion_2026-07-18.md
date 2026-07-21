# Sesión 2026-07-18 — Optimización de performance: 3 páginas públicas

## Resultado final (todo deployado, verificado y commiteado)

| Página | Antes | Después | Queries antes→después |
|---|---|---|---|
| ranking.php | 12s TTFB | 0.12s (~90x) | 15,100 → 13 |
| grafico-llaves-v2.php (la más vista, 5,374 hits/7d) | 2.1s | 0.10s (~20x) | 1,506 → 50 |
| todos-vs-todos.php | 0.27s | 0.12s | 166 → 48 |

## Commits (repo BT, con pointer bumps en repo raíz)
- `768d8de` perf(ranking): precarga en arrays elimina N+1 → logica/mostrar-ranking.php
- `f8cebd3` perf(fixture): gate rebuild _tabla_parejas + precarga → logica/grafico-llaves.incTMP.php
- `2e80a05` perf(llaves): precarga en render → logica/todos.vs.todos.php
- Sin push (no pedido)

## Backups de rollback (VPS reserplus, ANTES de cada edición)
```
/home/bt.com.py/public_html/logica/mostrar-ranking.php.bak-20260718
/home/bt.com.py/public_html/logica/grafico-llaves.incTMP.php.bak-20260718
/home/bt.com.py/public_html/logica/todos.vs.todos.php.bak-20260718
```
Rollback: `cp -a <backup> <original>`. REGLA del usuario (2026-07-18): nunca borrar/modificar nada en producción sin backup previo.

## Fix 1 — ranking.php (logica/mostrar-ranking.php)
- Root cause: triple recomputación N+1 (Top10 + FASE1 + FASE2) ≈18.5 queries × 816 pares jugador-categoría + **UPDATE _ranking (pos/nombre) por cada jugador en cada visita** (~800 writes/visita, ~270K/día)
- Fix: precarga 4 datasets (_ranking circuito, inscripciones+eventos, _p_usuarios, _p_categorias) indexados por ci|categoria|evento; helpers rk_eventos()/rk_puntos(); UPDATE eliminado (verificado: nadie lee pos/nombre — calculo_ranking_auto reinserta sin esas columnas)
- Búsqueda migrada a _p_usuarios canónico + prepared statements (antes buscaba _ranking.nombre, vacío en 79% de filas = jugadores inencontrables tras cada recálculo)
- Verificación: HTML byte-idéntico (diff=0)
- GOTCHA: logica/nocode-mostrar-ranking.php SÍ existe (45KB, CSS del modal adp-popup) — include relativo resuelve al dir del llamador; en el 1er deploy lo quité por buscarlo en la raíz equivocada, restaurado
- GOTCHA: CIs duplicados en _p_usuarios (6560193 Walber Souza id228/Castro id491, y 9677952) — código viejo tomaba primera fila heap; índice replica con if(!isset())

## Fix 2 — grafico-llaves-v2.php (logica/grafico-llaves.incTMP.php)
- Root cause: DELETE+INSERT completo de _tabla_parejas EN CADA VISITA (224 inscripciones × ~5 queries) + N+1 nombres/pagos + SELECT _todosvstodos muerto ($matriz sin uso) + sha1(columna) en WHEREs
- Fix: gate por firma de inscripciones (COUNT+MAX(id)+SUM(CRC32(campos)) → cache/parejas-sig-{evento}.txt); rebuild interno INTACTO (tabla crítica: los sorteos TVT la leen, esta página es el ÚNICO writer); rk_user_name/rk_estado_pago precargados; bloque muerto eliminado
- Elimina la race condition de visitantes concurrentes reconstruyendo la tabla
- Verificación: _tabla_parejas byte-idéntica post-rebuild, mismo set 224 jugadores, badges idénticos, vista admin ?rk= idéntica
- GOTCHA: el orden de parejas YA era inestable con el código viejo (v_p_inscriptos = JOIN sin ORDER BY, rebuild por visita → parejas bailaban entre refreshes; 2 capturas viejas difieren 388 líneas entre sí). Ahora queda estable hasta que cambien inscripciones
- El wrapper pone GIF "cargando" para disimular la lentitud (ya no hace falta, quedó)

## Fix 3 — todos-vs-todos.php (logica/todos.vs.todos.php — el de la RAÍZ public_html es otro archivo sin uso)
- Solo camino display. NO TOCADO: handler POST resultados+propagación+clasificación (L104-335, lógica crítica arreglada esta semana), auto-bye, toggle en_juego, fnPts admin
- Fix: user_ci2() precargado (era SELECT * _p_usuarios por jugador, ~120/visita); etiquetas slots (_p_grupos/_referencia_etiquetas) y visualizar_en_llaves precargados; siembra tabla_auxiliar con set en memoria (INSERT dispara en los MISMOS casos, race window igual que antes); query de grupos duplicada literal eliminada
- Verificación: HTML 0 diff en base/cat8/cat22; tabla_auxiliar count intacto (1450)

## Metodología (receta replicable, ver reference en memoria raíz)
1. Baseline: curl -w ttfb + contador `SHOW GLOBAL STATUS LIKE 'Questions'` antes/después de 1 visita
2. Backup .bak-YYYYMMDD en VPS ANTES de editar
3. Capturar HTML de referencia (todas las variantes: público, con params, admin)
4. Precarga en arrays PHP indexados; reemplazar queries en bucles por lookups; NO tocar lógica de escritura (o gatearla con firma sin cambiar su interior)
5. Deploy: scp a /tmp → php -l → cp + chown
6. Verificar: diff HTML = 0 (o explicar cada línea), sets de datos idénticos, tablas críticas intactas, re-medir queries+TTFB
7. Commit en repo BT + pointer bump en raíz

## Pendiente opcional
- Secret admin `?rk=c61fd3c895d9149ed9edf665` hardcodeado en grafico-llaves.incTMP.php → mover a .env
- CIs duplicados en _p_usuarios (6560193, 9677952) → limpieza de datos, decidir nombre real
- Detalle por evento en ranking se renderiza 2× por jugador (HTML 1.4MB crudo / 64KB gzip) → si se quiere bajar payload
- Micro-caché HTML: innecesario con TTFB ~0.1s
- session_start() en plantilla.php manda no-store → Cloudflare no cachea HTML (ya no importa)
