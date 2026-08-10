# Ranking Interclubes — LIVE (cierre 2026-08-10)

Ranking de CLUBES del circuito, hermano del ranking de jugadores.
**URL:** `https://bt.com.py/ranking.php?url=ranking-circuito-hernandariense&v3&ic`
(pestaña INTERCLUBES junto a JUGADORES; la pestaña aparece solo si el circuito
tiene algún interclubes culminado).

Este archivo es ESTADO ACTUAL, no bitácora: si algo cambia, se corrige acá.

## Reglas del modelo (definidas por el usuario)

1. **Puntos por posición final en CADA categoría**: campeón 100 · finalista 75 ·
   3° 60 · 4° 50 · participación 30 (participación = eliminado en fase de grupos).
   El puntaje del club en una fecha es la suma de sus categorías.
2. **Los puntos son POR FECHA**, como en el ranking de jugadores: se cargan en
   **Admin → Puntajes** eligiendo el evento. Cada fecha puede tener otra escala.
   `IC_PUNTOS_POSICION` (interclubes.functions.php) es solo el default de las
   fechas sin cargar. **La fecha 1 (ev15) ya está cargada** con 100/75/60/50/30
   (50 filas = 10 categorías × 5 posiciones en `_relacion_etiquetas_eventos`).
3. **Solo suman los eventos en estado `culminado`.** Un torneo entra al ranking
   cuando la organización lo cierra.
4. **Todas las fechas suman, sin descartes.** El club que no juega una fecha no
   suma esos puntos ("N de M eventos" bajo su nombre).
5. Una categoría no puntúa hasta que su final está definida (se avisa
   "N categorías en curso" en el detalle de esa fecha).
6. Desempate: puntos → títulos → finales → terceros → cuartos → nombre.
7. **Líder vs campeón**: mientras el circuito está abierto el hero es AZUL
   "Líder del Circuito · tras N fechas [, falta M]" con el aviso de que el campeón
   se define al cierre. Cerrado, hero DORADO "🏆 Campeón del Circuito".
   Cierre = `_circuitos.fecha_fin` cargada y ya pasada Y sin fechas interclubes
   en `activo`/`registro`. Eligiendo una fecha en el selector: "🏆 Campeón · <nombre>".
8. **Los nombres de fecha salen de `_p_eventos.evento`** (lo que el usuario pone
   en el admin). Prohibido numerarlas o inventarlas en el código — se removió un
   `$nroFecha` que anteponía "1ª fecha ·". ev15 hoy se llama "1°. FECHA".

## Estado de los datos hoy
- ev15 (`1°. FECHA`, circuito 1) **culminado**, 10 categorías, 6 clubes,
  237 partidos. Resultado: Vista Bar 815 (5 títulos) · Lujini 800 (5) ·
  Chiqui 515 · Moes 500 · Arena 460 · CSA4 360. Suma 3.450 = 345 × 10 ✔
  Líder del circuito: Vista Bar, por 15 pts (lo definen los terceros puestos).
- **Circuito 1 con `fecha_inicio` y `fecha_fin` en NULL A PROPÓSITO**: el circuito
  sigue abierto. Cargar el fin en Admin → Circuitos recién cuando se juegue la
  última fecha; ahí el hero pasa a campeón.
- ev16 "DEMO" queda afuera solo: `id_circuito=NULL` y estado previsualización.

## Identidad de clubes entre fechas
`_p_clubes` tiene `id_evento`: el mismo club en la fecha siguiente es OTRA fila.
El ranking los junta por `ic_clave_club()` (mayúsculas, sin acentos, sin
puntuación, espacios colapsados → "MOES - YOYI" = "MOES-YOYI").
Anti-drift en el ORIGEN, porque el nombre lo tipea el club al auto-registrarse:
- `interclubes-registro.php`: `<datalist>` con los clubes de interclubes
  anteriores (estados activo/registro/culminado) y, al guardar, se persiste el
  **nombre canónico del histórico**, no el tipeado.
- Mismo reuso en `tvt_api.php → crear_club` (alta desde el admin).
- Lo que NO se unifica solo: abreviaciones ("LUJINI" vs "Lujini Beach Tennis").
  Para eso está la lista, y si igual pasa, **solo el superadmin renombra**.
- **Sin tabla maestra de clubes y sin fusión automática** (decisión del usuario).

## Pantallas nuevas en el admin (solo superadmin)
- **Circuitos** (`goPage('circuitos')`, API `circuitos` / `guardar_circuito`):
  nombre, inicio y fin del circuito, cuántos interclubes culminados tiene y si
  está abierto o cerrado. Antes NO existía ninguna pantalla de circuitos: el
  circuito era un `<option>` fijo en el form de eventos.
- **Puntajes**: al elegir un evento `id_tipo_evento=5` aparece un editor de 5
  valores (Campeón/Finalista/3°/4°/Participación) en vez de la matriz de 16
  etiquetas × categoría; se ocultan "Copiar" y "Guardar todo" (son de esa matriz).
  API `guardar_puntajes_ic` escribe los 5 valores en TODAS las categorías del
  evento (etiquetas 8/6/10/4/1); `puntajes_evento` ahora devuelve `tipo_evento`.

