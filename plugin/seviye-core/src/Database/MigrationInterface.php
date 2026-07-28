<?php

declare(strict_types=1);

namespace Seviye\Core\Database;

interface MigrationInterface
{
    /**
     * Sortable, unique version identifier, e.g. "2026_07_28_000001".
     */
    public function version(): string;

    public function description(): string;

    public function up(ConnectionInterface $connection): void;

    public function down(ConnectionInterface $connection): void;
}
