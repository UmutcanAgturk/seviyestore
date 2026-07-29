-- Reference DDL for Seviye Pricing's table.
-- NOT the source of truth - generated for readability only.
-- The authoritative schema lives in:
--   plugin/seviye-pricing/src/Database/Migrations/CreatePriceRulesTable.php
-- Replace the {prefix} placeholder with your WordPress table prefix (default: wp_).

CREATE TABLE {prefix}scp_price_rules (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NULL,
    branch_id BIGINT UNSIGNED NULL,
    price DECIMAL(10,2) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY product_id (product_id),
    KEY student_id (student_id),
    KEY branch_id (branch_id),
    CONSTRAINT scp_price_rules_student_id_fk
        FOREIGN KEY (student_id) REFERENCES {prefix}scp_students (id) ON DELETE CASCADE,
    CONSTRAINT scp_price_rules_branch_id_fk
        FOREIGN KEY (branch_id) REFERENCES {prefix}scp_branches (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
