<?php

declare(strict_types=1);

namespace Seviye\Security\Support;

/**
 * Intentionally does not remove scp_user_identities / scp_password_tokens
 * data - deactivation is reversible by design, data loss is not.
 */
final class Deactivator
{
    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }
}
