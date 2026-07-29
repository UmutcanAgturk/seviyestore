<?php

declare(strict_types=1);

namespace Seviye\Parents\Repository;

use Seviye\Parents\Domain\NotificationPreference;
use Seviye\Parents\Domain\ParentProfile;

interface ParentProfileRepositoryInterface
{
    public function findByUserId(int $userId): ?ParentProfile;

    /**
     * Creates or updates the profile for $userId. $kvkkConsentGiven only
     * ever moves consent from "not given" to "given" - once a consent
     * timestamp exists it is never cleared or overwritten by a later call,
     * matching how KVKK consent records are expected to behave (immutable
     * once given).
     */
    public function upsert(
        int $userId,
        ?string $phone,
        NotificationPreference $notificationPreference,
        bool $kvkkConsentGiven
    ): ParentProfile;
}
