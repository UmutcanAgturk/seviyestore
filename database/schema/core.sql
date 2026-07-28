-- Reference DDL for Seviye Core's own tables.
-- NOT the source of truth - generated for readability only.
-- The authoritative schema lives in:
--   plugin/seviye-core/src/Database/MigrationRunner.php (scp_migrations)
--   plugin/seviye-core/src/Database/Migrations/CreateLogsTable.php (scp_logs)
--   plugin/seviye-core/src/Database/Migrations/CreateSettingsTable.php (scp_settings)
-- Replace the {prefix} placeholder with your WordPress table prefix (default: wp_).

CREATE TABLE {prefix}scp_migrations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    version VARCHAR(32) NOT NULL,
    description VARCHAR(255) NOT NULL,
    executed_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY version (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {prefix}scp_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    channel VARCHAR(64) NOT NULL DEFAULT 'core',
    level VARCHAR(16) NOT NULL DEFAULT 'info',
    message TEXT NOT NULL,
    context LONGTEXT NULL,
    user_id BIGINT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY channel (channel),
    KEY level (level),
    KEY user_id (user_id),
    KEY created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {prefix}scp_settings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    setting_key VARCHAR(191) NOT NULL,
    setting_value LONGTEXT NULL,
    autoload TINYINT(1) NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
