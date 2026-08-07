# BT — Log de acciones del admin (2026-08-07, LIVE)

Estado: **LIVE y verificado E2E.** Antes NO había auditoría de acciones
(`sc_log` es del ScriptCase viejo y murió en junio; `_p_usuarios_logs` está en 0).

## Tabla `_bt_log`
`id, fecha, actor, origen, accion, detalle, ip` — MyISAM utf8mb4_unicode_ci,
índices por fecha, accion y actor. Creada por SQL directo en el VPS.

## Captura: 4 puntos de entrada, no 50 acciones
`bt_log.inc.php` (archivo nuevo) con `bt_log($db, $actor, $origen, $accion, $datos)`,
llamado UNA vez por entrada, antes del dispatch:
- `tvt_api.php` → origen `admin` (cubre las ~50 acciones del admin de una)
- `interclubes_resultados.php` → origen `resultados`
- `interclubes_sorteo.php` → origen `sorteo`
- `interclubes.php` (form del club) → origen `form-club`, actor = "club: NOMBRE"

**Solo escrituras**, y el filtro es por VERBO inicial de la acción
(`BT_LOG_VERBOS`: crear/editar/eliminar/borrar/guardar/definir/cambiar/generar/
regenerar/asignar/quitar/limpiar/toggle/agregar/actualizar/promover/en_juego).
Por eso una acción nueva queda cubierta sola, sin mantener listas. Las lecturas
(kpis, estado, eventos, categorias…) no entran: si no, son miles de filas por día.
El detalle guarda los params en JSON, con pass/password/clave/token enmascarados
y los valores largos cortados a 200. El INSERT va en try/catch: **el log nunca
puede tumbar la acción que registra**.

## Pantalla (SOLO superadmin)
Ítem "Log" en el sidebar (grupo Administración) + `pg-log`, ambos dentro de
`if ($adminTipo === 'superadmin')`. Acción `log` en tvt_api con el mismo guard
por `$_SESSION['admin_tipo']` (no alcanza con esconder el ítem del menú).
Un solo buscador que va por LIKE contra actor + accion + detalle + origen
(buscás un CI y salen todos los toques a ese jugador), filtro de origen, rango
de fechas, 50 por página con el mismo paginador que Jugadores.

## Verificación
- `php -l` en los 6 archivos, JS de tvt_admin_v2 e interclubes_resultados con
  node --check, 20 asserts del filtro de verbos.
- E2E real: POST al form del club con `accion=editar_prueba_log` → quedó la fila
  `club: En lo de chiqui Beach | form-club | editar_prueba_log | {...} | ip`.
  Fila de prueba borrada después.
- Las 4 entradas responden 200 tras el deploy.
- Backups VPS: `.bak-20260807log`.

## Si hace falta después
No hay purga automática ni exportación a Excel (se dejaron afuera a propósito).
