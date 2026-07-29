<?php

declare(strict_types=1);

namespace Seviye\Parents\Domain;

/**
 * Veli-specific profile fields wp_users has no room for. One row per
 * parent WP user (no FK - see plugin/seviye-security/uninstall.php's
 * sibling reasoning for why platform tables never reference wp_users
 * at the database level).
 */
final class ParentProfile
{
    public function __construct(
        public readonly int $userId,
        public readonly ?string $phone,
        public readonly NotificationPreference $notificationPreference,
        public readonly ?string $kvkkConsentAt
    ) {
    }

    public function hasGivenKvkkConsent(): bool
    {
        return $this->kvkkConsentAt !== null;
    }
}
