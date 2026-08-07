# Admin BT — editar_jugador rompía sin respuesta + estado de los logs (2026-08-07)

Estado: **fix LIVE (commit 5fdaf40), 7 jugadores activados.**

## El bug: "Failed to execute 'json' on 'Response': Unexpected end of JSON input"
Ese mensaje es del navegador cuando `res.json()` recibe cuerpo vacío — NO es el
error real. El real estaba en `/home/bt.com.py/logs/php_log`:
`Incorrect date value: '' for column _p_usuarios.fecha_nacimiento en tvt_api.php:1272`.

`editar_jugador` escribía TODOS los campos como string. Con
`STRICT_TRANS_TABLES`, mandar `''` a una columna `date` o `enum` (sexo, estado)
es error → mysqli lanza excepción → script muerto sin cuerpo. Le pasaba a
cualquier jugador con fecha de nacimiento o sexo vacíos. `crear_jugador` nunca
falló porque ya salteaba los vacíos.

Fix (tvt_api.php):
- `editar_jugador`: vacío en fecha_nacimiento/sexo/estado → `NULL`; vacío en
  ci/tipo (NOT NULL) → se ignora el campo.
- `set_exception_handler` arriba de todo: cualquier excepción de cualquiera de
  las ~50 acciones devuelve JSON + error_log, en vez de dejar al navegador sin
  respuesta. Backup `tvt_api.php.bak-20260807c`.

Reproducido en la base real antes de tocar nada (`UPDATE ... fecha_nacimiento=''`
→ el mismo error; con NULL pasa).

## Jugadores inactivos: eran 7, quedaron 0
Activados a pedido del usuario: ids **1, 97, 216, 431, 440, 487, 494**
(Rolando Guillen, Roney Alonso, Vania Villalba, Hernan Zacarias, David Ramirez,
Diego Vera, Anderson Müller). Revertir:
`UPDATE _p_usuarios SET estado='inactivo' WHERE id IN (1,97,216,431,440,487,494)`.
Recordar: `estado` NO filtra nada en el flujo interclubes.

## Sistema de log: no hay auditoría de acciones
- `sc_log` — 26.892 filas del sistema viejo ScriptCase (application =
  form_todosvstodos, grid_*, menu_cliente). Última escritura junio 2026. El único
  INSERT vivo está en `logica/todos.vs.todos.php:136`. tvt_admin_v2/tvt_api e
  interclubes NO escriben ahí.
- `_p_usuarios_logs` — tabla espejo de `_p_usuarios` (misma estructura), **0 filas**,
  nunca se usó. Está lista para versionar cambios de jugador si se quiere.
- En disco: `logs/php_log` (errores PHP, rotado semanal) + access/error de Apache.
  Es log de errores, no de acciones.

Por eso no se puede saber quién desactivó a esos jugadores ni cuándo.
