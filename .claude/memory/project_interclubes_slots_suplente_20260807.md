# Interclubes — 2 partidos fijos por serie + suplente visible (2026-08-07, LIVE)

Estado: **LIVE en bt.com.py, verificado. Diff de posiciones de lo ya jugado = VACÍO.**

## Regla confirmada por el usuario
Plantel por club y categoría = **2 parejas + 1 suplente = 5 jugadores** (ya era lo
que permitía el sistema: `max_parejas=2` en las 10 categorías + índice único de
`_ic_suplentes`). Por eso la serie tiene **2 partidos regulares SIEMPRE** + el 3ro
de desempate si quedan 1-1.

## Cambio 1 — `IC_SLOTS_SERIE = 2`
Antes: `slots = max(1, min(parejas cargadas A, parejas cargadas B))` repetido en 9
lugares → un club que cargó 1 sola pareja generaba series de 1 partido (el caso
reportado: CHIQUI en cat 3, 1 pareja + 1 suplente).
Ahora: constante `IC_SLOTS_SERIE` en interclubes.functions.php, usada en los 9
sitios (functions ×3, resultados ×5, llaves.inc ×1). El sistema ya NO mira cuántas
parejas cargó cada club.
**Los jugadores pueden repetirse entre los 2 partidos** — la única validación al
cargar es que los 2 jugadores de una dupla sean distintos entre sí. No hubo que
tocar nada para eso (verificado en guardar_partido / cambiar_jugadores).

## Cambio 2 — suplente en la vista pública
`logica/grafico-interclubes.inc.php` nunca leía `_ic_suplentes` (pendiente abierto
desde el 03-ago). Ahora: query propia + render en las DOS vistas — "Por clubes"
(fila ámbar punteada bajo las parejas de esa categoría) y "Por categorías" (misma
fila + chip del club). NO suma a los contadores de parejas.
Detalle: si un club tiene suplente en una categoría donde todavía no cargó parejas,
se fuerza la key para que la categoría se dibuje igual (si no, quedaba invisible).

## Falsa pista descartada
El suplente (ci 5478436, Josué Aponte) tiene `estado=inactivo` en `_p_usuarios`.
**No era la causa**: ninguna query del flujo interclubes filtra por el estado del
jugador (los dos `estado='activo'` del código son de `_relacion_evento_categoria`).
El form del club siempre lo mostró; faltaba solo en la pública.

## PENDIENTE (no aprobado aún)
El nombre está doble-codificado en la base: `JosuÃ© Aponte` (`_p_usuarios` id=225,
medio='web-inscripcion update', viene de antes del interclubes). Ahora se ve así en
la pública. Fix propuesto: `UPDATE _p_usuarios SET nombre='Josué' WHERE id=225`.

## Series que ganan un 2do partido (verificado en vivo, ninguna tiene partidos aún)
cat 3 G2 ×2 · cat 8 G1 ×2 y G2 ×2 · cat 19 G2 ×3 · cat 20 G2 ×1 · cat 26 G2 ×3.
Las 4 categorías ya jugadas (4/5/9/10) no cambian: todos sus clubes tienen 2
parejas, o sea el `min()` ya daba 2.

## Verificación
- `scratchpad/dump_posiciones.php` viejo vs nuevo → **diff vacío**.
- `scratchpad/test_criterio.php` → 12 asserts ok (el criterio del 07-ago intacto).
- `php -l` ×4, JS del cargador con node --check, pública en vivo http 200 con 42
  filas de suplente (21 suplentes × 2 vistas).
- Backups VPS: `.bak-20260807b` (los `.bak-20260807` son de la sesión del criterio).
