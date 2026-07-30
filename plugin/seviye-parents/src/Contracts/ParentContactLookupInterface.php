<?php

declare(strict_types=1);

namespace Seviye\Parents\Contracts;

/**
 * Published contract for read-only "how do we reach this veli" lookups by
 * other modules - the boundary other modules are allowed to depend on (its
 * first consumer: Seviye Notifications' SMS channel, which needs a phone
 * number Seviye Core's own wp_users table has no room for), never on
 * Parents' internal Repository/Domain classes. Mirrors
 * Seviye\Students\Contracts\StudentLookupInterface.
 */
interface ParentContactLookupInterface
{
    /**
     * Null means either the user has no scp_parent_profiles row at all, or
     * has one with no phone on file (see Domain\ParentProfile - phone is
     * nullable, a veli is not required to provide it) - either way, "no
     * phone to send an SMS to", not an error.
     */
    public function phoneFor(int $userId): ?string;
}
