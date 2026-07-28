-- Reference DDL for Seviye Branches' tables.
-- NOT the source of truth - generated for readability only.
-- The authoritative schema lives in:
--   plugin/seviye-branches/src/Database/Migrations/CreateBranchesTable.php
--   plugin/seviye-branches/src/Database/Migrations/CreateBranchUsersTable.php
-- Replace the {prefix} placeholder with your WordPress table prefix (default: wp_).

CREATE TABLE {prefix}scp_branches (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(191) NOT NULL,
    slug VARCHAR(191) NOT NULL,
    iban CHAR(34) NULL,
    commission_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    phone VARCHAR(20) NULL,
    address TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {prefix}scp_branch_users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    branch_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY user_id (user_id),
    KEY branch_id (branch_id),
    CONSTRAINT scp_branch_users_branch_id_fk
        FOREIGN KEY (branch_id) REFERENCES {prefix}scp_branches (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
