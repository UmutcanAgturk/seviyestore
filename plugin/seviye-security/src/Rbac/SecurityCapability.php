<?php

declare(strict_types=1);

namespace Seviye\Security\Rbac;

/**
 * Security's first RBAC-gated endpoint: every prior endpoint in this
 * module is either pre-login/public (seviye/v1/auth/*) or self-service
 * authenticated-only (seviye/v1/security/2fa/*, gated on "is logged in",
 * not on a capability - every role manages its own 2FA). Configuring the
 * /admin IP allowlist is different: it is a platform-wide security policy,
 * not a personal setting, so it is restricted to the single most
 * privileged role.
 */
enum SecurityCapability: string
{
    /** Genel Merkez only: read/write the /admin zone's IP allowlist. */
    case MANAGE_SECURITY_SETTINGS = 'scp_manage_security_settings';
}
