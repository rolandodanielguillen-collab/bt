-- ============================================================================
-- Comunidad de la app móvil BT — tablas nuevas
-- ============================================================================
-- Fecha: 2026-08-10
--
-- SÓLO AGREGA. No altera ni una tabla existente de bt.com.py: el sitio y el
-- admin siguen funcionando igual con o sin esto aplicado.
--
-- Prefijo `_app_` para que se distingan de un vistazo de las tablas del sitio
-- (`_p_*`, `_ic_*`, `_todosvstodos`).
--
-- El autor de todo es una CI de `_p_usuarios` (el padrón que ya existe). No se
-- duplican datos de jugador: se referencia por CI, igual que hace el resto del
-- sistema.
--
-- NO EJECUTADO. Revisar antes de correr en producción; hacer backup primero.
-- ============================================================================

-- ── Auth: NO se crea nada ───────────────────────────────────────────────────
-- bt.com.py YA tiene login de jugador:
--   * credencial: `_p_usuarios.pase` (email o CI + pase)
--   * endpoint  : POST /api/usuario  → { status, token, ci, ... }
--   * sesión    : `_sesion_usuario` (sesion, id_usuario, ci, token)
-- La app reusa ESO. No hay tabla de credenciales ni de sesiones propia:
-- dos sistemas de contraseñas para el mismo jugador es peor que uno malo.
--
-- Deuda del sistema existente (NO se toca acá, queda anotada):
--   1. `pase` está en texto plano.
--   2. El token es sha1(id.email): determinístico y sin vencimiento.
--   3. api/decode.token.inc.php no valida nada (está comentado).

-- ── Muro ────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `_app_posts` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ci_autor`   VARCHAR(20)  NOT NULL,
  `texto`      TEXT         NOT NULL,
  `imagen`     VARCHAR(255) NULL,
  -- Alcance: NULL = todo el país; con circuito = sólo ese circuito.
  `id_circuito` INT         NULL,
  `creado`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `editado`    DATETIME     NULL,
  -- Borrado lógico: moderar sin perder el hilo de comentarios.
  `estado`     ENUM('publicado','oculto','borrado') NOT NULL DEFAULT 'publicado',
  -- Contador desnormalizado: el muro ordena y muestra likes en cada scroll,
  -- contar la tabla de likes en cada request no escala.
  `likes`      INT UNSIGNED NOT NULL DEFAULT 0,
  `comentarios` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_muro` (`estado`, `creado`),
  KEY `idx_autor` (`ci_autor`),
  KEY `idx_circuito` (`id_circuito`, `creado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `_app_comentarios` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_post`    INT UNSIGNED NOT NULL,
  -- Respuesta a otro comentario. Un solo nivel de anidación, como el diseño.
  `id_padre`   INT UNSIGNED NULL,
  `ci_autor`   VARCHAR(20)  NOT NULL,
  `texto`      TEXT         NOT NULL,
  `creado`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `estado`     ENUM('publicado','oculto','borrado') NOT NULL DEFAULT 'publicado',
  PRIMARY KEY (`id`),
  KEY `idx_post` (`id_post`, `creado`),
  KEY `idx_padre` (`id_padre`),
  CONSTRAINT `fk_com_post` FOREIGN KEY (`id_post`) REFERENCES `_app_posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Un like por jugador por post: lo garantiza la UNIQUE, no el código.
CREATE TABLE IF NOT EXISTS `_app_likes` (
  `id_post` INT UNSIGNED NOT NULL,
  `ci`      VARCHAR(20)  NOT NULL,
  `creado`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_post`, `ci`),
  KEY `idx_ci` (`ci`),
  CONSTRAINT `fk_like_post` FOREIGN KEY (`id_post`) REFERENCES `_app_posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @menciones ya resueltas a CI. Se escriben al publicar, y son la fuente de
-- las notificaciones "te etiquetaron".
CREATE TABLE IF NOT EXISTS `_app_menciones` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `origen`     ENUM('post','comentario','mensaje') NOT NULL,
  `id_origen`  INT UNSIGNED NOT NULL,
  `ci`         VARCHAR(20)  NOT NULL,
  `creado`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mencion` (`origen`, `id_origen`, `ci`),
  KEY `idx_ci` (`ci`, `creado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Buscar dupla ────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `_app_busca_dupla` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ci_autor`     VARCHAR(20)  NOT NULL,
  `id_categoria` INT          NULL,
  `texto`        TEXT         NOT NULL,
  `disponibilidad` VARCHAR(160) NULL,
  `creado`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  -- Se cierra sola cuando el jugador se inscribe, o a mano.
  `estado`       ENUM('abierta','cerrada') NOT NULL DEFAULT 'abierta',
  PRIMARY KEY (`id`),
  KEY `idx_abiertas` (`estado`, `creado`),
  KEY `idx_categoria` (`id_categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Mensajería ──────────────────────────────────────────────────────────────
-- Sólo chats 1-a-1 (es lo que muestra el diseño). `ci_a` < `ci_b` siempre, así
-- la UNIQUE impide que se creen dos conversaciones entre las mismas personas.

CREATE TABLE IF NOT EXISTS `_app_chats` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ci_a`        VARCHAR(20)  NOT NULL,
  `ci_b`        VARCHAR(20)  NOT NULL,
  `creado`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ultimo_msg`  DATETIME     NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_par` (`ci_a`, `ci_b`),
  KEY `idx_a` (`ci_a`, `ultimo_msg`),
  KEY `idx_b` (`ci_b`, `ultimo_msg`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `_app_mensajes` (
  `id`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_chat`  INT UNSIGNED NOT NULL,
  `ci_autor` VARCHAR(20)  NOT NULL,
  `texto`    TEXT         NOT NULL,
  `creado`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `leido`    DATETIME     NULL,
  PRIMARY KEY (`id`),
  KEY `idx_chat` (`id_chat`, `creado`),
  KEY `idx_sin_leer` (`id_chat`, `leido`),
  CONSTRAINT `fk_msg_chat` FOREIGN KEY (`id_chat`) REFERENCES `_app_chats` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Notificaciones y push ───────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `_app_notificaciones` (
  `id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ci`      VARCHAR(20)  NOT NULL,
  `tipo`    ENUM('partido','dupla','ranking','fixture','pago','mencion','comentario','mensaje') NOT NULL,
  `titulo`  VARCHAR(160) NOT NULL,
  `cuerpo`  VARCHAR(255) NULL,
  -- A dónde lleva al tocarla (ruta de la app, ej. /torneo/11).
  `destino` VARCHAR(160) NULL,
  `creado`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `leida`   DATETIME     NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ci` (`ci`, `creado`),
  KEY `idx_sin_leer` (`ci`, `leida`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tokens de Expo Notifications. Un jugador puede tener varios dispositivos.
CREATE TABLE IF NOT EXISTS `_app_dispositivos` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ci`         VARCHAR(20)  NOT NULL,
  `expo_token` VARCHAR(255) NOT NULL,
  `plataforma` ENUM('ios','android') NOT NULL,
  `creado`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ultimo_uso` DATETIME     NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_token` (`expo_token`),
  KEY `idx_ci` (`ci`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
