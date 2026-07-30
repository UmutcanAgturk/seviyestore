<?php

declare(strict_types=1);

namespace Seviye\Students\Contracts;

/**
 * Published contract for a full, read-only student directory listing -
 * distinct from {@see StudentLookupInterface} (single-student lookups by
 * id, used to resolve "which student is this cart item/rule for") because
 * an admin directory view is a genuinely different consumer need. First
 * consumer: Seviye Security's native wp-admin "Öğrenci" page.
 */
interface StudentDirectoryInterface
{
    /**
     * @return list<StudentDirectoryEntry>
     */
    public function all(): array;
}
