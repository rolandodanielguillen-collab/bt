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

## 2da tanda 2026-08-10 — previsión multi-evento
- **Solo eventos `culminado`** suman al ranking (regla del usuario; igual criterio
  que el ranking de jugadores en el admin). Como ev15 sigue en `activo`, HOY el
  ranking está vacío y la pestaña INTERCLUBES no aparece: se prende sola cuando
  la organización pase el evento a culminado.
- **Selector GENERAL · <evento> …** arriba (solo si hay más de un evento
  culminado); con uno elegido, el hero dice "Campeón del evento".
- Hero acumulado + línea de **campeones por evento**; bajo cada club, "N de M eventos".
- **Anti-drift de nombres de club** (el problema real del 2do evento, porque
  `_p_clubes` es por evento y el nombre es texto libre):
  · `ic_clave_club()` normaliza mayúsculas, acentos, PUNTUACIÓN y espacios
    ("MOES - YOYI" = "MOES-YOYI" — verificado, en el arnés pasaron de 2 filas a 1).
  · `interclubes-registro.php`: `<datalist>` con los clubes de interclubes
    anteriores (estados activo/registro/culminado — el demo ev16 queda afuera) y,
    en el POST, se guarda el **nombre canónico** del histórico, no el tipeado.
  · Mismo reuso en `tvt_api.php` → `crear_club` (alta desde el admin).
  · Lo que NO resuelve solo: abreviaciones ("LUJINI" vs "Lujini Beach Tennis").
    Para eso está la lista del formulario y, en última instancia, el superadmin.
- Verificado con un arnés fuera del webroot (`/tmp`, copia del renderer con el
  WHERE de eventos cambiado, ya borrado con safe-rm) forzando ev15+ev16:
  campeones por evento OK (Munich→Vista Bar, Demo→Moes), acumulado OK
  (Vista 845, Arena 520, Moes 600 ya unificado), `?ev=15` da exactamente
  815/800/515/500/460/360, `?ev=16` solo el demo.
- Backups de la 2da tanda: `.bak2-20260810`.

## 3ra tanda 2026-08-10 — semántica de circuito (líder vs campeón)
El usuario pasó ev15 a `culminado` (el ranking ya se ve). Planteó que con varias
fechas debe ser ranking de CIRCUITO con súper campeón al final, y que el badge
"CAMPEÓN GENERAL" desde la 1ra fecha estaba mal. Quedó así:
- **Circuito abierto** → hero AZUL "Líder del Circuito · N pts · tras N fechas
  [, falta M]" + aviso "el campeón se define al cierre".
- **Circuito cerrado** → hero DORADO "🏆 Campeón del Circuito".
- **Fecha elegida** en el selector → "Nª fecha · Campeón".
- Cierre = `_circuitos.fecha_fin` cargada y ya pasada. Refuerzo: si hay fechas
  interclubes del circuito en `activo`/`registro`, el circuito NO se considera
  cerrado aunque la fecha esté puesta.
- Selector: `CIRCUITO · 1ª FECHA · 2ª FECHA…` numeradas por fecha real.
- **Todas las fechas suman** (sin descartes), igual que el ranking de jugadores.
- **Admin → Circuitos** (nuevo, solo superadmin): nombre, inicio, fin del
  circuito + cuántos interclubes culminados tiene y si está abierto o cerrado.
  API: `circuitos` y `guardar_circuito` en tvt_api.php. NO existía pantalla de
  circuitos: el circuito era un `<option>` fijo en el form de eventos.
- Verificado en vivo los DOS estados: con `fecha_fin='2026-08-09'` el hero pasó a
  "🏆 Campeón del Circuito" sin aviso; restaurado a NULL volvió a "Líder del
  Circuito". Nadie más en el sitio lee `_circuitos.fecha_fin` (verificado por grep).
- Backups de esta tanda: `.bak3-20260810` (tvt_admin_v2, tvt_api).
- GOTCHA del admin: NO existen helpers `toast()` ni `esc()` en tvt_admin_v2 (se
  usa `alert()` y `api({action,...})` por GET); la clase de tarjeta es `ch-card`,
  no `card`. Y `bt_log()` ya se llama una sola vez global al inicio de tvt_api.

