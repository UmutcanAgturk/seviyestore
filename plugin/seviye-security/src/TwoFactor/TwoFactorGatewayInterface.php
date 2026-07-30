<?php

declare(strict_types=1);

namespace Seviye\Security\TwoFactor;

interface TwoFactorGatewayInterface
{
    public function find(int $userId): ?TwoFactorSecret;

    /**
     * Insert-or-replace: starting (or restarting) setup always resets
     * confirmed_at to NULL, even if a previous secret was already
     * confirmed - the old confirmed secret is no longer the one the user
     * would be verifying against, so it must not keep reading as "enabled".
     */
    public function store(int $userId, string $encryptedSecret): void;

    public function confirm(int $userId): void;

    public function delete(int $userId): void;
}
