-- Reference DDL for Seviye Finance's tables.
-- NOT the source of truth - generated for readability only.
-- The authoritative schema lives in:
--   plugin/seviye-finance/src/Database/Migrations/CreateHakedisEntriesTable.php
--   plugin/seviye-finance/src/Database/Migrations/CreateHakedisSettlementsTable.php
-- Replace the {prefix} placeholder with your WordPress table prefix (default: wp_).
--
-- Two separate immutable, append-only ledgers - deliberately no updated_at
-- column on either. A branch's outstanding balance is
-- SUM(hakedis_entries.amount) - SUM(hakedis_settlements.amount), always
-- computed, never cached. order_id/order_item_id point at WooCommerce's own
-- order tables - no FK, this platform never puts FKs on WordPress/WooCommerce
-- core tables. recorded_by likewise has no FK to wp_users - see
-- CreateHakedisSettlementsTable's docblock.

CREATE TABLE {prefix}scp_hakedis_entries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    branch_id BIGINT UNSIGNED NOT NULL,
    order_id BIGINT UNSIGNED NOT NULL,
    order_item_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    commission_rate DECIMAL(5,2) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    vat_amount DECIMAL(10,2) NOT NULL,
    type VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY order_item_type (order_id, order_item_id, type),
    KEY branch_id (branch_id),
    KEY student_id (student_id),
    CONSTRAINT scp_hakedis_entries_branch_id_fk
        FOREIGN KEY (branch_id) REFERENCES {prefix}scp_branches (id),
    CONSTRAINT scp_hakedis_entries_student_id_fk
        FOREIGN KEY (student_id) REFERENCES {prefix}scp_students (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {prefix}scp_hakedis_settlements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    branch_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    method VARCHAR(20) NOT NULL,
    note TEXT NULL,
    recorded_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY branch_id (branch_id),
    CONSTRAINT scp_hakedis_settlements_branch_id_fk
        FOREIGN KEY (branch_id) REFERENCES {prefix}scp_branches (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
