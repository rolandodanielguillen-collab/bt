---
name: sesion-2026-07-03
description: "Fix CI auto-lookup en formulario inscripción: .htaccess QSA + FEPARPA disabled"
metadata:
  type: project
---

# Sesión 2026-07-03 — Fix CI Lookup Inscripción

## Problema
Al escribir CI en el formulario de inscripción (`/torneo-10mafecha&2`), no se llenaban los datos del jugador.

## Causa raíz (2 bugs)

### Bug principal — `.htaccess` sin `[QSA]`
- `RewriteRule ^torneo-(.*)$ inscripcion.php?url=$1` no preservaba query string
- El JS hacía `fetch('/torneo-10mafecha?action=buscar_ci&ci=123')` → Apache descartaba `action` y `ci`
- **Fix**: agregado `[QSA]` a la regla
- Backup: `/home/bt.com.py/public_html/.htaccess.bak-20260703`

### Bug secundario — Validación FEPARPA heredada de padelsys.com
- `script.inc.php` tenía `if($idOrganizador==1)` que activaba checks de federación
- En bt.com.py, organizador 1 = "BEACH TENNIS" (no FEPARPA)
- **Fix**: condición cambiada a `if(false /* FEPARPA disabled */)`
- Backup: `/home/bt.com.py/public_html/script.inc.php.bak-20260703`
- Nota: este fix solo afecta `buscarJugador()` del v1, que no se usa en inscripcion-v2

## Arquitectura del formulario v2
- Input CI: `id="p1_ci"` con botón "Buscar" → `buscarCI(1)`
- Handler: `inscripcion.php` línea 110, `?action=buscar_ci&ci=...`
- Busca en `_p_usuarios` primero, fallback a `_ci_py` (padrón)
- Campos: `p1_nombres`, `p1_apellidos`, `p1_celular`, `p1_email`, `p1_ciudad`

## Bug menor pendiente
- API `/api/api.php?url=usuariosolo` devuelve `"ci":"hombre"` (alias SQL incorrecto, copia sexo en ci)

Detalle completo en memoria raíz: `project_bt_padelsys_admin.md`
