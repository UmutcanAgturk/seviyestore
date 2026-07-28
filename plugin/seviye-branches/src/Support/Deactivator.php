<?php

declare(strict_types=1);

namespace Seviye\Branches\Support;

/**
 * Intentionally does not remove scp_branches / scp_branch_users data -
 * deactivation is reversible by design, data loss is not.
 */
final class Deactivator
{
    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }
}
