<?php

declare(strict_types=1);

namespace Seviye\Students\Support;

/**
 * Intentionally does not remove scp_students / scp_student_parents data -
 * deactivation is reversible by design, data loss is not.
 */
final class Deactivator
{
    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }
}
