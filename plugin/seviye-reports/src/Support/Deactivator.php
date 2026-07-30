<?php

declare(strict_types=1);

namespace Seviye\Reports\Support;

/**
 * No data of its own to preserve/remove - Reports owns no scp_* table, it
 * only reads other modules' Contracts.
 */
final class Deactivator
{
    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }
}
