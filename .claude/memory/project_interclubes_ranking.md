# Ranking Interclubes — LIVE (2026-08-10)

Estado: **DEPLOYADO y verificado en vivo**. URL:
`https://bt.com.py/ranking.php?url=ranking-circuito-hernandariense&v3&ic`
(pestaña INTERCLUBES arriba del ranking de jugadores).

## Modelo de puntaje (definido por el usuario)
Por posición final en CADA categoría: **campeón 100 · finalista 75 · 3° 60 ·
4° 50 · participación 30** (participación = eliminado en fase de grupos).
El puntaje del club en el evento es la suma de sus categorías; el ranking
acumula los eventos interclubes del circuito.
Desempate: puntos → títulos → finales → terceros → cuartos.
Una categoría **no puntúa hasta que su final está definida** (se muestra
"N categorías en curso" en el detalle).

Constante `IC_PUNTOS_POSICION` en `interclubes.functions.php`. Si un evento
tiene cargada su matriz en el admin ("Puntajes por evento",
`_relacion_etiquetas_eventos`), ESA pisa la constante — mapeo por etiqueta en
`IC_ETIQUETA_POSICION` (8=campeón, 6=vice, 10=tercero, 4=cuarto, 1=grupos).

## Resultado real ev15 (verificado contra SQL independiente, coincide exacto)
Vista Bar 815 (5 títulos) · Lujini 800 (5) · Chiqui 515 · Moes 500 · Arena 460 ·
CSA4 360. Suma 3.450 = 345 × 10 categorías. Campeón general: **Vista Bar**,
por 15 pts, definido por los terceros puestos.

## Archivos
- `interclubes.functions.php` — +`IC_PUNTOS_POSICION`, `IC_ETIQUETA_POSICION`,
  `ic_posiciones_categoria()`, `ic_puntos_matriz()`, `ic_puntos_pos()`.
- `logica/mostrar-ranking-interclubes.php` **nuevo** — cálculo EN VIVO, 6 queries,
  sin tabla ni recálculo. TTFB medido 0.35s.
- `ranking.php` — `?ic` elige el renderer (sintaxis alternativa: el `if` de
  `grabar` tuvo que pasar a `if():` para poder encadenar `elseif():`).
- `logica/mostrar-ranking.php` — SOLO 2 bloques agregados (CSS de pestañas +
  markup); diff contra `.bak-20260810` = exactamente eso, nada más.
- `test_interclubes_ranking.php` — 20 asserts sin DB, `php test_interclubes_ranking.php`.

## Decisiones y techos
- **Clubes entre eventos**: `_p_clubes` tiene `id_evento` → se agrupa por nombre
  normalizado (`ric_clave`: mayúsculas, sin acentos, espacios colapsados). NO hay
  tabla maestra ni fusión automática: si un club se auto-registra con otro nombre,
  **solo el superadmin lo renombra** desde el admin (tab Clubes). Decisión del
  usuario 2026-08-10.
- La pestaña INTERCLUBES aparece solo si el circuito tiene eventos
  `id_tipo_evento=5` en estado activo/culminado (1 COUNT extra en la página de
  jugadores). El demo ev16 queda afuera solo: `id_circuito=NULL`.
- Columnas de la tabla en orden pedido: **Pos · Club · PART. · 4° · 🥉 · 🥈 · 🥇 ·
  TOTAL**; clic en la fila abre la clasificación categoría por categoría.
- Sin caché: si algún día hay ~10 eventos, la receta es la firma tipo
  grafico-llaves-v2, NO una tabla `_ranking_clubes`.

## Verificado en vivo
Ambas pestañas 200, sin warnings PHP; jugadores intacto (524 filas, top10 igual);
`?url=inexistente&ic` y `?ic` sin url → estado vacío, sin fatal.
Backups en VPS: `.bak-20260810` de los 3 archivos pisados.

Ver [[bt-interclubes]], [[bt-ranking-performance]].
