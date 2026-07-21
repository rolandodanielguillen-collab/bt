---
name: sesion-2026-07-21
description: Fix ranking por SETS (no games) + bracket 3 sets + salvaguarda grupos + orden torneos por fecha real
metadata:
  type: project
---

# Sesión 2026-07-21 — Ranking BT + listado torneos

## Fix 1 — Ranking por sets (crítico)
`calculo_ranking_auto.php:135-142` — antes sumaba games y con 18=18 saltaba la Final. Ahora cuenta sets ganados (`sA`/`sB`) y usa `$ganaEquipo1` para FINAL y RONDA.
Caso: ev 13 cat 3 final `id=3904` (6-2, 4-6, 8-10) → ganadores 3563177/4513326 = 100 pts.

## Fix 2 — Bracket muestra los 3 sets
`todos.vs.todos.php:2044-2057` — cascada `elseif` de un solo set → concatena todos con `implode`. Header `$resumenBk` cambiado a conteo de sets `(2-1)`. CSS `.bk-sc` con nowrap + letter-spacing.

## Fix 3 — Salvaguarda 3 sets en fase de grupos
Agregado set 2 al SELECT, conteo y suma de games en:
- `logica/calcular_clasificacion.php`
- `logica/cargar.auxiliar.v2-parte2.php`
- `logica/todos.vs.todos.php` (bloque tabla_auxiliar del POST 235-244)

## Bonus — Orden listado torneos por fecha real
`eventos.inc.php` — el SELECT alias `date_format(fecha,'%d-%m-%Y') AS fecha` hacía que `ORDER BY fecha DESC` ordenara el string dd-mm-yyyy alfabético por día. Fix: `ORDER BY _p_eventos.fecha DESC, prioridad DESC, id DESC` para forzar la columna DATE.

## Backups en VPS
- `calculo_ranking_auto.php.bak-20260721`
- `logica/todos.vs.todos.php.bak-20260721` (Fix 2) + `.bak-20260721-fix3` (Fix 3)
- `logica/calcular_clasificacion.php.bak-20260721-fix3`
- `logica/cargar.auxiliar.v2-parte2.php.bak-20260721-fix3`
- `eventos.inc.php.bak-20260721`

## Verificaciones
- `php -l` OK en los 5
- `curl calculo_ranking_auto.php?evento=13&debug=1` → 100/80 correctos cat 3
- Bracket HTML → Final muestra `6 4 8` vs `2 6 10` con `bk-sc-win` correcto
- Listado torneos → 10ma → 9na → 8va → ... → 1ra

## Entregables
- `INFORME_RANKING_20260721.md` (informe con los 3 bugs + fixes)
- `ranking_bt_snapshot_20260721.xlsx` (12 categorías, snapshot pre-fix)

Relacionado: [[bt-tournament-system]], [[ranking-confronto-fix]], [[bt-ranking-performance]]
