---
name: session-06jul2026
description: "Sync desde memoria raíz: estado completo del trabajo BT al 2026-07-03, inscripción fix CI auto-lookup"
metadata:
  type: project
---

## Estado al 2026-07-03 (sincronizado desde memoria raíz)

Todo lo grande está completado. Trabajo realizado:

### CRUD Puntajes (UI Matriz — 2026-07-02)
- Rediseñado como matriz: filas = categorías, columnas = rondas, celdas = inputs numéricos
- Header y primera columna sticky, celdas con valor resaltadas
- ETIQ_ORDER: `[1,9,2,3,4,6,8,11,12,13,14,15]` + ETIQ_SHORT para headers
- API: `puntajes_evento`, `guardar_puntajes_categoria`, `copiar_puntajes`
- Backup: `tvt_admin_v2.php.bak-puntajes-ui`

### Recálculo Ranking Evento 12 (2026-07-02)
- 228 registros recalculados con fuente primaria `_relacion_etiquetas_eventos`
- Cats estándar: min 30 pts (Grupo=30, Cuartos=40, Semi=60, Vice=80, Campeón=100)
- Cats mixtas: min 15 pts (Grupo/Cuartos/Semi=15, Vice=30, Campeón=50)
- Caso Luis Segovia (5971512): resuelto

### Fix CI Auto-Lookup Inscripción (2026-07-03)
- **Bug 1**: `.htaccess` sin `[QSA]` — query params descartados en rewrite
- **Bug 2**: Validación FEPARPA heredada de padelsys.com — disabled con `if(false)`
- Backups: `.htaccess.bak-20260703`, `script.inc.php.bak-20260703`

### Pendientes
- Proteger `calculo_ranking_auto.php` con sesión (sugerido, no pedido)
- Bug menor: API `/api/api.php?url=usuariosolo` devuelve `"ci":"hombre"` (copia sexo en campo ci)
