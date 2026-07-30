<?php

declare(strict_types=1);

namespace Seviye\Api\Rbac;

/**
 * Provisioning credentials for an external system (an accounting package, a
 * mobile app, ...) is a platform-wide security decision, not a personal
 * setting - restricted to the single most privileged role, exactly
 * mirroring Seviye\Security\Rbac\SecurityCapability::MANAGE_SECURITY_SETTINGS
 * and Seviye\Notifications\Rbac\NotificationCapability::MANAGE_NOTIFICATION_SETTINGS.
 */
enum ApiCapability: string
{
    /** Genel Merkez only: issue/list/revoke API keys for any user. */
    case MANAGE_API_KEYS = 'scp_manage_api_keys';
}
