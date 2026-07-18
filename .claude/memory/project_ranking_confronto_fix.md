---
name: ranking-confronto-fix
description: Fix ranking cálculo grupo único - confronto directo como desempate (antes solo ORDER BY ganados/puntos/g+)
metadata:
  type: project
---

# Fix Ranking - Confronto Directo (2026-07-18)

## Problema
El cálculo de ranking para categorías de grupo único usaba `ORDER BY ganados DESC, puntos DESC, g+ DESC` sin considerar confronto directo. Esto causaba asignación incorrecta de puntos cuando 2 equipos tenían mismas victorias pero diferente resultado en enfrentamiento directo.

## Caso concreto
Evento 12, categoría 20: Yeruti/Gabriela (CI 6004049/7191604) ganaron por confronto directo a Montse/Elen (CI 5827292/6321732), pero el ranking les daba 80 pts (vice) en vez de 100 (campeón).

## Fix aplicado
Ambos archivos parcheados con lógica de confronto directo (misma que `cargar.auxiliar.v2.php`):
- `/home/bt.com.py/public_html/calculo_ranking_auto.php` (PASO 3)
- `/home/bt.com.py/public_html/logica/calculo.ranking.php` (sección GRUPO ÚNICO)

## Algoritmo de clasificación correcto
1. Ganados DESC
2. Si 2 empatados → confronto directo (quién le ganó a quién)
3. Si 3+ empatados → SG (saldo games), sub-empates por confronto
4. Fallback → G+ (games a favor)

## Backups
- `calculo_ranking_auto.php.bak-20260718`
- `logica/calculo.ranking.php.bak-20260718`

## URLs
- Por evento: `https://bt.com.py/calculo_ranking_auto.php?evento=ID&debug=1`
- Global legacy: `https://bt.com.py/logica/calculo.ranking.php`

## Estado: COMPLETO
- Fix manual evento 12 cat 20: hecho (swap 100↔80)
- calculo_ranking_auto.php: parcheado y verificado
- calculo.ranking.php (legacy): parcheado y verificado
- Syntax check: OK ambos
