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

## 2026-08-08: llaves desincronizadas por corrección de marcador (commit d877498)

Segundo síntoma de la MISMA trampa, ahora confirmada como bug recurrente y no
como caso aislado del fix anterior. Ev15 cat 8: la tabla estaba bien (ARENA 1ro,
CHIQUI 2do, MOES 3ro — los tres a 1 serie, define el saldo de games +2/0/−2) pero
las semis tenían ARENA y CHIQUI de lados cambiados.

Causa exacta (por `_bt_log`): a las 00:10:24 el grupo 2 se completó y se
generaron las semis. Entre 00:12:21 y 00:13:52 el admin corrigió 3 marcadores
del grupo y uno de esos cambios forzó un desempate — la tabla se movió, el
bracket no. `ic_autogenerar_llaves` usaba INSERT IGNORE y ya no volvía a mirar.

Fix: `ic_upsert_llave()` + `ic_fase_con_juego()`. Cada guardado recalcula y
actualiza la fase siguiente (borrando sus partidos vacíos) **solo mientras esa
fase no tenga games cargados**. Si el bracket ya se juega, no se toca. Aplica
igual a final/3er puesto, que tenían el mismo problema un nivel más arriba.

Verificación: diff de `_ic_llaves` del ev15 entero antes/después del resync →
cambia SOLO cat 8 (semi1 13→8 en clubB, semi2 8→3 en clubA). Cat 3, con la
semi1 en juego, intacta. Público live: cat 8 = Lujini vs En lo de Chiqui y
ARENA BAR vs Vista Bar. Backup VPS `.bak-20260808`.
El test del repo suma el cruce de semis con los datos reales de cat 8 (13 asserts).

**Para la próxima:** el log `_bt_log` (campo `fecha`, no `creado`) sirvió para
datar el bug al minuto. Y el parámetro de la pública es `?categoria=N`, no `cat`.

## Cierre de sesión 2026-08-08 (madrugada)

Verificado a pedido del usuario que los dos fixes NO tocaron las categorías
firmes: posiciones de cats 4/5/9/10 = 24 líneas con diff vacío contra el
baseline previo a todo el trabajo; sus 16 filas de `_ic_llaves` idénticas; los
12 bloques de partidos (grupo+semi1+semi2 de cada una) con sus games intactos.
La prueba fuerte: el resync corrió sobre las 10 categorías del evento, las
evaluó y no las tocó — el único cambio en todo `_ic_llaves` del ev15 fue cat 8.

**Estado del ev15 al cerrar:** cat 3 con la semi1 EN JUEGO (CSA4 vs ARENA BAR,
serie 1-1 en desempate) y la semi2 terminada (Lujini 2-0 a Vista Bar). Cat 8
con las dos semis recién emparejadas, sin jugar. Cats 4/5/9/10 con las semis
jugadas y final + 3er puesto emparejados pero SIN jugar.

## OJO al retomar
- En cats 4/5/9/10 la final y el 3er puesto siguen siendo re-sincronizables
  (no tienen games cargados). Es a propósito: si se corrige un marcador de una
  semifinal, la final se reacomoda con el ganador correcto. Lo jugado no se
  mueve; lo que no arrancó sigue al resultado.
- Las 6 categorías con criterio nuevo ya están `visualizar_en_llaves='si'`
  (3, 4, 5, 8, 9, 10); 19, 20, 25 y 26 siguen en 'no' y sin partidos.
- Sigue sin definir si el partido de desempate debería sumar algo aparte
  (hoy suma sus games al saldo, y su victoria a "partidos ganados").
- Backups del VPS: `.bak-20260807b` (criterio) y `.bak-20260808` (llaves).
- Scripts de verificación reusables, en el VPS: `/tmp/dump_pos.php` (posiciones
  de todo el evento, solo lectura, para diffear cualquier cambio del cálculo) y
  `/tmp/resync_llaves.php` (corre ic_autogenerar_llaves sobre todas las cats).