## UI
- Tabla: **Pos · Club · PART. · 4° · 🥉 · 🥈 · 🥇 · TOTAL** (orden pedido por el
  usuario: arranca en participación y termina en total).
- Clic en el club → **dos niveles**: fechas colapsadas (nombre + puntos de esa
  fecha) → al abrir una, sus categorías. El puntaje de cada categoría cae **en la
  columna de la posición lograda**, con el nombre de la posición en chico.
- **Rayas verticales por columna en las DOS vistas** (regla en el bloque base del
  CSS, no dentro del media query): cabecera, fila del club y fila de categoría.
- Arriba: hero (líder/campeón) + campeones por fecha + barras de puntos por club.
  Selector `CIRCUITO · <nombre de cada fecha>` cuando hay más de una.
- **Móvil (≤600px)**: la MISMA tabla, rejilla compacta (pos 24 · club flexible ·
  5×27 · total 44 · chevron oculto) con **rayas verticales por columna**, y el
  detalle en la misma rejilla. Solo CSS; el HTML y el escritorio no cambian.
  Elegido por el usuario sobre 4 mockups; **confirmado en su teléfono: "quedó bien"**.
  No reabrir sin pedido explícito. Mockups:
  https://claude.ai/code/artifact/69492855-6347-4b1c-9c22-6663b5bdc049

## Archivos
- `logica/mostrar-ranking-interclubes.php` **nuevo** — cálculo EN VIVO, ~6 queries,
  sin tabla ni recálculo. TTFB 0.28–0.45s.
- `interclubes.functions.php` — `IC_PUNTOS_POSICION`, `IC_ETIQUETA_POSICION`,
  `ic_posiciones_categoria()`, `ic_puntos_matriz()`, `ic_puntos_pos()`,
  `ic_clave_club()`, `ic_clubes_previos()`, `ic_nombre_canonico()`.
- `ranking.php` — `?ic` elige el renderer (el `if` de `grabar` pasó a `if():`
  para poder encadenar `elseif():`; PHP no deja mezclar las dos sintaxis).
- `logica/mostrar-ranking.php` — SOLO pestañas (CSS + markup). Diff contra el
  backup = exactamente eso.
- `interclubes-registro.php`, `tvt_api.php`, `tvt_admin_v2.php` — anti-drift,
  API y pantallas nuevas.
- `test_interclubes_ranking.php` — 25 asserts sin DB: `php test_interclubes_ranking.php`.
- Backups en el VPS: `.bak-20260810`, `.bak2-20260810`, `.bak3-20260810`.

## Cómo se verificó (todo con datos reales)
- Totales del ranking contra una consulta SQL independiente: coinciden exacto.
- Multi-fecha: arnés fuera del webroot (copia del renderer con el WHERE de eventos
  cambiado) forzando ev15+ev16 → campeones por fecha, acumulado y `?ev=` correctos.
- Que la config de puntos manda: campeón=200 en ev15 → 1315/1300; restaurado → 815/800.
- Líder vs campeón: `fecha_fin='2026-08-09'` → hero dorado; NULL → hero azul.
- Endpoints de escritura (`guardar_puntajes_ic`, `guardar_circuito`) ejecutados de
  verdad extrayendo el bloque real de tvt_api.php contra el evento demo; filas de
  prueba borradas después. `guardar_circuito` rechaza fechas inválidas.
- Página de jugadores intacta (524 filas, top10 igual), sin warnings PHP,
  estados vacíos sin fatal, JS del admin pasado por `node --check`.

## Techos y gotchas
- Sin caché: si algún día hay ~10 fechas y se nota, la receta es la firma tipo
  `grafico-llaves-v2`, **NO** una tabla `_ranking_clubes`.
- `tvt_admin_v2.php` NO tiene helpers `toast()` ni `esc()` (se usa `alert()` y
  `api({action,...})` por GET); la clase de tarjeta es `ch-card`, no `card`;
  `bt_log()` ya se llama una sola vez, global, al inicio de `tvt_api.php`.
- Anchos de columna: deben ir por CLASE sola (`.c-n`), no por `.fila > div.c-n`,
  o el detalle no comparte rejilla con la cabecera y los puntajes no alinean.
- Hay un `/tmp/interclubes.functions.php` viejo (07-ago) en el VPS: un harness en
  /tmp con `__DIR__` levanta ESE en vez del de public_html.
- Nadie más en el sitio lee `_circuitos.fecha_fin` (verificado por grep).

## Cuando venga la 2ª fecha
Crear el evento interclubes → cargar sus puntos en Admin → Puntajes → al terminar,
pasarlo a `culminado`. El ranking la suma solo y aparece el selector de fechas.
Cuando sea la última del circuito, cargar el fin en Admin → Circuitos.

Ver [[bt-interclubes]], [[bt-ranking-performance]], [[bt-db-reference]].
