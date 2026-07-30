<?php

declare(strict_types=1);

namespace Seviye\Security\TwoFactor;

/**
 * What {@see TwoFactorService::beginSetup()} hands back to the caller: the
 * raw Base32 secret (shown once, in case the user's authenticator app can
 * only accept manual entry) and a standard `otpauth://` provisioning URI
 * (the same format Google Authenticator/Authy/etc. expect a QR code to
 * encode - this platform doesn't generate a QR image itself to avoid a new
 * dependency; the URI text is enough for any authenticator app that
 * supports manual/text provisioning, and a site operator can feed the URI
 * to any external QR generator if desired).
 */
final class TwoFactorSetup
{
    public function __construct(
        public readonly string $secret,
        public readonly string $otpauthUri
    ) {
    }
}
