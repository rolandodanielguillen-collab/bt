# Informe — criterio de puntos del ranking duplicado

Fecha: 2026-08-21 · Alcance: la LECTURA/agregación de `_ranking` (no el cálculo que escribe los puntos)

## Resumen

No son 3 copias: son **4 copias vivas** del mismo criterio (más 2 del criterio de trofeos).
Y ya **divergieron**: el panel de administración muestra un ranking distinto al del sitio y
al de la app desde el fix del 02/06/2026.

Medido contra producción (circuito 1, script solo-lectura): **653 filas, 164 jugadores,
12.460 puntos** de diferencia entre el criterio del admin y el del sitio.

## Las copias

| # | Archivo | Quién lo ve | Criterio |
|---|---------|-------------|----------|
| 1 | `logica/mostrar-ranking.php` — 3 veces en el mismo archivo: `:126-129` (totales), `:222-226` (Top 10), `:771-775` (detalle por fecha) | ranking público del sitio + export CSV | **NUEVO** (canónico) |
| 2 | `bt_app_api.php:761-905` (`action=ranking`) | la app móvil | NUEVO (re-implementado) |
| 3 | `tvt_api.php:490-639` (criterio en `:581`) | admin v2, pantalla Ranking | **VIEJO** |
| 4 | `logica/grafico-llaves.incTMP.php:445-493` (criterio en `:490`, vía `grafico-llaves-v2.php`) | siembra de llaves con `?rk=<clave>` | **VIEJO** |

Criterio **nuevo** (el que manda hoy, marcado en el código como `FIX 02/06/2026: puntos puros, sin restar padre`):

```php
$ptosMixto += rk_puntos($evId, $padreCat, $ci);
$ptosHijo  += rk_puntos($evId, $acategoria, $ci);   // puros
// total = $ptosMixto + $ptosHijo
```

Criterio **viejo** (copias 3 y 4):

```php
$esGU = /* la categoría del evento tiene etiquetas POS1..POS5 */;
$ptosMixto += $ppEvt;
$ptosHijo  += $esGU ? $phAcum : max(0, $phAcum - $ppEvt);   // resta el padre
```

El encabezado de `tvt_api.php:483-489` dice *"Replica la lógica EXACTA de mostrar-ranking.php"*.
Es falso desde junio: la web se arregló y el admin quedó atrás. Ese comentario es justamente
la trampa que hace que nadie lo revise.

### Duplicado paralelo: trofeos (hoy sí consistentes)

`jugador.inc.php:114-168` y `bt_app_api.php:1251-1277` cuentan campeón/finalista comparando
`_ranking.puntos` contra `_relacion_etiquetas_eventos` (fallback `_ranking_config`). Misma
lógica escrita dos veces. Hoy coinciden; es la próxima que se va a desincronizar.

### Lo que NO está duplicado

El cálculo que **escribe** en `_ranking` vive sólo en `logica/calculo.ranking.php` (servidor).
Ninguna de las opciones de abajo lo toca, así que **no se recalculan puntos** y las categorías
4/5/9/10 congeladas quedan como están.

## Impacto medido

Script solo-lectura sobre `bt_com_py` (circuito 1), comparando las dos fórmulas fila por fila:

```
filas con divergencia: 653 | jugadores: 164 | puntos de diferencia: 12460
ev1 cat3 ci5971512 hijo=40 padre=50  → web: 90   admin: 50   (resta 40)
ev1 cat3 ci5990970 hijo=30 padre=15  → web: 45   admin: 30   (resta 15)
```

Consecuencias hoy:

1. Quien mira la pantalla Ranking del admin cree estar viendo el ranking oficial y ve otros números.
2. La siembra de llaves (`grafico-llaves-v2.php`) ordena las parejas por un criterio que ya no
   usa nadie más → el orden de siembra sale de una fórmula muerta.
3. Cualquier cambio futuro de criterio hay que aplicarlo en 4 lugares; ya se demostró que no pasa.

## Opciones de arreglo

### Opción A — Parche de 5 líneas (sincronizar y listo)

Reemplazar en `tvt_api.php` y `grafico-llaves.incTMP.php` la línea del criterio viejo por la
del nuevo, borrar la consulta de Grupo Único (queda sin uso) y corregir los comentarios.

- Esfuerzo: **~20 min**. Riesgo: bajo, no toca la web ni la app.
- Deja: 4 copias sincronizadas hoy, misma trampa mañana.
- Bonus: se van 2 queries por jugador/evento en el admin (la de POS1..POS5 y una de padre).

### Opción B — Extraer `bt_ranking.functions.php` (recomendada)

Un archivo con la fuente única:

```php
rk_cargar($circuito)                    // precarga _ranking + inscripciones (4 queries)
rk_total($ci, $catHijo, $catPadre)      // → ['ptosMixto','ptosHijo','total']
rk_jugadores_cat($catHijo, $catPadre)   // ya existe en mostrar-ranking.php, se muda tal cual
```

`mostrar-ranking.php` lo incluye (hoy ya tiene esas funciones adentro: es mudarlas), `tvt_api.php`
y `bt_app_api.php` lo incluyen y borran su implementación, `grafico-llaves.incTMP.php` llama a
`rk_total()`.

- Esfuerzo: **~2 h** + verificación.
- Elimina la clase de bug completa, no sólo esta instancia.
- Efecto colateral bueno: la app hereda la precarga de 4 queries de la web y se le puede sacar
  el N+1 con caché en disco de 5 minutos (`bt_app_api.php:766`) — el ranking pasaría a ser
  instantáneo y siempre fresco.
