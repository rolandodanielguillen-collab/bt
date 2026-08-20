-- ============================================================================
-- Diagnóstico del numerito del ícono (badge) — 20-ago-2026
-- ============================================================================
-- En un iPhone con "Contadores" activo el badge no aparecía aunque el push
-- llegara. La app ahora informa `allowsBadge` al registrar el teléfono, así se
-- puede ver desde el servidor si el permiso está realmente concedido en vez de
-- pedirle al usuario que revise los ajustes. NULL = versión vieja de la app.
-- ============================================================================

ALTER TABLE `_app_dispositivos`
  ADD COLUMN `badge_ok` TINYINT(1) NULL AFTER `plataforma`;
