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

## OJO al retomar
- Las 6 categorías con criterio nuevo (3, 8, 19, 20, 25, 26) tienen
  `visualizar_en_llaves='no'` → la nota nueva todavía NO se ve en la pública.
  Se verá cuando el usuario las ponga visibles.
- Pendiente de definición del usuario: el orden exacto de "los demás criterios"
  (hoy: series ganadas → dif. de sets → sorteo) y si el desempate suma punto
  (hoy SÍ suma).
