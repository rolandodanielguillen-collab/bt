# Informe — Ranking BT: bugs de sets/games y correcciones propuestas

Fecha: 2026-07-21 · Caso disparador: evento 13, categoría 3, final `id=3904`
Ganadores reales por sets: **3563177 / 4513326** (perdieron set 1, ganaron sets 2 y 3)

Snapshot para comparar: `BT/ranking_bt_snapshot_20260721.xlsx`
(una hoja por categoría con puntos por evento y total, más hoja `_Resumen`)

---

## Regla del negocio (BT)

- **Fase de grupos**: normalmente **un solo set** por partido. El sistema debe estar **preparado** para soportar 3 sets si algún organizador excepcionalmente lo carga, pero es el caso raro.
- **Eliminatoria (llaves)**: 3 sets, con super tie-break si va 1-1.
- **Ganador siempre por sets**, nunca por suma de games.
- **La fase de grupos NO impacta `_ranking` directamente**. Alimenta `tabla_auxiliar` → posiciones 1..5 del grupo → esas posiciones son las que después dan puntos (POS1..POS5) al ranking, o clasifican a la eliminatoria. Todo el resto del ranking (campeón, vice, semi, cuartos…) sale del bracket eliminatorio.

Esto acota el impacto de cada fix.

---

## 1. Bugs encontrados

### Bug 1 — RANKING declara ganador sumando games (`calculo_ranking_auto.php:138-141`)

```php
$r1 = abs($row['rusultado_equipo1']) + abs($row['resultado2_equipo1']) + abs($row['resultado3_equipo1']);
$r2 = abs($row['resultado_equipo2']) + abs($row['resultado2_equipo2']) + abs($row['resultado3_equipo2']);
if ($r1 === $r2) continue;
if ($r1 > $r2) { /* ci1 gana */ } else { /* ci2 gana */ }
```

Suma **games**, no sets. Dos problemas:

- Puede declarar ganador al que perdió por sets. Ej: 7-6, 1-6, 10-8 → games 18-20 → dice equipo 2, pero por sets ganó equipo 1.
- **Caso disparador**: final del ev 13 cat 3 (`id=3904`) fue 6-2, 4-6, 8-10. Games: 6+4+8=18 vs 2+6+10=18 → `$r1 === $r2` → `continue` → **nunca procesa la final** → no asigna puntos de campeón ni vice desde el bracket.

Los 100 pts que hoy tienen 3563177/4513326 son manuales o quedaron de una corrida vieja con sólo el set 1 cargado (6-2 → `$r1 > $r2` → asignaba campeón al equipo equivocado). El algoritmo actual no sostiene el resultado real.

**Este es EL bug crítico. Impacta directamente los puntos que ven los jugadores.**

### Bug 2 — Bracket del front pinta sólo UN set (`todos.vs.todos.php:2044-2054`)

En el tab "Bracket eliminatorio", cada card muestra un solo par de números:

```php
$scoreShowA='-';$scoreShowB='-';
if($esByeBye):
    ...
elseif($bp['r11']>0||$bp['r12']>0): $scoreShowA=$bp['r11']; $scoreShowB=$bp['r12'];
elseif($bp['r21']>0||$bp['r22']>0): $scoreShowA=$bp['r21']; $scoreShowB=$bp['r22'];
elseif($bp['r31']>0||$bp['r32']>0): $scoreShowA=$bp['r31']; $scoreShowB=$bp['r32'];
endif;
```

Es cascada: si hay set 1, muestra sólo set 1. La final del ev 13 cat 3 se ve en el bracket como **6-2**, y la card marca correctamente al equipo perdedor de ese primer set como ganador general (porque el conteo real de sets sí es correcto en las líneas 2023-2026). O sea: **el bracket dice "ganó el equipo 2" pero muestra "6-2"**, lo cual visualmente contradice al ganador → parece que el sistema no reconoce el partido.

Esto es lo que reporta el usuario: "por más que se carguen los 3 sets no aparecen en el front".

