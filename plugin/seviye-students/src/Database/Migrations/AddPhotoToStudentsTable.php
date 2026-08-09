<?php

declare(strict_types=1);

namespace Seviye\Students\Database\Migrations;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\MigrationInterface;

/**
 * "Öğrenci profiline fotoğraf/avatar yükleme" - adds photo_attachment_id
 * to the ALREADY-DEPLOYED scp_students table (unlike
 * CreateOrderLineItemsTable's vat_amount/product_id, which could still be
 * folded into that table's own CREATE migration because it had never run
 * on a live install - scp_students has, so this is a SEPARATE migration
 * with its own version, not an edit to CreateStudentsTable.php).
 *
 * Stores a WordPress attachment ID (uploaded via the core /wp/v2/media
 * REST endpoint, the SAME upload path Marka/product images already use -
 * see scpUploadMedia() in assets/js/scp-api-fetch.js) rather than a bare
 * URL, so the theme can always ask WordPress for whatever image size it
 * needs (thumbnail for a list avatar, full for a detail view) without
 * re-storing multiple URLs. NULL means "no photo yet" - the veli/personel
 * dashboard falls back to the initials avatar (scp_render_avatar()/
 * window.scpAvatar()) in that case, exactly as it always has.
 *
 * dbDelta() is idempotent and diffs the FULL desired CREATE TABLE
 * definition against the live schema, so passing the complete column list
 * (not just the new one) is the correct, standard way to add a column via
 * dbDelta - it only issues the ALTER for what's actually missing.
 */
final class AddPhotoToStudentsTable implements MigrationInterface
{
    public function version(): string
    {
        return '2026_08_09_000001';
    }

    public function description(): string
    {
        return 'Adds photo_attachment_id to scp_students.';
    }

    public function up(ConnectionInterface $connection): void
    {
        $table = $connection->table('students');
        $charsetCollate = $connection->charsetCollate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            branch_id BIGINT UNSIGNED NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            education_year VARCHAR(9) NOT NULL,
            class_name VARCHAR(50) NOT NULL,
            tc_no CHAR(11) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            photo_attachment_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY branch_id (branch_id),
            KEY education_year (education_year)
        ) {$charsetCollate};";

        $connection->dbDelta($sql);
    }

    public function down(ConnectionInterface $connection): void
    {
        $connection->query(
            'ALTER TABLE ' . $connection->table('students') . ' DROP COLUMN photo_attachment_id'
        );
    }
}
