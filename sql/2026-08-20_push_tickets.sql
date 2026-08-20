-- ============================================================================
-- Push fase 3 — tickets de Expo para revisar recibos (20-ago-2026)
-- ============================================================================
-- Expo acepta el envío (ticket "ok") aunque el token esté muerto; el error
-- DeviceNotRegistered recién aparece en el RECIBO, minutos después. Sin esto,
-- cada reinstalación de la app deja un token zombi que infla `enviados` y el
-- KPI "Teléfonos con push". Los recibos se revisan al comienzo del siguiente
-- envío (bt_app_push.inc.php). Idempotente, sólo agrega.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `_app_push_tickets` (
  `id`         CHAR(36)     NOT NULL,           -- uuid del ticket de Expo
  `expo_token` VARCHAR(255) NOT NULL,
  `creado`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_creado` (`creado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
