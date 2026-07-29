-- Reference DDL for Seviye Commerce's table.
-- NOT the source of truth - generated for readability only.
-- The authoritative schema lives in:
--   plugin/seviye-commerce/src/Database/Migrations/CreateOrderLineItemsTable.php
-- Replace the {prefix} placeholder with your WordPress table prefix (default: wp_).
--
-- order_id/order_item_id point at WooCommerce's own order tables - no FK,
-- this platform never puts FKs on WordPress/WooCommerce core tables.

CREATE TABLE {prefix}scp_order_line_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    order_item_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NOT NULL,
    commission_rate DECIMAL(5,2) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    status VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY order_item (order_id, order_item_id),
    KEY student_id (student_id),
    KEY branch_id (branch_id),
    KEY status (status),
    CONSTRAINT scp_order_line_items_student_id_fk
        FOREIGN KEY (student_id) REFERENCES {prefix}scp_students (id),
    CONSTRAINT scp_order_line_items_branch_id_fk
        FOREIGN KEY (branch_id) REFERENCES {prefix}scp_branches (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
