---
name: bt-sesion-2026-07-02
description: "Sesión 2026-07-02: rediseño UI matriz de puntajes, recálculo ranking evento 12, fix pills categorías en todos-vs-todos"
metadata:
  type: project
---

# Sesión 2026-07-02 — bt.com.py (VPS 45.162.169.95, alias SSH `reserplus-vps`)

Trabajo hecho directamente sobre `/home/bt.com.py/public_html/` en el VPS. La memoria completa y detallada del proyecto vive en la memoria raíz: [[bt-padelsys-admin]] y [[bt-db-reference]] (en `C:\Users\rolan\.claude\projects\C--RepositorioSaaSFactory\memory\`).

## 1. Rediseño UI de Puntajes (tvt_admin_v2.php) — COMPLETADO Y VALIDADO
- Las cards en grid se superponían; se reemplazó por **matriz única**: filas = categorías, columnas = rondas, celdas = inputs numéricos.
- Header y primera columna sticky; celda vacía = ronda sin puntos; celdas con valor resaltadas (clase `has-val`, `--accent-glow`).
- `ETIQ_ORDER` cambiado a `[1,9,2,3,4,6,8,11,12,13,14,15]` + nuevo `ETIQ_SHORT`. Eliminadas `buildEtiqRow`/`addEtiqRow`.
- El usuario la validó usándola: cargó los puntajes del evento 12 con esta UI.
- Backup: `tvt_admin_v2.php.bak-puntajes-ui`

## 2. Recálculo Ranking Evento 12 (9na. FECHA) — COMPLETADO
- Verificado el botón RECALCULAR (pg-ranking, tab Calcular): llama a `calculo_ranking_auto.php?evento=ID&debug=1`, borra `_ranking WHERE evento=ID` y recalcula desde `_relacion_etiquetas_eventos` (fallback `_ranking_config` solo si no hay config).
- Ejecutado via curl con éxito: 228 registros, fuente primaria (56 registros de config, 12 categorías).
- Backup pre-recálculo en DB: tabla `_ranking_bak_ev12_20260702`.
- Correcciones logradas: cats por tabla 8 (C FEM) y 20 (OPEN FEM) pasaron de max 15 → 100 pts; cat 21 (MIXTO C) de 15 → 50; estándar subieron grupo 15→30.
- **Sistema de puntuación confirmado por el usuario**: mixtas (18,21,22,23) escala reducida 15/30/50; estándar 30/40/60/80/100; cats 8/20/21 usan etiquetas "por tabla" (12=1°,13=2°,14=3°,15=4°).
- Caso Luis Segovia (5971512): resuelto, valores coherentes en las 9 fechas.
- ⚠️ Pendiente sugerido: `calculo_ranking_auto.php` no exige login (accesible por URL).

## 3. Fix pills de categorías en todos-vs-todos.php — COMPLETADO
- Problema: en `logica/todos.vs.todos.php` (la entrada `todos-vs-todos.php` solo incluye plantilla.php) las pills estaban en flex con `overflow-x:auto` y **scrollbar oculta** → en desktop solo se veían 9 de 12 categorías.
- Fix: CSS grid `.cat-pills-grid` de 6 columnas (2 filas para 12 cats), responsive 3 cols <768px y 2 cols <400px. Mismo patrón que top.g-llaves.inc.php pero con el estilo claro de esta página.
- Backup: `logica/todos.vs.todos.php.bak-pills-grid`
- Verificado: los 12 botones renderizan en la grilla en `?evento=12`.

## Método de deploy usado (mejor que los patches str_replace)
1. Backup en VPS (`cp archivo archivo.bak-<motivo>`)
2. `scp` del archivo al scratchpad local, editar con herramientas locales
3. `scp` a `/tmp/` del VPS → `php -l` → recién ahí `cp` a producción

## Pendientes para próxima sesión
- Usuario puede pedir ajustes visuales de la grilla de pills (ej. 4 por fila) o de la matriz de puntajes.
- Proteger `calculo_ranking_auto.php` con sesión de admin (sugerido, no pedido).