- Riesgo real: `mostrar-ranking.php` es una página en producción con `include` posicionales; el
  orden de inclusión importa. Se mitiga con la verificación de abajo.

### Opción C — Tabla resumen en MySQL (el arreglo grande)

`_ranking_totales` (circuito, categoria, ci, ptos_mixto, ptos_hijo, total), recalculada al cerrar
un evento y con un botón de "recalcular" en el admin. Las 4 lecturas pasan a ser un `SELECT ... ORDER BY total DESC`.

- Esfuerzo: **~1 día**. El criterio queda escrito una sola vez, en el recalculador.
- Mata el N+1 en todos lados y el ranking deja de depender de recorrer inscripciones.
- Contra: agrega estado que se puede quedar viejo (si alguien edita `_ranking` a mano y no
  recalcula, todo el sitio miente). Hoy no hay presión de performance que lo justifique:
  la web ya baja a 4 queries y la app tiene caché.

### Recomendación

**A ahora** (la divergencia está viva y son 20 minutos), **B en la próxima ventana**. C sólo si
más adelante el ranking se vuelve lento o aparece un quinto consumidor.

> **21-ago: se aplicó la opción B.** El resultado, al final del documento.

## Cómo verificar cualquiera de las tres

1. Antes de tocar: bajar el CSV oficial (`ranking_export.php?url=<slug>`) y guardarlo.
2. Aplicar el cambio.
3. Bajar el CSV de nuevo: **debe ser byte por byte igual** (la web es la referencia; ninguna
   opción cambia sus números).
4. Comparar 5 jugadores en las 3 superficies (sitio, app `action=ranking`, admin) — con A/B/C
   los tres totales tienen que coincidir; hoy no coinciden.
5. Para la siembra: abrir `grafico-llaves-v2.php?...&rk=<clave>` de un evento cerrado y
   confirmar que el orden de parejas cambia como se espera (va a cambiar: hoy usa la fórmula vieja).

## Aparte (fuera de alcance, para anotar)

`logica/grafico-llaves.incTMP.php:421` tiene la clave de acceso escrita en el código
(`$RK_SECRET = 'c61f...'`). Con ese parámetro en la URL se ven los puntos de cada pareja. No lo
toqué; si se quiere, va a `/home/bt.com.py/.bt_app.env` como el resto de la config.


---

# CERRADO — 21-ago-2026: se aplicó la opción B

Se creó **`bt_ranking.functions.php`** y los cuatro consumidores pasaron a usarlo. Las cuatro
copias del criterio dejaron de existir: ahora hay una sola.

| Archivo | Antes | Ahora |
|---|---|---|
| `bt_ranking.functions.php` | — | **la única implementación** (`bt_rank_cargar`, `bt_rank_total`, `bt_rank_detalle`, `bt_rank_jugadores_cat`) |
| `logica/mostrar-ranking.php` | criterio escrito 3 veces + precarga propia | llama al módulo (−116 líneas) |
| `bt_app_api.php` | copia propia con N+1 | llama al módulo |
| `tvt_api.php` | **fórmula vieja** | llama al módulo |
| `logica/grafico-llaves.incTMP.php` | **fórmula vieja** | llama al módulo |

## Verificación (esto es lo que asegura que no se perdió nada)

1. **Baseline antes de tocar**: se guardó el HTML completo del ranking público y el JSON del
   endpoint de la app.
2. **Prueba sin tocar producción**: la versión nueva se subió como copia paralela
   (`ranking_test_nuevo.php`) y se comparó contra el baseline → **idéntica salvo una etiqueta
   `og:url`**, que dependía del nombre del archivo de prueba.
3. **Después de desplegar**: `ranking.php` en vivo quedó **byte por byte igual al baseline**.
4. **Export CSV** (que usa la misma función): sigue saliendo con los mismos números.
5. **App**: ningún jugador cambió de total ni de desglose. Cambió lo que tenía que cambiar:
   - se eliminó un **jugador duplicado** (CI 6560193 aparecía dos veces, como "Walber Souza" y
     "Walber Castro", y su puntaje se contaba dos veces en el Top 10);
   - 111 posiciones se movieron **entre jugadores empatados** y 155 desgloses quedaron ordenados
     por fecha ascendente: los dos casos ahora siguen el mismo criterio de desempate que el sitio.
6. **Admin**: comparado jugador por jugador contra la app en la categoría 3 → **55 de 55 iguales**.
   Antes esa pantalla mostraba otros números.
7. **Siembra de llaves**: la página carga sin errores y ahora muestra los mismos puntos que el
   ranking (940, 930, 900, 715…), no los de la fórmula muerta.

**No se tocó un solo dato**: todo esto es código de lectura. `_ranking` no se recalculó ni se
escribió, y las categorías 4/5/9/10 congeladas siguen exactamente como estaban.

## Respaldos en el servidor

`logica/mostrar-ranking.php.bak-20260821`, `bt_app_api.php.bak-20260821`,
`tvt_api.php.bak-20260821b`, `logica/grafico-llaves.incTMP.php.bak-20260821`.

## Gancho para la próxima vez

El día que cambie el criterio de puntos, se toca **un solo archivo**. Y si aparece un quinto
consumidor, que llame a `bt_rank_total()` en vez de copiar la cuenta.

Queda pendiente, aparte y anotado: `$RK_SECRET` sigue escrito en el código de
`logica/grafico-llaves.incTMP.php:421`.
