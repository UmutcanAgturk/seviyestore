<?php

declare(strict_types=1);

namespace Seviye\Security\Rbac;

/**
 * Submitting a KVKK talebi (export or deletion) is never capability-gated -
 * every logged-in user requests their own, the same "is logged in, not a
 * capability" rule 2FA already follows (see SecurityCapability's own
 * docblock). Only REVIEWING the deletion queue is a privileged action.
 */
enum PrivacyCapability: string
{
    /** Genel Merkez only: review/approve/reject pending deletion requests. */
    case MANAGE_PRIVACY_REQUESTS = 'scp_manage_privacy_requests';
}
