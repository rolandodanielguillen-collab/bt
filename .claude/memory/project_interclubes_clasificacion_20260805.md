---
name: bt-interclubes-clasificacion-games
description: "Clasificación interclubes por saldo de games (desempate ±1) — LIVE y verificado con datos reales, commit 9cb7c81"
metadata:
  type: project
---

# Interclubes — Clasificación por saldo de games (2026-08-05, noche)

## AGREGADO mismo día: nombres cortos (commit 61266c3, LIVE)
Jugadores se muestran como primer nombre + primer apellido en todos los
partidos (público, admin, selects, suplentes). Helper `ic_sql_nombre_corto()`
en interclubes.functions.php (SUBSTRING_INDEX sobre nombre y apellido por
separado — NO se puede partir el CONCAT ya armado). El form de inscripción
del club (interclubes.php) conserva nombre completo a propósito.
Backups `.bak-20260805-nombres` en VPS. Verificado en vivo: VÍCTOR ALFONSO,
MARCELO PEREIRA, LUCIO VARGAS.

Estado: **COMPLETO — LIVE en bt.com.py, verificado con el grupo real, commit 9cb7c81 pusheado.**

## Regla implementada (confirmada por el usuario)
- 1 punto por serie ganada.
- Desempate: **saldo de games global**; el 3er partido de una serie
  (es_desempate=1) vale **±1 al saldo** (no suma games reales ni sets).
- Confronto directo SOLO si persiste el empate exacto (pts y saldo iguales);
  último recurso posición de sorteo.
- Verificación con grupo real (cat E masc G1): Arena +6 (1°), Chiqui -2 (2°),
  Lujini -4 (3°) — usuario confirmó el -2 de Chiqui (su cuenta inicial decía 0).

## Archivos tocados (local + VPS)
- `interclubes.functions.php` — ic_stats (rama es_desempate ±1) + ic_posiciones
  (orden nuevo: pts → saldo → mini-liga pts/saldo → sorteo).
- `logica/interclubes.llaves.inc.php` — modal público: Club | Series | Games | Pts,
  saldo firmado con color (.saldo-pos/.saldo-neg) + nota al pie del criterio.
- `interclubes_resultados.php` — mismo cambio en el modal admin (JS, usa
  p.gamesF/p.gamesC que ya venían en el JSON de action=estado).

## Deploy
Receta de siempre: backups `.bak-20260805-games` en VPS (NO pisar los
`.bak-20260805` de la sesión de la tarde) → scp a /tmp → php -l → cp.
Verificado en vivo con curl (`?evento=sha1(15)`): columna Games + nota
renderizando, ambos grupos.

## Side-effect bueno
`ic_autogenerar_llaves` usa ic_posiciones → el 1° y 2° que pasan a semis
salen por el criterio correcto automáticamente.

## Check reproducible
`scratchpad/test_clasificacion.php` (sesión 82eec2e2) — asserts del ejemplo
real contra ic_posiciones. Si se necesita de nuevo: partidos a 1 set con los
games del relato + 2 desempates.