### Bug 3 — Fase de grupos ignora set 2 en clasificación (3 archivos)

`logica/calcular_clasificacion.php` líneas 26-40
`logica/cargar.auxiliar.v2-parte2.php` líneas 49-72
`logica/todos.vs.todos.php` líneas 235-243 y 264-265

El `SELECT` no incluye `resultado2_equipo1/2` y el conteo de sets sólo mira set 1 y set 3:

```php
if($r1a>0||$r1b>0){$r1a>$r1b?$sA++:$sB++;}
if($r3a>0||$r3b>0){$r3a>$r3b?$sA++:$sB++;}   // salta directo al set 3
```

**Impacto real acotado**: fase de grupos normalmente es 1 set, así que este bug no dispara en el 99% de los casos. Sólo muerde cuando alguien excepcionalmente carga 3 sets en un partido de grupo — ahí el partido queda con `sA==sB` (1-1 contando sólo sets 1 y 3), no suma ganado y no aporta al ordenamiento de la tabla_auxiliar. Y por consiguiente el ganador no propaga a la eliminatoria.

**Prioridad**: media. Es una salvaguarda, no un bug que impacte partidos históricos conocidos, pero conviene blindarlo para que el día que pase no rompa silenciosamente.

**Otras vistas ya lo hacen bien**: el precálculo del modal de clasificación (`todos.vs.todos.php:1836-1839`) y el conteo del bracket (`todos.vs.todos.php:2023-2026`) ya cuentan los 3 sets correctamente. Sólo faltan los 3 archivos citados arriba.

### Bug 4 — Cosmético, TABs en el UPDATE (`todos.vs.todos.php:112-120`)

Nombres de campo con TAB pegado:

```php
$R.="resultado2_equipo1\t=".abs(...).",";
```

MySQL lo tolera hoy, pero limpiar al tocar el archivo.

---

## 2. Correcciones propuestas (mínimas)

### Fix 1 — RANKING: contar sets, no games (CRÍTICO)

Reemplazar el bloque `calculo_ranking_auto.php:138-141` por:

```php
$sA = 0; $sB = 0;
if ($row['rusultado_equipo1']  > 0 || $row['resultado_equipo2']  > 0) { $row['rusultado_equipo1']  > $row['resultado_equipo2']  ? $sA++ : $sB++; }
if ($row['resultado2_equipo1'] > 0 || $row['resultado2_equipo2'] > 0) { $row['resultado2_equipo1'] > $row['resultado2_equipo2'] ? $sA++ : $sB++; }
if ($row['resultado3_equipo1'] > 0 || $row['resultado3_equipo2'] > 0) { $row['resultado3_equipo1'] > $row['resultado3_equipo2'] ? $sA++ : $sB++; }
if ($sA === $sB) continue;
$ganaEquipo1 = $sA > $sB;
```

Reemplazar los `if ($r1 > $r2)` que vienen después por `if ($ganaEquipo1)`.

Post-fix: correr `https://bt.com.py/calculo_ranking_auto.php?evento=13&debug=1` y diffear contra `ranking_bt_snapshot_20260721.xlsx`. Cualquier movimiento son partidos que antes se resolvían mal.

### Fix 2 — Bracket del front: mostrar los 3 sets (CRÍTICO para lo visual)

`todos.vs.todos.php:2044-2054`. Reemplazar la cascada por concatenación:

```php
$sets_bp = [];
if($bp['r11']>0||$bp['r12']>0) $sets_bp[] = [$bp['r11'],$bp['r12']];
if($bp['r21']>0||$bp['r22']>0) $sets_bp[] = [$bp['r21'],$bp['r22']];
if($bp['r31']>0||$bp['r32']>0) $sets_bp[] = [$bp['r31'],$bp['r32']];

if($esByeBye){
    $scoreShowA = $ganaA ? '1' : '0';
    $scoreShowB = $ganaA ? '0' : '1';
} elseif(empty($sets_bp)){
    $scoreShowA = '-'; $scoreShowB = '-';
} else {
    $scoreShowA = implode(' ', array_column($sets_bp, 0));
    $scoreShowB = implode(' ', array_column($sets_bp, 1));
}
```

