-- Reference DDL for Seviye Notifications' table.
-- NOT the source of truth - generated for readability only.
-- The authoritative schema lives in:
--   plugin/seviye-notifications/src/Database/Migrations/CreateNotificationsTable.php
-- Replace the {prefix} placeholder with your WordPress table prefix (default: wp_).

CREATE TABLE {prefix}scp_notifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    channel VARCHAR(10) NOT NULL,
    event_name VARCHAR(100) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    status VARCHAR(10) NOT NULL DEFAULT 'pending',
    error TEXT NULL,
    created_at DATETIME NOT NULL,
    sent_at DATETIME NULL,
    read_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY user_id (user_id),
    KEY channel (channel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