## 4ta tanda 2026-08-10 — puntajes POR FECHA (como el ranking de jugadores)
Pedido: "los puntajes tienen que ser por fecha de interclubes así como el ranking
de los jugadores". La matriz por evento ya mandaba sobre la constante, pero había
que cargarla en la grilla de 16 etiquetas × 10 categorías. Ahora:
- **Admin → Puntajes**, al elegir un evento `id_tipo_evento=5`, muestra un editor
  chico de 5 valores (Campeón/Finalista/3°/4°/Participación) en vez de la matriz;
  se ocultan Copiar y "Guardar todo" (son de la matriz de rondas).
- `guardar_puntajes_ic` escribe esos 5 valores en TODAS las categorías del evento
  (etiquetas 8/6/10/4/1). `puntajes_evento` ahora devuelve `tipo_evento`.
- **La fecha 1 (ev15) quedó con su tabla cargada**: 50 filas (10 cats × 5), mismos
  100/75/60/50/30 → el ranking dio idéntico (815/800/515/500/460/360).
- El pie del ranking muestra la escala de la(s) fecha(s) mostradas; si dos fechas
  tienen escalas distintas dice "cada fecha tiene su propia tabla".
- **Probado que la config manda**: puse campeón=200 en ev15 y la página pasó a
  1315/1300 (+5 títulos × 100) con el pie en "Campeón 200"; restaurado a 100 volvió
  a 815/800.
- El endpoint de guardado se ejecutó de verdad extrayendo el bloque real de
  tvt_api.php y corriéndolo contra el evento demo (escribió 90/70/55/45/25 con las
  etiquetas correctas); filas de prueba borradas después, ev15 intacto (50 filas).
- GOTCHA: hay un `/tmp/interclubes.functions.php` viejo (07-ago) en el VPS; un
  harness en /tmp con `__DIR__` lo levanta en vez del de public_html.

## Estado al cierre 2026-08-10
Usuario confirmó que las dos pantallas nuevas (Puntajes de interclubes y
Circuitos) se ven y andan. `guardar_circuito` probado ejecutando el bloque real:
guarda fechas, rechaza texto que no sea YYYY-MM-DD ("Fecha inválida") y vacía a
NULL. **El circuito 1 quedó con fecha_inicio y fecha_fin en NULL a propósito**:
sin fecha_fin el circuito está abierto y el ranking muestra LÍDER, que es lo
correcto hasta que se juegue la última fecha. Cuando la organización cierre el
circuito, cargar la fecha de fin en Admin → Circuitos y el hero pasa a CAMPEÓN
DEL CIRCUITO (dorado).

## 5ta tanda 2026-08-10 — presentación
- Detalle del club en DOS niveles: club → fecha (colapsada, con su nombre y sus
  puntos) → categorías. Antes abría directo en las categorías.
- Los puntajes del detalle caen EN LA COLUMNA de la posición lograda (mismos
  anchos que la cabecera); se sacó el padding del detalle que los desalineaba.
- **Nada de numerar las fechas en el código**: el nombre de cada fecha sale de
  `_p_eventos.evento`, que la organización configura en el admin (el usuario ya
  renombró ev15 a "1°. FECHA"). Se eliminó el `$nroFecha` que anteponía "1ª fecha ·".
- **Móvil (≤600px)**: primero se probó tarjeta de 2 líneas con chips — el usuario
  la rechazó ("no me gusta"). Se armaron 4 mockups en un artifact
  (`scratchpad/mockups-ranking-movil.html`,
  https://claude.ai/code/artifact/69492855-6347-4b1c-9c22-6663b5bdc049) y eligió
  **B1: la MISMA tabla compacta, con cabecera visible, rayas verticales por
  columna, y el detalle cayendo en su columna**. Anchos móviles: pos 24 · club
  flexible · 5×27 · total 44 · chevron oculto. Solo CSS; el HTML y el escritorio
  quedan igual.
- LECCIÓN del mockup: las celdas del detalle no tomaban ancho porque los anchos
  estaban en `.thead div.cX` y `.r > div.cX` — deben ir por CLASE sola para que
  cabecera, fila y detalle compartan rejilla. En producción ya estaba por clase;
  verificado que las 60 filas de categoría tienen las 9 celdas de la rejilla.

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
