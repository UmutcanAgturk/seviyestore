-- Reference DDL for Seviye Students' tables.
-- NOT the source of truth - generated for readability only.
-- The authoritative schema lives in:
--   plugin/seviye-students/src/Database/Migrations/CreateStudentsTable.php
--   plugin/seviye-students/src/Database/Migrations/CreateStudentParentsTable.php
-- Replace the {prefix} placeholder with your WordPress table prefix (default: wp_).

CREATE TABLE {prefix}scp_students (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    branch_id BIGINT UNSIGNED NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    education_year VARCHAR(9) NOT NULL,
    class_name VARCHAR(50) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY branch_id (branch_id),
    KEY education_year (education_year),
    CONSTRAINT scp_students_branch_id_fk
        FOREIGN KEY (branch_id) REFERENCES {prefix}scp_branches (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {prefix}scp_student_parents (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id BIGINT UNSIGNED NOT NULL,
    parent_user_id BIGINT UNSIGNED NOT NULL,
    relationship_type VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY student_parent (student_id, parent_user_id),
    KEY parent_user_id (parent_user_id),
    CONSTRAINT scp_student_parents_student_id_fk
        FOREIGN KEY (student_id) REFERENCES {prefix}scp_students (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
