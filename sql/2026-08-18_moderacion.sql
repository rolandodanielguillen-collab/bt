-- ============================================================================
-- Comunidad de la app BT — moderación, bloqueos, denuncias y avisos push
-- ============================================================================
-- Fecha: 2026-08-18. SÓLO AGREGA tablas `_app_*` (misma regla que 2026-08-10_social.sql):
-- no altera nada del sitio. Requisito de Google Play / App Store para contenido
-- de usuarios: términos aceptados, denunciar, bloquear, moderación real.
-- Backup antes de correr. Idempotente (IF NOT EXISTS / INSERT IGNORE).
-- ============================================================================

-- Estado "de app" de cada jugador (el padrón sigue siendo _p_usuarios).
CREATE TABLE IF NOT EXISTS `_app_jugadores` (
  `ci`                VARCHAR(20)  NOT NULL,
  `terminos_version`  VARCHAR(20)  NULL,
  `terminos_en`       DATETIME     NULL,
  `suspendido_hasta`  DATETIME     NULL,
  `suspendido_motivo` VARCHAR(255) NULL,
  `suspendido_por`    VARCHAR(100) NULL,
  `eliminado_en`      DATETIME     NULL,
  `creado`            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ci`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bloqueos: A no ve el contenido de B ni recibe sus mensajes.
CREATE TABLE IF NOT EXISTS `_app_bloqueos` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ci`           VARCHAR(20)  NOT NULL,
  `ci_bloqueado` VARCHAR(20)  NOT NULL,
  `creado`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_par` (`ci`, `ci_bloqueado`),
  KEY `idx_bloqueado` (`ci_bloqueado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Denuncias: las revisa el moderador en tvt_admin_v2.php (Moderación).
CREATE TABLE IF NOT EXISTS `_app_denuncias` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ci_denunciante` VARCHAR(20)  NOT NULL,
  `tipo`           ENUM('post','comentario','mensaje','dupla','jugador') NOT NULL,
  `ref_id`         INT UNSIGNED NULL,
  `ci_denunciado`  VARCHAR(20)  NULL,
  `motivo`         VARCHAR(40)  NOT NULL,
  `detalle`        VARCHAR(500) NULL,
  -- Copia del texto al momento de denunciar: si el autor lo edita/borra, el moderador igual lo ve.
  `texto`          TEXT         NULL,
  `creado`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `estado`         ENUM('pendiente','resuelta','descartada') NOT NULL DEFAULT 'pendiente',
  `accion`         VARCHAR(40)  NULL,
  `resuelto_por`   VARCHAR(100) NULL,
  `resuelto_en`    DATETIME     NULL,
  PRIMARY KEY (`id`),
  KEY `idx_estado` (`estado`, `creado`),
  KEY `idx_denunciado` (`ci_denunciado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Qué avisos push están activos (los edita el admin en "Avisos app").
CREATE TABLE IF NOT EXISTS `_app_push_config` (
  `tipo`   VARCHAR(30)  NOT NULL,
  `titulo` VARCHAR(80)  NOT NULL,
  `activo` TINYINT(1)   NOT NULL DEFAULT 1,
  `orden`  TINYINT      NOT NULL DEFAULT 0,
  PRIMARY KEY (`tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `_app_push_config` (`tipo`, `titulo`, `orden`) VALUES
  ('partido_en_juego',      'Tu partido está en juego',                    1),
  ('partido_proximo',       'Tu partido está por empezar',                 2),
  ('resultado_cargado',     'Se cargó el resultado de tu partido',         3),
  ('inscripciones_abren',   'Se abrieron las inscripciones de una fecha',  4),
  ('inscripciones_cierran', 'Las inscripciones cierran mañana',            5),
  ('ranking_actualizado',   'Ranking actualizado',                         6),
  ('mencion',               'Te mencionaron en la comunidad',              7),
  ('comentario',            'Comentaron tu publicación',                   8),
  ('mensaje',               'Te escribieron por chat',                     9),
  ('difusion',              'Avisos de la organización',                  10);

-- Difusiones que arma el admin; las envía el cron de push (fase siguiente).
CREATE TABLE IF NOT EXISTS `_app_difusiones` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `titulo`     VARCHAR(120) NOT NULL,
  `cuerpo`     VARCHAR(500) NOT NULL,
  `destino`    VARCHAR(160) NULL,
  `filtro`     ENUM('todos','evento','circuito') NOT NULL DEFAULT 'todos',
  `filtro_id`  INT UNSIGNED NULL,
  `creado_por` VARCHAR(100) NOT NULL,
  `creado`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `enviado_en` DATETIME     NULL,
  `enviados`   INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_pendientes` (`enviado_en`, `creado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