Así la card de la Final del ev 13 cat 3 muestra "6 4 8" arriba y "2 6 10" abajo — y visualmente coincide con el marcado del ganador. Con esto se resuelve el "no aparece en el front".

(Si la columna del bracket queda estrecha para 3 números, se ajusta el CSS `.bk-sc { min-width: … }` — es CSS puro, no cambia lógica.)

### Fix 3 — Fase de grupos: soportar 3 sets como salvaguarda

Aplicar el mismo patrón (agregar `resultado2_equipo1/2` al SELECT y al conteo) en los 3 archivos:

- `logica/calcular_clasificacion.php`
- `logica/cargar.auxiliar.v2-parte2.php`
- `logica/todos.vs.todos.php` (bloque tabla_auxiliar en el POST, líneas 235-265)

Es una salvaguarda por si algún organizador carga 3 sets en fase de grupos. No cambia el comportamiento del caso normal (partido a 1 set). Y para calcular la diferencia de games (`g+`/`g-`) también sumar el set 2:

```php
$gA = $r1a + $r2a + $r3a; $gB = $r1b + $r2b + $r3b;
```

### Fix 4 — Helper único (opcional, en pasada de limpieza)

Los 4 archivos hacen el mismo cálculo. Cuando pase la tormenta, extraer en `logica/funciones.php`:

```php
function sets_ganados(array $r): array {
    $sA = $sB = 0; $gA = $gB = 0;
    foreach ([['rusultado_equipo1','resultado_equipo2'],
              ['resultado2_equipo1','resultado2_equipo2'],
              ['resultado3_equipo1','resultado3_equipo2']] as [$a,$b]) {
        $ra = abs((int)($r[$a] ?? 0)); $rb = abs((int)($r[$b] ?? 0));
        if ($ra > 0 || $rb > 0) { $ra > $rb ? $sA++ : $sB++; }
        $gA += $ra; $gB += $rb;
    }
    return ['sA'=>$sA, 'sB'=>$sB, 'gA'=>$gA, 'gB'=>$gB, 'gana1'=>$sA>$sB, 'empate'=>$sA===$sB];
}
```

Un solo lugar para tocar el día que cambien las reglas del BT.

### Fix 5 — TAB en el UPDATE (`todos.vs.todos.php:112-120`)

Sacar los `\t` cuando se abra el archivo por otro motivo. No urge.

---

## 3. Orden sugerido de trabajo

1. Backup en VPS: `.bak-20260721` de los 4 archivos que se van a tocar.
2. **Fix 1 (ranking)** — el que le devuelve el puntaje correcto a los jugadores.
3. Correr `calculo_ranking_auto.php?evento=13&debug=1`.
4. Diffear contra `ranking_bt_snapshot_20260721.xlsx`.
5. **Fix 2 (bracket front)** — el visual que hace que el usuario "vea" la final resuelta.
6. Verificar en el front público del evento 13 cat 3 que la Final del bracket muestra los 3 sets y con ganador correcto.
7. **Fix 3 (salvaguarda grupos)** — si querés dejarlo blindado. Muy poco riesgo, muy poco impacto en la operación diaria.
8. Recalcular ranking de los 13 eventos si el diff del evento 13 salió bien. Guardar `_ranking` viejo como `_ranking_bak_20260721`.
9. Fix 4 y Fix 5 en una pasada aparte, sin apuro.

## 4. Lo que NO hay que tocar

- Confronto directo en `tabla_auxiliar` — ya se arregló el 2026-07-18 (`project_ranking_confronto_fix.md`).
- Propagación por bracket (`todos.vs.todos.php:172-176`) — ya cuenta bien los 3 sets.
- El display de cada partido en la vista pública (`todos.vs.todos.php:1544-1583`) — ya muestra los 3 sets bien y marca ganador correcto.
- `mostrar-ranking.php` — sólo lee `_ranking`. Cuando el cálculo esté bien, la vista se corrige sola.
