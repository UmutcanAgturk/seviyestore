-- Reference DDL for Seviye Finance's table.
-- NOT the source of truth - generated for readability only.
-- The authoritative schema lives in:
--   plugin/seviye-finance/src/Database/Migrations/CreateHakedisEntriesTable.php
-- Replace the {prefix} placeholder with your WordPress table prefix (default: wp_).
--
-- Immutable, append-only ledger - deliberately no updated_at column.
-- order_id/order_item_id point at WooCommerce's own order tables - no FK,
-- this platform never puts FKs on WordPress/WooCommerce core tables.

CREATE TABLE {prefix}scp_hakedis_entries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    branch_id BIGINT UNSIGNED NOT NULL,
    order_id BIGINT UNSIGNED NOT NULL,
    order_item_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    commission_rate DECIMAL(5,2) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
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
