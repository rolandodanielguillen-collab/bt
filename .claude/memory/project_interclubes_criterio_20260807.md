# Interclubes — Criterio de clasificación NUEVO + congelamiento por categoría (2026-08-07)

Estado: **LIVE en bt.com.py, verificado. Diff de posiciones de las categorías en
juego = VACÍO (no se movió nada de lo ya jugado).**

## Los dos criterios que conviven

`ic_criterio_viejo(int $idEvento, int $idCat): bool` decide cuál se aplica.
Constante `IC_CATS_CRITERIO_VIEJO = [15 => [4, 5, 9, 10]]` en
interclubes.functions.php.

**VIEJO** (ev15 cats 4 D MASC, 5 E MASC, 9 D FEM, 10 E FEM — ya jugaron con esto,
congeladas hasta terminar el torneo):
1 pt por SERIE ganada → saldo de games (el 3er partido de la serie vale ±1, no sus
games reales) → confronto directo (mini-liga entre empatados) → posición de sorteo.

**NUEVO** (todas las demás categorías y eventos futuros):
1 pt por PARTIDO ganado (desempate incluido) → saldo de games COMPLETOS (el 3er
partido suma su marcador real) → series ganadas → dif. de sets → posición de
sorteo. **SIN confronto directo.**

## Por qué lista explícita y no "la categoría ya tiene partidos"
El criterio de una categoría no puede cambiar solo el día que se carga su primer
resultado. La lista es por evento+categoría: la misma cat 4 en otro evento usa el
criterio nuevo (chequeado en el test).

## Archivos tocados (local + VPS, backups .bak-20260807)
- `interclubes.functions.php` — const + ic_criterio_viejo(); ic_stats($viejo):
  rama ±1 solo si viejo, y pts = sg (viejo) / pg (nuevo); ic_posiciones($viejo):
  early return con el orden nuevo, la rama vieja quedó intacta.
- `interclubes_resultados.php` — 2 call sites + `criterio_viejo` en el JSON de
  action=estado + modal JS (columna Part. y nota según criterio).
- `logica/interclubes.llaves.inc.php` — call site + modal público idem.
- Modal de clasificación ahora: Club | Series | Part. | Games | Pts.

## Verificación
- `scratchpad/test_criterio.php` (sesión 06138124): 12 asserts con los datos
  reales de cat 4 grupo 1 (CHIQUI/ARENA/MOES). Viejo → CHIQUI, MOES, ARENA con
  +6/-2/-4. Nuevo → CHIQUI(3pts,+5), ARENA(3,-6), MOES(2,+1).
- `scratchpad/dump_posiciones.php` corrido en el VPS con el archivo viejo y el
  nuevo → **diff vacío (42 líneas idénticas)**. Reusa reflection para saber si
  ic_posiciones acepta el 4º param, así sirve para diffear cualquier cambio futuro.
- Live: cat 4 http 200, 5 columnas, nota vieja. Admin 200.

## CORRECCIÓN de la misma noche (commit 6af876b) — los pts vuelven a ser SERIES

Lo de arriba (pts = partido ganado) duró unas horas. El usuario lo cacheó en
ev15 cat 3 grupo 1: VISTA BAR ganó una serie 2-1 y quedaba **3ro**, detrás de
MOES que perdió las dos series pero ganó 2 partidos sueltos con mejor saldo
de games (−2 vs −5). Regla real: primero se cuentan SERIES, después desempates.

**Criterio NUEVO corregido** (cats 3, 8, 19, 20, 25, 26 y eventos futuros):
1 pt por SERIE ganada → saldo de games COMPLETOS → **partidos ganados** →
dif. de sets → posición de sorteo. Sin confronto directo.

Código: `ic_stats` ahora hace `pts = sg` sin mirar `$viejo` — el flag decide
solo el DESEMPATE. En `ic_posiciones` la key del criterio nuevo cambió
`$c['sg']` (redundante con pts) por `$c['pg']`. La rama vieja, intacta.

Verificación: `dump_pos.php` antes/después contra la base real del ev15 →
cambian SOLO cat 3 y cat 8 (las de criterio nuevo con partidos cargados);
las 4 congeladas, línea por línea idénticas. Público live con `?categoria=N`
(ojo: el parámetro es `categoria`, no `cat`): cat 3 = CSA4 > VISTA BAR > MOES
con nota nueva, cats 5 y 10 con nota vieja y orden sin mover.

**Llaves de cat 3 rehechas.** `ic_autogenerar_llaves` usa INSERT IGNORE y nunca
borra → cambiar el criterio no recompone un bracket ya generado. Se borraron
las 2 filas de `_ic_llaves` + los 2 partidos vacíos de semi1 y se regeneró con
la función de producción: semi2 = Lujini vs Vista Bar (antes vs MOES), semi1 =
CSA4 vs ARENA BAR sin cambio. Guarda en el script: aborta si alguna llave ya
tiene games cargados. Backup de las filas viejas en
`/home/bt.com.py/backup_llaves_ev15_cat3_20260807.sql`.

Check permanente en el repo: `test_interclubes_criterio.php` (8 asserts, sin DB,
datos reales de cat 3 G1, cubre criterio nuevo y viejo). Corre con
`php test_interclubes_criterio.php`. Reemplaza al `scratchpad/test_criterio.php`
de la sesión anterior, que se perdía con el scratchpad.

## OJO al retomar
- Las 6 categorías con criterio nuevo ya están `visualizar_en_llaves='si'`
  (3, 4, 5, 8, 9, 10); 19, 20, 25 y 26 siguen en 'no' y sin partidos.
- Sigue sin definir si el partido de desempate debería sumar algo aparte
  (hoy suma sus games al saldo, y su victoria a "partidos ganados").
- Backups del VPS de esta corrección: `.bak-20260807b`.
