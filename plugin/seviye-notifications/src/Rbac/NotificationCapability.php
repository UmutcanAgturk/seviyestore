<?php

declare(strict_types=1);

namespace Seviye\Notifications\Rbac;

/**
 * Configuring the NetGSM SMS gateway is a platform-wide integration secret
 * (usercode/password/msgheader), not a personal setting - restricted to the
 * single most privileged role, exactly mirroring
 * Seviye\Security\Rbac\SecurityCapability::MANAGE_SECURITY_SETTINGS.
 */
enum NotificationCapability: string
{
    /** Genel Merkez only: read/write the NetGSM SMS gateway credentials. */
    case MANAGE_NOTIFICATION_SETTINGS = 'scp_manage_notification_settings';

    /** Genel Merkez / Bölge Müdürü: broadcast a "Toplu Duyuru" to any branch's velis, or all of them. */
    case SEND_BROADCAST = 'scp_send_broadcast';

    /** Şube Müdürü: broadcast a "Toplu Duyuru" only to their own branch's velis. */
    case SEND_OWN_BRANCH_BROADCAST = 'scp_send_own_branch_broadcast';
}
