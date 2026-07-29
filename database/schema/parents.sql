-- Reference DDL for Seviye Parents' table.
-- NOT the source of truth - generated for readability only.
-- The authoritative schema lives in:
--   plugin/seviye-parents/src/Database/Migrations/CreateParentProfilesTable.php
-- Replace the {prefix} placeholder with your WordPress table prefix (default: wp_).

CREATE TABLE {prefix}scp_parent_profiles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    phone VARCHAR(20) NULL,
    notification_preference VARCHAR(10) NOT NULL DEFAULT 'email',
    kvkk_consent_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
