<?php

declare(strict_types=1);

namespace Seviye\Security\Auth;

enum AuthFailureReason
{
    /**
     * Deliberately covers "unknown T.C. Kimlik No", "malformed T.C. Kimlik
     * No" and "wrong password" alike: never let a caller distinguish
     * "this identity doesn't exist" from "wrong password" for it, since
     * that distinction is exactly what lets an attacker enumerate valid
     * T.C. Kimlik No values.
     */
    case INVALID_CREDENTIALS;

    case THROTTLED;
}
