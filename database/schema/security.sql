-- Reference DDL for Seviye Security's tables.
-- NOT the source of truth - generated for readability only.
-- The authoritative schema lives in:
--   plugin/seviye-security/src/Database/Migrations/CreateUserIdentitiesTable.php
--   plugin/seviye-security/src/Database/Migrations/CreatePasswordTokensTable.php
-- Replace the {prefix} placeholder with your WordPress table prefix (default: wp_).

CREATE TABLE {prefix}scp_user_identities (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    tc_no CHAR(11) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY tc_no (tc_no),
    UNIQUE KEY user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {prefix}scp_password_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    purpose VARCHAR(20) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY token_hash (token_hash),
    KEY user_id (user_id),
    KEY expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
